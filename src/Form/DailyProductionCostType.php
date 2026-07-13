<?php

namespace App\Form;

use App\Entity\DailyProductionCost;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DailyProductionCostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('productionDate', DateType::class, [
                'label' => 'Date production',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['placeholder' => 'Date de la journee de production'],
            ])
            ->add('reference', TextType::class, [
                'label' => 'Reference',
                'required' => false,
                'attr' => ['maxlength' => 100, 'placeholder' => 'Auto si vide : CJ-2026-0001'],
            ])
            ->add('productName', TextType::class, [
                'label' => 'Produit',
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. Anchois filet'],
            ])
            ->add('responsible', TextType::class, [
                'label' => 'Responsable',
                'required' => false,
                'attr' => ['maxlength' => 150, 'placeholder' => 'Chef de production ou responsable du jour'],
            ])
            ->add('rawQuantityKg', NumberType::class, $this->numberOptions('Anchois sorti pour transformation (kg)', 3, '0.001', 'Ex. 600'))
            ->add('finishedProductKg', NumberType::class, $this->numberOptions('Poids produit fini (kg)', 3, '0.001', 'Ex. 288'))
            ->add('wasteKg', NumberType::class, $this->numberOptions('Poids dechets (kg)', 3, '0.001', 'Ex. 266'))
            ->add('lossKg', NumberType::class, $this->numberOptions('Poids pertes process (kg)', 3, '0.001', 'Ex. 46'))
            ->add('hourlyWorkers', IntegerType::class, [
                'label' => 'Nombre personnes a l heure',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1, 'placeholder' => 'Ex. 4'],
            ])
            ->add('hourlyHours', NumberType::class, $this->numberOptions('Heures par personne', 2, '0.01', 'Ex. 8', false))
            ->add('hourlyRate', NumberType::class, $this->numberOptions('Taux horaire moyen', 2, '0.01', 'Ex. 14', false))
            ->add('cleaningKg', NumberType::class, $this->numberOptions('Nettoyage anchois (kg)', 3, '0.001', 'Ex. 300', false))
            ->add('cleaningPricePerKg', NumberType::class, $this->numberOptions('Prix nettoyage / kg', 2, '0.01', 'Ex. 0.83', false))
            ->add('boxingKg', NumberType::class, $this->numberOptions('Mise en caisse (kg)', 3, '0.001', 'Ex. 280', false))
            ->add('boxingPricePerKg', NumberType::class, $this->numberOptions('Prix mise en caisse / kg', 2, '0.01', 'Ex. 2', false))
            ->add('otherTaskAmount', NumberType::class, $this->numberOptions('Autres taches', 2, '0.01', 'Montant direct', false))
            ->add('fixedSalaryMonthlyTotal', NumberType::class, $this->numberOptions('Masse salariale fixe mensuelle', 2, '0.01', 'Total salaires fixes du mois', false))
            ->add('fixedSalaryWorkingDays', IntegerType::class, [
                'label' => 'Jours travailles / mois',
                'required' => false,
                'empty_data' => '26',
                'attr' => ['min' => 1, 'step' => 1, 'placeholder' => 'Ex. 26'],
            ])
            ->add('cartonCount', IntegerType::class, [
                'label' => 'Nombre cartons',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1, 'placeholder' => 'Ex. 20'],
            ])
            ->add('cartonUnitCost', NumberType::class, $this->numberOptions('Prix carton', 2, '0.01', 'Dh / carton', false))
            ->add('sachetCount', IntegerType::class, [
                'label' => 'Nombre sachets',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1, 'placeholder' => 'Ex. 50'],
            ])
            ->add('sachetUnitCost', NumberType::class, $this->numberOptions('Prix sachet', 2, '0.01', 'Dh / sachet', false))
            ->add('labelCost', NumberType::class, $this->numberOptions('Cout etiquettes', 2, '0.01', 'Montant etiquettes', false))
            ->add('plasticFilmCost', NumberType::class, $this->numberOptions('Cout film plastique', 2, '0.01', 'Montant film', false))
            ->add('otherPackagingCost', NumberType::class, $this->numberOptions('Autres couts emballage', 2, '0.01', 'Montant direct', false))
            ->add('manualChargesAdjustment', NumberType::class, $this->numberOptions('Ajustement manuel charges', 2, '0.01', 'Frais non configures', false))
            ->add('notes', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 1500, 'placeholder' => 'Remarques de production, incident, hypothese de calcul...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DailyProductionCost::class,
        ]);
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, int $scale, string $step, string $placeholder, bool $required = true): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'html5' => true,
            'empty_data' => '0',
            'attr' => [
                'min' => 0,
                'step' => $step,
                'inputmode' => 'decimal',
                'placeholder' => $placeholder,
                'data-daily-cost-field' => 'true',
            ],
        ];
    }
}
