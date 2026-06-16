<?php

namespace App\Form;

use App\Entity\ExpenseCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ExpenseCategoryType extends AbstractType
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Ex. Carburant'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'attr' => ['placeholder' => 'Généré automatiquement si vide'],
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icône Bootstrap',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. bi-fuel-pump'],
            ])
            ->add('color', TextType::class, [
                'label' => 'Couleur Bootstrap',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. primary, success, warning...'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Description courte de la catégorie...'],
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            if (trim((string) ($data['slug'] ?? '')) === '' && trim((string) ($data['name'] ?? '')) !== '') {
                $data['slug'] = (string) $this->slugger->slug((string) $data['name'])->lower();
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ExpenseCategory::class]);
    }
}
