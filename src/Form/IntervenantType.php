<?php

namespace App\Form;

use App\Entity\Intervenant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class IntervenantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'label' => 'Nom de la boîte',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. ABC Maintenance',
                    'data-maintenance-company-name' => 'true',
                ],
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Ex. Karim',
                    'data-maintenance-firstname' => 'true',
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Ex. Benali',
                    'data-maintenance-lastname' => 'true',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. intervenant@example.com',
                    'data-maintenance-email' => 'true',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. 06 12 34 56 78',
                    'data-maintenance-phone' => 'true',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'placeholder' => 'Sélectionner un type',
                'choices' => Intervenant::TYPES,
            ])
            ->add('speciality', TextType::class, [
                'label' => 'Spécialité',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Climatisation, électricité, plomberie'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Informations utiles sur cet intervenant...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Intervenant::class]);
    }
}
