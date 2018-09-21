<?php

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class CurrentPopulationType extends AbstractType {

    public function buildForm( FormBuilderInterface $builder , array $options ) {

        foreach( $options['columns'] as $type => $columns ){
            foreach( $columns as $id => $population ){
                $builder
                    ->add( $type.'_'.$id , 'text' , array(
                        'label' => $population->getTrackingValue(),
                        'required' => false,
                        'data' => $population->getCount()
                    ) );
            }
        }

        if( $options['school_type'] == 'magnetSchool' ){
            $data = ( isset($options['capacity']) )
                    ? $options['capacity']
                    : 0;

    		$builder
    			->add( 'maxCapacity' , null , array(
                    'label' => 'Max Capacity',
                    'required' => false ,
                    'data' => $data
                ) )
    		;
        }
	}

    /**
     * {@inheritdoc}
     */
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
        $resolver->setDefaults( array(
            'columns' => [],
            'population_counts' => [],
            'capacity' => 0,
            'school_type' => 'magnetSchool',
        ) );
    }

	public function getName() {

		return 'current_population';
	}

}