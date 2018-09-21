<?php
/**
 * Company: Image In A Box
 * Date: 7/20/15
 * Time: 4:41 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class MagnetSchoolSettingType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder
			->add( 'waitList' , null , [ 'required' => false ] )
			->add( 'committeeScoreRequired' , null , [ 'required' => false ] )
			->add( 'minimumCommitteeScore' , 'choice' , [
				'choices' => [
					0 , 1 , 2 , 3 , 4
				] ,
				'placeholder' => 'Select a Minimum Score' ,
				'required' => false
			] )
		;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setDefaultOptions( OptionsResolverInterface $resolver ) {

		$resolver->setDefaults( [
			'data_class' => 'IIAB\MagnetBundle\Entity\MagnetSchoolSetting'
		] );
	}


	public function getName() {

		return 'committee_setting';
	}


}