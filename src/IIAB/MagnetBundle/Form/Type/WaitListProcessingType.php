<?php
/**
 * Company: Image In A Box
 * Date: 4/24/15
 * Time: 10:48 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class WaitListProcessingType extends AbstractType {

	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder
			->add( 'maxCapacity' , 'integer' , array(
				'attr' => ['readonly'=>'readonly'],
				'disabled' => true ) )
            ->add( 'waitListTotal' , 'integer' , array(
            	'attr' => ['readonly'=>'readonly'],
            	'disabled' => true ) )
			->add( 'availableSlots' , 'integer' , array(
			'attr' => ['readonly'=>'readonly'],
			'disabled' => true ) )
			->add( 'slotsToAward' , 'integer' , array( 'attr' => array( 'min' => 0 ) ) );
	}


	public function setDefaultOptions( OptionsResolverInterface $resolver ) {

		parent::setDefaultOptions( $resolver );
	}


	public function getName() {

		return 'wait_list_processing';
	}


}