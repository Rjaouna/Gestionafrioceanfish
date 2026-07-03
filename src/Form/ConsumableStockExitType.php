<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ConsumableStockExitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantity', NumberType::class, [
                'label' => 'Quantite sortie',
                'mapped' => false,
                'scale' => 2,
                'html5' => true,
                'constraints' => [new Assert\Positive(message: 'La quantite doit etre superieure a zero.')],
                'attr' => ['min' => 0.01, 'step' => '0.01'],
            ])
            ->add('movementDate', DateTimeType::class, [
                'label' => 'Date de la sortie',
                'mapped' => false,
                'widget' => 'single_text',
                'data' => new \DateTimeImmutable(),
            ])
            ->add('recipient', TextType::class, [
                'label' => 'Service / personne',
                'mapped' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Production, reception, equipe nuit...'],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif',
                'mapped' => false,
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Ex. distribution hebdomadaire, nettoyage, urgence...'],
            ]);
    }
}
