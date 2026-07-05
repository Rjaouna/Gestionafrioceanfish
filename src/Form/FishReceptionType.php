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

final class FishReceptionType extends AbstractType
{
    private const FOURNISSEURS = ['Hiba', 'Fournisseur import', 'Grossiste partenaire', 'Peche artisanale'];
    private const ESPECES = ['Sardine', 'Maquereau', 'Merlu', 'Poulpe', 'Crevette', 'Calamar', 'Sole', 'Thon', 'Anchois'];
    private const PRESENTATIONS = ['Entier', 'Filet', 'Tranche', 'HG', 'HGT', 'Sale', 'Marine', 'Sec', 'Fume'];
    private const ETATS = ['Frais', 'Refrigere', 'Congele', 'Transforme', 'A controler'];
    private const FRAICHEURS = ['Extra', 'A', 'B', 'Conforme', 'A controler', 'A renvoyer'];
    private const PROVENANCES = ['Port Casablanca', 'Port Rabat', 'Port Tanger', 'Port Agadir', 'Espagne', 'Afrique', 'Bouskoura'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateReception', DateType::class, $this->dateOptions('Date de reception'))
            ->add('heureDebutReception', TimeType::class, $this->timeOptions('Heure debut reception'))
            ->add('heureFinReception', TimeType::class, $this->timeOptions('Heure fin reception'))
            ->add('fournisseur', ChoiceType::class, $this->choiceOptions('Fournisseur', self::FOURNISSEURS))
            ->add('provenance', ChoiceType::class, $this->choiceOptions('Provenance', self::PROVENANCES, false))
            ->add('matriculeVehicule', TextType::class, $this->textOptions('Matricule vehicule', false, 80))
            ->add('chauffeur', TextType::class, $this->textOptions('Chauffeur', false, 150))
            ->add('especePoisson', ChoiceType::class, $this->choiceOptions('Espece poisson', self::ESPECES))
            ->add('nomScientifique', TextType::class, $this->textOptions('Nom scientifique', false, 150))
            ->add('presentationProduit', ChoiceType::class, $this->choiceOptions('Presentation produit', self::PRESENTATIONS))
            ->add('etatProduit', ChoiceType::class, $this->choiceOptions('Etat du produit', self::ETATS))
            ->add('numeroBonLivraison', TextType::class, $this->textOptions('N Bon de livraison', false, 120))
            ->add('quantiteIndiqueeBl', NumberType::class, $this->numberOptions('Quantite indiquee sur BL (kg)', 3, '0.001', false))
            ->add('quantiteReceptionnee', NumberType::class, $this->numberOptions('Quantite receptionnee (kg)', 3, '0.001'))
            ->add('nombreCaissesReception', IntegerType::class, $this->integerOptions('Nombre de caisses reception', false))
            ->add('temperaturePoissonReception', NumberType::class, $this->numberOptions('Temperature poisson reception', 2, '0.01', false, true))
            ->add('categorieFraicheur', ChoiceType::class, $this->choiceOptions('Categorie fraicheur', self::FRAICHEURS))
            ->add('presenceGlace', ChoiceType::class, [
                'label' => 'Presence de glace',
                'choices' => ['Oui' => true, 'Non' => false],
                'expanded' => false,
                'multiple' => false,
            ])
            ->add('responsableProduction', TextType::class, $this->textOptions('Responsable production', false, 150))
            ->add('signatureResponsable', TextType::class, $this->textOptions('Signature', false, 150))
            ->add('observations', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 2000],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FishReception::class,
        ]);
    }

    /** @return array<string, mixed> */
    private function choiceOptions(string $label, array $choices, bool $required = true): array
    {
        return [
            'label' => $label,
            'choices' => array_combine($choices, $choices),
            'placeholder' => $required ? 'Choisir...' : 'Non renseigne',
            'required' => $required,
        ];
    }

    /** @return array<string, mixed> */
    private function textOptions(string $label, bool $required = true, int $maxlength = 150): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'attr' => ['maxlength' => $maxlength],
        ];
    }

    /** @return array<string, mixed> */
    private function integerOptions(string $label, bool $required = true): array
    {
        return [
            'label' => $label,
            'required' => $required,
            'empty_data' => '0',
            'attr' => ['min' => 0, 'step' => 1],
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
}
