<?php

namespace IIAB\MagnetBundle\Service;

use LeanFrog\SharedDataBundle\Entity\StudentData;
use Doctrine\Common\Persistence\ObjectManager;

class GetStudentService {

	/** @var array */
	private $formData;

	/** @var ObjectManager */
	private $sharedEm;
  	private $magnetEm;

	public function __construct( array $formData , $doctrine ) {

		$this->formData = $formData;
		$this->sharedEm = $doctrine->getManager('shared');
		$this->magnetEm = $doctrine->getManager();
	}


	/**
	 * Get the current student if in the database.
	 *
	 * @return bool|array
	 */
	public function getStudent() {

		$studentFound = $this->sharedEm->getRepository( 'lfSharedDataBundle:Student' )->findOneBy( array(
			'birthday' => $this->formData['dob'] ,
			'stateID' => $this->formData['stateID']
		) );

		if( $studentFound == null ) {
			return false;
		}

		$studentRace = $this->magnetEm->getRepository( 'IIABMagnetBundle:Race' )->findOneBy([
			'race' => $studentFound->getRace()
		]);
		if( $studentFound->getGradeLevel() >= 96 && $studentFound->getGradeLevel() < 99 ) {
			$currentGrade = '98';
			$nextGrade = '99';
		} elseif( $studentFound->getGradeLevel() == 99 ) {
			$currentGrade = '99';
			$nextGrade = '0';
		} elseif( $studentFound->getGradeLevel() == 0 ) {
			$currentGrade = 0;
			$nextGrade = 1;
		} else {
			$currentGrade = $studentFound->getGradeLevel();
			$nextGrade = $currentGrade + 1;
		}

		$student = array(
			'stateID' => $studentFound->getStateID(),
			'first_name' => $studentFound->getFirstName() ,
			'last_name' => $studentFound->getLastName() ,
			'dob' => $studentFound->getBirthday() ,
			'race' => $studentRace ,
			'gender' => $studentFound->getGender(),
			'current_school' => $studentFound->getCurrentSchool() ,
			'current_grade' => $currentGrade ,
			'next_grade' => $nextGrade ,
			'address' => $studentFound->getAddress() ,
			'city' => $studentFound->getCity() ,
			'state' => $studentFound->getState(),
			'zip' => $studentFound->getZip() ,
			'studentEmail' => $studentFound->getEmail(),
		);

		$student_data = $studentFound->getAdditionalData();

		$teacher_keys = ($nextGrade <= 8)
			? ['homeroom_teacher_name']
			: [ 'math_teacher_name', 'english_teacher_name', 'counselor_name' ];

		foreach( $student_data as $datum ){
			if( in_array($datum->getMetaKey(), $teacher_keys ) ){
				$student[ $datum->getMetaKey() ] = $datum->getMetaValue();
			}
		}

		return $student;
	}

	/**
	 * Get all the students grades.
	 *
	 * @return array
	 */
	public function getGrades( $_the_state_id ) {

		$_student_found = $this->sharedEm->getRepository( 'lfSharedDataBundle:Student' )->findOneBy( array(
			'stateID' => $_the_state_id
		) );

		if( $_student_found == null ) {
			return [];
		}

		$grades = $_student_found->getGrades();

		return $grades;
	}

	/**
	 * Get all the students additional data.
	 *
	 * @return array
	 */
	public function getAdditionalData( $_the_state_id ){

		$_student_found = $this->sharedEm->getRepository( 'lfSharedDataBundle:Student' )->findOneBy( array(
			'stateID' => $_the_state_id
		) );

		if( $_student_found == null ) {
			return [];
		}

		$additional_data = $_student_found->getAdditionalData();

		$data_array =[];
		foreach( $additional_data as $datum ){

			$meta_key = $datum->getMetaKey();

			if( strpos( $meta_key , '_teacher' ) ){

				$datum->setMetaKey( $meta_key );

				$_teacher_found = $this->sharedEm->getRepository( 'lfSharedDataBundle:Teacher' )->findOneBy( array(
					'email' => $datum->getMetaValue()
				) );

				if( !empty( $_teacher_found ) ){
					$teacher_name = new StudentData();

					$teacher_name->setMetaKey( $meta_key.'_name' );
					$teacher_name->setMetaValue( $_teacher_found->getFirstName() .' '. $_teacher_found->getLastName() );
					$teacher_name->setStudent( $_student_found );

					$data_array[] = $teacher_name;
				}
			}
			$data_array[] = $datum;
		}

		return $data_array;
	}
}
