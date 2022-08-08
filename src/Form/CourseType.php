<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Код',
                'constraints' => [
                    new NotNull()
                ]
            ])
            ->add('name', TextType::class, [
                'label' => 'Название',
                'constraints' => [
                    new NotNull()
                ]
            ])
            ->add('price', MoneyType::class, [
                'mapped' => false,
                'label' => 'Стоимость',
                'currency' => 'RUB',
                'scale' => 2,
                'data' => $options['price'],
                'help' => 'Для бесплатного курса цена игнорируется',
                'constraints' => [
                    new NotNull()
                ]
            ])
            ->add('type', ChoiceType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Тип',
                'data' => $options['type'],
                'choices'  => [
                    'Бесплатный' => \App\Enum\CourseType::FREE_NAME,
                    'В аренду' => \App\Enum\CourseType::RENT_NAME,
                    'Полный' => \App\Enum\CourseType::BUY_NAME,
                ],
                'constraints' => [
                    new NotNull()
                ]
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Описание'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
            'price' => 0.0,
            'type' => \App\Enum\CourseType::FREE_NAME
        ]);

        $resolver->addAllowedTypes('price', ['int', 'float']);
        $resolver->addAllowedTypes('type', 'string');
    }
}
