<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class InterimWorkerEndMissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('missionEndDate', DateType::class, [
                'label' => 'Date de fin de mission',
                'mapped' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => $options['mission_end_date'] ?? new \DateTimeImmutable('today'),
                'constraints' => [
                    new Assert\NotNull(message: 'La date de fin de mission est obligatoire.'),
                ],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif de fin de mission',
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le motif de fin de mission est obligatoire.'),
                    new Assert\Length(max: 1500),
                ],
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Ex. fin de contrat, mission terminee, absence repetee...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'interim_worker_end_mission',
            'mission_end_date' => null,
        ]);
        $resolver->setAllowedTypes('mission_end_date', ['null', \DateTimeImmutable::class]);
    }
}
