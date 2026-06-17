<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fileConstraints = [
            new Assert\File(
                maxSize: $options['max_file_size'],
                mimeTypes: $options['allowed_mime_types'],
                maxSizeMessage: 'Le fichier est trop volumineux. La taille maximale autorisée est de {{ limit }} {{ suffix }}.',
                mimeTypesMessage: 'Ce type de fichier n’est pas autorisé.',
                uploadIniSizeErrorMessage: 'Le fichier est trop volumineux. La taille maximale autorisée par le serveur est de {{ limit }} {{ suffix }}.',
                uploadFormSizeErrorMessage: 'Le fichier dépasse la taille maximale autorisée.',
            ),
        ];

        if ($options['file_required']) {
            array_unshift($fileConstraints, new Assert\NotBlank(message: 'Le fichier est obligatoire.'));
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du document',
                'attr' => [
                    'placeholder' => 'Ex. Contrat client signé',
                    'data-document-name' => 'true',
                ],
            ])
            ->add('file', FileType::class, [
                'label' => $options['file_required'] ? 'Fichier' : 'Remplacer le fichier',
                'mapped' => false,
                'required' => $options['file_required'],
                'constraints' => $fileConstraints,
                'attr' => [
                    'accept' => implode(',', $options['allowed_mime_types']),
                    'placeholder' => 'Sélectionnez un fichier autorisé',
                    'data-document-file' => 'true',
                ],
                'help' => $options['file_required'] ? null : 'Laissez vide pour conserver le fichier actuel.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Ajoutez un descriptif, une référence ou une note utile...',
                ],
            ])
            ->add('category', TextType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'attr' => [
                    'list' => 'document-category-suggestions',
                    'placeholder' => 'Ex. Contrat, Facture, Administratif...',
                ],
            ])
            ->add('issuer', TextType::class, [
                'label' => 'Émetteur / origine',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. Client, Fournisseur, Banque...',
                ],
            ])
            ->add('language', TextType::class, [
                'label' => 'Langue',
                'required' => false,
                'attr' => [
                    'list' => 'document-language-suggestions',
                    'placeholder' => 'Ex. Français, Anglais, Portugais...',
                ],
            ])
            ->add('tags', TextareaType::class, [
                'label' => 'Tags',
                'required' => false,
                'help' => 'Séparez les mots-clés par des virgules.',
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Ex. arrêté conjoint, Guinée-Bissau, juridique, 2025',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_flip(Document::STATUS_LABELS),
            ])
            ->add('expiresAt', DateType::class, [
                'label' => 'Date d’expiration',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('confidentialityLevel', ChoiceType::class, [
                'label' => 'Confidentialité',
                'required' => false,
                'placeholder' => 'Non renseignée',
                'choices' => array_flip(Document::CONFIDENTIALITY_LABELS),
            ])
            ->add('version', TextType::class, [
                'label' => 'Version',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. v1, signé, brouillon, final',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'file_required' => true,
            'max_file_size' => '10M',
            'allowed_mime_types' => [],
        ]);
        $resolver->setAllowedTypes('file_required', 'bool');
        $resolver->setAllowedTypes('max_file_size', ['int', 'string']);
        $resolver->setAllowedTypes('allowed_mime_types', 'array');
    }
}
