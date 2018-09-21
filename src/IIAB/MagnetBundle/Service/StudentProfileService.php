<?php

namespace IIAB\MagnetBundle\Service;

use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionData;

class StudentProfileService {

    private $submission =  null;
    private $emLookup = null;
    private $data = [];
    private $profile_settings = [];

    public function __construct( $submission = null, $emLookup = null ){
        $this->submission = $submission;
        $this->emLookup = $emLookup;

        $all_data = $submission->getAdditionalData(true);
        foreach( $all_data as $datum ){
            $this->data[ $datum->getMetaKey() ] = $datum->getMetaValue();
        }

        $this->profile_settings = $this->getProfileSettings();
    }

    public function getProfileSettings(){
        $profile_settings = [];
        foreach( MYPICK_CONFIG['student_profiles'] as $settings ){

            if( in_array($this->submission->getNextGrade(), $settings['next_grade_levels'] ) ){
                $profile_settings = $settings;
            }
        }

        return $profile_settings;
    }

    public function getLearnerScreeningDeviceScore(){

        if( !$this->profile_settings['learner_screening_device'] ){
            return null;
        }

        $questions = \IIAB\MagnetBundle\Service\LearnerScreeningDeviceService::getQuestions();

        $total = 0;

        $fieldValues = [];
        foreach( array_keys( $questions ) as $key ){
            $fieldValues[] = ( isset( $this->data['learner_screening_device_'.$key] ) ) ? intval( $this->data['learner_screening_device_'.$key] ) : 0;
        }

        if( empty( $fieldValues ) ){
            return null;
        }

        rsort( $fieldValues );
        $total = $fieldValues[0]
            + $fieldValues[1]
            + $fieldValues[2]
            + $fieldValues[3];

        $total = ( $total >= $this->profile_settings['learner_screening_device']['minium_score'] )
                ? $total + $this->profile_settings['learner_screening_device']['score_conversion']
                : 0;
        return $total;
    }

    public function getTestingScores(){

        if( !$this->profile_settings['standardized_testing'] ){
            return null;
        }

        $test_scores = [
            'total' => 0,
        ];

        foreach( $this->profile_settings['standardized_testing'] as $test_type => $scoring ){

            if( !is_int( $test_type ) ){

                $test_percent = ( isset( $this->data[$test_type.'_test'] ) ) ? intval( $this->data[$test_type.'_test'] ) : null;

                if( $test_percent == null ){
                    return null;
                }

                $test_result = 0;

                foreach( $scoring as $percent => $score ){
                    $test_result = ( !$test_result && $test_percent >= $percent) ? $score : $test_result;
                }

                $test_scores[ $test_type ] =  $test_result;
                $test_scores['total'] += $test_result;
            }
        }
        return $test_scores;
    }

    public function getGradesScores(){

        if( !$this->profile_settings['grades'] ){
            return null;
        }

        $can_calculate = true;
        $grade_scores = [
            'total' => 0,
        ];

        $grades = $this->submission->getGrades();

        $missing_grades = [];
        foreach( $this->submission->getAdditionalData(true) as $datum ){
            if( $datum->getMetaKey() == 'missing_grade' ){
                $missing_grades[ $datum->getMetaValue() ] = $datum;
            }
        }

        $grade_hash = [];
        foreach( $grades as $grade ){
            $grade_hash[ $grade->getAcademicYear() ]
                [ str_replace('9wk', '9 weeks', $grade->getAcademicTerm() ) ]
                [ $grade->getCourseType() ] = $grade;

            if( $grade->getAcademicYear() == 0
                && $grade->getNumericGrade() <= 3
                && $grade->getNumericGrade() >= 1
            ){
                $subject = $grade->getCourseType();
                $term = str_replace('9wk', '9 weeks', $grade->getAcademicTerm() );

                $section_count = ( isset( $section_hash[$subject][ $term ] ) ) ? count( $section_hash[$subject][ $term ] ) : 0;
                $section = ( !empty( $grade->getSectionNumber() ) ) ? $grade->getSectionNumber() : 'section '.$section_count;
                $section_hash[$subject][ $term ][ $section ] = $grade;
            }
        }

        foreach( $this->profile_settings['grades'] as $grade_section ){

            $grade_choices = [];

            if( empty( $grade_section['sections'] ) || !$grade_section['sections'] ){
                $alpha_labels = [
                    3 => '3 & 3',
                    2 => '3 & 2 or 2 & 2',
                    1 => '3 & 1 or 2 & 1'
                ];

                $alpha_point_hash = [];

                if( $grade_section['scores'] ){
                    foreach( $grade_section['scores'] as $alpha_grade => $score ){

                        $label = ( empty( $grade_section['average'] ) ) ? $alpha_grade : $alpha_labels[$alpha_grade];
                        $alpha_point_hash[ $alpha_grade ] = $score;
                        $grade_choices[ $label .': '. $score .' points'] = $score;
                    }
                }
            } else if( isset( $grade_section['score_by_lowest_grade'] )
                    && $grade_section['score_by_lowest_grade']
            ){
                $score_by_lowest_per_term = [];

                foreach( $grade_section['subjects'] as $subject ){
                    foreach( $grade_section['terms'] as $term ){
                        $score_by_lowest_per_term[$subject][$term] = [
                            'total' => 0,
                            'values' => [],
                        ];
                    }
                }
            } else {
                $score_by_points =[];


                foreach( $grade_section['subjects'] as $subject ){
                    $grade_count = 0;
                    foreach( $grade_section['terms'] as $term ){
                        $score_by_points[$subject][$term] = [
                            'total' => 0,
                            'values' => [],
                        ];
                        $grade_count += ( isset( $section_hash[$subject][ $term ] ) ) ? count( $section_hash[$subject][ $term ] ) : 0;
                    }
                    $grade_count = ( $grade_count ) ? $grade_count : 1;
                    foreach( $grade_section['terms'] as $term ){
                        foreach( $grade_section['scores'] as $grade => $score ){
                            $score_by_points[$subject][$term]['values'][$grade] = round( $score / $grade_count , 3 );
                        }
                    }
                }
            }

            $year_offset = $grade_section['year_offset'];
            foreach( $grade_section['subjects'] as $subject ){

                $grade_scores[$subject]['total'] = 0;

                foreach( $grade_section['terms'] as $term_index => $term ){

                    $missing_key = implode( ' / ', [
                        $this->submission->getOpenEnrollment()->getOffsetYear( $year_offset ),
                        $term,
                        $subject
                    ]);

                    if( empty( $grade_section['sections'] ) ){

                        if( isset( $alpha_point_hash ) && $alpha_point_hash ){

                            $alpha = null;
                            if( isset( $grade_hash[$year_offset][$term][$subject] ) ) {
                                $numericGrade = floatval( $grade_hash[$year_offset][$term][$subject]->getNumericGrade() );

                                if( $numericGrade >= 90 ){
                                    $alpha = $alpha_point_hash['A'];
                                } else if( $numericGrade >= 80 ){
                                    $alpha = $alpha_point_hash['B'];
                                } else if( $numericGrade >= 70 ){
                                    $alpha = $alpha_point_hash['C'];
                                }

                                if( !empty( $this->emLookup ) && isset( $missing_grades[ $missing_key ] ) ){
                                    $this->submission->removeAdditionalDatum( $missing_grades[ $missing_key ] );
                                    $this->emLookup->remove( $missing_grades[ $missing_key ] );
                                }
                            } else {
                                $can_calculate = false;
                                if( !empty( $this->emLookup ) && empty( $missing_grades[ $missing_key ] ) ){
                                    $missing_grade = new SubmissionData();
                                    $missing_grade->setMetaKey('missing_grade');
                                    $missing_grade->setMetaValue( $missing_key );
                                    $missing_grade->setSubmission( $this->submission );
                                    $this->submission->addAdditionalDatum( $missing_grade );
                                    $this->emLookup->persist( $missing_grade );
                                }
                            }

                            $grade_scores[$term][$subject] = $alpha;
                            $grade_scores['total'] += $alpha;
                            $grade_scores[$subject]['total'] += $alpha;
                        }
                    } else {

                        if( isset( $grade_section['score_by_lowest_grade'] )
                            && $grade_section['score_by_lowest_grade']
                        ){

                            $score_hash = $grade_section['scores'];
                            $lowest_grade = 999;
                            $highest_grade = 0;
                            $is_missing_grade = true;

                            if( isset( $section_hash[$subject][$term] ) ){
                                foreach( $section_hash[$subject][$term] as $section => $grade ){
                                    $is_missing_grade = false;

                                    $numericGrade = floatval( $grade->getNumericGrade() );

                                    $grade_scores[$subject]['sections'][$term][$section] = $numericGrade;

                                    $lowest_grade = ( $numericGrade < $lowest_grade )
                                        ? $numericGrade
                                        : $lowest_grade;

                                    $highest_grade = ( $numericGrade > $highest_grade )
                                        ? $numericGrade
                                        : $highest_grade;
                                }
                            }

                            $score = ( isset( $grade_section['scores'][$lowest_grade] ) )
                                    ? $grade_section['scores'][$lowest_grade]
                                    : null;

                            $grade_scores[$term][$subject] = ( $lowest_grade == $highest_grade ) ? $lowest_grade : $lowest_grade .' and '. $highest_grade;
                            $score_by_lowest_per_term[$subject][$term]['total'] = $lowest_grade;

                            if( $is_missing_grade ){
                                $can_calculate = false;
                                if( !empty( $this->emLookup ) && empty( $missing_grades[ $missing_key ] ) ){
                                    $missing_grade = new SubmissionData();
                                    $missing_grade->setMetaKey('missing_grade');
                                    $missing_grade->setMetaValue( $missing_key );
                                    $missing_grade->setSubmission( $this->submission );
                                    $this->submission->addAdditionalDatum( $missing_grade );
                                    $this->emLookup->persist( $missing_grade );
                                }
                            } else if( !empty( $this->emLookup )
                                && isset( $missing_grades[ $missing_key ] )
                            ){
                                $this->submission->removeAdditionalDatum( $missing_grades[ $missing_key ] );
                                $this->emLookup->remove( $missing_grades[ $missing_key ] );
                            }
                        } else {

                            $score_hash = $grade_section['scores'];

                            $is_missing_grade = true;
                            if( isset( $section_hash[$subject][$term] ) ){
                                foreach( $section_hash[$subject][$term] as $section => $grade ){
                                    $is_missing_grade = false;
                                    $numericGrade = floatval( $grade->getNumericGrade() );

                                    $score = ( isset( $score_by_points[$subject][$term]['values'][$numericGrade] ) )
                                        ? $score_by_points[$subject][$term]['values'][$numericGrade]
                                        : null;

                                    $grade_scores[$subject]['sections'][$term][$section] = $numericGrade;
                                    $grade_scores['total'] += $score;
                                    $grade_scores[$subject]['total'] += $score;
                                }
                            }

                            if( $is_missing_grade ){
                                $can_calculate = false;
                                if( !empty( $this->emLookup ) && empty( $missing_grades[ $missing_key ] ) ){
                                    $missing_grade = new SubmissionData();
                                    $missing_grade->setMetaKey('missing_grade');
                                    $missing_grade->setMetaValue( $missing_key );
                                    $missing_grade->setSubmission( $this->submission );
                                    $this->submission->addAdditionalDatum( $missing_grade );
                                    $this->emLookup->persist( $missing_grade );
                                }
                            } else if( !empty( $this->emLookup )
                                && isset( $missing_grades[ $missing_key ] )
                            ){
                                $this->submission->removeAdditionalDatum( $missing_grades[ $missing_key ] );
                                $this->emLookup->remove( $missing_grades[ $missing_key ] );
                            }
                        }
                    }
                }
            }

            if( isset( $score_by_lowest_per_term )
            ){

                $lowest_score = 999;
                foreach( $score_by_lowest_per_term as $subject => $terms ){
                    foreach( $terms as $term => $term_array ){
                        if( $term_array['total'] < $lowest_score ){
                            $lowest_score = $term_array['total'];
                        }
                    }
                    if( $lowest_score >= 1 && $lowest_score < 999  ){
                        $grade_scores[$subject]['total'] = $grade_section['scores'][$lowest_score];
                        $grade_scores['total'] += $grade_section['scores'][$lowest_score];
                    }
                }
            }
        }
        if( !empty( $this->emLookup ) ){
            $this->emLookup->flush();
        }
        return ( $can_calculate || true ) ? $grade_scores : null;
    }

    public function getProfileScores( $all_or_nothing = true ){
        $scores = [
            'learner_screening_device' => $this->getLearnerScreeningDeviceScore(),
            'testing' => $this->getTestingScores(),
            'grades' => $this->getGradesScores(),
        ];

        if( $all_or_nothing
            && (
                $scores['learner_screening_device'] === null
                || $scores['testing'] === null
                || $scores['grades'] === null
            )
        ){
            return null;
        }

        $scores['total'] = round( $scores['learner_screening_device']
            + $scores['testing']['total']
            + $scores['grades']['total'] );

        $scores['percentage'] = round( ( round( $scores['total'] ) / 75 ) * 100 );

        return $scores;
    }
}