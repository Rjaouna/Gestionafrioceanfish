<?php

namespace App\Form;

use App\Entity\FishReception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FishReceptionStageCorrectionType extends AbstractType
{
    public const STAGE_TREATMENT = 'traitement';
    public const STAGE_FREEZING = 'congelation';
    public const STAGE_STORAGE = 'stockage';
    public const STAGE_PACKAGING = 'emballage';
    public const STAGE_SHIPPING = 'expedition';

    public const STAGE_LABELS = [
        self::STAGE_TREATMENT => 'Traitement / Production',
        self::STAGE_FREEZING => 'Congelation',
        self::STAGE_STORAGE => 'Cristallisation',
        self::STAGE_PACKAGING => 'Emballage + retour chambre',
        self::STAGE_SHIPPING => 'Expedition',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        switch ($options['stage']) {
            case self::STAGE_TREATMENT:
                $this->buildTreatment($builder);
                break;
            case self::STAGE_FREEZING:
                $this->buildFreezing($builder, $options);
                break;
            case self::STAGE_STORAGE:
                $this->buildStorage($builder, $options);
                break;
            case self::STAGE_PACKAGING:
                $this->buildPackaging($builder, $options);
                break;
            case self::STAGE_SHIPPING:
                $this->buildShipping($builder);
                break;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FishReception::class,
            'stage' => self::STAGE_TREATMENT,
            'tunnel_choices' => [],
            'positive_storage_choices' => [],
            'validation_groups' => false,
        ]);
        $resolver->setAllowedValues('stage', array_keys(self::STAGE_LABELS));
        $resolver->setAllowedTypes('tunnel_choices', 'array');
        $resolver->setAllowedTypes('positive_storage_choices', 'array');
    }

    private function buildTreatment(FormBuilderInterface $builder): void
    {
        $builder
            ->add('quantiteTotalePreparee', NumberType::class, $this->numberOptions('Quantite en traitement (kg)', 3, '0.001'))
            ->add('dateDebutTraitement', DateType::class, $this->dateOptions('Date traitement', false))
            ->add('heureDebutTraitement', TimeType::class, $this->timeOptions('Heure debut traitement', false))
            ->add('temperatureEauGlacee', NumberType::class, $this->numberOptions('Temperature eau glacee', 2, '0.01', false, true))
            ->add('poidsMoyenParCaisse', NumberType::class, $this->numberOptions('Poids moyen par caisse (kg)', 3, '0.001', false))
            ->add('nombreCaissesApresTraitement', IntegerType::class, $this->integerOptions('Nombre de caisses apres traitement', false))
            ->add('nombreMoules', IntegerType::class, $this->integerOptions('Nombre de moules', false))
            ->add('nombreCaissesParPalette', IntegerType::class, $this->integerOptions('Nombre de caisses par palette', false))
            ->add('nombreTotalPalettes', IntegerType::class, $this->integerOptions('Nombre total de palettes', false));
    }

    /** @param array<string, mixed> $options */
    private function buildFreezing(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantiteCongelee', NumberType::class, $this->numberOptions('Produit fini envoye tunnel (kg)', 3, '0.001'))
            ->add('poidsDechetsTraitement', NumberType::class, $this->numberOptions('Dechets traitement (kg)', 3, '0.001', false))
            ->add('poidsPertesTraitement', NumberType::class, $this->numberOptions('Pertes traitement (kg)', 3, '0.001', false))
            ->add('tunnel', $options['tunnel_choices'] === [] ? TextType::class : ChoiceType::class, $this->choiceOrTextOptions('Tunnel', $options['tunnel_choices'], false, 80, 'Ex. Tunnel 1'))
            ->add('dateEntreeTunnel', DateType::class, $this->dateOptions('Date entree tunnel', false))
            ->add('heureEntreeTunnel', TimeType::class, $this->timeOptions('Heure entree tunnel', false))
            ->add('temperatureTunnel', NumberType::class, $this->numberOptions('Temperature tunnel', 2, '0.01', false, true))
            ->add('temperatureCoeurProduit', NumberType::class, $this->numberOptions('Temperature coeur produit', 2, '0.01', false, true));
    }

    /** @param array<string, mixed> $options */
    private function buildStorage(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantiteStockee', NumberType::class, $this->numberOptions('Quantite en cristallisation (kg)', 3, '0.001'))
            ->add('dateSortieTunnel', DateType::class, $this->dateOptions('Date sortie tunnel', false))
            ->add('heureSortieTunnel', TimeType::class, $this->timeOptions('Heure sortie tunnel', false))
            ->add('chambreFroide', $options['positive_storage_choices'] === [] ? TextType::class : ChoiceType::class, $this->choiceOrTextOptions('Chambre de cristallisation', $options['positive_storage_choices'], false, 120, 'Ex. Chambre positive 1'))
            ->add('dateEntreeStockage', DateType::class, $this->dateOptions('Date entree chambre', false))
            ->add('heureEntreeStockage', TimeType::class, $this->timeOptions('Heure entree chambre', false))
            ->add('temperatureChambre', NumberType::class, $this->numberOptions('Temperature chambre', 2, '0.01', false, true))
            ->add('temperatureStockage', NumberType::class, $this->numberOptions('Temperature produit', 2, '0.01', false, true));
    }

    /** @param array<string, mixed> $options */
    private function buildPackaging(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantiteConditionnee', NumberType::class, $this->numberOptions('Quantite emballee (kg)', 3, '0.001'))
            ->add('quantiteRemiseEnChambre', NumberType::class, $this->numberOptions('Quantite remise en chambre (kg)', 3, '0.001'))
            ->add('produitConditionne', TextType::class, $this->textOptions('Produit conditionne', false, 150, 'Ex. carton 10 kg'))
            ->add('dateConditionnement', DateType::class, $this->dateOptions('Date emballage', false))
            ->add('heureDebutConditionnement', TimeType::class, $this->timeOptions('Heure debut emballage', false))
            ->add('heureFinConditionnement', TimeType::class, $this->timeOptions('Heure fin emballage', false))
            ->add('poidsNet', NumberType::class, $this->numberOptions('Poids net (kg)', 3, '0.001', false))
            ->add('poidsDechetsEmballage', NumberType::class, $this->numberOptions('Dechets emballage (kg)', 3, '0.001', false))
            ->add('poidsPertesEmballage', NumberType::class, $this->numberOptions('Pertes emballage (kg)', 3, '0.001', false))
            ->add('coutHoraireEmballage', NumberType::class, $this->numberOptions('Cout horaire emballage', 2, '0.01', false))
            ->add('chambreRemiseEnChambre', $options['positive_storage_choices'] === [] ? TextType::class : ChoiceType::class, $this->choiceOrTextOptions('Chambre retour apres emballage', $options['positive_storage_choices'], false, 120, 'Ex. Chambre positive 1'))
            ->add('dateRemiseEnChambre', DateType::class, $this->dateOptions('Date retour chambre', false))
            ->add('heureRemiseEnChambre', TimeType::class, $this->timeOptions('Heure retour chambre', false))
            ->add('temperatureChambreRemise', NumberType::class, $this->numberOptions('Temperature chambre retour', 2, '0.01', false, true))
            ->add('temperatureProduitRemise', NumberType::class, $this->numberOptions('Temperature produit retour', 2, '0.01', false, true));
    }

    private function buildShipping(FormBuilderInterface $builder): void
    {
        $builder
            ->add('quantiteTotaleExpediee', NumberType::class, $this->numberOptions('Quantite expediee (kg)', 3, '0.001'))
            ->add('destinationFinaleClient', TextType::class, $this->textOptions('Destination finale / Client', false, 150, 'Client ou destination'))
            ->add('expeditionDateDepart', DateType::class, $this->dateOptions('Date expedition', false))
            ->add('expeditionHeureDepart', TimeType::class, $this->timeOptions('Heure depart camion', false))
            ->add('expeditionMatriculeVehicule', TextType::class, $this->textOptions('Matricule camion', false, 80, 'Ex. 12345-A-6'))
            ->add('expeditionChauffeur', TextType::class, $this->textOptions('Nom chauffeur', false, 150, 'Nom et prenom'))
            ->add('expeditionResponsableChargement', TextType::class, $this->textOptions('Responsable chargement', false, 150, 'Responsable chargement'))
            ->add('expeditionTemperatureProduit', NumberType::class, $this->numberOptions('Temperature produit au chargement', 2, '0.01', false, true))
            ->add('expeditionNumeroPlomb', TextType::class, $this->textOptions('Numero plomb / scelle', false, 80, 'Numero plomb'))
            ->add('expeditionObservations', TextareaType::class, [
                'label' => 'Observations expedition',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 2000, 'placeholder' => 'Observation chargement, documents, etat camion...'],
            ]);
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
    private function integerOptions(string $label, bool $required = true): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'empty_data' => '0',
            'attr' => ['min' => 0, 'step' => 1, 'placeholder' => 'Ex. 0'],
        ];
    }

    /** @return array<string, mixed> */
    private function numberOptions(string $label, int $scale = 2, string $step = '0.01', bool $required = true, bool $allowNegative = false): array
    {
        $attr = ['step' => $step, 'placeholder' => $allowNegative ? 'Ex. -18' : 'Ex. 0'];
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
            'attr' => ['placeholder' => 'Selectionner une date'],
        ];
    }

    /** @return array<string, mixed> */
    private function timeOptions(string $label, bool $required = true): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'attr' => ['placeholder' => 'Ex. 08:30'],
        ];
    }

    /**
     * @param array<string, string> $choices
     *
     * @return array<string, mixed>
     */
    private function choiceOrTextOptions(string $label, array $choices, bool $required, int $maxlength, string $placeholder): array
    {
        if ($choices === []) {
            return $this->textOptions($label, $required, $maxlength, $placeholder);
        }

        return [
            'label' => $label,
            'required' => $required,
            'placeholder' => 'Selectionner...',
            'choices' => $choices,
        ];
    }
}
