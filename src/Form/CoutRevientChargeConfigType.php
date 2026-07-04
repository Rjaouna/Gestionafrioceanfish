<?php

namespace App\Form;

use App\Entity\CoutRevientChargeConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CoutRevientChargeConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la charge',
                'attr' => ['maxlength' => 140, 'placeholder' => 'Ex. Tunnel de congelation'],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Categorie',
                'choices' => array_flip(CoutRevientChargeConfig::CATEGORY_LABELS),
            ])
            ->add('calculationUnit', ChoiceType::class, [
                'label' => 'Mode de calcul',
                'choices' => array_flip(CoutRevientChargeConfig::UNIT_LABELS),
                'help' => 'Pour une charge mensuelle, le lot demandera le nombre de jours utilises et calculera un prorata sur 30 jours.',
            ])
            ->add('unitCost', NumberType::class, [
                'label' => 'Cout unitaire',
                'required' => false,
                'html5' => true,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => '0.0001', 'inputmode' => 'decimal'],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Charge active',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Note interne',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 1000],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoutRevientChargeConfig::class,
        ]);
    }
}
