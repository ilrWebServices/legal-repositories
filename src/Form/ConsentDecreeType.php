<?php

namespace App\Form;

use App\Entity\ConsentDecree;
use App\Form\DataTransformer\JSONDataTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConsentDecreeType extends AbstractType
{

    private $transformer;

    public function __construct(JSONDataTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('case_number')
            ->add('version')
            ->add('case_name')
            ->add('document')
        ;

        $builder->get('document')
            ->addModelTransformer($this->transformer);
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ConsentDecree::class,
        ]);
    }
}
