<?php

namespace App\Form;

use App\Entity\FactoryUnit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FactoryUnitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => ['maxlength' => 120, 'placeholder' => 'Ex. Tunnel 1, Chambre negative 2'],
            ])
            ->add('code', TextType::class, [
                'label' => 'Reference',
                'required' => false,
                'help' => 'Laissez vide pour generer automatiquement.',
                'attr' => ['maxlength' => 60, 'placeholder' => 'Auto'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => array_flip(FactoryUnit::TYPE_LABELS),
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_flip(FactoryUnit::STATUS_LABELS),
            ])
            ->add('capacityKg', NumberType::class, $this->numberOptions('Capacite kg', '0.001'))
            ->add('capacityPallets', IntegerType::class, $this->integerOptions('Capacite palettes'))
            ->add('capacityBoxes', IntegerType::class, $this->integerOptions('Capacite caisses'))
            ->add('lengthMeters', NumberType::class, $this->numberOptions('Longueur (m)', '0.01'))
            ->add('widthMeters', NumberType::class, $this->numberOptions('Largeur (m)', '0.01'))
            ->add('heightMeters', NumberType::class, $this->numberOptions('Hauteur (m)', '0.01'))
            ->add('floorLevel', TextType::class, [
                'label' => 'Etage / niveau',
                'required' => false,
                'attr' => ['maxlength' => 80, 'placeholder' => 'Ex. RDC, Etage 1'],
            ])
            ->add('locationLabel', TextType::class, [
                'label' => 'Emplacement',
                'required' => false,
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. Zone froid cote reception'],
            ])
            ->add('targetTemperature', NumberType::class, $this->temperatureOptions('Temperature cible'))
            ->add('minTemperature', NumberType::class, $this->temperatureOptions('Temperature min'))
            ->add('maxTemperature', NumberType::class, $this->temperatureOptions('Temperature max'))
            ->add('sortOrder', IntegerType::class, $this->integerOptions('Ordre'))
            ->add('isActive', CheckboxType::class, [
                'label' => 'Disponible dans les selections',
                'required' => false,
            ])
            ->add('isSaturated', CheckboxType::class, [
                'label' => 'Sature',
                'required' => false,
                'help' => 'Activez si la piece ne doit plus recevoir de stock.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 1200],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FactoryUnit::class,
        ]);
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, string $step): array
    {
        return [
            'label' => $label,
            'required' => false,
            'empty_data' => '0',
            'html5' => true,
            'attr' => ['min' => 0, 'step' => $step, 'inputmode' => 'decimal'],
        ];
    }

    /** @return array<string, mixed> */
    private function integerOptions(string $label): array
    {
        return [
            'label' => $label,
            'required' => false,
            'empty_data' => '0',
            'attr' => ['min' => 0, 'step' => 1],
        ];
    }

    /** @return array<string, mixed> */
    private function temperatureOptions(string $label): array
    {
        return [
            'label' => $label,
            'required' => false,
            'html5' => true,
            'attr' => ['step' => '0.01', 'inputmode' => 'decimal', 'placeholder' => 'Ex. -18'],
        ];
    }
}
