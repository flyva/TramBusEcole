<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class SlideUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Choisis une image.'),
                    new File(
                        maxSize: '8M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WEBP, GIF.',
                    ),
                ],
            ])
            ->add('caption', TextType::class, [
                'label' => 'Légende (optionnel)',
                'required' => false,
            ])
        ;
    }
}
