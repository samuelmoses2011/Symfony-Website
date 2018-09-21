<?php

namespace LeanFrog\SharedDataBundle\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormEvents;

class PopulationRowType extends AbstractType {
    /**
     * {@inheritdoc}
     */
    public function buildForm( FormBuilderInterface $builder , array $options ) {

        $options = array_merge( [
            'display_year' => true,
            'display_type' => true,
            'display_date' => true,
        ], $options );


        if( $options['display_year'] ){

            $builder
            ->add( 'academicYear', null, [
                'label' => 'Academic Year',
                'attr' => ['readonly'=>'readonly']
            ] );
        }

        if( $options['display_type'] ){

            $builder
            ->add( 'updateType', null, [
            ]);
        }

        if( $options['display_date'] ){

            $builder
            ->add( 'updateDateTime' , 'datetime' , [
                'label' => 'Date Time',
                'required' => false,
                'attr' => ['readonly'=>'readonly'],
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd hh:mm',
            ] );
        }

        $builder
            ->add( 'Black' , 'integer' , [
                'label' => 'Black',
                'required' => true,
                'attr' => ['class'=>'sonata-inline'],
            ] )

            ->add( 'White' , 'integer' , [
                'label' => 'White',
                'required' => true,
                'attr' => ['class'=>'sonata-inline'],
            ] )

            ->add( 'Other' , 'integer' , [
                'label' => 'Other',
                'required' => true,
                'attr' => ['class'=>'sonata-inline'],
            ] )
            ->add( 'None' , 'integer' , [
                'label' => 'Not Specified',
                'required' => true,
                'attr' => ['class'=>'sonata-inline'],
            ] );

    }

    public function configureOptions( \Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
         $resolver->setDefined('display_year');
         $resolver->setDefined('display_type');
         $resolver->setDefined('display_date');
    }

}