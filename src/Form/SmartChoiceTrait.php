<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

trait SmartChoiceTrait
{
    private function addSmartChoice(
        FormBuilderInterface $builder,
        string $field,
        string $label,
        array $values,
        bool $required = true,
        int $maxlength = 150,
        ?string $current = null,
        array $choiceOptions = [],
    ): void {
        $builder
            ->add($field, ChoiceType::class, $this->smartChoiceOptions($field, $label, $values, $required, $maxlength, $current, $choiceOptions))
            ->add($this->smartChoiceCustomName($field), TextType::class, $this->smartChoiceCustomOptions($label, $maxlength));
    }

    /**
     * @param array<string, array{label: string, values: array<int, string>, required?: bool, maxlength?: int, choice_options?: array<string, mixed>}> $configs
     */
    private function addSmartChoiceSubmitListener(FormBuilderInterface $builder, array $configs): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($configs): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $form = $event->getForm();
            foreach ($configs as $field => $config) {
                $customField = $this->smartChoiceCustomName($field);
                $submitted = trim((string) ($data[$field] ?? ''));
                $custom = trim((string) ($data[$customField] ?? ''));

                if ($submitted === $this->smartChoiceOtherValue()) {
                    $submitted = $custom;
                    $data[$field] = $submitted;
                }

                if ($submitted !== '') {
                    $config['values'][] = $submitted;
                }

                $choiceOptions = $config['choice_options'] ?? [];
                unset($choiceOptions['data']);

                $form->add($field, ChoiceType::class, $this->smartChoiceOptions(
                    $field,
                    $config['label'],
                    $config['values'],
                    $config['required'] ?? true,
                    $config['maxlength'] ?? 150,
                    $submitted,
                    $choiceOptions,
                ));
            }

            $event->setData($data);
        });
    }

    /** @param array<int, string> $values */
    private function smartChoiceOptions(
        string $field,
        string $label,
        array $values,
        bool $required,
        int $maxlength,
        ?string $current = null,
        array $choiceOptions = [],
    ): array {
        $choiceValues = $this->smartChoiceValues($values, $current);
        $choices = $choiceValues === [] ? [] : array_combine($choiceValues, $choiceValues);
        $choices['Autre'] = $this->smartChoiceOtherValue();

        $options = [
            'label' => $label,
            'choices' => $choices,
            'placeholder' => $required ? 'Choisir...' : 'Non renseigne',
            'required' => $required,
            'attr' => [
                'data-smart-choice-select' => 'true',
                'data-smart-choice-custom' => $this->smartChoiceCustomName($field),
                'data-smart-choice-maxlength' => (string) $maxlength,
            ],
        ];

        $choiceOptions['attr'] = array_merge($options['attr'], $choiceOptions['attr'] ?? []);

        return array_replace_recursive($options, $choiceOptions);
    }

    /** @return array<string, mixed> */
    private function smartChoiceCustomOptions(string $label, int $maxlength): array
    {
        return [
            'label' => 'Nouvelle valeur - '.$label,
            'mapped' => false,
            'required' => false,
            'attr' => [
                'maxlength' => $maxlength,
                'placeholder' => 'Saisir puis enregistrer',
                'data-smart-choice-custom-input' => 'true',
            ],
        ];
    }

    /** @param array<int, string> $values @return list<string> */
    private function smartChoiceValues(array $values, ?string $current = null): array
    {
        if ($current !== null && trim($current) !== '') {
            $values[] = trim($current);
        }

        $normalized = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '' || $value === $this->smartChoiceOtherValue()) {
                continue;
            }

            $key = mb_strtolower($value);
            $normalized[$key] ??= $value;
        }

        natcasesort($normalized);

        return array_values($normalized);
    }

    private function smartChoiceCustomName(string $field): string
    {
        return $field.'Custom';
    }

    private function smartChoiceOtherValue(): string
    {
        return '__other__';
    }
}
