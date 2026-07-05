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
            ->add('quantity', NumberType::class, $this->quantityOptions('Quantite a entrer en stock (kg)', (float) $options['available_quantity']))
            ->add('chambreFroide', empty($options['factory_unit_choices']) ? TextType::class : ChoiceType::class, $this->factoryUnitOptions('Chambre froide / zone de stockage', $options['factory_unit_choices'], 'Ex. Chambre negative 1'))
            ->add('temperatureChambre', NumberType::class, $this->numberOptions('Temperature chambre', 2, '0.01', false, true))
            ->add('temperatureStockage', NumberType::class, $this->numberOptions('Temperature produit stocke', 2, '0.01', false, true))
            ->add('dateEntreeStockage', DateType::class, $this->dateOptions('Date entree stockage', false))
            ->add('heureEntreeStockage', TimeType::class, $this->timeOptions('Heure entree stockage'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FishReception::class,
            'available_quantity' => 0.0,
            'factory_unit_choices' => [],
        ]);
        $resolver->setAllowedTypes('available_quantity', ['float', 'int']);
        $resolver->setAllowedTypes('factory_unit_choices', ['array']);
    }

    /** @return array<string, mixed> */
    private function quantityOptions(string $label, float $available): array
    {
        return [
            'label' => $label,
            'mapped' => false,
            'required' => true,
            'data' => $available > 0 ? round($available, 3) : null,
            'attr' => ['min' => 0.001, 'max' => max(0.001, round($available, 3)), 'step' => '0.001'],
            'help' => sprintf('Disponible apres congelation : %.3f kg', max(0.0, $available)),
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
    private function factoryUnitOptions(string $label, array $choices, string $placeholder): array
    {
        if ($choices === []) {
            return [
                'label' => $label,
                'required' => true,
                'attr' => ['maxlength' => 120, 'placeholder' => $placeholder],
            ];
        }

        return [
            'label' => $label,
            'required' => true,
            'placeholder' => 'Selectionner...',
            'choices' => $choices,
            'help' => 'Liste issue de Composition usine. Les pieces arretees, inactives ou saturees ne sont pas proposees.',
        ];
    }
}
