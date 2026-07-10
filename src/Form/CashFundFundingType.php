<?php

namespace App\Form;

use App\Entity\CashFundTransaction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CashFundFundingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('movementDate', DateType::class, [
                'label' => 'Date alimentation',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['placeholder' => 'Ex. 2026-07-09'],
            ])
            ->add('amount', MoneyType::class, [
                'label' => 'Montant recu',
                'currency' => 'MAD',
                'divisor' => 1,
                'constraints' => [
                    new Assert\Positive(message: 'Le montant doit etre superieur a 0.'),
                ],
                'attr' => [
                    'placeholder' => 'Ex. 5000',
                    'min' => '0.01',
                    'step' => '0.01',
                ],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Mode remise',
                'choices' => CashFundTransaction::PAYMENT_METHOD_CHOICES,
                'placeholder' => 'Selectionner',
            ])
            ->add('sourceName', TextType::class, [
                'label' => 'Donneur / origine',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Patron'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observation',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Reference, remarque ou contexte...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CashFundTransaction::class,
        ]);
    }
}
