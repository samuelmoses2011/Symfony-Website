<?php
/**
 * Company: Image In A Box
 * Date: 12/23/14
 * Time: 12:36 PM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class StartApplicationType extends AbstractType {

	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder
			->add( 'student_status' , 'choice' , array(
				'label' => 'form.field.studentstatus' , //'Student Status' ,
				// 'choices' => array(
				// 	'' => 'form.option.choose' , //'Choose an option' ,
				// 	'current' => 'form.option.enrolled' , //'Enrolled MPS Student (PreK - 11th grade)' ,
				// 	'new' => 'form.option.new.student' , //'New MPS Student' ,
				// ) ,
				'choices' => array(
					'form.option.choose' => '' , //'Choose an option' ,
					'form.option.enrolled' => 'current' , //'Enrolled MPS Student (PreK - 11th grade)' ,
					'form.option.new.student' => 'new' , //'New MPS Student' ,
				) ,
				'constraints' => array(
					new NotBlank()
				)
			) )
			->add( 'step' , 'hidden' , array(
				'attr' => array(
					'value' => 0
				)
			) )
			->add( 'submit' , 'submit' , array(
				'label' => 'form.next' , //'Next',
				'attr' => array(
					'class' => 'right radius',
				),
			) );

	}

	public function getName() {

		return 'start';
	}

}