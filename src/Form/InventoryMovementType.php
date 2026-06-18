<?php

namespace App\Form;

use App\Entity\InventoryItem;
use App\Entity\InventoryLocation;
use App\Entity\InventoryMovement;
use App\Entity\InventorySite;
use App\Entity\User;
use App\Repository\InventoryItemRepository;
use App\Repository\InventoryLocationRepository;
use App\Repository\InventorySiteRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InventoryMovementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $selectedItemId = $builder->getData() instanceof InventoryMovement
            ? $builder->getData()->getItem()?->getId()
            : null;

        $builder
            ->add('item', EntityType::class, [
                'class' => InventoryItem::class,
                'choice_label' => static fn (InventoryItem $item): string => sprintf('%s - %s', $item->getReference(), $item->getName()),
                'query_builder' => static function (InventoryItemRepository $repository) use ($selectedItemId) {
                    $query = $repository->createQueryBuilder('i')
                        ->andWhere('i.isDeleted = false')
                        ->orderBy('i.name', 'ASC');

                    if ($selectedItemId !== null) {
                        $query
                            ->andWhere('(i.isActive = true OR i.id = :selectedItem)')
                            ->setParameter('selectedItem', $selectedItemId);
                    } else {
                        $query->andWhere('i.isActive = true');
                    }

                    return $query;
                },
                'label' => 'Materiel',
                'placeholder' => 'Selectionner un materiel',
                'disabled' => $options['item_locked'],
            ])
            ->add('movementType', ChoiceType::class, [
                'label' => 'Type de mouvement',
                'choices' => InventoryMovement::TYPES,
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantite',
                'attr' => ['min' => 0],
            ])
            ->add('toSite', EntityType::class, [
                'class' => InventorySite::class,
                'choice_label' => 'name',
                'query_builder' => static fn (InventorySiteRepository $repository) => $repository->createQueryBuilder('s')->andWhere('s.isActive = true')->orderBy('s.name', 'ASC'),
                'label' => 'Site de destination',
                'required' => false,
                'placeholder' => 'Inchange',
            ])
            ->add('toLocation', EntityType::class, [
                'class' => InventoryLocation::class,
                'choice_label' => static fn (InventoryLocation $location): string => $location->getDisplayName(),
                'query_builder' => static fn (InventoryLocationRepository $repository) => $repository->createQueryBuilder('l')->leftJoin('l.site', 's')->addSelect('s')->andWhere('l.isActive = true')->orderBy('s.name', 'ASC')->addOrderBy('l.name', 'ASC'),
                'label' => 'Emplacement de destination',
                'required' => false,
                'placeholder' => 'Inchange',
            ])
            ->add('responsibleUser', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user): string => $user->getDisplayName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')->andWhere('u.isActive = true')->orderBy('u.firstName', 'ASC')->addOrderBy('u.lastName', 'ASC')->addOrderBy('u.email', 'ASC'),
                'label' => 'Responsable concerne',
                'required' => false,
                'placeholder' => 'Aucun',
            ])
            ->add('movementDate', DateTimeType::class, [
                'label' => 'Date du mouvement',
                'widget' => 'single_text',
            ])
            ->add('reason', TextType::class, [
                'label' => 'Motif',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InventoryMovement::class,
            'item_locked' => false,
        ]);
        $resolver->setAllowedTypes('item_locked', 'bool');
    }
}
