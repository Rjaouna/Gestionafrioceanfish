<?php

namespace App\Form;

use App\Entity\AppModule;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class UserManagementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $roleChoices = $options['role_choices'] ?? [
            'Utilisateur' => 'ROLE_USER',
            'Administrateur' => 'ROLE_ADMIN',
            'Super administrateur' => 'ROLE_SUPER_ADMIN',
        ];

        $builder
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'Ex. prenom.nom@afrioceanfish.com'],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Aïcha'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. Ndiaye'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'required' => $options['password_required'],
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => '12 caractères minimum',
                    'data-secret-input' => 'true',
                ],
                'constraints' => $options['password_required'] ? [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 12),
                ] : [],
            ]);

        if ($options['can_manage_roles']) {
            $builder->add('roles', ChoiceType::class, [
                'label' => 'Rôle',
                'mapped' => false,
                'choices' => $roleChoices,
                'multiple' => false,
                'expanded' => true,
                'data' => $options['selected_role'],
            ]);
        }

        $builder
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
            ])
            ->add('modules', EntityType::class, [
                'class' => AppModule::class,
                'choice_label' => 'name',
                'choice_value' => 'id',
                'mapped' => false,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Modules accessibles',
                'data' => $options['selected_modules'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'password_required' => true,
            'selected_modules' => [],
            'can_manage_roles' => false,
            'selected_role' => 'ROLE_USER',
            'role_choices' => [
                'Utilisateur' => 'ROLE_USER',
                'Administrateur' => 'ROLE_ADMIN',
                'Super administrateur' => 'ROLE_SUPER_ADMIN',
            ],
        ]);
        $resolver->setAllowedTypes('password_required', 'bool');
        $resolver->setAllowedTypes('selected_modules', 'array');
        $resolver->setAllowedTypes('can_manage_roles', 'bool');
        $resolver->setAllowedTypes('selected_role', 'string');
        $resolver->setAllowedTypes('role_choices', 'array');
    }
}
