<?php

namespace App\Form;

use App\Entity\InventoryCampaign;
use App\Entity\InventorySite;
use App\Entity\User;
use App\Repository\InventorySiteRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InventoryCampaignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la campagne',
                'attr' => ['placeholder' => 'Ex. Inventaire depot juin 2026'],
            ])
            ->add('site', EntityType::class, [
                'class' => InventorySite::class,
                'choice_label' => 'name',
                'query_builder' => static fn (InventorySiteRepository $repository) => $repository->createQueryBuilder('s')->andWhere('s.isActive = true')->orderBy('s.name', 'ASC'),
                'label' => 'Site controle',
                'required' => false,
                'placeholder' => 'Tous les sites visibles',
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de debut',
                'widget' => 'single_text',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => InventoryCampaign::STATUSES,
            ])
            ->add('responsibleUser', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user): string => $user->getDisplayName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')->andWhere('u.isActive = true')->orderBy('u.firstName', 'ASC')->addOrderBy('u.lastName', 'ASC')->addOrderBy('u.email', 'ASC'),
                'label' => 'Responsable',
                'required' => false,
                'placeholder' => 'Moi',
            ])
            ->add('participantsText', TextareaType::class, [
                'label' => 'Participants',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Un nom ou email par ligne'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InventoryCampaign::class]);
    }
}
