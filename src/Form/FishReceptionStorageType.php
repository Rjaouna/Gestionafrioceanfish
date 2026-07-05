<?php

namespace App\Form;

use App\Entity\FishReception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FishReceptionStorageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('heureSortieTunnel', TimeType::class, $this->timeOptions('Heure sortie tunnel', true))
            ->add('quantity', NumberType::class, $this->quantityOptions('Quantité à entrer en stock (kg)', (float) $options['available_quantity']))
            ->add('chambreFroide', empty($options['factory_unit_choices']) ? TextType::class : ChoiceType::class, $this->factoryUnitOptions('Chambre froide / zone de stockage', $options['factory_unit_choices'], 'Ex. Chambre negative 1', $options['capacity_check_url']))
            ->add('temperatureChambre', NumberType::class, $this->numberOptions('Température chambre', 2, '0.01', false, true))
            ->add('temperatureStockage', NumberType::class, $this->numberOptions('Température produit stocké', 2, '0.01', false, true))
            ->add('dateEntreeStockage', DateType::class, $this->dateOptions('Date entrée stockage', false))
            ->add('heureEntreeStockage', TimeType::class, $this->timeOptions('Heure entrée stockage'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FishReception::class,
            'available_quantity' => 0.0,
            'factory_unit_choices' => [],
            'capacity_check_url' => null,
        ]);
        $resolver->setAllowedTypes('available_quantity', ['float', 'int']);
        $resolver->setAllowedTypes('factory_unit_choices', ['array']);
        $resolver->setAllowedTypes('capacity_check_url', ['null', 'string']);
    }

    /** @return array<string, mixed> */
    private function quantityOptions(string $label, float $available): array
    {
        return [
            'label' => $label,
            'mapped' => false,
            'required' => true,
            'data' => $available > 0 ? round($available, 3) : null,
            'attr' => [
                'min' => 0.001,
                'max' => max(0.001, round($available, 3)),
                'step' => '0.001',
                'placeholder' => 'Ex. 500',
                'data-factory-capacity-quantity' => 'true',
                'data-stage-quantity-limit' => 'true',
                'data-stage-available' => (string) round(max(0.0, $available), 3),
                'data-stage-requested-label' => 'a stocker',
                'data-stage-available-label' => 'apres congelation',
                'data-stage-submit-message' => 'Quantite a stocker superieure au reste congele.',
            ],
            'help' => sprintf('Disponible après congélation : %.3f kg', max(0.0, $available)),
        ];
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, int $scale = 2, string $step = '0.01', bool $required = true, bool $allowNegative = false): array
    {
        $attr = ['step' => $step, 'placeholder' => $allowNegative ? 'Ex. -20' : 'Ex. 0'];
        if (!$allowNegative) {
            $attr['min'] = 0;
        }

        return [
            'label' => $label,
            'required' => $required,
            'empty_data' => $required || !$allowNegative ? '0' : null,
            'attr' => $attr,
        ];
    }

    /** @return array<string, mixed> */
    private function dateOptions(string $label, bool $required = true): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'attr' => ['placeholder' => 'Date d entree en stock'],
        ];
    }

    /** @return array<string, mixed> */
    private function timeOptions(string $label, bool $required = false): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'attr' => ['placeholder' => $required ? 'Ex. 12:30' : 'Ex. 13:00'],
        ];
    }

    /** @param array<string, string> $choices @return array<string, mixed> */
    private function factoryUnitOptions(string $label, array $choices, string $placeholder, ?string $capacityCheckUrl): array
    {
        $attr = [
            'data-factory-capacity-location' => 'true',
        ];
        if ($capacityCheckUrl !== null) {
            $attr['data-factory-capacity-url'] = $capacityCheckUrl;
        }

        if ($choices === []) {
            return [
                'label' => $label,
                'required' => true,
                'attr' => $attr + ['maxlength' => 120, 'placeholder' => $placeholder],
            ];
        }

        return [
            'label' => $label,
            'required' => true,
            'placeholder' => 'Selectionner...',
            'choices' => $choices,
            'attr' => $attr,
            'help' => 'Liste issue de Composition usine. Les pieces arretees, inactives ou saturees ne sont pas proposees.',
        ];
    }
}
