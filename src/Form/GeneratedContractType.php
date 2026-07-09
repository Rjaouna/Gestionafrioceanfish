<?php

namespace App\Form;

use App\Entity\GeneratedContract;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GeneratedContractType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contractDate', DateType::class, [
                'label' => 'Date du contrat',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('campaign', TextType::class, [
                'label' => 'Campagne',
                'attr' => ['maxlength' => 30, 'placeholder' => 'Ex. 2026/2027'],
            ])
            ->add('signingCity', TextType::class, [
                'label' => 'Ville de signature',
                'attr' => ['maxlength' => 120, 'placeholder' => 'Ex. Casablanca'],
            ])
            ->add('clientCompanyName', TextType::class, [
                'label' => 'Raison sociale du client',
                'attr' => ['maxlength' => 180, 'placeholder' => 'Ex. STE NETTOFISH'],
            ])
            ->add('clientAddress', TextareaType::class, [
                'label' => 'Adresse complete du client',
                'attr' => ['rows' => 3, 'maxlength' => 1200, 'placeholder' => 'Siege social, lot, ville et pays'],
            ])
            ->add('representativeTitle', ChoiceType::class, [
                'label' => 'Civilite',
                'choices' => ['Monsieur' => 'Monsieur', 'Madame' => 'Madame'],
            ])
            ->add('representativeName', TextType::class, [
                'label' => 'Nom complet du representant',
                'attr' => ['maxlength' => 180, 'placeholder' => 'Ex. LAZRAK MOHAMED FAHID'],
            ])
            ->add('representativeIdNumber', TextType::class, [
                'label' => 'CIN / piece d identite',
                'attr' => ['maxlength' => 80, 'placeholder' => 'Ex. BE701435'],
            ])
            ->add('internalNotes', TextareaType::class, [
                'label' => 'Notes internes',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 1200, 'placeholder' => 'Information interne non imprimee sur le contrat'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => GeneratedContract::class]);
    }
}
