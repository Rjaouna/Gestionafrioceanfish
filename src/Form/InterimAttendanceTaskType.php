<?php

namespace App\Form;

use App\Entity\InterimAttendance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InterimAttendanceTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('attendanceDate', DateType::class, [
                'label' => 'Date de la tache',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'max' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                    'placeholder' => 'Date du pointage',
                ],
            ])
            ->add('taskType', ChoiceType::class, [
                'label' => 'Type de tache',
                'choices' => array_flip(InterimAttendance::TASK_LABELS),
                'placeholder' => 'Choisir une tache',
                'attr' => ['data-attendance-task-type' => 'true'],
            ])
            ->add('taskQuantity', NumberType::class, [
                'label' => 'Quantite',
                'required' => false,
                'html5' => true,
                'empty_data' => '0',
                'attr' => [
                    'min' => 0,
                    'step' => '0.001',
                    'inputmode' => 'decimal',
                    'placeholder' => 'Nombre de caisses ou kg',
                    'data-attendance-task-quantity' => 'true',
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Remarque sur la tache, lot, equipe...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InterimAttendance::class,
        ]);
    }
}
