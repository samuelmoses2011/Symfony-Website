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
use IIAB\MagnetBundle\Entity\SubmissionData;

class CalculateGPAService {

	/** @var EntityManager */
	private $emLookup;

    private $final_precision;

    private $default_method = [
        'current_grade_levels' => [2,3,4,5,6,7,8,9,10,11,12],
        'quality_convert_before_average' => false,
        'years' => [
            [
                'year_offset' => -1,
                'subjects' => [ 'english', 'math', 'social', 'science' ],
                'terms' => [ 'semester 1', 'semester 2' ],
                'precision' => null,
                'average_subject_before_year' => true,
            ],
            [
                'year_offset' => 0,
                'subjects' => [ 'english', 'math', 'social', 'science' ],
                'terms' => [ 'semester 1' ],
                'precision' => null,
                'average_subject_before_year' => true,
            ]
        ]
    ];

    private $methods;

	function __construct( EntityManager $emLookup ) {

		$this->emLookup = $emLookup;

        $this->final_precision = ( isset( MYPICK_CONFIG['gpa_calculations']['precision'] ) )
            ? MYPICK_CONFIG['gpa_calculations']['precision'] : 2;

        $this->methods = ( isset( MYPICK_CONFIG['gpa_calculations']['methods'] ) )
            ? MYPICK_CONFIG['gpa_calculations']['methods'] : $this->default_method;
	}

	public $success = [];

	public function calculateGPA( Submission $submission ){

        if( !$submission->doesRequire( 'calculated_gpa' ) ){
            return null;
        }

	    $grade_level = $submission->getCurrentGrade();
        $bad_grade_data = false;

        $method = '';
        foreach( $this->methods as $possible_method ){
            if( in_array($grade_level, $possible_method['current_grade_levels'] ) ){
                $method = $possible_method;
                break;
            }
        }

        //If no method is defined for this year, the gpa is not required
        if( empty( $method ) ){
            return null;
        }

        //calculate the gpa for each year
        $gpa_per_year = [];
        foreach ( $method['years'] as $year_setting ){

            if( isset( $year_setting['average_subject_before_year'] )
                && $year_setting['average_subject_before_year']
            ){

                $gpa_per_subject = [];
                foreach( $year_setting['subjects'] as $subject ){

                    $gpa_result = $this->getGPAbyYear(
                        $submission,
                        $year_setting['year_offset'],
                        [$subject],
                        $year_setting['terms'],
                        $method['quality_convert_before_average']
                    );

                    if( $gpa_result === null ){
                        $bad_grade_data = true;
                    }
                    $gpa_per_subject[] = $gpa_result;

                }

                $gpa_per_subject = array_filter($gpa_per_subject);
                $year_gpa = ( $gpa_per_subject ) ? array_sum($gpa_per_subject)/count($gpa_per_subject) : 0;
            } else {

                $gpa_result = $this->getGPAbyYear(
                    $submission,
                    $year_setting['year_offset'],
                    $year_setting['subjects'],
                    $year_setting['terms'],
                    $method['quality_convert_before_average']
                );

                if( $gpa_result === null ){
                    $bad_grade_data = true;
                }
                $year_gpa = $gpa_result;
            }

            if( isset( $year_setting['precision'] )
                && $year_setting['precission'] !== null ){
                $year_gpa = round( $year_gpa, $year_setting['precission'] );
            }
            $gpa_per_year[] = [
                'gpa' => $year_gpa,
                'grade_count' => count($year_setting['subjects']) * count($year_setting['terms'])
            ];
        }
        //if we were missing data the gpa cannot be calculated
        if( $bad_grade_data ){
            return null;
        }

        //average the yearly gpa weighted by the number of terms in each year
        $total = 0;
        $grade_count = 0;
        foreach( $gpa_per_year as $per_year ){
            $total += $per_year['gpa'] * $per_year['grade_count'];
            $grade_count += $per_year['grade_count'];
        }
        $gpa = $total / $grade_count;
        return round( $gpa, $this->final_precision );
    }

    private function convertGradeToQuality( $grade ){

	    if( round( $grade ) >= 90 ){
	        return 4;
        } else if( round( $grade ) >= 80 ){
	        return 3;
        } else if( round( $grade ) >= 70 ){
            return 2;
        } else if( round( $grade ) >= 60 ){
            return 1;
        } else {
            return 0;
        }
    }

    private function getGPAbyYear(
        Submission $submission,
        $year_offset = 0,
        $subjects = [ 'english', 'math', 'social', 'science', 'reading' ],
        $terms = [ '1st 9 weeks', '2nd 9 weeks', '3rd 9 weeks', '4th 9 weeks' ],
        $quality_convert_before_average = true
    ){

        $grade_count = count( $subjects ) * count( $terms );

	    $all_grades = $submission->getGrades();
	    $use_grades = [];
	    $maybe_use_grades = [];

	    foreach( $all_grades as $grade ){
	        if(
	            $grade->getAcademicYear() == $year_offset
                && in_array( $grade->getAcademicTerm(), $terms )
                && in_array( $grade->getCourseType(), $subjects )
            ){
	            if( !isset( $maybe_use_grades[ $grade->getAcademicTerm() ][ $grade->getCourseType() ] ) ){
                    $maybe_use_grades[ $grade->getAcademicTerm() ][ $grade->getCourseType() ] = [];
                }
                $maybe_use_grades[ $grade->getAcademicTerm() ][ $grade->getCourseType() ][] = $grade;
            }
        }

        $missing_grades = [];
        $duplicate_grades = [];
        $submission_data_objects = $submission->getAdditionalData( true );
        foreach( $submission_data_objects as $submission_data_object ){

            if( $submission_data_object->getMetaKey() == 'missing_grade' ){
                $missing_grades[] = $submission_data_object;
            } else if( $submission_data_object->getMetaKey() == 'duplicate_grade' ){
                $duplicate_grades[] = $submission_data_object;
            }
        }

        $use_grades = [];
        foreach( $terms as $term  ){
	        foreach( $subjects as $subject ){

                $missing_grade = null;
                $missing_grade_data = implode(' / ', [$submission->getOpenEnrollment()->getOffsetYear( $year_offset ), $term, $subject] );

                foreach( $missing_grades as $submission_data ){
                    if( $submission_data->getMetaValue() == $missing_grade_data ){
                        $missing_grade = $submission_data;
                    }
                }

                $duplicate_grade = null;
                $duplicate_grade_data = implode(' / ', [$submission->getOpenEnrollment()->getOffsetYear( $year_offset ), $term, $subject] );
                foreach( $duplicate_grades as $submission_data ){
                    if( $submission_data->getMetaValue() == $duplicate_grade_data ){
                        $duplicate_grade = $submission_data;
                    }
                }

	            if( empty( $maybe_use_grades[ $term ][ $subject ] ) ){

                    if( !empty( $duplicate_grade ) ){
                        $submission->removeAdditionalDatum( $duplicate_grade );
                        $this->emLookup->remove($duplicate_grade);
                    }

                    if( empty( $missing_grade ) ){
                        $missing_grade = new SubmissionData();
                        $missing_grade->setMetaKey('missing_grade');
                        $missing_grade->setMetaValue( $missing_grade_data );
                        $missing_grade->setSubmission( $submission );
                        $submission->addAdditionalDatum( $missing_grade );
                        $this->emLookup->persist( $missing_grade );
                    }

                } else {

                    if( !empty( $missing_grade ) ){
                        $submission->removeAdditionalDatum( $missing_grade );
                        $this->emLookup->remove($missing_grade);
                    }

	                $use_count = 0;
	                $maybe_use_count = 0;
	                $use_index = 0;

	                foreach( $maybe_use_grades[ $term ][ $subject ] as $index => $grade ){

	                    if( !empty( $grade->getUseInCalculations() ) ){
	                        $use_count++;
	                        $use_index = $index;
                        } else if( $grade->getUseInCalculations() !== 0 ) {
	                        $maybe_use_count++;
	                        $use_index = $index;
                        }
                    }

                    if( $use_count == 1 || ( $use_count == 0 && $maybe_use_count == 1 ) ){
                        $maybe_use_grades[ $term ][ $subject ][ $use_index ]->setUseInCalculations( 1 );
	                    $use_grades[] = ( $quality_convert_before_average ) ? $this->convertGradeToQuality( $maybe_use_grades[ $term ][ $subject ][ $use_index ]->getNumericGrade() ) : $maybe_use_grades[ $term ][ $subject ][ $use_index ]->getNumericGrade();

                        if( !empty( $duplicate_grade ) ){
                            $submission->removeAdditionalDatum( $duplicate_grade );
                            $this->emLookup->remove($duplicate_grade);
                        }

                    } else {
                        if(  $use_count > 1 || ( $use_count == 0 && $maybe_use_count > 1 ) )  {

                            if( empty( $duplicate_grade ) ){
                                $duplicate_grade = new SubmissionData();
                                $duplicate_grade->setMetaKey('duplicate_grade');
                                $duplicate_grade->setMetaValue( $duplicate_grade_data );
                                $duplicate_grade->setSubmission( $submission );
                                $this->emLookup->persist( $duplicate_grade );
                                $submission->addAdditionalDatum( $duplicate_grade );
                            }
                        } else {
                            if( !empty( $duplicate_grade ) ){
                                $submission->removeAdditionalDatum( $duplicate_grade );
                                $this->emLookup->remove($duplicate_grade);
                            }
                        }
                    }
                }
            }
        }

        if( count( $use_grades ) != $grade_count ){
	        return null;
        }

        $this->emLookup->persist( $submission );

        if( $quality_convert_before_average ){
            return array_sum( $use_grades ) / $grade_count;
        } else {
            return $this->convertGradeToQuality( array_sum( $use_grades ) / $grade_count );
        }
    }
}