<?php

namespace App\Form;

use App\Entity\Expense;
use App\Entity\ExpenseCategory;
use App\Entity\ExpenseDocument;
use App\Entity\Intervenant;
use App\Repository\ExpenseCategoryRepository;
use App\Repository\IntervenantRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ExpenseType extends AbstractType
{
    public function __construct(private readonly IntervenantRepository $intervenantRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fileConstraints = [
            new Assert\File(
                maxSize: $options['max_file_size'],
                mimeTypes: $options['allowed_mime_types'],
                maxSizeMessage: 'Le fichier est trop volumineux. La taille maximale autorisée est de {{ limit }} {{ suffix }}.',
                mimeTypesMessage: 'Ce type de fichier n’est pas autorisé.',
                uploadIniSizeErrorMessage: 'Le fichier est trop volumineux. La taille maximale autorisée par le serveur est de {{ limit }} {{ suffix }}.',
            ),
        ];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Libellé de la dépense',
                'attr' => ['placeholder' => 'Ex. Plein carburant utilitaire'],
            ])
            ->add('expenseDate', DateType::class, [
                'label' => 'Date de la dépense',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['placeholder' => 'Ex. 2026-06-16'],
            ])
            ->add('category', EntityType::class, [
                'class' => ExpenseCategory::class,
                'choice_label' => 'name',
                'query_builder' => static fn (ExpenseCategoryRepository $repository) => $repository->createQueryBuilder('c')
                    ->andWhere('c.isActive = true')
                    ->orderBy('c.name', 'ASC'),
                'label' => 'Catégorie',
                'placeholder' => 'Sélectionner une catégorie',
                'required' => false,
            ])
            ->add('customCategoryName', TextType::class, [
                'label' => 'Nouvelle catégorie',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Length(max: 140),
                ],
                'attr' => ['placeholder' => 'Ex. Péage, carburant, matériel...'],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Moyen de paiement',
                'choices' => Expense::PAYMENT_METHODS,
                'placeholder' => 'Sélectionner un moyen de paiement',
            ])
            ->add('amountHt', NumberType::class, [
                'label' => 'Montant HT',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'placeholder' => 'Ex. 120.00',
                    'min' => '0',
                    'step' => '0.01',
                    'data-expense-amount-ht' => 'true',
                ],
            ])
            ->add('vatRate', NumberType::class, [
                'label' => 'Taux de TVA (%)',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'placeholder' => 'Ex. 20',
                    'min' => '0',
                    'max' => '100',
                    'step' => '0.01',
                    'data-expense-vat-rate' => 'true',
                ],
            ])
            ->add('supplierIntervenant', EntityType::class, [
                'class' => Intervenant::class,
                'choice_label' => static fn (Intervenant $intervenant): string => $intervenant->getDisplayName(),
                'choice_attr' => static fn (Intervenant $intervenant): array => [
                    'data-name' => $intervenant->getDisplayName(),
                    'data-email' => $intervenant->getEmail() ?? '',
                    'data-phone' => $intervenant->getPhone() ?? '',
                    'data-type' => $intervenant->getType(),
                    'data-speciality' => $intervenant->getSpeciality() ?? '',
                ],
                'query_builder' => static fn (IntervenantRepository $repository) => $repository->createQueryBuilder('i')
                    ->andWhere('i.isActive = true')
                    ->orderBy('i.lastname', 'ASC')
                    ->addOrderBy('i.firstname', 'ASC'),
                'label' => 'Fournisseur ou bénéficiaire',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Autre / saisie manuelle',
                'attr' => ['data-expense-supplier-select' => 'true'],
            ])
            ->add('supplierName', TextType::class, [
                'label' => 'Nom du fournisseur',
                'attr' => [
                    'placeholder' => 'Ex. TotalEnergies, Orange, prestataire...',
                    'data-expense-supplier-name' => 'true',
                ],
            ])
            ->add('supplierEmail', EmailType::class, [
                'label' => 'Email fournisseur',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. facturation@fournisseur.com',
                    'data-expense-supplier-email' => 'true',
                ],
            ])
            ->add('supplierPhone', TelType::class, [
                'label' => 'Téléphone fournisseur',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex. 06 12 34 56 78',
                    'data-expense-supplier-phone' => 'true',
                ],
            ])
            ->add('invoiceNumber', TextType::class, [
                'label' => 'Numéro de facture',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. FAC-2026-001'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Note interne, contexte ou détail utile...'],
            ])
            ->add('documentType', ChoiceType::class, [
                'label' => 'Type de justificatif',
                'mapped' => false,
                'required' => false,
                'choices' => ExpenseDocument::DOCUMENT_TYPES,
                'placeholder' => 'Sélectionner un type',
                'data' => ExpenseDocument::TYPE_INVOICE,
            ])
            ->add('documentFile', FileType::class, [
                'label' => $options['document_required'] ? 'Justificatif' : 'Remplacer le justificatif',
                'mapped' => false,
                'required' => $options['document_required'],
                'constraints' => $fileConstraints,
                'attr' => [
                    'accept' => implode(',', $options['allowed_mime_types']),
                    'placeholder' => 'Sélectionnez une facture, un reçu ou un justificatif',
                ],
                'help' => $options['document_required'] ? null : 'Laissez vide pour conserver le justificatif actuel.',
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            if (($data['category'] ?? null) === '__other__') {
                $data['category'] = '';
            }

            if (!empty($data['supplierIntervenant'])) {
                $intervenant = $this->intervenantRepository->find((int) $data['supplierIntervenant']);
                if ($intervenant instanceof Intervenant) {
                    $data['supplierName'] = $intervenant->getDisplayName();
                    $data['supplierEmail'] = $intervenant->getEmail();
                    $data['supplierPhone'] = $intervenant->getPhone();
                }
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Expense::class,
            'document_required' => false,
            'max_file_size' => '10M',
            'allowed_mime_types' => [],
        ]);
        $resolver->setAllowedTypes('document_required', 'bool');
        $resolver->setAllowedTypes('max_file_size', ['int', 'string']);
        $resolver->setAllowedTypes('allowed_mime_types', 'array');
    }
}
