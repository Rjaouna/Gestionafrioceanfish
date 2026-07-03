<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class InterimWorkerDoNotRecallType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('actionDate', DateType::class, [
                'label' => 'Date de decision',
                'mapped' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => $options['action_date'] ?? new \DateTimeImmutable('today'),
                'constraints' => [
                    new Assert\NotNull(message: 'La date de decision est obligatoire.'),
                ],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif grave',
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le motif grave est obligatoire pour bloquer un rappel.'),
                    new Assert\Length(max: 1500),
                ],
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Expliquez le probleme constate et pourquoi la personne ne doit plus etre rappelee.',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'interim_worker_do_not_recall',
            'action_date' => null,
        ]);
        $resolver->setAllowedTypes('action_date', ['null', \DateTimeImmutable::class]);
    }
}
