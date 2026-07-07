<?php

namespace App\Form;

use App\Entity\InterimAttendanceRate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InterimAttendanceRateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Nom du tarif',
                'attr' => ['maxlength' => 120],
            ])
            ->add('unitLabel', TextType::class, [
                'label' => 'Unite',
                'attr' => ['maxlength' => 40, 'placeholder' => 'heure, nettoyage, caisse...'],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Prix unitaire',
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.01'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Tarif actif',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InterimAttendanceRate::class,
        ]);
    }
}
