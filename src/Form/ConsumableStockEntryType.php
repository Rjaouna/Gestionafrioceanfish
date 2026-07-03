<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ConsumableStockEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantity', NumberType::class, [
                'label' => 'Quantite recue',
                'mapped' => false,
                'scale' => 2,
                'html5' => true,
                'constraints' => [new Assert\Positive(message: 'La quantite doit etre superieure a zero.')],
                'attr' => ['min' => 0.01, 'step' => '0.01'],
            ])
            ->add('movementDate', DateTimeType::class, [
                'label' => "Date de l'entree",
                'mapped' => false,
                'widget' => 'single_text',
                'data' => new \DateTimeImmutable(),
            ])
            ->add('supplier', TextType::class, [
                'label' => 'Fournisseur',
                'mapped' => false,
                'required' => false,
            ])
            ->add('unitCost', NumberType::class, [
                'label' => 'Prix unitaire',
                'mapped' => false,
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.01'],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Commentaire',
                'mapped' => false,
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Ex. achat facture, livraison fournisseur...'],
            ]);
    }
}
