<?php

namespace App\Form;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AppointmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex. Rendez-vous client'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description / note',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Notes utiles avant le rendez-vous...'],
            ])
            ->add('appointmentType', ChoiceType::class, [
                'label' => 'Type',
                'choices' => Appointment::TYPE_CHOICES,
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorite',
                'choices' => Appointment::PRIORITY_CHOICES,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => Appointment::STATUS_CHOICES,
            ])
            ->add('startAt', DateTimeType::class, [
                'label' => 'Debut',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endAt', DateTimeType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('allDay', CheckboxType::class, [
                'label' => 'Journee entiere',
                'required' => false,
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => ['placeholder' => 'Bureau, site client, adresse...'],
            ])
            ->add('meetingLink', TextType::class, [
                'label' => 'Lien visio',
                'required' => false,
                'attr' => ['placeholder' => 'https://...'],
            ])
            ->add('customerName', TextType::class, [
                'label' => 'Client / contact',
                'required' => false,
                'attr' => ['placeholder' => 'Nom du client ou contact'],
            ])
            ->add('customerEmail', EmailType::class, [
                'label' => 'Email client',
                'required' => false,
                'attr' => ['placeholder' => 'contact@example.com'],
            ])
            ->add('customerPhone', TelType::class, [
                'label' => 'Telephone client',
                'required' => false,
                'attr' => ['placeholder' => '+33 ...'],
            ])
            ->add('participantUsers', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user): string => $user->getDisplayName().' - '.$user->getEmail(),
                'choice_value' => 'id',
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilder('u')
                    ->andWhere('u.isActive = true')
                    ->orderBy('u.firstName', 'ASC')
                    ->addOrderBy('u.lastName', 'ASC')
                    ->addOrderBy('u.email', 'ASC'),
                'label' => 'Participants',
                'mapped' => false,
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'data' => $options['selected_participants'],
                'attr' => [
                    'size' => 7,
                    'data-appointment-participants-select' => 'true',
                ],
            ])
            ->add('reminderAt', DateTimeType::class, [
                'label' => 'Rappel',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Appointment::class,
            'selected_participants' => [],
        ]);
        $resolver->setAllowedTypes('selected_participants', 'array');
    }
}
