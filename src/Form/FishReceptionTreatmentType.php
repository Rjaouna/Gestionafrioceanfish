<?php

namespace App\Form;

use App\Entity\FishReception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FishReceptionTreatmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantity', NumberType::class, $this->quantityOptions('Quantite a envoyer au traitement (kg)', (float) $options['available_quantity']))
            ->add('dateDebutTraitement', DateType::class, $this->dateOptions('Date debut traitement'))
            ->add('heureDebutTraitement', TimeType::class, $this->timeOptions('Heure debut traitement'))
            ->add('temperatureEauGlacee', NumberType::class, $this->numberOptions('Temperature eau glacee', 2, '0.01', false, true))
            ->add('poidsMoyenParCaisse', NumberType::class, $this->numberOptions('Poids moyen par caisse (kg)', 3, '0.001', false))
            ->add('nombreCaissesApresTraitement', IntegerType::class, $this->integerOptions('Nombre de caisses apres traitement', false, [
                'readonly' => 'readonly',
                'data-treatment-box-count' => 'true',
            ], 'Calcule automatiquement : quantite / poids moyen par caisse.'))
            ->add('nombreMoules', IntegerType::class, $this->integerOptions('Nombre de moules', false))
            ->add('nombreCaissesParEtage', IntegerType::class, $this->integerOptions('Nombre de caisses par etage', false, [
                'data-treatment-boxes-per-layer' => 'true',
            ], null, false, 5))
            ->add('nombreNiveauxPalette', IntegerType::class, $this->integerOptions('Nombre de niveaux', false, [
                'data-treatment-pallet-levels' => 'true',
            ], null, false, 16))
            ->add('nombreCaissesParPalette', IntegerType::class, $this->integerOptions('Nombre de caisses par palette', false, [
                'readonly' => 'readonly',
                'data-treatment-boxes-per-pallet' => 'true',
            ], 'Calcule automatiquement : caisses par etage x nombre de niveaux.'))
            ->add('nombreTotalPalettes', IntegerType::class, $this->integerOptions('Nombre total de palettes', false, [
                'readonly' => 'readonly',
                'data-treatment-pallet-count' => 'true',
            ], 'Calcule automatiquement : caisses / caisses par palette.'));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $reception = $event->getData();
            if ($reception instanceof FishReception && $reception->getDateDebutTraitement() === null) {
                $reception->setDateDebutTraitement(new \DateTimeImmutable('today'));
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $reception = $event->getData();
            if (!$reception instanceof FishReception) {
                return;
            }

            $form = $event->getForm();
            $boxesPerLayer = max(0, (int) $form->get('nombreCaissesParEtage')->getData());
            $palletLevels = max(0, (int) $form->get('nombreNiveauxPalette')->getData());

            $reception->setNombreCaissesParPalette(
                $boxesPerLayer > 0 && $palletLevels > 0 ? $boxesPerLayer * $palletLevels : 0
            );
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FishReception::class,
            'available_quantity' => 0.0,
        ]);
        $resolver->setAllowedTypes('available_quantity', ['float', 'int']);
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
                'data-treatment-total-weight' => 'true',
            ],
            'help' => sprintf('Disponible : %.3f kg', max(0.0, $available)),
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
        ];
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, int $scale = 2, string $step = '0.01', bool $required = true, bool $allowNegative = false): array
    {
        $attr = ['step' => $step];
        if (!$allowNegative) {
            $attr['min'] = 0;
        }
        if ($label === 'Poids moyen par caisse (kg)') {
            $attr['data-treatment-box-weight'] = 'true';
        }

        return [
            'label' => $label,
            'required' => $required,
            'empty_data' => $required || !$allowNegative ? '0' : null,
            'attr' => $attr,
        ];
    }

    /**
     * @param array<string, string> $attr
     *
     * @return array<string, mixed>
     */
    private function integerOptions(string $label, bool $required = true, array $attr = [], ?string $help = null, bool $mapped = true, ?int $data = null): array
    {
        $options = [
            'label' => $label,
            'mapped' => $mapped,
            'required' => $required,
            'empty_data' => '0',
            'attr' => ['min' => 0, 'step' => 1] + $attr,
            'help' => $help,
        ];

        if ($data !== null) {
            $options['data'] = $data;
        }

        return $options;
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
}
