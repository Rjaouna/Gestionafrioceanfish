<?php

namespace App\Form;

use App\Entity\InventoryLocation;
use App\Entity\InventorySite;
use App\Repository\InventorySiteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InventoryLocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('site', EntityType::class, [
                'class' => InventorySite::class,
                'choice_label' => 'name',
                'query_builder' => static fn (InventorySiteRepository $repository) => $repository->createQueryBuilder('s')->andWhere('s.isActive = true')->orderBy('s.name', 'ASC'),
                'label' => 'Site',
                'placeholder' => 'Sélectionner un site',
                'disabled' => $options['site_locked'],
            ])
            ->add('name', TextType::class, ['label' => 'Nom de l’emplacement'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('isActive', CheckboxType::class, ['label' => 'Actif', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InventoryLocation::class]);
        $resolver->setDefault('site_locked', false);
        $resolver->setAllowedTypes('site_locked', 'bool');
    }
}
