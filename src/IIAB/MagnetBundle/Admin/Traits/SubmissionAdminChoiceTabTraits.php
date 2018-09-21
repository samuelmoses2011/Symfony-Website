<?php

namespace IIAB\MagnetBundle\Admin\Traits;

trait SubmissionAdminChoiceTabTraits {

    private $possible_choices = [
                'first' =>[],
                'second' =>[],
                'third' =>[]
            ];

    private function getChoiceTabs( $object ){

        if( $this->choice_tabs ){
            return $this->choice_tabs;
        }

        foreach( $this->possible_choices as $choice_type => $choice_tab){

            if( $choice_type == 'third' && $object->getNextGrade() < 6 ){
                break;
            }

            $choosen_school = $object->{'get'. ucwords($choice_type) .'Choice'}();
            $focus_description = ( !empty( $choosen_school) ) ? $choosen_school->getProgram()->getAdditionalData('focus_description') : [];
            $focus_description = ( count( $focus_description ) ) ? $focus_description[0]->getMetaValue() : '';

            $is_needed = $this->choiceTabNeeded( $choosen_school );

            $this->choice_tabs[$choice_type] = [
                'is_needed' => $is_needed['is_needed'],
                'school' => $choosen_school,
                'focus_description' => $focus_description,
                'name' => ( !empty( $is_needed['name'] ) )
                    ? $is_needed['name']
                    : ucfirst($choice_type) .' Choice '. ucfirst( $focus_description ),
            ];
        }
    }

    protected function choiceTabNeeded( $school ){

        $tab_required = [
            'is_needed' => false,
            'name' => '',
        ];
        if( empty( $school ) ){
            return $tab_required;
        }

        $required_if = [
            'writing_prompt',
            'audition_total'
        ];

        $requires_multiple = false;
        foreach( $required_if as $required ){
            $maybe = $school->doesRequire( $required );
            if( $maybe ){

                if( $tab_required['name'] ) { $requires_multiple = true; }
                $tab_required['name'] = ucwords( str_replace( '_', ' ', str_replace( '_total', '', $required ) ) );

                $tab_required['is_needed'] = true;
            }
        }

        if( $requires_multiple ){
            $tab_required['name'] = '';
        }

        return $tab_required;
    }

    private function addChoiceTabs( $form, $object ){

        $this->getChoiceTabs( $object );
        foreach ( $this->choice_tabs as $choice_type => $choice_tab ){
            if( $choice_tab['is_needed'] ){
                $form
                ->tab( $choice_tab['name'] )
                ->end();
            }
        }
    }

    private function addChoiceList( $form, $object ){
        $form->with( 'Choices' );
        foreach( $this->choice_tabs as $choice_type => $choice_tab ) {

            if ( (!empty($choice_tab['school']) )
                && ( $this->user->hasSchool( $choice_tab['school']->getName() )
                    || empty( $this->user_schools )
                )
            ) {

                $focus_list = '';
                if( !empty( $choice_tab['school'] ) ) {
                    $focus_list .= ($object->{'get' . ucwords($choice_type) . 'ChoiceFirstChoiceFocus'}()) ? '1st: <strong>' . $object->{'get' . ucwords($choice_type) . 'ChoiceFirstChoiceFocus'}() . '</strong>' : '';
                    $focus_list .= ($object->{'get' . ucwords($choice_type) . 'ChoiceSecondChoiceFocus'}()) ? ' 2nd: <strong>' . $object->{'get' . ucwords($choice_type) . 'ChoiceSecondChoiceFocus'}() . '</strong>' : '';
                    $focus_list .= ($object->{'get' . ucwords($choice_type) . 'ChoiceThirdChoiceFocus'}()) ? ' 3rd: <strong>' . $object->{'get' . ucwords($choice_type) . 'ChoiceThirdChoiceFocus'}() . '</strong>' : '';
                }
                $form->add($choice_type.'Choice', null, array(
                    'placeholder' => 'Choose a School',
                    'query_builder' => function ( $er) {
                        global $object;
                        return $er->createQueryBuilder('a')->where('a.openEnrollment = ' . $object->getOpenEnrollment()->getId())->orderBy('a.id', 'ASC');
                    },
                    'help' => $focus_list,
                ));

                $assessment_test_required = !empty( $choice_tab['school'] ) && $choice_tab['school']->doesRequire( 'assessment_test_eligible');
                if( $assessment_test_required ) {
                    $test_score = $object->getAdditionalDataByKey($choice_type . '_choice_assessment_test_score');
                    $test_score = (empty($test_score)) ? null : $test_score->getMetaValue();
                    $form->add($choice_type . '_choice_assessment_test_score', 'text', array(
                        'label' => 'Testing Score',
                        'required' => false,
                        'mapped' => false,
                        'data' => $test_score,
                    ));

                    $test_eligible = $object->getAdditionalDataByKey($choice_type . '_choice_assessment_test_eligible');
                    $test_eligible = (empty($test_eligible)) ? null : $test_eligible->getMetaValue();
                    $form->add($choice_type . '_choice_assessment_test_eligible', 'choice', array(
                        'label' => 'Testing Eligibility',
                        'placeholder' => 'Choose an Option',
                        'choices' => array_flip( array(
                            1 => 'Eligible',
                            0 => 'Ineligible'
                        )),
                        'required' => false,
                        'mapped' => false,
                        'data' => $test_eligible,
                        'help' => ($choice_type != 'third' && !$course_eligible_required && !$combined_audition_score_required) ? '<hr style="border-top: 1px solid #00a65a;"/>' : null,
                    ));
                }

                $course_eligible_required = ( !empty( $choice_tab['school'] ) && $choice_tab['school']->doesRequire( 'course_eligibility_met')  );
                if( $course_eligible_required ) {

                    $course_eligible = $object->getAdditionalDataByKey($choice_type . '_choice_course_eligibility_met');
                    $course_eligible = (empty($course_eligible)) ? null : $course_eligible->getMetaValue();
                    $form->add($choice_type . '_choice_course_eligibility_met', 'choice', array(
                        'label' => 'Course Eligibility Met',
                        'placeholder' => 'Choose an Option',
                        'choices' => array_flip( array(
                            1 => 'Yes',
                            0 => 'No'
                        )),
                        'required' => false,
                        'mapped' => false,
                        'data' => $course_eligible,
                        'help' => ($choice_type != 'third' && !$combined_audition_score_required) ? '<hr style="border-top: 1px solid #00a65a;"/>' : null,
                    ));
                }
            }
        }
        $form->end();
    }

    private function addChoiceTabForms( $form, $object ){

        foreach( $this->choice_tabs as $choice_type => $choice_tab ){

            if( !empty( $choice_tab['school'] )
                && $choice_tab['is_needed']){

                $form
                    ->tab( $choice_tab['name'] );
                $block_index = 0;

                $form->with( $choice_tab['name'] );

                    $writing_prompt_required = ( !empty( $choice_tab['school'] ) && $choice_tab['school']->doesRequire( 'writing_prompt')  );
                    if( $writing_prompt_required ) {

                        $writing_prompt = $object->getAdditionalDataByKey('writing_prompt');
                        $writing_prompt = (empty($writing_prompt)) ? null : $writing_prompt->getMetaValue();

                        $form->add($choice_type . '_choice_writing_prompt', 'text', array(
                            'label' => 'Writing Prompt',
                            'attr' => [ 'readonly' => 'readonly' ],
                            'required' => false,
                            'mapped' => false,
                            'data' => $writing_prompt,
                        ));

                        $writing_sample = $object->getAdditionalDataByKey('writing_sample');
                        $writing_sample = (empty($writing_sample)) ? null : $writing_sample->getMetaValue();
                        $form->add($choice_type . '_choice_writing_sample', 'textarea', array(
                            'label' => 'Writing Sample',
                            'attr' => [ 'rows' => '10', 'readonly' => 'readonly' ],
                            'required' => false,
                            'mapped' => false,
                            'data' => $writing_sample,
                        ));

                        $student_email = $object->getAdditionalDataByKey('student_email');
                        $student_email = (empty($student_email)) ? null : $student_email->getMetaValue();
                        $form->add('student_email', 'email', array(
                            'label' => 'Student Email',
                            'required' => false,
                            'mapped' => false,
                            'data' => $student_email,
                        ));

                        $link_url = 'https://specialty.tuscaloosacityschools.com/writing/'.
                            $object->getId().'.'.$object->getUrl();
                        $form->add( 'writing_sample_url', 'text', [
                            'label' => 'Student Link: '. $link_url,
                            'required' => false,
                            'mapped' => false,
                            'data' => null,
                            'attr' => ['class' => 'hide']

                        ]);


                        $pdf_url = $this->generateUrl( 'print-writing-sample', ['id'=>$object->getId()]);
                        $form
                            ->add( 'writing_sample_pdf', 'text', array(
                                    'label' => ' ',
                                    'required' => false,
                                    'mapped' => false,
                                    'attr' => [ 'style' => 'display: none;'],
                                    'help' => '<button '.
                                        'title="Writing Sample" '.
                                        'onclick="window.open(\''.$pdf_url.'\'); return false;" '.
                                        'type="button" class="btn btn-info" name="btn_print_applicant">'.
                                        '<i class="fa fa-file-pdf-o"></i> Print Writing Sample</button>'
                                ));


                    }

                    $auditions_required = ( !empty( $choice_tab['school'] ) && $choice_tab['school']->doesRequire( 'audition_total')  );
                    if( $auditions_required ){

                        $audition_choices = [
                            'Exceptional' => 4,
                            'Ready' => 3,
                            'Not Ready' => 2,
                            'No Show' => 0,
                        ];

                        $audition_1 = $object->getAdditionalDataByKey('audition_1');
                        $audition_1 = (empty($audition_1)) ? null : $audition_1->getMetaValue();

                        $form->add('audition_1', 'choice', array(
                            'label' => 'Audition #1',
                            'required' => false,
                            'mapped' => false,
                            'choices' => $audition_choices,
                            'attr' => ['class' => 'audition_input'],
                            'data' => $audition_1,
                        ));

                        $audition_2 = $object->getAdditionalDataByKey('audition_2');
                        $audition_2 = (empty($audition_2)) ? null : $audition_2->getMetaValue();

                        $form->add('audition_2', 'choice', array(
                            'label' => 'Audition #2',
                            'required' => false,
                            'mapped' => false,
                            'choices' => $audition_choices,
                            'attr' => ['class' => 'audition_input'],
                            'data' => $audition_2,
                        ));

                        $combined_audition_score_required = ( !empty( $choice_tab['school'] ) && $choice_tab['school']->doesRequire( 'audition_total')  );

                        $combined_score = $object->getAdditionalDataByKey('audition_total');
                        $combined_score = (empty($combined_score)) ? null : $combined_score->getMetaValue();

                        $form->add('audition_total', 'integer', array(
                            'label' => 'Audition Point Total',
                            'scale' => 1,
                            'required' => false,
                            'mapped' => false,
                            'data' => $combined_score,
                            'attr' => ['min' => 0, 'max' => 8, 'class' => 'audition_total'],
                            'help' => 'Lowest (0) &ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;Highest (8) <hr style="border-top: 1px solid #00a65a;"/>',
                        ));
                    }

                    $interview_required = ( !empty( $choice_tab['school'] ) && $choice_tab['school']->doesRequire( 'interview')  );
                    if( $interview_required ){
                        $interview = $object->getAdditionalDataByKey($choice_type . '_choice_interview');
                        $interview = (empty($interview)) ? null : $interview->getMetaValue();

                        $form->add($choice_type . '_choice_interview', 'choice', array(
                            'label' => 'Interview',
                            'placeholder' => 'Choose an Option',
                            'choices' => array_flip( array(
                                0 =>'Fail',
                                1 =>'Pass',
                            )),
                            'required' => false,
                            'mapped' => false,
                            'data' => $interview,
                        ));
                    }

                    $interest_required = ( !empty( $choice_tab['school'] ) && $choice_tab['school']->doesRequire( 'interest')  );
                    if( $interest_required ){
                        $interest = $object->getAdditionalDataByKey($choice_type . '_choice_interest');
                        $interest = (empty($interest)) ? null : $interest->getMetaValue();

                        $form->add($choice_type . '_choice_interest', 'choice', array(
                            'label' => 'Interest',
                            'placeholder' => 'Choose an Option',
                            'choices' => array_flip( array(
                                0 =>'Fail',
                                1 =>'Pass',
                            )),
                            'required' => false,
                            'mapped' => false,
                            'data' => $interest,
                        ));
                    }
                $form->end();

                if ( (!empty($choice_tab['school']) )
                    && ( $this->user->hasSchool( $choice_tab['school']->getName() )
                        || empty( $this->user_schools )
                    )
                ) {
                    $focus_data = $object->getFocusDataByChoice($choice_type);

                    foreach ($focus_data as $key => $data) {

                        $block_title = ucfirst( $choice_type ) .' '. ucfirst( $choice_tab['focus_description']);
                        $form
                            ->with($choice_made .': '. $block_title );

                        if ($data['choices']) {

                            $choices = [];
                            $extras = [];
                            foreach( $data['choices'] as $choice ){
                                $choices[ $choice['choice'] ] = $choice['choice' ];
                                if( !empty( $choice['extra_field_1'] ) && !in_array( $choice['extra_field_1'], $extras ) ){
                                    $extras['extra_1'] = $choice['extra_field_1'];
                                }
                                if( !empty( $choice['extra_field_2'] ) && !in_array( $choice['extra_field_2'], $extras ) ){
                                    $extras['extra_2'] = $choice['extra_field_2'];
                                }
                                if( !empty( $choice['extra_field_3'] ) && !in_array( $choice['extra_field_3'], $extras ) ){
                                    $extras['extra_3'] = $choice['extra_field_3'];
                                }
                            }

                            $prefix = $choice_type .'_choice_';
                            $label = $key;
                            if (substr($label, 0, strlen($prefix)) == $prefix) {
                                $label = substr($key, strlen($prefix));
                            }
                            $label =  ucwords( str_replace( 'focus', 'audition', str_replace('_', ' ', $label) ) );

                            $form
                                ->add($key, 'choice', array(
                                    'label' => $label,
                                    'placeholder' => 'Choose an Option',
                                    'choices' => $choices,
                                    'required' => false,
                                    'mapped' => false,
                                    'data' => $data['selected'],
                                ));

                            foreach( $extras as $extra_key => $extra ){

                                $extra_selected = $object->getAdditionalDataByKey( $key.'_'.$extra_key );
                                $form->add( $key .'_'. $extra_key, 'text', array(
                                    'label' => ucwords( str_replace( 'focus', 'audition', str_replace('_', ' ', $extra ) ) ),
                                    'required' => false,
                                    'mapped' => false,
                                    'data' => ( $extra_selected ) ? $extra_selected->getMetaValue(): null,
                                ));
                            }

                            foreach ($data['extra'] as $extra_key => $extra_data) {

                                if( strpos($extra_key, '_score') == false || ${$choice_type .'Choice' }->doesRequire('audition_score') ) {

                                    if ($extra_data['choices']) {

                                        $form->add($extra_key, 'choice', array(
                                            'label' => ucwords(str_replace('focus', 'audition', str_replace('_', ' ', $extra_key))),
                                            'placeholder' => 'Choose an Option',
                                            'choices' => array_combine($extra_data['choices'], $extra_data['choices']),
                                            'required' => false,
                                            'mapped' => false,
                                            'data' => $extra_data['selected'],
                                            'help' => (strpos($extra_key, '_score') !== false) ? 'Lowest (0) &ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;&ndash;Highest (4)' : null,
                                        ));
                                    }
                                }
                            }

                            if( !empty( $choice_tab['school'] ) ) {
                                $form->add( 'xxx'. $choice_type .$block_index, 'text', array(
                                    'label' => ' ',
                                    'required' => false,
                                    'mapped' => false,
                                    'attr' => [ 'style' => 'display: none;'],
                                    'help' => '<button '.
                                        'title="Print Applicant Data for ' . $choice_tab['school']->getName() .' '. $block_titles[ $block_index ] .'" '.
                                        'onclick="window.open(\'http://magnet.mps.k12.al.us/admin/submission/'.$object->getId().'/print-applicant/'.$choice_tab['school']->getId().'/print/'. ($block_index +1) .'\'); return false;" '.
                                        'type="button" class="btn btn-info" name="btn_print_applicant">'.
                                        '<i class="fa fa-file-pdf-o"></i> Print Applicant '. $block_titles[ $block_index ] .' Choice</button>'
                                ));
                            }


                        }
                        $block_index++;
                        $form->end();
                    }
                }
                $form->end();
            }
        }
    }
}