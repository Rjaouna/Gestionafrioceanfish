<?php

namespace App\Form;

use App\Entity\ConsumableStockItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ConsumableStockItemType extends AbstractType
{
    use SmartChoiceTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $item = $builder->getData();
        $isExisting = $item instanceof ConsumableStockItem && null !== $item->getId();
        $choiceLists = $options['choice_lists'];

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
            ->add('minimumQuantity', NumberType::class, [
                'label' => 'Stock minimum',
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'step' => '0.01'],
            ])
            ->add('supplierPhone', TextType::class, [
                'label' => 'Numéro fournisseur',
                'required' => false,
                'attr' => ['placeholder' => 'Téléphone ou numéro fournisseur'],
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

        $configs = [
            'category' => ['label' => 'Catégorie', 'values' => $choiceLists['categories'] ?? [], 'required' => false, 'maxlength' => 120],
            'unit' => ['label' => 'Unite', 'values' => $choiceLists['units'] ?? [], 'required' => true, 'maxlength' => 40],
            'storageLocation' => ['label' => 'Emplacement', 'values' => $choiceLists['storageLocations'] ?? [], 'required' => false, 'maxlength' => 180],
            'preferredSupplier' => ['label' => 'Nom fournisseur', 'values' => $choiceLists['preferredSuppliers'] ?? [], 'required' => false, 'maxlength' => 180],
        ];

        $this->addSmartChoice($builder, 'category', 'Catégorie', $configs['category']['values'], false, 120, $isExisting ? $item->getCategory() : null);
        $this->addSmartChoice($builder, 'unit', 'Unite', $configs['unit']['values'], true, 40, $isExisting ? $item->getUnit() : null, $isExisting ? [] : ['data' => null]);
        $this->addSmartChoice($builder, 'storageLocation', 'Emplacement', $configs['storageLocation']['values'], false, 180, $isExisting ? $item->getStorageLocation() : null);
        $this->addSmartChoice($builder, 'preferredSupplier', 'Nom fournisseur', $configs['preferredSupplier']['values'], false, 180, $isExisting ? $item->getPreferredSupplier() : null);
        $this->addSmartChoiceSubmitListener($builder, $configs);

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
            'choice_lists' => [],
        ]);
        $resolver->setAllowedTypes('include_initial_quantity', 'bool');
        $resolver->setAllowedTypes('show_reference', 'bool');
        $resolver->setAllowedTypes('choice_lists', 'array');
    }
}
