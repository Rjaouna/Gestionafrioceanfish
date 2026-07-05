<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ConsumableStockInventoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('countedQuantity', NumberType::class, [
                'label' => 'Quantité comptée',
                'mapped' => false,
                'scale' => 2,
                'html5' => true,
                'constraints' => [new Assert\PositiveOrZero],
                'attr' => ['min' => 0, 'step' => '0.01'],
            ])
            ->add('movementDate', DateTimeType::class, [
                'label' => "Date de l'inventaire",
                'mapped' => false,
                'widget' => 'single_text',
                'data' => new \DateTimeImmutable(),
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Observation',
                'mapped' => false,
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Ex. inventaire mensuel, ecart constate...'],
            ]);
    }
}
