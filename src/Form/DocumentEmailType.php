<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('recipientEmail', EmailType::class, [
            'label' => 'Adresse e-mail du destinataire',
            'constraints' => [
                new Assert\NotBlank(message: 'L’adresse e-mail du destinataire est obligatoire.'),
                new Assert\Email(message: 'L’adresse e-mail du destinataire n’est pas valide.'),
            ],
            'attr' => [
                'autocomplete' => 'email',
                'placeholder' => 'Ex. client@entreprise.com',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'document_email',
        ]);
    }
}
