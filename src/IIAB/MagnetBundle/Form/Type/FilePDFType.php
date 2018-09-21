<?php

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class FilePDFType extends AbstractType {

    public function buildForm( FormBuilderInterface $builder , array $options ) {

        $usage_choices = ( isset( $options['usage_choices']) ) ? $options['usage_choices'] : [];
        $usage_choices = ( is_array( $usage_choices ) ) ? $usage_choices : [ $usage_choices => $usage_choices ];

        switch( count( $usage_choices ) ){
            case 0:
                $usage_type = 'hidden';
                $usage_options = [];
                break;
            case 1:
                $usage_type = 'text';
                $usage_options = [
                    'disabled' => true,
                    'data' => reset( $usage_choices )
                ];
                break;
            default:
                $usage_type = 'choice';
                $usage_options = [
                    'label' => 'test',
                    'placeholder' => 'form.option.choose',
                    'choices' => $usage_choices,
                ];
        }

        $builder
            ->add( 'pdfFile', FileType::class , [
                'attr' => [
                    'accept' => 'application/pdf'
                ]
            ])
            ->add( 'usage', $usage_type, $usage_options );



    }

    public function configureOptions( \Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
        $resolver->setDefined('usage_choices');
    }

        /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName() {

        return 'file_pdf';
    }

}