<?php

namespace App\Form;

use App\Entity\InterimWorker;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class InterimWorkerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $positionChoices = array_values(array_unique(array_filter(array_map(
            static fn (mixed $position): string => trim((string) $position),
            array_merge(InterimWorker::POSITION_CHOICES, $options['position_choices']),
        ))));
        natcasesort($positionChoices);

        $photoConstraints = [
            new Assert\File(
                maxSize: $options['max_photo_size'],
                mimeTypes: $options['photo_mime_types'],
                maxSizeMessage: 'La photo est trop volumineuse.',
                mimeTypesMessage: 'La photo doit etre au format JPG, JPEG, PNG ou WEBP.',
            ),
        ];

        $documentConstraints = [
            new Assert\All([
                new Assert\File(
                    maxSize: $options['max_document_size'],
                    mimeTypes: $options['document_mime_types'],
                    maxSizeMessage: 'Un document est trop volumineux.',
                    mimeTypesMessage: 'Les documents acceptes sont JPG, JPEG, PNG ou PDF.',
                ),
            ]),
        ];

        $builder
            ->add('photo', FileType::class, [
                'label' => 'Photo',
                'mapped' => false,
                'required' => false,
                'constraints' => $photoConstraints,
                'attr' => [
                    'accept' => implode(',', $options['photo_mime_types']),
                    'data-interim-photo-input' => 'true',
                ],
                'help' => 'JPG, JPEG, PNG ou WEBP. 2 Mo maximum. Laissez vide pour conserver la photo actuelle.',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Nom', 'maxlength' => 120],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['placeholder' => 'Prénom', 'maxlength' => 120],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Adresse complete', 'maxlength' => 255],
            ])
            ->add('position', ChoiceType::class, [
                'label' => 'Poste',
                'required' => false,
                'placeholder' => 'Non renseigne',
                'choices' => array_combine($positionChoices, $positionChoices),
                'empty_data' => null,
            ])
            ->add('workerType', ChoiceType::class, [
                'label' => 'Profil',
                'required' => false,
                'choices' => array_flip(InterimWorker::TYPE_LABELS),
                'empty_data' => InterimWorker::TYPE_OTHER,
                'help' => 'Sélectionnez Étudiant(e) pour les personnes prévues uniquement pendant les vacances.',
            ])
            ->add('registrationNumber', TextType::class, [
                'label' => 'Matricule',
                'required' => false,
                'empty_data' => null,
                'attr' => ['placeholder' => 'Auto si vide : INT-2026-0001', 'maxlength' => 50],
                'help' => 'Laissez vide pour generer le matricule automatiquement.',
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'label' => 'Téléphone',
                'attr' => [
                    'placeholder' => '06..., 07..., +212... ou +33...',
                    'inputmode' => 'tel',
                    'maxlength' => 20,
                    'data-interim-phone' => 'true',
                ],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'max' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                    'data-interim-birth-date' => 'true',
                ],
            ])
            ->add('birthPlace', TextType::class, [
                'label' => 'Lieu de naissance',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ])
            ->add('cin', TextType::class, [
                'label' => 'CIN',
                'required' => false,
                'attr' => ['maxlength' => 30, 'data-interim-cin' => 'true'],
            ])
            ->add('passportNumber', TextType::class, [
                'label' => 'N° passeport',
                'required' => false,
                'attr' => ['maxlength' => 40, 'data-interim-passport-number' => 'true'],
            ])
            ->add('passportIssueCountry', TextType::class, [
                'label' => 'Pays emetteur',
                'required' => false,
                'attr' => ['maxlength' => 3, 'placeholder' => 'CIV, MAR, FRA...'],
            ])
            ->add('nationality', TextType::class, [
                'label' => 'Nationalite',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Sexe',
                'required' => false,
                'placeholder' => 'Non renseigne',
                'choices' => [
                    'Femme' => 'F',
                    'Homme' => 'M',
                    'Non precise' => 'X',
                ],
            ])
            ->add('passportIssuedAt', DateType::class, [
                'label' => 'Date delivrance passeport',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('passportExpiresAt', DateType::class, [
                'label' => 'Date expiration passeport',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('passportMrz', HiddenType::class, [
                'required' => false,
            ])
            ->add('familySituation', ChoiceType::class, [
                'label' => 'Situation familiale',
                'required' => false,
                'choices' => array_flip(InterimWorker::FAMILY_LABELS),
                'expanded' => true,
                'empty_data' => InterimWorker::FAMILY_SINGLE,
            ])
            ->add('childrenCount', IntegerType::class, [
                'label' => 'Nombre d’enfants',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'max' => 20, 'data-interim-children-count' => 'true'],
            ])
            ->add('hireDate', DateType::class, [
                'required' => false,
                'label' => 'Date d’embauche',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'min' => (new \DateTimeImmutable('-20 years'))->format('Y-m-d'),
                    'max' => (new \DateTimeImmutable('+1 month'))->format('Y-m-d'),
                ],
            ])
            ->add('missionEndDate', DateType::class, [
                'label' => 'Date de fin de mission',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('tempAgency', TextType::class, [
                'label' => 'Agence d’interim',
                'required' => false,
                'attr' => ['list' => 'interim-agency-suggestions'],
            ])
            ->add('managerObservations', TextareaType::class, [
                'label' => 'Observations / commentaires du responsable',
                'required' => false,
                'attr' => ['rows' => 6, 'maxlength' => 1000, 'data-interim-observations' => 'true'],
            ])
            ->add('employeeSignature', TextType::class, [
                'label' => 'Signature du salarie',
                'required' => false,
                'attr' => ['maxlength' => 120, 'placeholder' => 'Nom ou mention de signature'],
            ])
            ->add('managerSignature', TextType::class, [
                'label' => 'Signature du responsable',
                'required' => false,
                'attr' => ['maxlength' => 120, 'placeholder' => 'Nom du responsable'],
            ])
            ->add('signatureDate', DateType::class, [
                'label' => 'Date de signature',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('internalComment', TextareaType::class, [
                'label' => 'Commentaire interne',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'choices' => array_flip(InterimWorker::STATUS_LABELS),
                'empty_data' => InterimWorker::STATUS_ACTIVE,
                'help' => 'Pour une fin de mission ou un statut A ne pas rappeler, utilisez les boutons d action afin de saisir le motif et la date.',
            ])
            ->add('documents', FileType::class, [
                'label' => 'Documents joints',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'constraints' => $documentConstraints,
                'attr' => [
                    'accept' => implode(',', $options['document_mime_types']),
                    'multiple' => 'multiple',
                ],
                'help' => 'Formats acceptes : JPG, JPEG, PNG ou PDF.',
            ]);

        if (!$options['show_registration_number']) {
            $builder->remove('registrationNumber');
        }

        if (!$options['show_hire_date']) {
            $builder->remove('hireDate');
        }

        if (!$options['show_mission_end_date']) {
            $builder->remove('missionEndDate');
        }

        if (!$options['show_status']) {
            $builder->remove('status');
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InterimWorker::class,
            'max_photo_size' => '2M',
            'max_document_size' => '10M',
            'photo_mime_types' => [],
            'document_mime_types' => [],
            'show_registration_number' => true,
            'show_hire_date' => false,
            'show_mission_end_date' => false,
            'show_status' => true,
            'position_choices' => [],
        ]);
        $resolver->setAllowedTypes('max_photo_size', ['int', 'string']);
        $resolver->setAllowedTypes('max_document_size', ['int', 'string']);
        $resolver->setAllowedTypes('photo_mime_types', 'array');
        $resolver->setAllowedTypes('document_mime_types', 'array');
        $resolver->setAllowedTypes('show_registration_number', 'bool');
        $resolver->setAllowedTypes('show_hire_date', 'bool');
        $resolver->setAllowedTypes('show_mission_end_date', 'bool');
        $resolver->setAllowedTypes('show_status', 'bool');
        $resolver->setAllowedTypes('position_choices', 'array');
    }
}
