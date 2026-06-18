<?php

namespace App\Form;

use App\Entity\InventoryCampaignLine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InventoryCampaignLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('countedQuantity', IntegerType::class, [
                'label' => 'Quantité comptée',
                'required' => false,
                'attr' => ['min' => 0],
            ])
            ->add('countedLocation', TextType::class, [
                'label' => 'Emplacement constaté',
                'required' => false,
            ])
            ->add('checkStatus', ChoiceType::class, [
                'label' => 'Statut de contrôle',
                'choices' => InventoryCampaignLine::CHECK_STATUSES,
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InventoryCampaignLine::class]);
    }
}
