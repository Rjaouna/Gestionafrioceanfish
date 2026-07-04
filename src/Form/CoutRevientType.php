<?php

namespace App\Form;

use App\Entity\CoutRevient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CoutRevientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateProduction', DateType::class, [
                'label' => 'Date production',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('numeroLot', TextType::class, [
                'label' => 'Numero lot',
                'required' => false,
                'attr' => ['maxlength' => 100, 'placeholder' => 'Auto si vide : CR-2026-0001'],
                'help' => 'Laissez vide pour generer un numero automatiquement.',
            ])
            ->add('produit', TextType::class, [
                'label' => 'Produit',
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. Filet de sardine'],
            ])
            ->add('especePoisson', TextType::class, [
                'label' => 'Espece poisson',
                'required' => false,
                'attr' => ['maxlength' => 150, 'placeholder' => 'Ex. Sardine, anchois, maquereau'],
            ])
            ->add('client', TextType::class, [
                'label' => 'Client',
                'required' => false,
                'attr' => ['maxlength' => 150],
            ])
            ->add('responsableProduction', TextType::class, [
                'label' => 'Responsable production',
                'required' => false,
                'attr' => ['maxlength' => 150],
            ])
            ->add('poidsBrutRecu', NumberType::class, $this->numberOptions('Poids brut recu (kg)', 3, '0.001'))
            ->add('poidsMisEnProduction', NumberType::class, $this->numberOptions('Poids mis en production (kg)', 3, '0.001'))
            ->add('prixAchatKg', NumberType::class, $this->numberOptions('Prix achat / kg', 2, '0.01'))
            ->add('fraisTransportAchat', NumberType::class, $this->numberOptions('Frais transport achat', 2, '0.01', false))
            ->add('autresFraisAchat', NumberType::class, $this->numberOptions('Autres frais achat', 2, '0.01', false))
            ->add('poidsProduitFini', NumberType::class, $this->numberOptions('Poids produit fini (kg)', 3, '0.001'))
            ->add('poidsDechets', NumberType::class, $this->numberOptions('Poids dechets (kg)', 3, '0.001', false))
            ->add('poidsPerte', NumberType::class, $this->numberOptions('Poids perte (kg)', 3, '0.001', false))
            ->add('modeCalculMainOeuvre', ChoiceType::class, [
                'label' => 'Mode calcul main d oeuvre',
                'choices' => array_flip(CoutRevient::MODE_LABELS),
                'attr' => ['data-cout-mode' => 'true'],
            ])
            ->add('nombreOperatrices', IntegerType::class, [
                'label' => 'Nombre operatrices',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1, 'data-cout-field' => 'nombreOperatrices'],
            ])
            ->add('nombreHeures', NumberType::class, $this->numberOptions('Nombre heures', 2, '0.01', false))
            ->add('coutHoraireMoyen', NumberType::class, $this->numberOptions('Cout horaire moyen', 2, '0.01', false))
            ->add('prixTacheKg', NumberType::class, $this->numberOptions('Prix tache / kg', 2, '0.01', false))
            ->add('kgTraitesMainOeuvre', NumberType::class, $this->numberOptions('Kg traites main oeuvre', 3, '0.001', false))
            ->add('coutMainOeuvreDirect', NumberType::class, $this->numberOptions('Montant direct main oeuvre', 2, '0.01', false))
            ->add('nombreCartons', IntegerType::class, [
                'label' => 'Nombre cartons',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1, 'data-cout-field' => 'nombreCartons'],
            ])
            ->add('prixCarton', NumberType::class, $this->numberOptions('Prix carton', 2, '0.01', false))
            ->add('nombreSachets', IntegerType::class, [
                'label' => 'Nombre sachets',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'step' => 1, 'data-cout-field' => 'nombreSachets'],
            ])
            ->add('prixSachet', NumberType::class, $this->numberOptions('Prix sachet', 2, '0.01', false))
            ->add('coutEtiquettes', NumberType::class, $this->numberOptions('Cout etiquettes', 2, '0.01', false))
            ->add('coutFilmPlastique', NumberType::class, $this->numberOptions('Cout film plastique', 2, '0.01', false))
            ->add('autresCoutEmballage', NumberType::class, $this->numberOptions('Autres couts emballage', 2, '0.01', false))
            ->add('coutElectricite', NumberType::class, $this->numberOptions('Electricite', 2, '0.01', false))
            ->add('coutEau', NumberType::class, $this->numberOptions('Eau', 2, '0.01', false))
            ->add('coutGlace', NumberType::class, $this->numberOptions('Glace', 2, '0.01', false))
            ->add('coutNettoyage', NumberType::class, $this->numberOptions('Nettoyage', 2, '0.01', false))
            ->add('coutMaintenance', NumberType::class, $this->numberOptions('Maintenance', 2, '0.01', false))
            ->add('coutTransportLivraison', NumberType::class, $this->numberOptions('Transport livraison', 2, '0.01', false))
            ->add('autresCharges', NumberType::class, $this->numberOptions('Autres charges', 2, '0.01', false))
            ->add('prixVenteKg', NumberType::class, $this->numberOptions('Prix vente / kg', 2, '0.01', false, false))
            ->add('observation', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 1500],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoutRevient::class,
        ]);
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, int $scale, string $step, bool $required = true, bool $defaultZero = true): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'html5' => true,
            'empty_data' => $defaultZero ? '0' : null,
            'attr' => [
                'min' => 0,
                'step' => $step,
                'inputmode' => 'decimal',
                'data-cout-field' => 'true',
            ],
        ];
    }
}
