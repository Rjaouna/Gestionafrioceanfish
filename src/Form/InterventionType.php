<?php

namespace App\Form;

use App\Entity\Intervention;
use App\Entity\Intervenant;
use App\Entity\MaintenanceContract;
use App\Repository\IntervenantRepository;
use App\Repository\MaintenanceContractRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InterventionType extends AbstractType
{
    public function __construct(private readonly IntervenantRepository $intervenantRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex. Maintenance climatisation'],
            ])
            ->add('intervenant', EntityType::class, [
                'class' => Intervenant::class,
                'choice_label' => static fn (Intervenant $intervenant): string => $intervenant->getDisplayLabel(),
                'choice_attr' => static fn (Intervenant $intervenant): array => [
                    'data-name' => $intervenant->getDisplayLabel(),
                    'data-email' => $intervenant->getEmail() ?? '',
                    'data-phone' => $intervenant->getPhone() ?? '',
                    'data-type' => $intervenant->getType(),
                    'data-speciality' => $intervenant->getSpeciality() ?? '',
                ],
                'query_builder' => static fn (IntervenantRepository $repository) => $repository->createQueryBuilder('i')
                    ->andWhere('i.isActive = true')
                    ->andWhere('i.isDeleted = false')
                    ->orderBy('i.lastname', 'ASC')
                    ->addOrderBy('i.firstname', 'ASC'),
                'label' => 'Intervenant',
                'required' => true,
                'placeholder' => 'Sélectionner un intervenant',
                'attr' => ['data-maintenance-intervenant-select' => 'true'],
                'choice_filter' => static function (?Intervenant $intervenant) use ($options): bool {
                    if (!is_array($options['visible_intervenant_ids'])) {
                        return true;
                    }

                    return $intervenant instanceof Intervenant && in_array($intervenant->getId(), $options['visible_intervenant_ids'], true);
                },
            ])
            ->add('contract', EntityType::class, [
                'class' => MaintenanceContract::class,
                'choice_label' => static fn (MaintenanceContract $contract): string => sprintf('%s - %s', $contract->getReference(), $contract->getCustomerName()),
                'choice_attr' => static fn (MaintenanceContract $contract): array => [
                    'data-intervenant-id' => (string) ($contract->getIntervenant()?->getId() ?? ''),
                ],
                'query_builder' => static fn (MaintenanceContractRepository $repository) => $repository->createQueryBuilder('c')
                    ->andWhere('c.isActive = true')
                    ->andWhere('c.isDeleted = false')
                    ->orderBy('c.reference', 'ASC'),
                'label' => 'Contrat lié',
                'required' => false,
                'placeholder' => 'Sélectionner d’abord un intervenant',
                'attr' => ['data-maintenance-contract-select' => 'true'],
                'choice_filter' => static function (?MaintenanceContract $contract) use ($options): bool {
                    if (!is_array($options['visible_contract_ids'])) {
                        return true;
                    }

                    return $contract instanceof MaintenanceContract && in_array($contract->getId(), $options['visible_contract_ids'], true);
                },
            ])
            ->add('customerName', TextType::class, [
                'label' => 'Nom de l’intervenant',
                'attr' => [
                    'placeholder' => 'Rempli automatiquement après sélection',
                    'readonly' => true,
                    'data-maintenance-intervenant-name' => 'true',
                ],
            ])
            ->add('customerEmail', EmailType::class, [
                'label' => 'Email intervenant',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Rempli automatiquement après sélection',
                    'readonly' => true,
                    'data-maintenance-intervenant-email' => 'true',
                ],
            ])
            ->add('customerPhone', TelType::class, [
                'label' => 'Téléphone intervenant',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Rempli automatiquement après sélection',
                    'readonly' => true,
                    'data-maintenance-intervenant-phone' => 'true',
                ],
            ])
            ->add('customerAddress', TextareaType::class, [
                'label' => 'Adresse / lieu d’intervention',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Adresse où l’intervention aura lieu'],
            ])
            ->add('plannedAt', DateTimeType::class, [
                'label' => 'Date prévue',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['placeholder' => 'Ex. 2026-06-16 14:00'],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'placeholder' => 'Sélectionner une priorité',
                'choices' => Intervention::PRIORITIES,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Besoin, contexte, problème constaté...'],
            ])
            ->add('internalNotes', TextareaType::class, [
                'label' => 'Notes internes',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Notes visibles uniquement en interne...'],
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data) || empty($data['intervenant'])) {
                return;
            }

            $intervenant = $this->intervenantRepository->find((int) $data['intervenant']);
            if (!$intervenant instanceof Intervenant) {
                return;
            }

            $data['customerName'] = $intervenant->getDisplayLabel();
            $data['customerEmail'] = $intervenant->getEmail();
            $data['customerPhone'] = $intervenant->getPhone();
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Intervention::class,
            'visible_intervenant_ids' => null,
            'visible_contract_ids' => null,
        ]);
        $resolver->setAllowedTypes('visible_intervenant_ids', ['null', 'array']);
        $resolver->setAllowedTypes('visible_contract_ids', ['null', 'array']);
    }
}
