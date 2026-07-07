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

final class FishReceptionFreezingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantity', NumberType::class, $this->quantityOptions(
                'Produit fini a congeler (kg)',
                (float) $options['available_quantity'],
                (float) $options['source_quantity'],
                (float) $options['current_frozen_quantity'],
            ))
            ->add('poidsDechetsTraitement', NumberType::class, $this->numberOptions('Dechets traitement (kg)', 3, '0.001', false, false, [
                'data-fish-freezing-waste' => 'true',
                'data-fish-freezing-balance-field' => 'true',
            ]))
            ->add('poidsPertesTraitement', NumberType::class, $this->numberOptions('Pertes traitement (kg)', 3, '0.001', false, false, [
                'data-fish-freezing-loss' => 'true',
                'data-fish-freezing-balance-field' => 'true',
            ]))
            ->add('tunnel', empty($options['factory_unit_choices']) ? TextType::class : ChoiceType::class, $this->factoryUnitOptions('Tunnel', $options['factory_unit_choices'], 'Ex. Tunnel 3', $options['capacity_check_url']))
            ->add('dateEntreeTunnel', DateType::class, $this->dateOptions('Date entree tunnel'))
            ->add('heureEntreeTunnel', TimeType::class, $this->timeOptions('Heure entree tunnel'))
            ->add('temperatureTunnel', NumberType::class, $this->numberOptions('Temperature tunnel', 2, '0.01', false, true))
            ->add('temperatureCoeurProduit', NumberType::class, $this->numberOptions('Temperature a coeur produit', 2, '0.01', false, true));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FishReception::class,
            'available_quantity' => 0.0,
            'source_quantity' => 0.0,
            'current_frozen_quantity' => 0.0,
            'factory_unit_choices' => [],
            'capacity_check_url' => null,
        ]);
        $resolver->setAllowedTypes('available_quantity', ['float', 'int']);
        $resolver->setAllowedTypes('source_quantity', ['float', 'int']);
        $resolver->setAllowedTypes('current_frozen_quantity', ['float', 'int']);
        $resolver->setAllowedTypes('factory_unit_choices', ['array']);
        $resolver->setAllowedTypes('capacity_check_url', ['null', 'string']);
    }

    /** @return array<string, mixed> */
    private function quantityOptions(string $label, float $available, float $source, float $currentFrozen): array
    {
        return [
            'label' => $label,
            'mapped' => false,
            'required' => true,
            'data' => $available > 0 ? round($available, 3) : 0,
            'attr' => [
                'min' => 0,
                'max' => max(0.001, round($available, 3)),
                'step' => '0.001',
                'placeholder' => 'Ex. 338',
                'data-fish-freezing-finished' => 'true',
                'data-fish-freezing-balance-field' => 'true',
                'data-fish-freezing-source' => (string) round(max(0.0, $source), 3),
                'data-fish-freezing-current-frozen' => (string) round(max(0.0, $currentFrozen), 3),
                'data-freezing-capacity-quantity' => 'true',
                'data-stage-quantity-limit' => 'true',
                'data-stage-available' => (string) round(max(0.0, $available), 3),
                'data-stage-requested-label' => 'a congeler',
                'data-stage-available-label' => 'en traitement',
                'data-stage-submit-message' => 'Produit fini a congeler superieur au reste traitement disponible.',
            ],
            'help' => sprintf('Reste traitement non solde : %.3f kg', max(0.0, $available)),
        ];
    }

    /** @return array<string, mixed> */
    private function dateOptions(string $label): array
    {
        return [
            'label' => $label,
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'attr' => ['placeholder' => 'Obligatoire si PF > 0'],
            'help' => 'Obligatoire seulement si le produit fini a congeler est superieur a 0 kg.',
        ];
    }

    /**
     * @param array<string, string> $extraAttr
     *
     * @return array<string, mixed>
     */
    private function numberOptions(string $label, int $scale = 2, string $step = '0.01', bool $required = true, bool $allowNegative = false, array $extraAttr = []): array
    {
        $attr = ['step' => $step, 'placeholder' => $allowNegative ? 'Ex. -40' : 'Ex. 0'] + $extraAttr;
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
    private function timeOptions(string $label): array
    {
        return [
            'label' => $label,
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'attr' => ['placeholder' => 'Obligatoire si PF > 0'],
            'help' => 'Obligatoire seulement si le produit fini a congeler est superieur a 0 kg.',
        ];
    }

    /** @param array<string, string> $choices @return array<string, mixed> */
    private function factoryUnitOptions(string $label, array $choices, string $placeholder, ?string $capacityCheckUrl): array
    {
        $attr = [
            'data-freezing-capacity-tunnel' => 'true',
        ];
        if ($capacityCheckUrl !== null) {
            $attr['data-freezing-capacity-url'] = $capacityCheckUrl;
        }

        if ($choices === []) {
            return [
                'label' => $label,
                'required' => false,
                'attr' => $attr + ['maxlength' => 80, 'placeholder' => $placeholder],
                'help' => 'Obligatoire seulement si le produit fini a congeler est superieur a 0 kg.',
            ];
        }

        return [
            'label' => $label,
            'required' => false,
            'placeholder' => 'Selectionner...',
            'choices' => $choices,
            'attr' => $attr,
            'help' => 'Obligatoire si PF > 0. Seuls les tunnels operationnels, actifs et non satures sont proposes.',
        ];
    }
}
