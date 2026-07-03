<?php

namespace App\Form;

use App\Entity\ConsumableStockItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ConsumableStockItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['show_reference']) {
            $builder->add('reference', TextType::class, [
                'label' => 'Reference',
                'required' => true,
                'attr' => ['placeholder' => 'Ex. STK-0001'],
            ]);
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Produit',
                'attr' => ['placeholder' => 'Ex. Gants nitrile, bavettes, savon...'],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Categorie',
                'choices' => ConsumableStockItem::CATEGORY_CHOICES,
                'placeholder' => 'Selectionner',
                'required' => false,
            ])
            ->add('unit', ChoiceType::class, [
                'label' => 'Unite',
                'choices' => ConsumableStockItem::UNIT_CHOICES,
            ])
            ->add('minimumQuantity', NumberType::class, [
                'label' => 'Stock minimum',
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.01'],
            ])
            ->add('storageLocation', TextType::class, [
                'label' => 'Emplacement',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Magasin, bureau RH, reserve...'],
            ])
            ->add('preferredSupplier', TextType::class, [
                'label' => 'Nom fournisseur',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. fournisseur habituel'],
            ])
            ->add('supplierPhone', TextType::class, [
                'label' => 'Numero fournisseur',
                'required' => false,
                'attr' => ['placeholder' => 'Telephone ou numero fournisseur'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Produit actif',
                'required' => false,
            ]);

        if ($options['include_initial_quantity']) {
            $builder->add('initialQuantity', NumberType::class, [
                'label' => 'Stock initial',
                'mapped' => false,
                'data' => 0,
                'scale' => 2,
                'html5' => true,
                'constraints' => [new Assert\PositiveOrZero],
                'attr' => ['min' => 0, 'step' => '0.01'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ConsumableStockItem::class,
            'include_initial_quantity' => false,
            'show_reference' => true,
        ]);
        $resolver->setAllowedTypes('include_initial_quantity', 'bool');
        $resolver->setAllowedTypes('show_reference', 'bool');
    }
}
