<?php

namespace SocialPlacesBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use SocialPlacesBundle\Entity\Contacts;
use SocialPlacesBundle\Entity\Subscription;
use SocialPlacesBundle\Form\ContactFormType;
use SocialPlacesBundle\Form\MailChimpFormType;

use Welp\MailchimpBundle\Event\SubscriberEvent;
use Welp\MailchimpBundle\Subscriber\Subscriber;

class DefaultController extends Controller
{
    /**
     * @Route("/")
     */
    public function createAction(Request $request)
    {
        // get the Contacts entity
        $contact  = new Contacts();

        // create the contact form
        $form = $this->createForm(ContactFormType::class, $contact);
        $form->handleRequest($request);

        // if the form was submitted then process
        if($form->isSubmitted() && $form->isValid())
            {
                // presist the form data to the database
                $em = $this->getDoctrine()->getManager();
                $em->persist($contact);
                $em->flush();

                // send the confirmation email to the user
                $this->sendClientMail($contact);

                // send the confirmation email to the admin
                $this->sendAdminMail($contact);

                // then redirect the user to the success page
                return $this->redirectToRoute("confirm", array('id' => $contact->getId()));
            }

        return $this->render('SocialPlacesBundle:Default:index.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * @Route("/confirm/{id}",  name="confirm")
     */
    public function confirmMailchimpSubscription(Request $request, $id)
    {
        $contact = $this->getDoctrine()
                ->getRepository("SocialPlacesBundle:Contacts")
                ->findOneById($id);

        // create the mailchimp confirmation form
        $form = $this->createForm(MailChimpFormType::class, $contact);
        $form->handleRequest($request);

        // if the form was submitted then process
        if($form->isSubmitted())
            {
                $canSubscribe = $form->get('subscribe')->getData();

                //if the user gives permission then we subscribe him to MailChimp
                if ($canSubscribe == 'true') {

                    $this->newSubscriber($contact);
                }

                // then redirect the user to the success page
                return $this->redirectToRoute("register_success", array('id' => $id));
            }

            return $this->render('SocialPlacesBundle:Default:confirm.html.twig', array(
                'form' => $form->createView(),
                'contact' => $contact
            ));
    }

    /**
     * @Route("/success/{id}",  name="register_success")
     */
    public function successAction($id)
    {
        $contact = $this->getDoctrine()
                ->getRepository("SocialPlacesBundle:Contacts")
                ->findOneById($id);

        return $this->render('SocialPlacesBundle:Default:success.html.twig', array(
            'contact' => $contact
        ));
    }

    /**
     * send registration confirmation email
     *
     * @param - contact person email address
     */
    public function sendClientMail($contact)
    {
        $recipient = $contact->getEmail();

        $htmlTemplate = 'SocialPlacesBundle:Email:client_email.html.twig';
        $plaintextTemplate = strip_tags($htmlTemplate);

        $message = \Swift_Message::newInstance()
        ->setSubject('Registration Confirmation')
        ->setFrom('info@nonexistingemail.co.za')
        ->setTo($recipient)
        ->setBody(
            $this->renderView(
                // SocialPlacesBundle/Resources/views/Emails/registration.html.twig
                $htmlTemplate,
                array('contact' => $contact)
            ),
            'text/html'
        )

        ->addPart(
            $this->renderView(
                $plaintextTemplate,
                array('contact' => $contact)
            ),
            'text/plain'
        )
        ;

        $this->get('mailer')->send($message);
    }

    /**
     * send registrarion confirmation email to admin
     *
     * @param - contact person email address and optional admin email address
     */
    public function sendAdminMail($contact)
    {
        $adminEmail = $this->container->getParameter('admin_email_address');

        $htmlTemplate = 'SocialPlacesBundle:Email:admin_email.html.twig';
        $plaintextTemplate = strip_tags($htmlTemplate);

        $messageAdmin = \Swift_Message::newInstance()
        ->setSubject('Registration Confirmation')
        ->setFrom('info@nonexistingemail.co.za')
        ->setTo($adminEmail)
        ->setBody(
            $this->renderView(
                // SocialPlacesBundle/Resources/views/Emails/registration.html.twig
                $htmlTemplate,
                array('contact' => $contact)
            ),
            'text/html'
        )

        ->addPart(
            $this->renderView(
                $plaintextTemplate,
                array('contact' => $contact)
            ),
            'text/plain'
        )
        ;

        $this->get('mailer')->send($messageAdmin);
    }

    /**
     * make API call to MailChimp and subscribe the contact to the MailChimp list
     *
     * @param - contact person object
     */
    public function newSubscriber($contact)
    {
        $emailAddress = null;
        $status = null;
        $subscriberStatus = null;

        // get the MailChimp API key
        $listId = $this->container->getParameter('mailchimp_list_id');

        $MailChimp = $this->container->get('welp_mailchimp.mailchimp_master');

        // set the subscriber data
        $subscriber = new Subscriber($contact->getEmail(), [
            'EMAIL' => $contact->getEmail(),
            'FNAME' => $contact->getFirstname(),
            'LNAME' => $contact->getLastname(),
            ], [
                'language' => 'en'
            ]);

        // make the API call and subscribe the contact
        $this->container->get('event_dispatcher')->dispatch(
            SubscriberEvent::EVENT_SUBSCRIBE,
            new SubscriberEvent($listId, $subscriber)
        );

        // get the last API response
        $response = $MailChimp->getLastResponse();

        // check if the subscriber status is success
        if (isset($response['body']) && !empty($response['body'])) {
            $responseArray = explode(',', $response['body']);
                if (isset($responseArray[4]) && !empty($responseArray[4])) {
                    $status = explode(',', $responseArray[4]);
                        if (isset($status[0]) && !empty($status[0])) {
                            $statusval = explode(':', $status[0]);

                            $subscriberStatus = 'success';
                        }
                }
        } else {
            $subscriberStatus = 'fail';
        }

        // if the subscription was successful then log the email address and status
        // in the database
        if ($MailChimp->success()) {

            $subscription = new Subscription();

            // log the response data to the database
            $em = $this->getDoctrine()->getManager();
            $subscription->setEmail($contact->getEmail());
            $subscription->setStatus($subscriberStatus);
            $em->persist($subscription);
            $em->flush();

        } else {

            // else if error then just log the response in the database text field
            $imploded = implode(",", $result);

            $subscription = new Subscription();

            // log the response data to the database
            $em = $this->getDoctrine()->getManager();
            $subscription->setNotes($imploded);
            $subscription->setStatus($subscriberStatus);
            $em->persist($subscription);
            $em->flush();
        }
    }
}
