<?php

namespace App\Form;

use App\Entity\AppModule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AppModuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Ex. Gestion commerciale'],
            ])
            ->add('slug', TextType::class, [
                'attr' => ['placeholder' => 'Ex. gestion-commerciale'],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Décrivez l’utilité de ce module...'],
            ])
            ->add('icon', TextType::class, [
                'help' => 'Exemple : bi-key',
                'attr' => ['placeholder' => 'Ex. bi-grid'],
            ])
            ->add('routeName', TextType::class, [
                'label' => 'Nom de route',
                'attr' => ['placeholder' => 'Ex. app_module_index'],
            ])
            ->add('isActive', CheckboxType::class, ['label' => 'Module actif', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AppModule::class]);
    }
}
