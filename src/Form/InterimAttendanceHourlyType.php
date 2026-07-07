<?php

namespace App\Form;

use App\Entity\InterimAttendance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InterimAttendanceHourlyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('attendanceDate', DateType::class, [
                'label' => 'Date de la journee',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'max' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                    'placeholder' => 'Date du pointage',
                ],
            ])
            ->add('morningPresent', CheckboxType::class, [
                'label' => 'Present le matin',
                'required' => false,
                'attr' => ['data-attendance-halfday-toggle' => 'morning'],
            ])
            ->add('morningStart', TimeType::class, [
                'label' => 'Debut matin',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['data-attendance-time' => 'morning'],
            ])
            ->add('morningEnd', TimeType::class, [
                'label' => 'Fin matin',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['data-attendance-time' => 'morning'],
            ])
            ->add('afternoonPresent', CheckboxType::class, [
                'label' => 'Present l apres-midi',
                'required' => false,
                'attr' => ['data-attendance-halfday-toggle' => 'afternoon'],
            ])
            ->add('afternoonStart', TimeType::class, [
                'label' => 'Debut apres-midi',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['data-attendance-time' => 'afternoon'],
            ])
            ->add('afternoonEnd', TimeType::class, [
                'label' => 'Fin apres-midi',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['data-attendance-time' => 'afternoon'],
            ])
            ->add('hourlyRate', NumberType::class, [
                'label' => 'Taux horaire',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'placeholder' => 'Ex. 15.00',
                    'data-attendance-hourly-rate' => 'true',
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Retard, absence partielle, remarque paiement...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InterimAttendance::class,
        ]);
    }
}
