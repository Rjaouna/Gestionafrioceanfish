<?php

namespace App\Form;

use App\Entity\InventoryCartonStock;
use App\Entity\InventoryCartonStockLine;
use App\Repository\InventoryCartonStockRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InventoryCartonStockLineType extends AbstractType
{
    private const EDITABLE_LINE_TYPES = [
        'Ligne stock' => 'item',
        'Transport' => 'transport',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('stock', EntityType::class, [
                'class' => InventoryCartonStock::class,
                'choice_label' => 'name',
                'query_builder' => static fn (InventoryCartonStockRepository $repository) => $repository->createQueryBuilder('s')->andWhere('s.isActive = true')->orderBy('s.name', 'ASC'),
                'label' => 'Stock carton',
                'placeholder' => 'Sélectionner un stock',
            ])
            ->add('groupName', TextType::class, ['label' => 'Groupe / client', 'required' => false])
            ->add('reference', TextType::class, ['label' => 'Référence'])
            ->add('quantity', IntegerType::class, ['label' => 'Quantité', 'required' => false])
            ->add('unitPrice', NumberType::class, ['label' => 'Prix unitaire', 'required' => false, 'scale' => 3, 'html5' => true])
            ->add('totalAmount', NumberType::class, ['label' => 'Total', 'required' => false, 'scale' => 3, 'html5' => true])
            ->add('lineType', ChoiceType::class, [
                'label' => 'Type de ligne',
                'choices' => self::EDITABLE_LINE_TYPES,
            ])
            ->add('notes', TextareaType::class, ['label' => 'Notes', 'required' => false, 'attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InventoryCartonStockLine::class]);
    }
}
