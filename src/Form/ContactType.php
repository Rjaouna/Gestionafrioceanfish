<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nom complet',
                'attr' => ['placeholder' => 'Ex. Société Dupont, Jean Martin, Assistance dépannage'],
            ])
            ->add('type', TextType::class, [
                'label' => 'Type',
                'attr' => [
                    'placeholder' => 'Ex. Client, Fournisseur, Dépannage',
                    'list' => 'contact-type-suggestions',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. contact@entreprise.com'],
            ])
            ->add('contactPersonName', TextType::class, [
                'label' => 'Personne contact',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Nadia Benali, M. Dupont'],
            ])
            ->add('contactPersonPosition', TextType::class, [
                'label' => 'Poste du contact',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Responsable de l’agence, commercial, gérant'],
            ])
            ->add('mobile', TelType::class, [
                'label' => 'Portable',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. 06 12 34 56 78'],
            ])
            ->add('mobileSecondary', TelType::class, [
                'label' => 'Portable 2',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. 07 12 34 56 78'],
            ])
            ->add('mobileTertiary', TelType::class, [
                'label' => 'Portable 3',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. 06 98 76 54 32'],
            ])
            ->add('landline', TelType::class, [
                'label' => 'Fixe',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. 01 23 45 67 89'],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Casablanca, Agadir, Paris'],
            ])
            ->add('postalAddress', TextareaType::class, [
                'label' => 'Adresse postale',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Ex. 12 rue de Paris, 75001 Paris',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
            'type_suggestions' => [],
        ]);
        $resolver->setAllowedTypes('type_suggestions', 'array');
    }
}
