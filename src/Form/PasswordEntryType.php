<?php

namespace App\Form;

use App\Entity\PasswordEntry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Ex. Hébergement du site Afriocean fish'],
            ])
            ->add('login', TextType::class, [
                'label' => 'Identifiant',
                'attr' => ['placeholder' => 'Ex. admin@afrioceanfish.com'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'required' => $options['password_required'],
                'attr' => [
                    'autocomplete' => $options['password_autocomplete'],
                    'data-secret-input' => 'true',
                    'placeholder' => 'Saisissez ou générez un mot de passe',
                ],
                'constraints' => $options['password_required'] ? [new Assert\NotBlank()] : [],
            ])
            ->add('link', TextType::class, [
                'label' => 'Lien, URL ou adresse IP',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. https://exemple.com ou 192.168.1.10'],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Ajoutez une note utile sur cet accès...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PasswordEntry::class,
            'password_required' => true,
            'password_autocomplete' => 'new-password',
        ]);
        $resolver->setAllowedTypes('password_required', 'bool');
        $resolver->setAllowedTypes('password_autocomplete', 'string');
    }
}
