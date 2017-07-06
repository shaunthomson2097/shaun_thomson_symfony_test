<?php

namespace SocialPlacesBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use EWZ\Bundle\RecaptchaBundle\Form\Type\EWZRecaptchaType;
use EWZ\Bundle\RecaptchaBundle\Validator\Constraints\IsTrue as RecaptchaTrue;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('recaptcha', EWZRecaptchaType::class, array(
            'attr'        => array(
            'options' => array(
                'theme' => 'light',
                'type'  => 'image',
                'size'  => 'normal'
                )
            ),
            'mapped'      => true,
            'error_bubbling' => true
        ))

        ->add('firstname', TextType::Class, array(
            'required' => false
        ))
        ->add('lastname', TextType::Class, array(
            'required' => false
        ))
        ->add('email', EmailType::Class, array(
            'required' => false
        ))
        ->add('contactNumber', TextType::Class, array(
            'required' => false
        ))
        ->add('dob', BirthdayType::class, array(
            'placeholder' => array(
                'year' => 'Year', 'month' => 'Month', 'day' => 'Day',
            )
        ))
        ->add('street', TextType::Class, array(
        ))
        ->add('suburb', TextType::Class, array(
        ))
        ->add('city', TextType::Class, array(
        ))
        ->add('province', ChoiceType::Class, array(
            'placeholder' => '- Choose Province -',
            'choices' => array(
                'Eastern Cape' => 'Eastern Cape',
                'Free State' => 'Free State',
                'Gauteng' => 'Gauteng',
                'KwaZulu-Natal' => 'KwaZulu-Natal',
                'Limpopo' => 'Limpopo',
                'Mpumalanga' => 'Mpumalanga',
                'North West' => 'North West',
                'Northern Cape' => 'Northern Cape',
                'Western Cape' => 'Western Cape'
            )
        ))
        ->add('poBox', TextType::Class, array(
        ))

        ->add('notes')
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'SocialPlacesBundle\Entity\Contacts'
        ));
    }
}
