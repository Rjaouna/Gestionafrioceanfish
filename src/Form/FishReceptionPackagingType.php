<?php

namespace App\Form;

use App\Entity\FishReception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FishReceptionPackagingType extends AbstractType
{
    use ReceptionSmartChoiceTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $reception = $builder->getData();
        $smartFields = [
            'produitConditionne' => [
                'label' => 'Produit conditionné',
                'values' => $options['choice_lists']['produitConditionne'] ?? [],
                'required' => true,
                'maxlength' => 150,
            ],
        ];

        $builder
            ->add('quantity', NumberType::class, $this->withAttr($this->quantityOptions('Quantité à conditionner / emballer (kg)', (float) $options['available_quantity']), ['data-fish-packaging-quantity' => 'true']))
            ->add('dateConditionnement', DateType::class, $this->dateOptions('Date conditionnement', false))
            ->add('heureDebutConditionnement', TimeType::class, $this->withAttr($this->timeOptions('Heure début conditionnement'), ['data-fish-packaging-start' => 'true']))
            ->add('heureFinConditionnement', TimeType::class, $this->withAttr($this->timeOptions('Heure fin conditionnement'), ['data-fish-packaging-end' => 'true']))
            ->add('poidsNet', NumberType::class, $this->withAttr($this->numberOptions('Poids net (kg)', 3, '0.001', false), ['data-fish-packaging-net' => 'true']))
            ->add('poidsDechetsEmballage', NumberType::class, $this->withAttr($this->numberOptions('Déchets emballage (kg)', 3, '0.001', false), ['data-fish-packaging-waste' => 'true']))
            ->add('poidsPertesEmballage', NumberType::class, $this->withAttr($this->numberOptions('Pertes emballage (kg)', 3, '0.001', false), ['data-fish-packaging-loss' => 'true']))
            ->add('coutHoraireEmballage', NumberType::class, $this->withAttr($this->numberOptions('Coût horaire emballage (MAD / heure)', 2, '0.01', false), ['data-fish-packaging-hourly-cost' => 'true']));

        $this->addReceptionSmartChoice($builder, 'produitConditionne', 'Produit conditionné', $smartFields['produitConditionne']['values'], true, 150, $reception instanceof FishReception ? $reception->getProduitConditionne() : null);
        $this->addReceptionSmartChoiceSubmitListener($builder, $smartFields);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FishReception::class,
            'available_quantity' => 0.0,
            'choice_lists' => [],
        ]);
        $resolver->setAllowedTypes('available_quantity', ['float', 'int']);
        $resolver->setAllowedTypes('choice_lists', 'array');
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

    /** @param array<string, mixed> $options */
    private function withAttr(array $options, array $attr): array
    {
        $options['attr'] = array_merge($options['attr'] ?? [], $attr);

        return $options;
    }

}
