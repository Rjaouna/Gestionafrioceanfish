<?php

namespace App\Form;

use App\Entity\InterimWorker;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class InterimWorkerStatusActionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'label' => 'Nouveau statut',
                'mapped' => false,
                'choices' => array_flip(InterimWorker::STATUS_LABELS),
                'data' => $options['current_status'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le statut est obligatoire.'),
                ],
            ])
            ->add('actionDate', DateType::class, [
                'label' => 'Date du changement',
                'mapped' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => new \DateTimeImmutable('today'),
                'constraints' => [
                    new Assert\NotNull(message: 'La date du changement est obligatoire.'),
                ],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif / note interne',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Length(max: 1500),
                ],
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Note facultative sur ce changement de statut.',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'interim_worker_status_action',
            'current_status' => InterimWorker::STATUS_ACTIVE,
        ]);
        $resolver->setAllowedTypes('current_status', 'string');
    }
}
