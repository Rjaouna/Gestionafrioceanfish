<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ConsumableStockExitType extends AbstractType
{
    use SmartChoiceTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choiceLists = $options['choice_lists'];

        $builder
            ->add('quantity', NumberType::class, [
                'label' => 'Quantité sortie',
                'mapped' => false,
                'scale' => 2,
                'html5' => true,
                'constraints' => [new Assert\Positive(message: 'La quantité doit être supérieure à zéro.')],
                'attr' => ['min' => 0.01, 'step' => '0.01'],
            ])
            ->add('movementDate', DateTimeType::class, [
                'label' => 'Date de la sortie',
                'mapped' => false,
                'widget' => 'single_text',
                'data' => new \DateTimeImmutable(),
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif',
                'mapped' => false,
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Ex. distribution hebdomadaire, nettoyage, urgence...'],
            ]);

        $configs = [
            'recipient' => ['label' => 'Service / personne', 'values' => $choiceLists['recipients'] ?? [], 'required' => false, 'maxlength' => 180, 'choice_options' => ['mapped' => false]],
        ];

        $this->addSmartChoice($builder, 'recipient', 'Service / personne', $configs['recipient']['values'], false, 180, null, ['mapped' => false]);
        $this->addSmartChoiceSubmitListener($builder, $configs);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['choice_lists' => []]);
        $resolver->setAllowedTypes('choice_lists', 'array');
    }
}
