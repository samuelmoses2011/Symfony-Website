<?php
/**
 * Company: Image In A Box
 * Date: 2/2/15
 * Time: 12:00 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Service;

use Doctrine\ORM\EntityManager;
use IIAB\MagnetBundle\Entity\OpenEnrollment;
use IIAB\MagnetBundle\Entity\Student;
use IIAB\MagnetBundle\Entity\Submission;

class ValidateSiblingService {

	/** @var EntityManager */
	private $emLookup;

	function __construct( EntityManager $emLookup ) {

		$this->emLookup = $emLookup;
	}

	/**
	 * Scans for any sibling data and returns an multi-dimension array.
	 *
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return array
	 */
	public function getInvalidSiblings( OpenEnrollment $openEnrollment = null ) {

		$siblingSubmissions = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionData' )->createQueryBuilder( 'd' )
			->where( 'd.metaValue is not null ' );
		if( $openEnrollment != null ) {
			$siblingSubmissions->leftJoin( 'd.submission' , 's' )->andWhere( 's.openEnrollment = :enrollment' )->setParameter( 'enrollment' , $openEnrollment );
		}

		$siblingSubmissions = $siblingSubmissions->getQuery()->getResult();

		$invalidSiblings = array();
		if( count( $siblingSubmissions ) > 0 ) {

			foreach( $siblingSubmissions as $submissionData ) {

				if( $submissionData->getMetaKey() == 'First Choice Sibling ID' || $submissionData->getMetaKey() == 'Second Choice Sibling ID' || $submissionData->getMetaKey() == 'Third Choice Sibling ID' ) {
					//Checking Sibling ID
					$siblingID = $submissionData->getMetaValue();
					$siblingData = $this->findStudentByStateID( $siblingID );

					//If the results is null, it means the sibling ID was not found.
					if( $siblingData == null ) {
						if( !isset( $invalidSiblings[$submissionData->getMetaKey()] ) ) {
							$invalidSiblings[$submissionData->getMetaKey()] = array();
						}
						$invalidSiblings[$submissionData->getMetaKey()][$submissionData->getSubmission()->getId()] = $submissionData->getSubmission();
					} else {
						//Need to ensure the school is the same at the schools that the data is in.
						//Example First choice Sibling ID Current School must be First Choice of Submission.

						$siblingCurrentSchool = strtoupper( trim( $siblingData->getCurrentSchool() ) );

						$applyingForSchool = '';
						switch( $submissionData->getMetaKey() ) {

							case 'First Choice Sibling ID':
								$applyingForSchool = $submissionData->getSubmission()->getFirstChoice()->getName();
								break;

							case 'Second Choice Sibling ID':
								$applyingForSchool = $submissionData->getSubmission()->getSecondChoice()->getName();
								break;

							case 'Third Choice Sibling ID':
								$applyingForSchool = $submissionData->getSubmission()->getThirdChoice()->getName();
								break;
						}
						$applyingForSchool = strtoupper( trim( $applyingForSchool ) );

						if( $applyingForSchool == 'ACADEMY FOR SCIENCE AND FOREIGN LANGUAGE' ) {
							if( $siblingCurrentSchool != 'ACADEMY FOR SCIENCE & FOREIGN LANG.' ) {
								if( !is_array( $invalidSiblings[$submissionData->getMetaKey()] ) ) {
									$invalidSiblings[$submissionData->getMetaKey()] = array();
								}
								$invalidSiblings[$submissionData->getMetaKey()][$submissionData->getSubmission()->getId()] = $submissionData->getSubmission();
							}
						} elseif(
							$applyingForSchool == 'COLUMBIA HIGH SCHOOL MYP' ||
							$applyingForSchool == 'COLUMBIA HIGH SCHOOL IBCP' ||
							$applyingForSchool == 'COLUMBIA HIGH SCHOOL DP' ) {
							if( $siblingCurrentSchool != 'COLUMBIA HIGH SCHOOL' ) {
								if( !is_array( $invalidSiblings[$submissionData->getMetaKey()] ) ) {
									$invalidSiblings[$submissionData->getMetaKey()] = array();
								}
								$invalidSiblings[$submissionData->getMetaKey()][$submissionData->getSubmission()->getId()] = $submissionData->getSubmission();
							}
						} elseif(
							$applyingForSchool == 'LEE SCHOOL OF CREATIVE AND PERFORMING ARTS - DANCE' ||
							$applyingForSchool == 'LEE SCHOOL OF CREATIVE AND PERFORMING ARTS - ORCHESTRA' ||
							$applyingForSchool == 'LEE SCHOOL OF CREATIVE AND PERFORMING ARTS - PHOTOGRAPHY' ||
							$applyingForSchool == 'LEE SCHOOL OF CREATIVE AND PERFORMING ARTS - TECHNICAL THEATRE' ||
							$applyingForSchool == 'LEE SCHOOL OF CREATIVE AND PERFORMING ARTS - THEATRE PERFORMANCE' ||
							$applyingForSchool == 'LEE SCHOOL OF CREATIVE AND PERFORMING ARTS - VIDEO/BROADCAST JOURNALISM' ||
							$applyingForSchool == 'LEE SCHOOL OF CREATIVE AND PERFORMING ARTS - VISUAL ART' ||
							$applyingForSchool == 'LEE SCHOOL OF CREATIVE AND PERFORMING ARTS - VOCAL PERFORMANCE' ||
							$applyingForSchool == 'LEE SCHOOL OF CREATIVE AND PERFORMING ARTS - CREATIVE WRITING') {
							if( $siblingCurrentSchool != 'LEE HIGH SCHOOL' ) {
								if( !is_array( $invalidSiblings[$submissionData->getMetaKey()] ) ) {
									$invalidSiblings[$submissionData->getMetaKey()] = array();
								}
								$invalidSiblings[$submissionData->getMetaKey()][$submissionData->getSubmission()->getId()] = $submissionData->getSubmission();
							}
						} elseif( $applyingForSchool == 'J.O. JOHNSON LAW ACADEMY' ) {
							if( $siblingCurrentSchool != 'JOHNSON HIGH SCHOOL' ) {
								if( !is_array( $invalidSiblings[$submissionData->getMetaKey()] ) ) {
									$invalidSiblings[$submissionData->getMetaKey()] = array();
								}
								$invalidSiblings[$submissionData->getMetaKey()][$submissionData->getSubmission()->getId()] = $submissionData->getSubmission();
							}
						} elseif( $applyingForSchool == 'WILLIAMS TECHNOLOGY MIDDLE SCHOOL' ) {
							if( $siblingCurrentSchool != 'WILLIAMS MIDDLE SCHOOL' ) {
								if( !is_array( $invalidSiblings[$submissionData->getMetaKey()] ) ) {
									$invalidSiblings[$submissionData->getMetaKey()] = array();
								}
								$invalidSiblings[$submissionData->getMetaKey()][$submissionData->getSubmission()->getId()] = $submissionData->getSubmission();
							}
						} elseif( $applyingForSchool != $siblingCurrentSchool ) {
							if( !is_array( $invalidSiblings[$submissionData->getMetaKey()] ) ) {
								$invalidSiblings[$submissionData->getMetaKey()] = array();
							}
							$invalidSiblings[$submissionData->getMetaKey()][$submissionData->getSubmission()->getId()] = $submissionData->getSubmission();
						}
					}
				}
			}
		}

		return $invalidSiblings;
	}

	/**
	 * Validate a Sibling ID by using either a Submission or sibling string to test.
	 * This will only valid Submission or Sibling and will return a boolean value.
	 *
	 * @param Submission $submission
	 * @param string     $siblingID
	 * @deprecated deprecated since version 1.10.3 use validateSiblingAttendsSchool instead.
	 *
	 * @return bool
	 */
	public function validateSibling( Submission $submission = null , $siblingID = '' ) {

		$passValidation = false;
		$testStateIDValue = null;

		if( $submission != null ) {

			$testStateIDValue = $submission->getFirstSiblingValue();
			if( !empty( $testStateIDValue ) ) {
				$siblingData = $this->findStudentByStateID( $testStateIDValue );

				//If the results is not null, it means the sibling ID was found.
				if( $siblingData != null ) {
					$passValidation = true;
				}
				$testStateIDValue = null;
			}
			$testStateIDValue = $submission->getSecondSiblingValue();
			if( !empty( $testStateIDValue ) ) {

				$siblingData = $this->findStudentByStateID( $testStateIDValue );

				//If the results is not null, it means the sibling ID was found.
				if( $siblingData != null ) {
					$passValidation = true;
				}
				$testStateIDValue = null;
			}
			$testStateIDValue = $submission->getThirdSiblingValue();
			if( !empty( $testStateIDValue ) ) {

				$siblingData = $this->findStudentByStateID( $testStateIDValue );

				//If the results is not null, it means the sibling ID was found.
				if( $siblingData != null ) {
					$passValidation = true;
				}
				$testStateIDValue = null;
			}
		} else if( !empty( $siblingID ) ) {
			//Testing the sibling ID that was passed in. This will be used to validate a Sibling ID on submission.
			$siblingData = $this->findStudentByStateID( $siblingID );

			//If the results is not null, it means the sibling ID was found.
			if( $siblingData != null ) {
				$passValidation = true;
			}
			$siblingID = '';
		}

		return $passValidation;

	}

	/**
	 * Find any student by State ID
	 *
	 * @param int $stateID
	 *
	 * @return Student|null
	 */
	private function findStudentByStateID( $stateID = 0 ) {

		$student = $this->emLookup->getRepository( 'IIABMagnetBundle:Student' )->findOneBy( array(
			'stateID' => $stateID
		) );
		return $student;
	}

    /**
     * Check if Find a student (by State ID) attends a specific school (by Magnet School ID)
     *
     * @param int $stateID
     * @param int $schoolID
     *
     * @return bool
     */
	public function validateSiblingAttendsSchool( $stateID = 0 , $schoolID = 0 ) {

		$student = $this->findStudentByStateID( $stateID );

		$selectedSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findOneBy( [
			'id' => $schoolID
		] );


		if( $student == null ) {
			return false;
		}
		if( $selectedSchool == null ) {
			return false;
		}

		$matched = ( strcasecmp( $selectedSchool->getName() , $student->getCurrentSchool() ) === 0 );

		if( false == $matched ) {
			//Not matched yet, Try matching with Program Alt Name.
			$program = $selectedSchool->getProgram();

			$matched = ( strcasecmp( $program->getName() , $student->getCurrentSchool() ) === 0 );

			if( false == $matched ) {

				$iNow_names = [];
				foreach( $program->getINowNames() as $iNowName){
					$iNow_names[] = strtolower( $iNowName->getINowName() );
				}
				$matched = in_array( strtolower( $student->getCurrentSchool() ), $iNow_names );
			}

			$program = null;
		}

		$student = null;
		$selectedSchool = null;

		return $matched;
	}
}