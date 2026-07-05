<?php

namespace App\Form;

use App\Entity\FishReception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FishReceptionTreatmentCancelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $available = (float) $options['available_quantity'];

        $builder
            ->add('quantity', NumberType::class, [
                'label' => 'Quantite a annuler du traitement (kg)',
                'mapped' => false,
                'required' => true,
                'data' => $available > 0 ? round($available, 3) : null,
                'attr' => ['min' => 0.001, 'max' => max(0.001, round($available, 3)), 'step' => '0.001'],
                'help' => sprintf('Annulable : %.3f kg non encore emballe.', max(0.0, $available)),
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif / observation',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Ex. erreur de saisie, lot non traite, retour stock reception...',
                ],
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
}
