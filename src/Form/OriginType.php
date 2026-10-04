<?php

namespace App\Form;

use App\Entity\Setting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class OriginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('originAddress', TextType::class, [
                'label' => 'Adresse (là où est installé le tableau)',
                'attr' => ['autocomplete' => 'off', 'placeholder' => 'Commence à taper une adresse...'],
                'constraints' => [new NotBlank()],
            ])
            ->add('originLat', HiddenType::class)
            ->add('originLng', HiddenType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Setting::class,
        ]);
    }
}
