<?php

namespace App\Form;

use App\Entity\CoutRevient;
use App\Entity\FishReception;
use App\Repository\FishReceptionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CoutRevientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateProduction', DateType::class, [
                'label' => 'Date production',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('numeroLot', TextType::class, [
                'label' => 'Numéro lot',
                'required' => false,
                'attr' => ['maxlength' => 100, 'placeholder' => 'Auto si vide : CR-2026-0001'],
                'help' => 'Laissez vide pour générer un numéro automatiquement.',
            ])
            ->add('produit', TextType::class, [
                'label' => 'Produit',
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. Filet de sardine'],
            ])
            ->add('especePoisson', TextType::class, [
                'label' => 'Espèce poisson',
                'required' => false,
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. Sardine, anchois, maquereau'],
            ])
            ->add('client', TextType::class, [
                'label' => 'Client',
                'required' => false,
                'attr' => ['maxlength' => 150],
            ])
            ->add('responsableProduction', TextType::class, [
                'label' => 'Responsable production',
                'required' => false,
                'attr' => ['maxlength' => 150],
            ])
            ->add('reception', EntityType::class, [
                'label' => 'Réception matière première',
                'class' => FishReception::class,
                'choice_label' => static fn (FishReception $reception): string => sprintf(
                    '%s - %s - %s kg dispo - %s %s/kg',
                    (string) $reception->getNumeroReception(),
                    (string) $reception->getEspecePoisson(),
                    number_format($reception->getQuantiteDisponibleProductionValue(), 3, ',', ' '),
                    number_format($reception->getCoutKgReceptionValue(), 2, ',', ' '),
                    $reception->getReceptionDevise(),
                ),
                'query_builder' => static fn (FishReceptionRepository $repository) => $repository->createQueryBuilder('r')
                    ->andWhere('r.isDeleted = false')
                    ->andWhere('r.statut NOT IN (:locked)')
                    ->andWhere('(r.quantiteReceptionnee > r.quantiteUtiliseeProduction OR r.id = :currentReceptionId)')
                    ->setParameter('locked', [FishReception::STATUS_DRAFT, FishReception::STATUS_BLOCKED, FishReception::STATUS_CLOSED])
                    ->setParameter('currentReceptionId', $options['current_reception'] instanceof FishReception ? $options['current_reception']->getId() : 0)
                    ->orderBy('r.dateReception', 'DESC')
                    ->addOrderBy('r.id', 'DESC'),
                'choice_attr' => function (FishReception $reception) use ($options): array {
                    $current = $options['current_reception'];
                    $currentAllocation = $current instanceof FishReception && $current->getId() === $reception->getId()
                        ? (float) $options['current_allocation']
                        : 0.0;
                    $available = $reception->getQuantiteDisponibleProductionValue() + $currentAllocation;

                    return [
                        'data-reception-number' => (string) $reception->getNumeroReception(),
                        'data-lot-number' => (string) $reception->getNumeroLot(),
                        'data-species' => (string) $reception->getEspecePoisson(),
                        'data-product' => (string) $reception->getPresentationProduit(),
                        'data-supplier' => (string) $reception->getFournisseur(),
                        'data-received' => (string) $reception->getQuantiteReceptionnee(),
                        'data-used' => (string) $reception->getQuantiteUtiliseeProduction(),
                        'data-available' => (string) number_format($available, 3, '.', ''),
                        'data-operation-label' => $reception->getOperationTypeLabel(),
                        'data-reception-currency' => $reception->getReceptionDevise(),
                        'data-reception-total-cost' => (string) number_format($reception->getCoutTotalReceptionValue(), 2, '.', ''),
                        'data-reception-cost-kg' => (string) number_format($reception->getCoutKgReceptionValue(), 6, '.', ''),
                        'data-reception-purchase-cost-kg' => (string) number_format($reception->getCoutAchatKgReceptionValue(), 6, '.', ''),
                        'data-reception-transport-fee-kg' => (string) number_format($reception->getCoutFraisTransportKgReceptionValue(), 6, '.', ''),
                        'data-reception-other-fees-kg' => (string) number_format($reception->getCoutAutresFraisKgReceptionValue(), 6, '.', ''),
                    ];
                },
                'placeholder' => 'Choisir une reception disponible',
                'required' => false,
                'help' => 'La quantité saisie dans poids mis en production sera déduite de cette réception.',
            ])
            ->add('poidsBrutRecu', NumberType::class, $this->numberOptions('Poids brut recu (kg)', 3, '0.001'))
            ->add('poidsMisEnProduction', NumberType::class, $this->numberOptions('Poids mis en production (kg)', 3, '0.001'))
            ->add('prixAchatKg', NumberType::class, $this->numberOptions('Prix achat / kg', 2, '0.01'))
            ->add('fraisTransportAchat', NumberType::class, $this->numberOptions('Frais transport achat', 2, '0.01', false))
            ->add('autresFraisAchat', NumberType::class, $this->numberOptions('Autres frais achat', 2, '0.01', false))
            ->add('poidsProduitFini', NumberType::class, $this->numberOptions('Poids produit fini (kg)', 3, '0.001'))
            ->add('poidsDechets', NumberType::class, $this->numberOptions('Poids déchets (kg)', 3, '0.001', false))
            ->add('poidsPerte', NumberType::class, $this->numberOptions('Poids perte (kg)', 3, '0.001', false))
            ->add('modeCalculMainOeuvre', ChoiceType::class, [
                'label' => 'Mode calcul main d oeuvre',
                'choices' => array_flip(CoutRevient::MODE_LABELS),
                'attr' => ['data-cout-mode' => 'true'],
            ])
            ->add('nombreOperatrices', IntegerType::class, [
                'label' => 'Nombre operatrices',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1, 'data-cout-field' => 'nombreOperatrices'],
            ])
            ->add('nombreHeures', NumberType::class, $this->numberOptions('Nombre heures', 2, '0.01', false))
            ->add('coutHoraireMoyen', NumberType::class, $this->numberOptions('Cout horaire moyen', 2, '0.01', false))
            ->add('prixTacheKg', NumberType::class, $this->numberOptions('Prix tache / kg', 2, '0.01', false))
            ->add('kgTraitesMainOeuvre', NumberType::class, $this->numberOptions('Kg traites main oeuvre', 3, '0.001', false))
            ->add('coutMainOeuvreDirect', NumberType::class, $this->numberOptions('Montant direct main oeuvre', 2, '0.01', false))
            ->add('nombreCartons', IntegerType::class, [
                'label' => 'Nombre cartons',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1, 'data-cout-field' => 'nombreCartons'],
            ])
            ->add('prixCarton', NumberType::class, $this->numberOptions('Prix carton', 2, '0.01', false))
            ->add('nombreSachets', IntegerType::class, [
                'label' => 'Nombre sachets',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1, 'data-cout-field' => 'nombreSachets'],
            ])
            ->add('prixSachet', NumberType::class, $this->numberOptions('Prix sachet', 2, '0.01', false))
            ->add('coutEtiquettes', NumberType::class, $this->numberOptions('Cout etiquettes', 2, '0.01', false))
            ->add('coutFilmPlastique', NumberType::class, $this->numberOptions('Cout film plastique', 2, '0.01', false))
            ->add('autresCoutEmballage', NumberType::class, $this->numberOptions('Autres couts emballage', 2, '0.01', false))
            ->add('coutElectricite', NumberType::class, $this->numberOptions('Electricite', 2, '0.01', false))
            ->add('coutEau', NumberType::class, $this->numberOptions('Eau', 2, '0.01', false))
            ->add('coutGlace', NumberType::class, $this->numberOptions('Glace', 2, '0.01', false))
            ->add('coutNettoyage', NumberType::class, $this->numberOptions('Nettoyage', 2, '0.01', false))
            ->add('coutMaintenance', NumberType::class, $this->numberOptions('Maintenance', 2, '0.01', false))
            ->add('coutTransportLivraison', NumberType::class, $this->numberOptions('Transport livraison', 2, '0.01', false))
            ->add('autresCharges', NumberType::class, $this->numberOptions('Autres charges', 2, '0.01', false))
            ->add('prixVenteKg', NumberType::class, $this->numberOptions('Prix vente / kg', 2, '0.01', false, false))
            ->add('observation', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 1500],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoutRevient::class,
            'current_reception' => null,
            'current_allocation' => 0.0,
        ]);
        $resolver->setAllowedTypes('current_reception', [FishReception::class, 'null']);
        $resolver->setAllowedTypes('current_allocation', ['int', 'float']);
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, int $scale, string $step, bool $required = true, bool $defaultZero = true): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'html5' => true,
            'empty_data' => $defaultZero ? '0' : null,
            'attr' => [
                'min' => 0,
                'step' => $step,
                'inputmode' => 'decimal',
                'data-cout-field' => 'true',
            ],
        ];
    }
}
