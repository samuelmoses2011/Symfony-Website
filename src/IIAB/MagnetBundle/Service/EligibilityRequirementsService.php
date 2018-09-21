<?php

namespace IIAB\MagnetBundle\Service;

use Doctrine\ORM\EntityManager;
use IIAB\MagnetBundle\Entity\Eligibility;
use IIAB\MagnetBundle\Entity\MagnetSchool;
use IIAB\MagnetBundle\Entity\Program;
use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Service\OrdinalService;

class EligibilityRequirementsService {


	/** @var array */
	private $student;

	/** @var EntityManager */
	private $emLookup;

    /** @var array */
	private $eligibilityFieldObjects = [];

	private $ordinal;

    private $eligibility_fields = [];

	public function __construct( EntityManager $emLookup ) {

		$this->emLookup = $emLookup;
		$this->ordinal = new OrdinalService();
        $this->eligibility_fields = MYPICK_CONFIG['eligibility_fields'];
	}

	/**
	 * @return EntityManager
	 */
	public function getEmLookup() {

		return $this->emLookup;
	}

	/**
	 * @param EntityManager $emLookup
	 */
	public function setEmLookup( $emLookup ) {

		$this->emLookup = $emLookup;
	}

    public function getEligibilitySubmissionDataKeysForSchool( MagnetSchool $school ){

        $number_of_choices = ( $school->getGrade() < 6 ) ? 2 : 3;
        $focus_depth = $school->getProgram()->getAdditionalData( 'focus_placement' );
        $focus_depth = ( isset( $focus_depth[0] ) ) ? $this->ordinal->getIndex( $focus_depth[0]->getMetaValue() ) : 0;
        $focus_depth = ( $focus_depth ) ? $focus_depth : 3;

        $eligibility_fields = $this->getEligibilityFieldIDs();
        $required_keys = [];

        foreach(  $eligibility_fields as $field => $settings ){

            if( $school->doesRequire( 'combined_'. $field ) ){

                if( $settings['by_choice'] ){

                    for ($choice_index = 1; $choice_index <= $number_of_choices; $choice_index++) {
                        $required_keys[] = $this->ordinal->getOrdinalText( $choice_index ) .'_choice_combined_'. $field;
                    }
                } else {
                    $required_keys[] = 'combined_'. $field;
                }

            } else if( $school->doesRequire( $field ) ) {

                if( $settings['by_choice'] ){

                    if( $settings['by_focus'] ){
                        for ($choice_index = 1; $choice_index <= $number_of_choices; $choice_index++) {

                            for( $focus_index = 1; $focus_index <= $focus_depth; $focus_index++ ){
                                $required_keys[] = $this->ordinal->getOrdinalText( $choice_index ) .'_choice_'.$this->ordinal->getOrdinalText( $focus_index ) .'_choice_'. $field;
                            }
                        }
                    } else {
                        for ($choice_index = 1; $choice_index <= $number_of_choices; $choice_index++) {
                            $required_keys[] = $this->ordinal->getOrdinalText( $choice_index ) .'_choice_'. $field;
                        }
                    }

                } else {
                    $required_keys[] = $field;
                }
            }

        }
    return $required_keys;
    }

    public function doesSubmissionHaveAllEligibility( Submission $submission, MagnetSchool $school, $focus = '' ){

	    $id = 0;
        $dump = ( $submission->getId() == $id);

        $capacity_by_focus = $school->isCapacityByFocus();

        $school_chosen = '';
        if( $submission->getFirstChoice() == $school ){
            $school_chosen = 'First';
        } else if( $submission->getSecondChoice() == $school ){
            $school_chosen = 'Second';
        } else if( $submission->getThirdChoice() == $school ){
            $school_chosen = 'Third';
        }

        if( !$school_chosen ){
            if( $dump ){ var_dump('Not Chosen'); die; }
            return false;
        }

        $focus_chosen = 'first';
        if( $submission->{'get'. ucfirst( $school_chosen ) .'ChoiceFirstChoiceFocus' }() == $focus ){
            $focus_chosen = 'first';
        } else if( $submission->{'get'. ucfirst( $school_chosen ) .'ChoiceSecondChoiceFocus' }() == $focus ){
            $focus_chosen = 'second';
        } else if( $submission->{'get'. ucfirst( $school_chosen ) .'ChoiceThirdChoiceFocus' }() == $focus ){
            $focus_chosen = 'third';
        }

        if( $capacity_by_focus && empty( $submission->{'get'.$school_chosen.'Choice'.ucfirst( $focus_chosen).'ChoiceFocus'}() ) ){
            if( $dump ){ var_dump('No Focus');  die;}
            return false;
        }

        $focus_description = $school->getProgram()->getAdditionalData('focus_description');
        $focus_description = ( isset( $focus_description[0] ) ) ? $focus_description[0]->getMetaValue() : false ;

        if( $capacity_by_focus
            && !$school->doesRequire( 'combined_audition_score' )
            ){
            $audition_score = $submission->getAdditionalDataByKey( strtolower($school_chosen).'_choice_'. strtolower( $focus_chosen ) .'_choice_focus_score' );
            $audition_score = ( empty( $audition_score ) ) ? 0 : intval( $audition_score->getMetaValue() );

            $required_score = $this->emLookup->getRepository('IIABMagnetBundle:Eligibility')->findOneBy( [
                'program' => $school->getProgram(),
                'criteriaType' => 'audition_score'
            ] );

            if( is_null( $required_score ) ){
                $required_score = $this->emLookup->getRepository('IIABMagnetBundle:Eligibility')->findOneBy( [
                    'magnetSchool' => $school,
                    'criteriaType' => 'audition_score'
                ] );
            }

            if( $required_score != null ) {

                $passing_threshold = (!empty($required_score) && $required_score->getPassingThreshold()) ? intval($required_score->getPassingThreshold()) : 1;

                if ($passing_threshold < 99 &&
                    $audition_score < $passing_threshold) {
                    if ($dump) {
                        var_dump($required_score);
                        var_dump('Bad Audition');
                        die;
                    }
                    return false;
                }
            }
        }

	    $eligibility_fields = $this->getEligibilityFieldIDs();

	    $program_criteria = $this->emLookup->getRepository('IIABMagnetBundle:Eligibility')->findBy( [
            'program' => $school->getProgram(),
            'criteriaType' => array_keys( $eligibility_fields )
        ] );

	    foreach( $program_criteria as $eligibility){

	        if( $eligibility->getCriteriaType() != 'audition_score'
                || $eligibility->getCourseTitle() == 'combined'
            ) {

                $data_keys = [];
                if( isset( $eligibility_fields[ $eligibility->getCriteriaType() ]['info_field'] )
                    && $eligibility_fields[ $eligibility->getCriteriaType() ]['info_field'] !== false
                ){

                    foreach( array_keys( $eligibility_fields[ $eligibility->getCriteriaType() ]['info_field'] )
                        as $key ){
                            $data_keys[] = $key;
                    }
                } else {
                    $data_key = ($eligibility->getCourseTitle() == 'combined') ? 'combined_' : '';
                    $data_key .= $eligibility->getCriteriaType();
                    $data_keys[] = $data_key;
                }

                if ($eligibility_fields[$eligibility->getCriteriaType()]['by_choice']) {
                    foreach( $data_keys as $index => $data_key ){
                        $data_keys[$index] = strtolower($school_chosen) . '_choice_' . $data_key;
                    }
                }

                if( $eligibility->getCriteriaType() == 'recommendations' ){
                    foreach( $data_keys as $index => $data_key ){
                        $data_keys[$index] = $data_key .'_overall_recommendation';
                    }
                }

                foreach( $data_keys as $data_key ){
                    $data_check = (
                        !empty( $submission->getAdditionalDataByKey( $data_key ) )
                        && $submission->getAdditionalDataByKey( $data_key )->getMetaValue() !== null
                    ) ? $submission->getAdditionalDataByKey( $data_key )->getMetaValue() : 0;

                    $maybe_exempt = $submission->getAdditionalDataByKey( $data_key.'_exempt' );
                    $maybe_exempt = ( !empty( $maybe_exempt ) && $maybe_exempt->getmetaValue() );


                    if ( $eligibility->getPassingThreshold() != 'ignore'
                         && !$maybe_exempt
                         && $data_check < $eligibility->getPassingThreshold()
                    ) {
                        if ($dump) {
                            var_dump('program ' . $data_key .' '. $data_check .' '. $eligibility->getPassingThreshold() );
                            die;
                        }
                        return false;
                    }
                }
            }
        }

        $school_criteria = $this->emLookup->getRepository('IIABMagnetBundle:Eligibility')->findBy( [
            'magnetSchool' => $school,
            'criteriaType' => array_keys( $eligibility_fields )
        ] );

        foreach( $school_criteria as $eligibility){

            if( $eligibility->getCriteriaType() != 'audition_score'
                || $eligibility->getCourseTitle() == 'combined'
            ){

                $data_keys = [];
                if( isset( $eligibility_fields[ $eligibility->getCriteriaType() ]['info_field'] )
                    && $eligibility_fields[ $eligibility->getCriteriaType() ]['info_field'] !== false
                ){

                    foreach( array_keys( $eligibility_fields[ $eligibility->getCriteriaType() ]['info_field'] )
                        as $key ){
                            $data_keys[] = $key;
                    }
                } else {
                    $data_key = ($eligibility->getCourseTitle() == 'combined') ? 'combined_' : '';
                    $data_key .= $eligibility->getCriteriaType();
                    $data_keys[] = $data_key;
                }

                if ($eligibility_fields[$eligibility->getCriteriaType()]['by_choice']) {

                    foreach( $data_keys as $index => $data_key ){
                        if ($submission->getFirstChoice()->getId() == $school->getId()) {
                            $data_keys[$index] = 'first_choice_' . $data_key;
                        } else if ($submission->getSecondChoice()->getId() == $school->getId()) {
                            $data_keys[$index] = 'second_choice_' . $data_key;
                        } else if ($submission->getThirdChoice()->getId() == $school->getId()) {
                            $data_keys[$index] = 'third_choice_' . $data_key;
                        }
                    }
                }

                foreach( $data_keys as $data_key ){
                    $data_check = (
                        !empty( $submission->getAdditionalDataByKey( $data_key ) )
                        && $submission->getAdditionalDataByKey( $data_key )->getMetaValue() !== null
                    ) ? $submission->getAdditionalDataByKey( $data_key )->getMetaValue() : 0;

                    if ($eligibility->getPassingThreshold() < 99 && $data_check < $eligibility->getPassingThreshold() ) {
                        if ($dump) {
                            var_dump('school ' . $data_key);
                            die;
                        }
                        return false;
                    }
                }
            }
        }

        return true;
    }

	/**
	 * @param array $student
	 * @param MagnetSchool $school
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function doesStudentPassRequirements( array $student , MagnetSchool $school ) {

		$this->student = $student;

		$findAnyEligibilityforSchool = $this->emLookup->getRepository('IIABMagnetBundle:Eligibility')->findBy( array(
			'magnetSchool' => $school,
		) );

		//Check to see if the SchoolName/Grade has eligibility requirements

		if( $findAnyEligibilityforSchool == null ) {
			//if no eligibility requirements
			return array( true , 0 , '' );
		} else {
			//if there are eligibility requirements, look up $schoolName's eligibility criteria from database
			//loop through the list of eligibility criteria

			//Default Returns
			$passGradeArray = array();
			$passRequirements = true;
			$missingGrades = false;
			$passCourseTitle = array();
			$eligibilityCheck = array();

			foreach( $findAnyEligibilityforSchool as $requirement ) {

                $criteria = $requirement->getCriteriaType();

                //skip eligibility fields
                $eligibilityFields = $this->getEligibilityFieldIDs();
                if( in_array( strtolower( $criteria ), array_keys( $eligibilityFields ) ) ){
                      continue;
                }

				if( empty( $criteria ) ) {
					//Criteria is Blank so just continue.
					continue;
				}


				list( $passRequirementReturn , $passGradeArray[] , $passCourseTitle[] , $eligibilityCheck[] , $missingGrades ) = $this->checkStudentAgainstCriteria( $requirement , $student['submissionID'] );

				if( $passRequirementReturn == false ) {
					$passRequirements = false;
					//return array( false , $passGrade , $passCourseTitle , $eligibilityCheck );
				}
			}
			return array( $passRequirements , $passGradeArray , $passCourseTitle , $eligibilityCheck , $missingGrades );
		}
	}

	public function doesSubmissionPassRequirements( Submission $submission , MagnetSchool $school ) {

		return $this->doesStudentPassRequirements( [ 'submissionID' => $submission->getId() ] , $school );
	}

	/**
	 * @param Submission   $submission
	 * @param MagnetSchool $magnetSchool
	 *
	 * @return array
	 */
	public function getAcademicYearsAndTerms( Submission $submission , MagnetSchool $magnetSchool ) {

		list( $uniqueCourseIDs , $numberOfSemesters ) = $this->getEligibilityCourseIDs( $magnetSchool );


		//TODO: Need to make this a setup requirement.
		if ($numberOfSemesters % 2 == 0) {
			//Even number of Semesters
			$minYear = ( date('Y') - ( ( $numberOfSemesters / 2 ) - 1 ) );
		} else {
			//Odd number of Semesters
			$minYear = ( date('Y') - ceil( $numberOfSemesters / 2 ) );
		}

		$years = $this->getAcademicYears( $submission , $numberOfSemesters );

		//Semesters Query:
		//SELECT `academicYear`, `academicTerm` FROM `submissiongrade` WHERE `submission_id` = ### GROUP BY `academicYear`, `academicTerm` ORDER BY `academicYear` DESC, `academicTerm` DESC LIMIT 0,1
		$academicYearsAndTerms = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
			->select( 'g.academicYear' )
			->addSelect( 'g.academicTerm' )
			->where( 'g.submission = :submission' )
			//->andWhere( 'UPPER(g.academicTerm) IN (:semester)' )
			->andWhere( 'g.academicYear IN (:years)' )
			->andWhere( 'g.academicYear >= :minYear' )
			->groupBy( 'g.academicYear' )
			->addGroupBy( 'g.academicTerm' )
			->orderBy( 'g.academicYear' , 'DESC' )
			->addOrderBy( 'g.academicTerm' , 'DESC' )
			->setParameters( array(
				'submission' => $submission ,
				//'semester' => [ 'SEMESTER 1' , 'SEMESTER 2' ] ,
				'years' => $years ,
				'minYear' => $minYear
			) )
			->getQuery()
			->getResult();

		return $academicYearsAndTerms;
	}

	/**
	 * @param Submission $submission
	 * @param int        $numberOfSemesters
	 *
	 * @return array
	 */
	public function getAcademicYears( Submission $submission , $numberOfSemesters = 0 ) {

		$numberOfYears = 0;
		//TODO: Need to make this a setup requirement.
		if ($numberOfSemesters % 2 == 0) {
			//Even number of Semesters
			$numberOfYears = ( $numberOfSemesters / 2 );
		} else {
			//Odd number of Semesters
			$numberOfYears = ceil( $numberOfSemesters / 2 );
		}

		//Semesters Query:
		//SELECT `academicYear`, `academicTerm` FROM `submissiongrade` WHERE `submission_id` = ### GROUP BY `academicYear`, `academicTerm` ORDER BY `academicYear` DESC, `academicTerm` DESC LIMIT 0,1
		$academicYears = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
			->select( 'g.academicYear' )
			->where( 'g.submission = :submission' )
			->groupBy( 'g.academicYear' )
			->orderBy( 'g.academicYear' , 'DESC' )
			->setParameters( array(
				'submission' => $submission ,
			) )
			->setMaxResults( $numberOfYears )
			->getQuery()
			->getResult();

		$resultYears = [ ];

		foreach( $academicYears as $years ) {
			$resultYears[] = $years['academicYear'];
		}

		return $resultYears;
	}

	/**
	 *
	 * Returns the Unique CourseTypeIDs for the MagnetSchool Eligibility.
	 *
	 * @param MagnetSchool $school
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getEligibilityCourseIDs( MagnetSchool $school ) {

		$uniqueCourseIDs = array();
		$numberOfSemesters = 0;

		$eligibilities = $this->emLookup->getRepository('IIABMagnetBundle:Eligibility')->findBy( array(
			'magnetSchool' => $school,
		) );

		$eligibility_fields = $this->getEligibilityFieldIDs();

		foreach( $eligibilities as $eligibility ) {

			$criteria = $eligibility->getCriteriaType();

			if( isset( $eligibility_fields[$criteria] ) ){
			    continue;
            }

			if( empty( $criteria ) ) {
				//Criteria is Blank so just continue.
				continue;
			}

			$whatToCheck = strtoupper( $criteria );
			if( $eligibility->getNumberofSemesters() >= $numberOfSemesters ) {
				$numberOfSemesters = $eligibility->getNumberofSemesters();
			}

			switch( $whatToCheck ) {

				case 'GPA CHECK';
					$courseTypeToAverage = unserialize( $eligibility->getCourseTypeToAverage() );
					foreach( $courseTypeToAverage as $courseTypeID ) {
						if( ! isset( $uniqueCourseIDs[$courseTypeID] ) ) {
							$uniqueCourseIDs[$courseTypeID] = 1;
						}
					}
					break;

				case 'COURSE TITLE CHECK';
					$courseID = $eligibility->getCourseTypeToCheckTitle();
					if( ! isset( $uniqueCourseIDs[$courseID] ) ) {
						$uniqueCourseIDs[$courseID] = 1;
					}
					$courseID = null;
					break;

				default:
					throw new \Exception( 'Eligibility Requirement error. Criteria Type of "' . $whatToCheck . '" is not defined. Please fix this.');
					break;

			}
		}

		return array( array_keys( $uniqueCourseIDs ) , $numberOfSemesters );

	}

	/**
	 * @param Eligibility $eligibility
	 * @param int   $submissionID
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function checkStudentAgainstCriteria( Eligibility $eligibility , $submissionID ) {

		$responseBoolean = false;
		$missingGrades = false;
		$responseGrade = 0;
		$responseCourseTitle = '';
		$eligibilityCheck = '';

		$whatCheck = strtoupper( $eligibility->getCriteriaType() );

		switch( $whatCheck ) {

			//if criteriaType is GPA check
			case 'GPA CHECK';
				//If $courseTypestoAvg has only one course type label (4,7,etc) in it
				$courseTypeToAverage = unserialize( $eligibility->getCourseTypeToAverage() ); //Un-serialize because the information is stored in an array and stored as serialized.
				$studentAverageOfGrades = 0;
				$studentAverageOfGradesCount = 0;
				$minNumberOfGrades = count( $courseTypeToAverage ) * $eligibility->getNumberofSemesters();
				$eligibilityCheck = 'GPA CHECK';

				$submission = $this->emLookup->getRepository('IIABMagnetBundle:Submission')->find($submissionID);

				$academicYearsAndTerms = $this->getAcademicYearsAndTerms( $submission , $eligibility->getMagnetSchool() );
				$academicYearsAndTermsFormatted = array(
					'years' => array(),
					'terms' => array(),
				);
				foreach( $academicYearsAndTerms as $academicYearsAndTerm ) {
					if( !in_array( $academicYearsAndTerm['academicYear'] , $academicYearsAndTermsFormatted['years'] ) ) {
						$academicYearsAndTermsFormatted['years'][] = strtoupper( $academicYearsAndTerm['academicYear'] );
					}
					if( !in_array( $academicYearsAndTerm['academicTerm'] , $academicYearsAndTermsFormatted['terms'] ) ) {
						$academicYearsAndTermsFormatted['terms'][] = strtoupper( $academicYearsAndTerm['academicTerm'] );
					}
				}

				//TODO: Need to make this a setup requirement.
				if( !in_array( date('Y') , $academicYearsAndTermsFormatted['years'] ) ) {
					$missingGrades = true;
				}

				if( !is_array( $courseTypeToAverage ) ) {
					throw new \Exception( 'Course Type To Average database value is not an array.' );
				}

				$semesterRequirements = [];

				foreach( $courseTypeToAverage as $courseTypeID ) {
					//Go back in $studentRecord for $courseType by the $numofSemesterstoAvg and sum up and divide by $numofSemesterstoAvg


					$grades = $this->emLookup->getRepository('IIABMagnetBundle:SubmissionGrade')->createQueryBuilder( 'g' )
						->where( 'g.submission = :submission' )
						->andWhere( 'g.courseTypeID = :courseID' )
						->andWhere( 'UPPER(g.academicYear) IN (:years)' )
						->andWhere( 'UPPER(g.academicTerm) IN (:terms)' )
						->orderBy( 'g.academicYear' , 'DESC' )
						->addOrderBy( 'g.academicTerm' , 'DESC' )
						->setParameters( array(
							'submission' => $this->student['submissionID'],
							'courseID' => $courseTypeID ,
							'years' => $academicYearsAndTermsFormatted['years'],
							'terms' => $academicYearsAndTermsFormatted['terms']
						) )
						->getQuery()
						->getResult()
					;
					if( count( $grades ) == 0 ) {
						$missingGrades = true;
						continue;
					}

					if( $grades != null ) {
						//sort $student grade info by course type, then break $student grade data into an array for each grade
						//then sort each grade data array by year and semester and go back in $studentRecord for $courseType by the $numofSemesterstoAvg and sum up and divide by $numofSemesterstoAvg

						//The grade average still needs to be the sum of all applicable core grades divided by the number of grades summed.
						/** @var \IIAB\MagnetBundle\Entity\SubmissionGrade $grade */
						foreach( $grades as $grade ) {
							$studentAverageOfGrades += $grade->getNumericGrade();
							$semesterRequirements[$grade->getAcademicYear()][$grade->getAcademicTerm()][] = $grade->getCourseTypeID();
							$semesterRequirements[$grade->getAcademicYear()][$grade->getAcademicTerm()] = array_unique( $semesterRequirements[$grade->getAcademicYear()][$grade->getAcademicTerm()] );
							$studentAverageOfGradesCount++;
						}
						//After loop through all the grades, average the record based on the number of required Semesters..
						//$courseAverageOfGrades = $courseAverageOfGrades / $eligibility->getNumberofSemesters();
						//then add the averaged CourseGrades back into the Main StudentAverageOfGrades, because there might be more courseIDs to check.
						//$studentAverageOfGrades += $courseAverageOfGrades;

					}
					//Reset to keep memory low.
					$grades = null;
				}

				//Validates only Semester 1 and Semester 2 for all four Course IDs
				foreach( $semesterRequirements as $year => $terms ) {
					foreach( $terms as $term => $courses ) {
						if( preg_match( '/(recovery|summer|middle)/i' , $term ) === 0 ) {
							foreach( $courseTypeToAverage as $courseTypeID ) {
								if( !in_array( $courseTypeID , $courses ) ) {
									$missingGrades = true;
								}
							}
						}
					}
				}

				if( count( $courseTypeToAverage ) > 0 && $studentAverageOfGrades > 0 ) {
					//The grade average still needs to be the sum of all applicable core grades divided by the number of grades summed.
					$studentAverageOfGrades = $studentAverageOfGrades / $studentAverageOfGradesCount;
					$responseGrade = $studentAverageOfGrades;
				}

				/*if( $studentAverageOfGradesCount < $minNumberOfGrades ) {
					$missingGrades = true;
				}*/

				//Check the average calculated above against the $avgThreshold
				if( $studentAverageOfGrades >= (float) $eligibility->getPassingThreshold() ) {
					//if greater than $avgThreshold
					//return true
					$responseBoolean = true;
				}

				//Memory control
				$studentAverageOfGradesCount = null;
				$studentAverageOfGrades = null;
				$courseTypeToAverage = null;

				break;

			//if criteriaType is course title check
			case 'COURSE TITLE CHECK';
				$eligibilityCheck = 'COURSE TITLE CHECK';

				//if the last semester's course name
				$lastCourseTitleToCheckAgainst = $this->emLookup->getRepository('IIABMagnetBundle:SubmissionGrade')->findBy( array(
					'submission' => $this->student['submissionID'],
					'courseTypeID' => $eligibility->getCourseTypeToCheckTitle(),
				) , array( 'academicYear' => 'DESC' , 'academicTerm' => 'DESC' ) , 1 );
				$responseCourseTitle = $eligibility->getCourseTitle();

				if( count( $lastCourseTitleToCheckAgainst ) == 1 ) {
					//Loop through because FindBy returns an Array and it will only loop once.
					foreach( $lastCourseTitleToCheckAgainst as $grade ) {

						switch( $eligibility->getComparison() ) {

							case "!=":
								if( strtoupper( $grade->getCourseName() ) !=  strtoupper( $eligibility->getCourseTitle() ) ) {
									$responseBoolean = true;
								}
								break;

							case "==":
								if( strtoupper( $grade->getCourseName() ) ==  strtoupper( $eligibility->getCourseTitle() ) ) {
									$responseBoolean = true;
								}
								break;

							default:
								throw new \Exception( 'Comparison Operation error. Comparison Operation of ' . $eligibility->getComparison() . ' is not defined. Please fix this.');
								break;
						}
					}
				}
				break;

			default:
				throw new \Exception( 'Eligibility Requirement error. Criteria Type of ' . $eligibility->getCriteriaType() . ' is not defined. Please fix this.');
				break;
		}

		return array( $responseBoolean , $responseGrade , $responseCourseTitle , $eligibilityCheck , $missingGrades );
	}

    /**
     * Returns array defining available eligibility fields and options
     *
     * @return array
     */
	public function getEligibilityFieldIDs(){

        return $this->eligibility_fields;
    }

    /**
     * returns single object if by program or array of objects if by school
     *
     * @param string $key
     * @param Program $program
     * @return mixed
     */
    public function getEligibilityField( $key = '',  Program $program ) {

	    if( isset( $this->eligibilityFieldObjects[ $key ] [ $program->getId() ] ) ){
	        return $this->eligibilityFieldObjects[ $key ] [ $program->getId() ];
        }

        $program_eligibility = $this->emLookup->getRepository('IIABMagnetBundle:Eligibility')
            ->findOneBy([
                'program' => $program,
                'criteriaType' => $key
            ]);

        $school_eligibility = $this->emLookup->getRepository('IIABMagnetBundle:Eligibility')
            ->findBy([
                'magnetSchool' => $program->getMagnetSchools()->toArray(),
                'criteriaType' => $key
            ]);

        $this->eligibilityFieldObjects[ $key ][ $program->getId() ] = ( $school_eligibility ) ? $school_eligibility : $program_eligibility;

        return  $this->eligibilityFieldObjects[ $key ][ $program->getId() ];
    }


    /**
     * @param string $key
     * @param Program $program
     *
     * @return string // '', school, program
     */
    public function getEligibilityFieldRequiredBy( $key = '',  Program $program ) {

        $eligibility = $this->getEligibilityField( $key, $program );

        if ( is_array($eligibility) ) {
            return 'school';
        } else if ( !empty( $eligibility ) ) {
            return 'program';
        }
        return null;
    }

    /**
     * Returns passing threshold values as scalar for by_program, array for by_school, or null
     *
     * @param string $key
     * @param Program $program
     * @return array|null
     */
    public function getEligibilityFieldThresholds( $key = '',  Program $program ){
        switch ( $this->getEligibilityFieldRequiredBy( $key, $program ) ){
            case 'program':

                return $this->eligibilityFieldObjects[ $key ][ $program->getId() ]->getPassingThreshold();
            break;

            case 'school':

                $thresholds = [];

                foreach( $this->eligibilityFieldObjects[ $key ][ $program->getId() ] as $eligibility ){
                    $thresholds[ $eligibility->getMagnetSchool()->getId() ] = $eligibility->getPassingThreshold();
                }

                return $thresholds;
            break;

            default:
                return null;
        }
    }

    public static function getParentKey( $key ){

        $returnKey = $key;
        foreach( MYPICK_CONFIG['eligibility_fields'] as $parentKey => $fieldSettings ){

            if( isset( $fieldSettings['info_field'] )
                && $fieldSettings['info_field']
            ){
                if( isset( $fieldSettings['info_field']['label'] ) ){
                    if( $fieldSettings['info_field']['key'] == $key )
                    {
                        if( !isset( MYPICK_CONFIG['eligibility_fields'][$key] ) ){
                            $returnKey = $parentKey;
                        }
                    }
                } else {

                    foreach( $fieldSettings['info_field'] as $info_field ){
                        if( $info_field['key'] == $key )
                        {
                            if( !isset( MYPICK_CONFIG['eligibility_fields'][$key] ) ){
                                $returnKey = $parentKey;
                            }
                        }
                    }
                }
            }
        }
        return $returnKey;
    }
}