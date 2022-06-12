<?php

namespace App\Form;

use App\Security\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'Email'
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => true,
                'mapped' => false,
                'options' => ['attr' => ['class' => 'password-field']],
                'attr' => ['autocomplete' => 'new-password'],
                'invalid_message' => 'Пароли должны совпадать',
                'first_options'  => ['label' => 'Пароль'],
                'second_options' => ['label' => 'Повторите пароль'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Пожалуйста, придумайте пароль',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Пароль должен состоять минимум из {{ limit }} символов',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                    ]),
                ],
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}