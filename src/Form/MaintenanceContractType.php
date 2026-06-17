<?php

namespace App\Form;

use App\Entity\MaintenanceContract;
use App\Entity\Intervenant;
use App\Repository\IntervenantRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MaintenanceContractType extends AbstractType
{
    public function __construct(private readonly IntervenantRepository $intervenantRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('intervenant', EntityType::class, [
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
                'label' => 'Adresse / lieu couvert',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Adresse ou périmètre couvert par ce contrat'],
            ])
            ->add('contractType', HiddenType::class, [
                'required' => false,
                'attr' => ['data-maintenance-contract-type-value' => 'true'],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'placeholder' => 'Ex. 2026-01-01',
                    'data-maintenance-contract-start' => 'true',
                ],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'placeholder' => 'Ex. 2026-12-31',
                    'data-maintenance-contract-end' => 'true',
                ],
            ])
            ->add('interventionFrequency', ChoiceType::class, [
                'label' => 'Fréquence',
                'placeholder' => 'Sélectionner une fréquence',
                'choices' => MaintenanceContract::FREQUENCIES,
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant',
                'required' => false,
                'scale' => 2,
                'attr' => ['placeholder' => 'Ex. 1200.00'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'placeholder' => 'Sélectionner un statut',
                'choices' => MaintenanceContract::STATUSES,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Résumé du contrat...'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes internes',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Notes internes...'],
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

            $data['customerName'] = $intervenant->getDisplayName();
            $data['customerEmail'] = $intervenant->getEmail();
            $data['customerPhone'] = $intervenant->getPhone();
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MaintenanceContract::class,
            'visible_intervenant_ids' => null,
        ]);
        $resolver->setAllowedTypes('visible_intervenant_ids', ['null', 'array']);
    }
}
