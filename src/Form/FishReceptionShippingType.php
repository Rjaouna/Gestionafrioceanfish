<?php

namespace App\Form;

use App\Entity\FishReception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FishReceptionShippingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $reception = $builder->getData();
        $now = new \DateTimeImmutable();
        $dateDepart = $reception instanceof FishReception && $reception->getExpeditionDateDepart() instanceof \DateTimeImmutable
            ? $reception->getExpeditionDateDepart()
            : $now;
        $heureDepart = $reception instanceof FishReception && $reception->getExpeditionHeureDepart() instanceof \DateTimeImmutable
            ? $reception->getExpeditionHeureDepart()
            : $now;

        $builder
            ->add('quantity', NumberType::class, $this->quantityOptions('Quantite a expedier (kg)', (float) $options['available_quantity']))
            ->add('destinationFinaleClient', TextType::class, [
                'label' => 'Destination finale / Client',
                'required' => true,
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. Client, export, depot...'],
            ])
            ->add('expeditionDateDepart', DateType::class, [
                'label' => 'Date expedition',
                'required' => true,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => $dateDepart,
            ])
            ->add('expeditionHeureDepart', TimeType::class, [
                'label' => 'Heure depart camion',
                'required' => true,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => $heureDepart,
            ])
            ->add('expeditionMatriculeVehicule', TextType::class, $this->textOptions('Matricule camion', true, 80, 'Ex. 12345-A-6'))
            ->add('expeditionChauffeur', TextType::class, $this->textOptions('Nom chauffeur', true, 150, 'Nom et prenom du chauffeur'))
            ->add('expeditionResponsableChargement', TextType::class, $this->textOptions('Responsable chargement', true, 150, 'Personne qui a charge le camion'))
            ->add('expeditionTemperatureProduit', NumberType::class, $this->numberOptions('Temperature produit au chargement', false, true))
            ->add('expeditionNumeroPlomb', TextType::class, $this->textOptions('Numero plomb / scelle', false, 80, 'Scelle camion ou plomb'))
            ->add('expeditionObservations', TextareaType::class, [
                'label' => 'Observations expedition',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 2000, 'placeholder' => 'Etat camion, remarques chargement, documents remis...'],
            ]);
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
            'attr' => ['min' => 0.001, 'max' => max(0.001, round($available, 3)), 'step' => '0.001'],
            'help' => sprintf('Disponible en stock : %.3f kg', max(0.0, $available)),
        ];
    }

    /** @return array<string, mixed> */
    private function textOptions(string $label, bool $required, int $maxlength, string $placeholder): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'attr' => ['maxlength' => $maxlength, 'placeholder' => $placeholder],
        ];
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, bool $required = true, bool $allowNegative = false): array
    {
        $attr = ['step' => '0.01'];
        if (!$allowNegative) {
            $attr['min'] = 0;
        }

        return [
            'label' => $label,
            'required' => $required,
            'empty_data' => null,
            'attr' => $attr,
        ];
    }
}
