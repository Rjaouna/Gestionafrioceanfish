<?php

namespace App\Form;

use App\Entity\FishReception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FishReceptionReturnStorageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantity', NumberType::class, $this->quantityOptions((float) $options['available_quantity']))
            ->add('chambreRemiseEnChambre', ChoiceType::class, $this->factoryUnitOptions($options['factory_unit_choices'], (string) $options['capacity_check_url']))
            ->add('dateRemiseEnChambre', DateType::class, $this->dateOptions('Date remise en chambre'))
            ->add('heureRemiseEnChambre', TimeType::class, $this->timeOptions('Heure remise en chambre'))
            ->add('temperatureChambreRemise', NumberType::class, $this->numberOptions('Temperature chambre positive', true))
            ->add('temperatureProduitRemise', NumberType::class, $this->numberOptions('Temperature produit remis en chambre', true));
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
    private function quantityOptions(float $available): array
    {
        return [
            'label' => 'Quantite a remettre en chambre apres emballage (kg)',
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
                'data-stage-requested-label' => 'a remettre en chambre',
                'data-stage-available-label' => 'apres emballage',
                'data-stage-submit-message' => 'Quantite a remettre en chambre superieure au reste emballe.',
            ],
            'help' => sprintf('Disponible apres emballage : %.3f kg', max(0.0, $available)),
        ];
    }

    /** @param array<string, string> $choices @return array<string, mixed> */
    private function factoryUnitOptions(array $choices, ?string $capacityCheckUrl): array
    {
        $attr = ['data-factory-capacity-location' => 'true'];
        if ($capacityCheckUrl !== null) {
            $attr['data-factory-capacity-url'] = $capacityCheckUrl;
        }

        return [
            'label' => 'Chambre de retour',
            'required' => true,
            'placeholder' => 'Selectionner...',
            'choices' => $choices,
            'attr' => $attr,
            'help' => $choices === []
                ? 'Aucune chambre active disponible. Ajoutez ou activez une chambre dans Composition usine.'
                : 'Liste issue de Composition usine. Selectionnez la chambre ou le produit revient apres emballage.',
        ];
    }

    /** @return array<string, mixed> */
    private function dateOptions(string $label): array
    {
        return [
            'label' => $label,
            'required' => true,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'attr' => ['placeholder' => 'Date du mouvement'],
        ];
    }

    /** @return array<string, mixed> */
    private function timeOptions(string $label): array
    {
        return [
            'label' => $label,
            'required' => true,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'attr' => ['placeholder' => 'Ex. 14:30'],
        ];
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, bool $allowNegative = false): array
    {
        $attr = ['step' => '0.01', 'placeholder' => $allowNegative ? 'Ex. 2' : 'Ex. 0'];
        if (!$allowNegative) {
            $attr['min'] = 0;
        }

        return [
            'label' => $label,
            'required' => false,
            'empty_data' => null,
            'attr' => $attr,
        ];
    }
}
