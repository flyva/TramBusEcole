<?php

namespace App\Form;

use App\Entity\ScreenOffPeriod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScreenOffPeriodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startTime', TimeType::class, [
                'label' => 'Extinction',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Rallumage',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ScreenOffPeriod::class,
        ]);
    }
}
