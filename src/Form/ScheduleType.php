<?php

namespace App\Form;

use App\Entity\Setting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('scheduleEnabled', CheckboxType::class, [
                'label' => "Activer l'extinction automatique de l'écran",
                'required' => false,
            ])
            ->add('screenOnTime', TimeType::class, [
                'label' => 'Allumage',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
            ])
            ->add('screenOffTime', TimeType::class, [
                'label' => 'Extinction',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Setting::class,
        ]);
    }
}
