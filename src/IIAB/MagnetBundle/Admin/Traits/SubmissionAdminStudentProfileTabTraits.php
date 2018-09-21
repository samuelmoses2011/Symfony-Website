<?php

namespace IIAB\MagnetBundle\Admin\Traits;

use IIAB\MagnetBundle\Form\Type\StudentProfileType;

trait SubmissionAdminStudentProfileTabTraits {

    protected $student_profile_tab = [];

    private function addStudentProfileTabs( $form, $object ){

        $this->getStudentProfileTabs( $object );

        if( $this->student_profile_tab
            && $this->student_profile_tab['is_needed']
        ){
            $form
                ->tab( 'Student Profile' )
                ->end();
        }
    }

    private function getStudentProfileTabs( $object ){

        if( isset( MYPICK_CONFIG['eligibility_fields']['student_profile'] )
            && MYPICK_CONFIG['eligibility_fields']['student_profile']
        ){
            $this->student_profile_tab = [
                'is_needed' => $object->doesRequire( 'student_profile' ),
                'name' => MYPICK_CONFIG['eligibility_fields']['student_profile']['label'],
            ];
        }
    }

    private function addStudentProfileTabForms( $form, $object ){

        if( $this->student_profile_tab
            && $this->student_profile_tab['is_needed']
        ){

            $form->tab( 'Student Profile' );

                $form->with( $this->student_profile_tab['name'] );

                    $form->add( 'submission_student_profile', StudentProfileType::class, [
                        'submission' => $object,
                        'mapped' => false,
                        'required' => false,
                        'label' => false
                    ]);
                $form->end();

                $form->with( 'Print Form' );

                    $pdf_url = $this->generateUrl( 'print-student-profile', ['id'=>$object->getId()]);
                    $form
                        ->add( 'student_profile_pdf', 'text', array(
                                'label' => ' ',
                                'required' => false,
                                'mapped' => false,
                                'attr' => [ 'style' => 'display: none;'],
                                'help' => '<button '.
                                    'title="Student Profile" '.
                                    'onclick="window.open(\''.$pdf_url.'\'); return false;" '.
                                    'type="button" class="btn btn-info" name="btn_print_applicant">'.
                                    '<i class="fa fa-file-pdf-o"></i> Print Student Profile</button>'
                            ));

                $form->end();
            $form->end();
        }
    }
}

