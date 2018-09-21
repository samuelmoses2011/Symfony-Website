<?php

namespace IIAB\MagnetBundle\Form\Type;

use IIAB\MagnetBundle\Service\StudentProfileService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class StudentProfileType extends AbstractType {

    /**
     * {@inheritdoc}
     */
    public function buildForm( FormBuilderInterface $builder , array $options ) {

        $submission = $options['submission'];
        $additional_data = $options['submission']->getAdditionalData(true);

        $data = [];
        foreach( $additional_data as $datum ){
            $data[ $datum->getMetaKey() ] = $datum->getMetaValue();
        }

        $maybe_disabled = false;
        if( isset( $options['mapped'] ) && $options['mapped'] === false ){
            $maybe_disabled = true;
        }

        $studentProfileService = new StudentProfileService( $submission );

        $scores = $studentProfileService->getProfileScores();

        $profile_settings = $studentProfileService->getProfileSettings();

        if( !$profile_settings ){
            return;
        }

        if( !isset( $profile_settings['calculate_score'] )
            || $profile_settings['calculate_score']
        ){
            $builder
            ->add( 'student_profile_score', 'text', [
                'mapped' => false,
                'data' =>  ( isset( $scores['total'] ) ) ? $scores['total'] : null,
            ])
            ->add( 'student_profile_percentage', 'text', [
                'mapped' => false,
                'data' =>  ( isset( $scores['percentage'] ) ) ? $scores['percentage'] : null,
            ]);
        }

        if( $profile_settings['learner_screening_device']
            && !$maybe_disabled
        ){
            $builder
                ->add( 'student_profile_LPSD', LearnerScreeningDeviceType::class, [
                    'submission' => $options['submission'],
                    'scoring' => $profile_settings['learner_screening_device'],
                    'mapped' => false,
                    'required' => false,
                    'label' => false
                ]);

            $children = $builder->all();
            foreach( $children as $child ){
                if( $child->getName() == 'student_profile_LPSD'){
                    $fields = $child->all();
                    break;
                }
            }


            $lsd_score = $studentProfileService->getLearnerScreeningDeviceScore();

            $builder
                ->add( 'learner_screening_device_score', 'text', [
                    'label' => 'Learner Screening Device (LPSD) Criteria',
                    'data' => $lsd_score,
                ]);

        }

        if( $profile_settings['standardized_testing']
            && !$maybe_disabled
        ){

            $testing_scores = $studentProfileService->getTestingScores();

            foreach( $profile_settings['standardized_testing'] as $test_type => $scoring ){

                if( is_int( $test_type ) ){

                    $test_type = $scoring;
                    $test_percent = ( isset( $data[$test_type.'_test'] ) ) ? intval( $data[$test_type.'_test'] ) : 0;

                    $builder->add($test_type.'_test', 'text', [
                        'label' => ucfirst( $test_type ). ' Standardized Testing Criteria',
                        'data' => $test_percent,
                    ]);

                } else {

                    if( !is_int( $test_type ) ){

                        $testing_choices = [];
                        $max = 99;

                        foreach( $scoring as $percent => $score ){
                            $testing_choices[ $percent.'-'.$max.'%: '.$score.' points' ] = $score;
                            $max = $percent - 1;
                        }

                        $builder->add($test_type.'_test', 'choice', [
                            'label' => ucfirst( $test_type ). ' Standardized Testing Criteria',
                            'choices' => $testing_choices,
                            'data' => ( isset( $testing_scores[$test_type] ) ) ? $testing_scores[$test_type] : null,
                        ]);
                    }
                }
            }
        }

        if( $profile_settings['grades']
            && !$maybe_disabled
        ){

            $grade_scores = $studentProfileService->getGradesScores();

            $grades = $submission->getGrades();
            $grade_hash = [];
            foreach( $grades as $grade ){
                $grade_hash[ $grade->getAcademicYear() ]
                    [ str_replace('9wk', '9 weeks', $grade->getAcademicTerm() ) ]
                    [$grade->getCourseType() ] = $grade;
                if( $grade->getAcademicYear() == 0
                    || $grade->getAcademicYear() == -1
                ){
                    $section_hash[ str_replace('9wk', '9 weeks', $grade->getAcademicTerm() ) ][ $grade->getSectionNumber() ] = $grade;
                }
            }

            foreach( $profile_settings['grades'] as $grade_section ){

                $grade_choices = [];

                if( $grade_section['scores'] ){
                    $alpha_labels = [
                        3 => '3 & 3',
                        2 => '3 & 2 or 2 & 2',
                        1 => '3 & 1 or 2 & 1'
                    ];

                    $alpha_point_hash = [];

                    foreach( $grade_section['scores'] as $alpha_grade => $score ){

                        $label = ( empty( $grade_section['average'] ) ) ? $alpha_grade : $alpha_labels[$alpha_grade];
                        $grade_choices[ $label .': '. $score .' points'] = $score;
                    }
                }

                foreach( $grade_section['subjects'] as $subject ){

                    foreach( $grade_section['terms'] as $term_index => $term ){

                        if( empty( $grade_section['sections'] ) ){

                            if( isset( $alpha_point_hash ) ){

                                $builder->add( $subject.'_'.$term_index, 'choice', [
                                    'label' => ucfirst( $subject ) .': '. ucwords( $term ),
                                    'choices' => $grade_choices,
                                    'data' => ( isset($grade_scores[$term][$subject]) ) ? $grade_scores[$term][$subject] : null,
                                ]);
                            } else {

                                $numericGrade = (isset( $grade_hash[0][$term][$subject] ) )
                                        ? floatval( $grade_hash[0][$term][$subject]->getNumericGrade() )
                                        : 0;

                                $builder->add( $subject.'_'.$term_index, 'text', [
                                    'label' => ucfirst( $subject ) .': '. ucwords( $term ),
                                    'data' => $numericGrade,
                                ]);
                            }
                        } else {

                            if( isset( $grade_section['score_by_lowest_grade'] )
                                && $grade_section['score_by_lowest_grade']
                            ){

                                $builder->add( $subject.'_'.$term_index, 'choice', [
                                    'label' => ucfirst( $subject ) .': '. ucwords( $term ),
                                    'choices' => $grade_choices,
                                    'placeholder' => 'form.option.choose',
                                    'data' => ( isset($grade_scores[$term][$section]) ) ? $grade_scores[$term][$section] : null,
                                ]);

                            } else {

                                foreach( $grade_section['sections'] as $section_index => $section ){

                                    $numericGrade = (isset( $section_hash[$term][$section] ))
                                        ? floatval( $section_hash[$term][$section]->getNumericGrade() )
                                        : 0;

                                    $builder->add( $subject.'_'.$term_index.'_'.$section_index, 'choice', [
                                        'label' => ucfirst( $subject ) .': '. ucwords( $term ),
                                        'placeholder' => 'form.option.choose',
                                        'choices' => $grade_choices,
                                        'data' => ( isset( $grade_scores[$term][$section] ) ) ? $grade_scores[$term][$section] : null,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        if( $profile_settings['conduct'] ){
            $max = ( isset($profile_settings['conduct']['odr']) ) ? $profile_settings['conduct']['odr'] : 3;
            for( $odr_index = 1; $odr_index <= $max; $odr_index++ ){
                $builder
                ->add( 'odr_'.$odr_index, 'textarea', [
                    'label' => 'ODR '.$odr_index,
                    'mapped' => false,
                    'data' => ( isset( $data['odr_'.$odr_index] ) ) ? $data['odr_'.$odr_index] : null,
                    'attr' => [ 'rows' => '5' ],
                ]);
            }
        }

        if( isset( $options['mapped'] ) && $options['mapped'] !== false ){
            $builder
                ->add( 'submit', 'submit', [
                    'label' => 'Submit Profile',
                    'attr' => ['class' => 'btn btn-success btn-lg']
                ]);
        }
    }

    public function configureOptions( \Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
         $resolver->setRequired('submission');
    }
}