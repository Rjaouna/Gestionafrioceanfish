<?php

namespace App\Form;

use App\Entity\WasteSale;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WasteSaleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('saleDate', DateType::class, [
                'label' => 'Date vente / paiement',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['placeholder' => 'Date de la vente'],
            ])
            ->add('buyerName', TextType::class, [
                'label' => 'Nom de l acheteur',
                'attr' => [
                    'placeholder' => 'Ex. Acheteur local, ferme, client...',
                    'list' => 'waste-sale-buyer-suggestions',
                ],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices' => WasteSale::PAYMENT_METHOD_CHOICES,
            ])
            ->add('weightKg', NumberType::class, [
                'label' => 'Poids vendu (kg)',
                'scale' => 3,
                'html5' => true,
                'attr' => [
                    'placeholder' => 'Ex. 120',
                    'min' => '0.001',
                    'step' => '0.001',
                    'data-waste-sale-weight' => 'true',
                ],
            ])
            ->add('unitPrice', NumberType::class, [
                'label' => 'Prix / kg',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'readonly' => 'readonly',
                    'min' => '0.01',
                    'step' => '0.01',
                    'data-waste-sale-unit-price' => 'true',
                ],
                'help' => 'Tarif actuel : 0.60 dh/kg.',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observation',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Remarque, numero cheque, information camion...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WasteSale::class]);
    }
}
