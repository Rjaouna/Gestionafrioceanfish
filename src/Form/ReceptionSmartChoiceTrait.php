<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

trait ReceptionSmartChoiceTrait
{
    private function addReceptionSmartChoice(
        FormBuilderInterface $builder,
        string $field,
        string $label,
        array $values,
        bool $required = true,
        int $maxlength = 150,
        ?string $current = null,
    ): void {
        $builder
            ->add($field, ChoiceType::class, $this->receptionSmartChoiceOptions($field, $label, $values, $required, $maxlength, $current))
            ->add($this->receptionSmartCustomName($field), TextType::class, $this->receptionSmartCustomOptions($label, $maxlength));
    }

    /**
     * @param array<string, array{label: string, values: array<int, string>, required?: bool, maxlength?: int}> $configs
     */
    private function addReceptionSmartChoiceSubmitListener(FormBuilderInterface $builder, array $configs): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($configs): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $form = $event->getForm();
            foreach ($configs as $field => $config) {
                $customField = $this->receptionSmartCustomName($field);
                $submitted = trim((string) ($data[$field] ?? ''));
                $custom = trim((string) ($data[$customField] ?? ''));

                if ($submitted === $this->receptionSmartOtherValue()) {
                    $submitted = $custom;
                    $data[$field] = $submitted;
                }

                if ($submitted !== '') {
                    $config['values'][] = $submitted;
                }

                $form->add($field, ChoiceType::class, $this->receptionSmartChoiceOptions(
                    $field,
                    $config['label'],
                    $config['values'],
                    $config['required'] ?? true,
                    $config['maxlength'] ?? 150,
                    $submitted,
                ));
            }

            $event->setData($data);
        });
    }

    /** @param array<int, string> $values */
    private function receptionSmartChoiceOptions(
        string $field,
        string $label,
        array $values,
        bool $required,
        int $maxlength,
        ?string $current = null,
    ): array {
        $choiceValues = $this->receptionSmartChoiceValues($values, $current);
        $choices = $choiceValues === [] ? [] : array_combine($choiceValues, $choiceValues);
        $choices['Autre'] = $this->receptionSmartOtherValue();

        return [
            'label' => $label,
            'choices' => $choices,
            'placeholder' => $required ? 'Choisir...' : 'Non renseigne',
            'required' => $required,
            'attr' => [
                'data-reception-smart-select' => 'true',
                'data-reception-smart-custom' => $this->receptionSmartCustomName($field),
                'data-reception-smart-maxlength' => (string) $maxlength,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function receptionSmartCustomOptions(string $label, int $maxlength): array
    {
        return [
            'label' => 'Nouvelle valeur - '.$label,
            'mapped' => false,
            'required' => false,
            'attr' => [
                'maxlength' => $maxlength,
                'placeholder' => 'Saisir puis enregistrer',
                'data-reception-smart-custom-input' => 'true',
            ],
        ];
    }

    /** @param array<int, string> $values @return list<string> */
    private function receptionSmartChoiceValues(array $values, ?string $current = null): array
    {
        if ($current !== null && trim($current) !== '') {
            $values[] = trim($current);
        }

        $normalized = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '' || $value === $this->receptionSmartOtherValue()) {
                continue;
            }

            $key = mb_strtolower($value);
            $normalized[$key] ??= $value;
        }

        natcasesort($normalized);

        return array_values($normalized);
    }

    private function receptionSmartCustomName(string $field): string
    {
        return $field.'Custom';
    }

    private function receptionSmartOtherValue(): string
    {
        return '__other__';
    }
}
