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
            ->add('quantity', NumberType::class, $this->quantityOptions('Quantité à congeler (kg)', (float) $options['available_quantity']))
            ->add('tunnel', empty($options['factory_unit_choices']) ? TextType::class : ChoiceType::class, $this->factoryUnitOptions('Tunnel', $options['factory_unit_choices'], 'Ex. Tunnel 3', $options['capacity_check_url']))
            ->add('heureEntreeTunnel', TimeType::class, $this->timeOptions('Heure entrée tunnel'))
            ->add('temperatureTunnel', NumberType::class, $this->numberOptions('Température tunnel', 2, '0.01', false, true))
            ->add('dateSortieTunnel', DateType::class, $this->dateOptions('Date sortie tunnel', false))
            ->add('temperatureCoeurProduit', NumberType::class, $this->numberOptions('Température à coeur produit', 2, '0.01', false, true));
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
                'data-freezing-capacity-quantity' => 'true',
            ],
            'help' => sprintf('Disponible apres traitement : %.3f kg', max(0.0, $available)),
        ];
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, int $scale = 2, string $step = '0.01', bool $required = true, bool $allowNegative = false): array
    {
        $attr = ['step' => $step];
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
                'required' => true,
                'attr' => $attr + ['maxlength' => 80, 'placeholder' => $placeholder],
            ];
        }

        return [
            'label' => $label,
            'required' => true,
            'placeholder' => 'Selectionner...',
            'choices' => $choices,
            'attr' => $attr,
            'help' => 'Seuls les tunnels operationnels, actifs et non satures sont proposes.',
        ];
    }
}
