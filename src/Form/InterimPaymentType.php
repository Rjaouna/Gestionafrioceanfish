<?php

namespace App\Form;

use App\Entity\InterimPayment;
use App\Entity\InterimWorker;
use App\Repository\InterimWorkerRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InterimPaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('worker', EntityType::class, [
                'class' => InterimWorker::class,
                'choice_label' => static fn (InterimWorker $worker): string => sprintf('%s - %s', $worker->getFullName(), $worker->getRegistrationNumber() ?: 'sans matricule'),
                'query_builder' => static fn (InterimWorkerRepository $repository) => $repository->createQueryBuilder('w')
                    ->andWhere('w.isDeleted = false')
                    ->orderBy('w.lastName', 'ASC')
                    ->addOrderBy('w.firstName', 'ASC'),
                'label' => 'Interimaire',
                'placeholder' => 'Selectionner un interimaire',
            ])
            ->add('paymentDate', DateType::class, [
                'label' => 'Date paiement',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('periodFrom', DateType::class, [
                'label' => 'Periode du',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('periodTo', DateType::class, [
                'label' => 'Periode au',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant paye',
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => '0.01', 'step' => '0.01', 'placeholder' => 'Ex. 1250.00'],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Moyen de paiement',
                'choices' => array_flip(InterimPayment::METHOD_LABELS),
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_flip(InterimPayment::STATUS_LABELS),
            ])
            ->add('reference', TextType::class, [
                'label' => 'Reference',
                'required' => false,
                'attr' => ['maxlength' => 120, 'placeholder' => 'Ex. recu, virement, note interne'],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Note',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 1000, 'placeholder' => 'Observation paiement, avance, regularisation...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InterimPayment::class,
        ]);
    }
}
