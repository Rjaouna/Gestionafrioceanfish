<?php

namespace App\Form;

use App\Entity\FishReceptionStorageMovement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FishReceptionInitialStorageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantityKg', NumberType::class, $this->quantityOptions((float) $options['available_quantity']))
            ->add('location', empty($options['factory_unit_choices']) ? TextType::class : ChoiceType::class, $this->factoryUnitOptions($options['factory_unit_choices'], (string) $options['capacity_check_url']))
            ->add('movementDate', DateType::class, $this->dateOptions('Date entrée stockage initial'))
            ->add('movementTime', TimeType::class, $this->timeOptions('Heure entrée stockage initial'))
            ->add('temperatureChamber', NumberType::class, $this->numberOptions('Température chambre', true))
            ->add('temperatureProduct', NumberType::class, $this->numberOptions('Température produit', true))
            ->add('note', TextareaType::class, [
                'label' => 'Observation',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 1000, 'placeholder' => 'Ex. mise en attente avant traitement, qualité OK, palette séparée...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FishReceptionStorageMovement::class,
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
            'label' => 'Quantité à stocker depuis la réception (kg)',
            'required' => true,
            'data' => $available > 0 ? round($available, 3) : null,
            'attr' => [
                'min' => 0.001,
                'max' => max(0.001, round($available, 3)),
                'step' => '0.001',
                'placeholder' => 'Ex. 1500',
                'data-factory-capacity-quantity' => 'true',
                'data-stage-quantity-limit' => 'true',
                'data-stage-available' => (string) round(max(0.0, $available), 3),
                'data-stage-requested-label' => 'a stocker',
                'data-stage-available-label' => 'non encore stockes',
                'data-stage-submit-message' => 'Quantite a stocker superieure au reste reception.',
            ],
            'help' => sprintf('Reste à stocker depuis la réception : %.3f kg', max(0.0, $available)),
        ];
    }

    /** @param array<string, string> $choices @return array<string, mixed> */
    private function factoryUnitOptions(array $choices, ?string $capacityCheckUrl): array
    {
        $attr = ['data-factory-capacity-location' => 'true'];
        if ($capacityCheckUrl !== null) {
            $attr['data-factory-capacity-url'] = $capacityCheckUrl;
        }

        if ($choices === []) {
            return [
                'label' => 'Chambre / zone de stockage initial',
                'required' => true,
                'attr' => $attr + ['maxlength' => 120, 'placeholder' => 'Ex. Chambre positive 1'],
            ];
        }

        return [
            'label' => 'Chambre / zone de stockage initial',
            'required' => true,
            'placeholder' => 'Selectionner...',
            'choices' => $choices,
            'attr' => $attr,
            'help' => 'Liste issue de Composition usine. Les tunnels ne sont pas proposés pour le stockage initial.',
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
            'attr' => ['placeholder' => 'Date de mise en chambre'],
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
            'attr' => ['placeholder' => 'Ex. 09:30'],
        ];
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, bool $allowNegative = false): array
    {
        $attr = ['step' => '0.01', 'placeholder' => $allowNegative ? 'Ex. -2' : 'Ex. 0'];
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
