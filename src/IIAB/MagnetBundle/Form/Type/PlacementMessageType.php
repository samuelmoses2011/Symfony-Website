<?php
/**
 * Company: Image In A Box
 * Date: 3/10/15
 * Time: 11:38 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class PlacementMessageType extends AbstractType {

    /**
     * {@inheritdoc}
     */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

        $builder
			->add( 'interview' , 'choice' ,[
				'choices' => [ 0 => 'Not Required' , 1 => 'Required' ] ,
                'expanded' => false ,
                'multiple' => false ,
				'required' => false,
                'attr' => [ 'data' ]
			] )
			->add( 'specialRequirement' , 'textarea' , array( 'required' => false ) );
	}

	public function setDefaultOptions( OptionsResolverInterface $resolver ) {

		$resolver->setDefaults( array(
			'data_class' => 'IIAB\MagnetBundle\Entity\PlacementMessage'
		) );
	}


	public function getName() {

        return 'next_step';
		//return 'placementMessage';

	}
}