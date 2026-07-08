<?php

namespace App\Form;

use App\Entity\FishYieldStudy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FishYieldStudyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('studyDate', DateType::class, [
                'label' => 'Date etude',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['placeholder' => 'Date de l essai'],
            ])
            ->add('reference', TextType::class, [
                'label' => 'Reference',
                'required' => false,
                'attr' => ['maxlength' => 80, 'placeholder' => 'Auto : ERP-2026-0001'],
                'help' => 'Laissez vide pour generer automatiquement.',
            ])
            ->add('clientName', TextType::class, [
                'label' => 'Client',
                'required' => false,
                'attr' => ['maxlength' => 180, 'placeholder' => 'Nom du client ou fournisseur'],
            ])
            ->add('operatorName', TextType::class, [
                'label' => 'Operateur',
                'required' => false,
                'attr' => ['maxlength' => 150, 'placeholder' => 'Personne qui a realise l essai'],
            ])
            ->add('speciesName', TextType::class, [
                'label' => 'Nom de l espece',
                'attr' => ['maxlength' => 180, 'placeholder' => 'Ex. Anchois, maquereau, sardine'],
            ])
            ->add('hasMixedFish', CheckboxType::class, [
                'label' => 'Autre poisson melange',
                'required' => false,
                'attr' => ['data-fish-yield-mixed-toggle' => 'true'],
            ])
            ->add('mixedFishName', TextType::class, [
                'label' => 'Poisson melange detecte',
                'required' => false,
                'attr' => ['maxlength' => 180, 'placeholder' => 'Espece melangee si detectee'],
            ])
            ->add('rawBoxWeight', NumberType::class, $this->numberOptions('Poids caisse matiere premiere (kg)', 'rawBoxWeight', 'Poids brut de la caisse avant decongelation'))
            ->add('thawedBoxWeight', NumberType::class, $this->numberOptions('Poids caisse apres decongelation (kg)', 'thawedBoxWeight', 'Poids apres egouttage/decongelation'))
            ->add('piecesPerKg', NumberType::class, $this->numberOptions('Moule - pieces / kg', 'piecesPerKg', 'Nombre de pieces pour faire 1 kg', 2, '0.01'))
            ->add('finishedProductWeight', NumberType::class, $this->numberOptions('Poids produit fini filet (kg)', 'finishedProductWeight', 'Poids net obtenu en filet'))
            ->add('wasteWeight', NumberType::class, $this->numberOptions('Poids dechets (kg)', 'wasteWeight', 'Tetes, arêtes, peaux, viscères...'))
            ->add('lossWeight', NumberType::class, $this->numberOptions('Poids pertes (kg)', 'lossWeight', 'Ecart process hors dechets identifies'))
            ->add('containerWeight', NumberType::class, $this->numberOptions('Poids conteneur a estimer (kg)', 'containerWeight', 'Poids matiere premiere du conteneur'))
            ->add('observations', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 1800, 'placeholder' => 'Qualite, odeur, calibre, texture, remarques client...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FishYieldStudy::class]);
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, string $field, string $placeholder, int $scale = 3, string $step = '0.001'): array
    {
        return [
            'label' => $label,
            'required' => false,
            'html5' => true,
            'empty_data' => '0',
            'attr' => [
                'min' => 0,
                'step' => $step,
                'inputmode' => 'decimal',
                'placeholder' => $placeholder,
                'data-fish-yield-field' => $field,
                'data-scale' => $scale,
            ],
        ];
    }
}
