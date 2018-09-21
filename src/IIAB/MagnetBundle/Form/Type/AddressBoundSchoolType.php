<?php
/**
 * Company: Image In A Box
 * Date: 9/30/15
 * Time: 2:45 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class AddressBoundSchoolType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$grades = array(
			99 => 'PreK',
			0 => 'K',
			1 => '1' ,
			2 => '2' ,
			3 => '3' ,
			4 => '4' ,
			5 => '5' ,
			6 => '6' ,
			7 => '7' ,
			8 => '8' ,
			9 => '9' ,
			10 => '10' ,
			11 => '11' ,
			12 => '12' ,
		);

		$builder->add( 'startGrade' , 'choice' , [
			'choices' => $grades ,
			'required' => false
		] );
		$builder->add( 'endGrade' , 'choice' , [
			'choices' => $grades ,
			'required' => false
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function setDefaultOptions( OptionsResolverInterface $resolver ) {


		$resolver->setDefaults( [
			'data_class' => 'IIAB\MagnetBundle\Entity\AddressBoundSchool'
		] );
	}

	public function getName() {

		return 'address_bound_school';
	}

}