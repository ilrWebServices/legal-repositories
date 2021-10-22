<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type;

class ConsentDecreeDocument extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('from', Type\TextType::class, [
                'label' => 'from',
            ])
            ->add('to', Type\TextType::class, [
                'label' => 'to',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
    }
}
