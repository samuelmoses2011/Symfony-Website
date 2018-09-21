<?php
/**
 * Company: Image In A Box
 * Date: 7/21/15
 * Time: 1:48 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class GpaSettingType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$courses = [
			3 => 'English' ,
			4 => 'Math' ,
			7 => 'Science' ,
			9 => 'Social Studies' ,
		];
		$semesters = [
			1 => '1' ,
			2 => '2' ,
			3 => '3' ,
			4 => '4' ,
			5 => '5' ,
			6 => '6'
		];
		$compare = [
			'=' => 'Equals' ,
			'>=' => 'Greater than or equals to' ,
			'>' => 'Greater than' ,
			'<' => 'Less than' ,
			'<=' => 'Less than or equals to' ,
		];

		$builder
			->add( 'criteriaType' , 'choice' , [
				'choices' => [
					'' => 'None' ,
					'GPA Check' => 'GPA Check' ,
				//	'Course Title Check' => 'Course Title Check' ,
				],
				//'placeholder' => 'Criteria Requirement' ,
				'required' => false ,
			] )
			->add( 'courseTypeToAverage' , 'choice' , [
				'choices' => [ $courses ] ,
				'expanded' => true ,
				'multiple' => true ,
			] )
			->add( 'numberofSemesters' , 'choice' , [
				'choices' => $semesters ,
				'expanded' => false ,
				'multiple' => false ,
				'placeholder' => 'Number of Semesters',
				'required' => false ,
			] )
			/*->add( 'courseTypeToCheckTitle' , 'choice' , [
				'choices' => [ $courses ] ,
				'expanded' => true ,
				'multiple' => false ,
				'required' => false ,
			] )
			->add( 'courseTitle' )*/
			->add( 'comparison' , 'choice' , [
				'choices' => $compare ,
				'expanded' => false ,
				'multiple' => false ,
				'placeholder' => 'Select a Comparison' ,
				'required' => false ,
			] )
			->add( 'passingThreshold' , 'integer' , [
				'attr' => [
					'max' => 100 ,
					'min' => 0 ,
					'maxlength' => 3 ,
					'style' => 'width: 55px;'
				] ,
				'required' => false ,
			] )
			/*
			 * To handle the storage of the serialized string
			 * we need to add a ModelTransformer to convert it back and forth.
			 */
			->get( 'courseTypeToAverage' )
			->addModelTransformer( new CallbackTransformer(
				function ( $originalCourseTypeToAverage ) {

					//Handling the original Form Data.
					try {
						if( $originalCourseTypeToAverage === null ) {
							return [ ];
						}
						return unserialize( $originalCourseTypeToAverage );
					} catch( \Exception $e ) {
						var_dump( $originalCourseTypeToAverage , $e );
						die;
					}
				} ,
				function ( $submittedCourseTypeToAverage ) {
					
					//Handling the Submitted Form Data.
					try {
						if( $submittedCourseTypeToAverage === null ) {
							return serialize( [ ] );
						}
						return serialize( $submittedCourseTypeToAverage );
					} catch( \Exception $e ) {
						var_dump( $submittedCourseTypeToAverage , $e );
						die;
					}
				}
			) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function setDefaultOptions( OptionsResolverInterface $resolver ) {

		$resolver->setDefaults( [
			'data_class' => 'IIAB\MagnetBundle\Entity\Eligibility' ,
		] );
	}

	public function getName() {

		return 'eligibility_settings';
	}


}