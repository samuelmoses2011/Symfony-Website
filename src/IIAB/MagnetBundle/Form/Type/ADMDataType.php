<?php
/**
 * Company: Image In A Box
 * Date: 3/10/15
 * Time: 6:19 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ADMDataType extends AbstractType {

	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder
			->add( 'black' , null , array( 'label' => 'Black' ) )
			->add( 'white' , null , array( 'label' => 'White' ) )
			->add( 'other' , null , array( 'label' => 'Other' ) )
		;
	}

	public function setDefaultOptions( OptionsResolverInterface $resolver ) {

		$resolver->setDefaults( array(
			'data_class' => 'IIAB\MagnetBundle\Entity\ADMData'
		) );
	}


	public function getName() {

		return 'adm_data';
	}

}