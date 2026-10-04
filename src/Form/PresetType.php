<?php

namespace App\Form;

use App\Entity\Preset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PresetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom (ex: École, Travail, Aéroport...)',
                'constraints' => [new NotBlank()],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'attr' => ['autocomplete' => 'off', 'placeholder' => 'Commence à taper une adresse...'],
                'constraints' => [new NotBlank()],
            ])
            ->add('lat', HiddenType::class)
            ->add('lng', HiddenType::class)
            ->add('daysOfWeek', ChoiceType::class, [
                'label' => 'Jours (laisse vide pour un usage ponctuel uniquement)',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Lundi' => 1,
                    'Mardi' => 2,
                    'Mercredi' => 3,
                    'Jeudi' => 4,
                    'Vendredi' => 5,
                    'Samedi' => 6,
                    'Dimanche' => 7,
                ],
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Preset::class,
        ]);
    }
}
