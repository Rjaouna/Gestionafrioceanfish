<?php

namespace App\Form;

use App\Entity\InventoryItem;
use App\Entity\InventoryLocation;
use App\Entity\InventorySite;
use App\Entity\User;
use App\Repository\InventoryLocationRepository;
use App\Repository\InventorySiteRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class InventoryItemType extends AbstractType
{
    use SmartChoiceTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $item = $builder->getData();
        $isExisting = $item instanceof InventoryItem && null !== $item->getId();
        $choiceLists = $options['choice_lists'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du matériel',
                'attr' => ['placeholder' => 'Ex. Ordinateur portable, imprimante, caisse...'],
            ])
            ->add('dimensions', TextType::class, [
                'label' => 'Dimensions',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. 60*40*15, D:45...',
                ],
            ])
            ->add('color', TextType::class, [
                'label' => 'Couleur',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. Blanche, gris, inox...',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('ownershipType', ChoiceType::class, [
                'label' => 'Propriete',
                'choices' => InventoryItem::OWNERSHIP_TYPES,
            ])
            ->add('ownerName', TextType::class, [
                'label' => 'Propriétaire externe',
                'required' => false,
                'attr' => ['placeholder' => 'Client, loueur ou fournisseur si besoin'],
            ])
            ->add('site', EntityType::class, [
                'class' => InventorySite::class,
                'choice_label' => 'name',
                'query_builder' => static fn (InventorySiteRepository $repository) => $repository->createQueryBuilder('s')->andWhere('s.isActive = true')->orderBy('s.name', 'ASC'),
                'label' => 'Site',
                'required' => false,
                'placeholder' => 'Aucun site',
            ])
            ->add('location', EntityType::class, [
                'class' => InventoryLocation::class,
                'choice_label' => static fn (InventoryLocation $location): string => $location->getDisplayName(),
                'query_builder' => static fn (InventoryLocationRepository $repository) => $repository->createQueryBuilder('l')->leftJoin('l.site', 's')->addSelect('s')->andWhere('l.isActive = true')->orderBy('s.name', 'ASC')->addOrderBy('l.name', 'ASC'),
                'label' => 'Emplacement',
                'required' => false,
                'placeholder' => 'Aucun emplacement',
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité totale',
                'attr' => ['min' => 0],
            ])
            ->add('availableQuantity', IntegerType::class, [
                'label' => 'Quantité disponible',
                'attr' => ['min' => 0],
            ])
            ->add('serialNumber', TextType::class, [
                'label' => 'Numéro de série',
                'required' => false,
            ])
            ->add('brand', TextType::class, [
                'label' => 'Marque',
                'required' => false,
            ])
            ->add('model', TextType::class, [
                'label' => 'Modele',
                'required' => false,
            ])
            ->add('condition', ChoiceType::class, [
                'label' => 'État',
                'choices' => InventoryItem::CONDITIONS,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => InventoryItem::STATUSES,
            ])
            ->add('logisticsStatus', ChoiceType::class, [
                'label' => 'Suivi entre les usines',
                'choices' => InventoryItem::LOGISTICS_STATUSES,
            ])
            ->add('acquisitionDate', DateType::class, [
                'label' => 'Date d achat',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('entryDate', DateType::class, [
                'label' => 'Date d’entrée',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('acquisitionValue', NumberType::class, [
                'label' => 'Prix / valeur unitaire',
                'required' => false,
                'scale' => 2,
            ])
            ->add('responsibleUser', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user): string => $user->getDisplayName(),
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')->andWhere('u.isActive = true')->orderBy('u.firstName', 'ASC')->addOrderBy('u.lastName', 'ASC')->addOrderBy('u.email', 'ASC'),
                'label' => 'Responsable',
                'required' => false,
                'placeholder' => 'Aucun responsable',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes internes',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('file', FileType::class, [
                'label' => 'Pièce jointe',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File(
                        maxSize: $options['max_file_size'],
                        mimeTypes: $options['allowed_mime_types'],
                        maxSizeMessage: 'Le fichier est trop volumineux.',
                        mimeTypesMessage: 'Ce type de fichier n’est pas autorisé.',
                    ),
                ],
                'attr' => ['accept' => implode(',', $options['allowed_mime_types'])],
            ])
            ->add('attachmentType', ChoiceType::class, [
                'label' => 'Type de pièce jointe',
                'mapped' => false,
                'required' => false,
                'choices' => [
                    'Photo' => 'photo',
                    'Facture' => 'invoice',
                    'Garantie' => 'warranty',
                    'Document' => 'document',
                    'Autre' => 'other',
                ],
                'data' => 'document',
            ]);

        $categoryName = trim((string) $options['category_name']);
        $configs = [
            'categoryName' => ['label' => 'Catégorie', 'values' => $choiceLists['categories'] ?? [], 'required' => false, 'maxlength' => 120, 'choice_options' => ['mapped' => false]],
            'unit' => ['label' => 'Unite', 'values' => $choiceLists['units'] ?? [], 'required' => true, 'maxlength' => 40],
        ];

        $this->addSmartChoice($builder, 'categoryName', 'Catégorie', $configs['categoryName']['values'], false, 120, $categoryName !== '' ? $categoryName : null, ['mapped' => false, 'data' => $categoryName !== '' ? $categoryName : null]);
        $this->addSmartChoice($builder, 'unit', 'Unite', $configs['unit']['values'], true, 40, $isExisting ? $item->getUnit() : null, $isExisting ? [] : ['data' => null]);
        $this->addSmartChoiceSubmitListener($builder, $configs);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InventoryItem::class,
            'allowed_mime_types' => [],
            'max_file_size' => '10M',
            'category_name' => '',
            'category_suggestions' => [],
            'choice_lists' => [],
        ]);
        $resolver->setAllowedTypes('allowed_mime_types', 'array');
        $resolver->setAllowedTypes('max_file_size', ['int', 'string']);
        $resolver->setAllowedTypes('category_name', ['string', 'null']);
        $resolver->setAllowedTypes('category_suggestions', 'array');
        $resolver->setAllowedTypes('choice_lists', 'array');
    }
}
