<?php
/**
 * Company: Image In A Box
 * Date: 1/5/15
 * Time: 4:09 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;

class SubmissionGradeAdmin extends AbstractAdmin {

	protected function configureFormFields( FormMapper $form ) {

        $parent = false;
        if($this->hasParentFieldDescription()) {
            $parent = $this->getParentFieldDescription()->getAdmin()->getSubject();
        }

        $object = $this->getSubject();

		$courses = array_flip([
		    'english' => 'English' ,
            'math' => 'Math' ,
            'science' => 'Science' ,
            'social' => 'Social Studies',
            'reading' => 'Reading'
        ]);

		$academicYears = array_flip([
            '0' => $parent->getOpenEnrollment()->getOffsetYear(0),
            '-1' => $parent->getOpenEnrollment()->getOffsetYear(-1)
        ]);

		$goodTerms = array_flip([
		    'semester 1' => 'Semester 1' ,
            'semester 2' => 'Semester 2' ,
            '1st 9 weeks' => '1st 9 Weeks',
            '2nd 9 weeks' => '2nd 9 Weeks',
            '3rd 9 weeks' => '3rd 9 Weeks',
            '4th 9 weeks' => '4th 9 Weeks',
        ]);

		$academicTerms = $this->getConfigurationPool()->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:SubmissionGrade')->createQueryBuilder('g')
			->select('g.academicTerm')
			->distinct( true )
			->orderBy('g.academicTerm' , 'ASC')
			->where('g.academicTerm NOT IN (:terms)')
			->setParameter( 'terms' , array_values( $goodTerms ) )
			->getQuery()
			->getResult();

		$academicTermsArray = array();
		foreach( $academicTerms as $academicTerm ) {
			$academicTermString = $academicTerm['academicTerm'];
			$academicTermsArray[$academicTermString] = $academicTermString;
		}
		$academicTermsArray = array_merge( $goodTerms, $academicTermsArray );

		$form
			->add( 'academicYear', 'choice', array(
			   'placeholder' => 'Choose an option',
                'choices' => $academicYears
            ) )
			->add( 'academicTerm' , 'choice' , array(
				'placeholder' => 'Choose an option',
				'choices' => $academicTermsArray
			) )
			->add( 'courseType' , 'choice' , array(
				'choices' => $courses,
				'placeholder' => 'Choose an option',
				'label' => 'Course Type'
			) )
//			->add( 'courseType' , null , array(
//				'label' => 'Core Name'
//			) )
            ->add( 'sectionNumber' , null , array(
                'label' => 'Section Number',
                'attr' => [ 'style' => 'text-transform: capitalize;' ]
            ) )
			->add( 'courseName' , null , array(
				'label' => 'Class Name',
                'attr' => [ 'style' => 'text-transform: capitalize;' ]
			) )
			->add( 'numericGrade' , null , array(
				'label' => 'Grade (0-100)'
			) )
            ->add( 'calculationMarker' , 'text' , array(
                'label' => 'Use to Calculate GPA',
                //'placeholder' => '',
                //'choices' => [0 => 'Do Not Use', 1 => 'Use'],
                'required' => false,
                'disabled' => true,
                'mapped' => false,
                'data' => ( is_object ( $object ) && $object->getUseInCalculations() == 1 ) ? 'Use' : '',
            ) )
		;
	}

	protected function configureListFields( ListMapper $list ) {

		$list
			->add( 'academicYear' )
			->add( 'academicTerm' )
			->add( 'courseTypeID' )
			->add( 'courseType' )
			->add( 'courseName' )
			->add( 'numericGrade' )
		;
	}


}