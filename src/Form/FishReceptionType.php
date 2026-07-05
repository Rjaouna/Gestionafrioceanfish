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
    use ReceptionSmartChoiceTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $reception = $builder->getData();
        $choiceLists = $options['choice_lists'];
        $smartFields = [
            'fournisseur' => ['label' => 'Fournisseur', 'values' => $choiceLists['fournisseur'] ?? [], 'required' => true, 'maxlength' => 150],
            'provenance' => ['label' => 'Provenance', 'values' => $choiceLists['provenance'] ?? [], 'required' => false, 'maxlength' => 150],
            'especePoisson' => ['label' => 'Espece poisson', 'values' => $choiceLists['especePoisson'] ?? [], 'required' => true, 'maxlength' => 120],
            'presentationProduit' => ['label' => 'Presentation produit', 'values' => $choiceLists['presentationProduit'] ?? [], 'required' => true, 'maxlength' => 120],
            'etatProduit' => ['label' => 'Etat du produit', 'values' => $choiceLists['etatProduit'] ?? [], 'required' => true, 'maxlength' => 120],
            'categorieFraicheur' => ['label' => 'Categorie fraicheur', 'values' => $choiceLists['categorieFraicheur'] ?? [], 'required' => true, 'maxlength' => 80],
        ];

        $builder
            ->add('dateReception', DateType::class, $this->dateOptions('Date de reception'))
            ->add('heureDebutReception', TimeType::class, $this->timeOptions('Heure debut reception'))
            ->add('heureFinReception', TimeType::class, $this->timeOptions('Heure fin reception'))
            ->add('matriculeVehicule', TextType::class, $this->textOptions('Matricule vehicule', false, 80))
            ->add('chauffeur', TextType::class, $this->textOptions('Chauffeur', false, 150))
            ->add('nomScientifique', TextType::class, $this->textOptions('Nom scientifique', false, 150))
            ->add('numeroBonLivraison', TextType::class, $this->textOptions('N Bon de livraison', false, 120))
            ->add('quantiteIndiqueeBl', NumberType::class, $this->numberOptions('Quantite indiquee sur BL (kg)', 3, '0.001', false))
            ->add('quantiteReceptionnee', NumberType::class, $this->numberOptions('Quantite receptionnee (kg)', 3, '0.001'))
            ->add('nombreCaissesReception', IntegerType::class, $this->integerOptions('Nombre de caisses reception', false))
            ->add('temperaturePoissonReception', NumberType::class, $this->numberOptions('Temperature poisson reception', 2, '0.01', false, true))
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

        $this->addReceptionSmartChoice($builder, 'fournisseur', 'Fournisseur', $smartFields['fournisseur']['values'], true, 150, $reception instanceof FishReception ? $reception->getFournisseur() : null);
        $this->addReceptionSmartChoice($builder, 'provenance', 'Provenance', $smartFields['provenance']['values'], false, 150, $reception instanceof FishReception ? $reception->getProvenance() : null);
        $this->addReceptionSmartChoice($builder, 'especePoisson', 'Espece poisson', $smartFields['especePoisson']['values'], true, 120, $reception instanceof FishReception ? $reception->getEspecePoisson() : null);
        $this->addReceptionSmartChoice($builder, 'presentationProduit', 'Presentation produit', $smartFields['presentationProduit']['values'], true, 120, $reception instanceof FishReception ? $reception->getPresentationProduit() : null);
        $this->addReceptionSmartChoice($builder, 'etatProduit', 'Etat du produit', $smartFields['etatProduit']['values'], true, 120, $reception instanceof FishReception ? $reception->getEtatProduit() : null);
        $this->addReceptionSmartChoice($builder, 'categorieFraicheur', 'Categorie fraicheur', $smartFields['categorieFraicheur']['values'], true, 80, $reception instanceof FishReception ? $reception->getCategorieFraicheur() : null);
        $this->addReceptionSmartChoiceSubmitListener($builder, $smartFields);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FishReception::class,
            'choice_lists' => [],
        ]);
        $resolver->setAllowedTypes('choice_lists', 'array');
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
