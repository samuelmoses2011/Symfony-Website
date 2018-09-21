<?php

namespace IIAB\MagnetBundle\Admin\Traits;

use IIAB\MagnetBundle\Form\Type\LearnerScreeningDeviceType;

trait SubmissionAdminLearnerScreeningDeviceTabTraits {

    protected $learner_screening_device_tab = [];

    private function addLearnerScreeningDeviceTabs( $form, $object ){

        $this->getLearnerScreeningDeviceTabs( $object );

        if( $this->learner_screening_device_tab
            && $this->learner_screening_device_tab['is_needed']
        ){
            $form
                ->tab( 'Learner Profile Screening Device' )
                ->end();
        }
    }

    private function getLearnerScreeningDeviceTabs( $object ){

        if( isset( MYPICK_CONFIG['eligibility_fields']['learner_screening_device'] )
            && MYPICK_CONFIG['eligibility_fields']['learner_screening_device']
        ){
            $this->learner_screening_device_tab = [
                'is_needed' => $object->doesRequire( 'learner_screening_device' ),
                'name' => MYPICK_CONFIG['eligibility_fields']['learner_screening_device']['label'],
            ];
        }
    }

    private function addLearnerScreeningDeviceTabForms( $form, $object ){

        if( $this->learner_screening_device_tab
            && $this->learner_screening_device_tab['is_needed']
        ){

            $form->tab( 'Learner Profile Screening Device' );

                $form->with( $this->learner_screening_device_tab['name'] );

                    $email = $object->getAdditionalDataByKey( 'homeroom_teacher_email' );
                    $email = ( $email != null ) ? $email->getMetaValue() : null;

                    $form->add('homeroom_teacher_email', 'text', array(
                        'label' => 'Email',
                        'required' => false,
                        'mapped' => false,
                        'data' => $email,
                        'sonata_help' => 'Changes to this field will only affect new messages that go out.',
                        'help' => '<button '.
                                            'title="Resend Learner Profile Screening Device Email"'.
                                            'type="button" class="btn btn-info resend-email" data-email-type="learner_screening_device" data-submission-id="'.$object->getId().'">'.
                                            '<i class="fa fa-paper-plane"></i> Resend Learner Profile Screening Device Email<span></span></button>'
                    ));

                    $form->add( 'submission_learner_screening_device', LearnerScreeningDeviceType::class, [
                        'submission' => $object,
                        'mapped' => false,
                        'required' => false,
                        'label' => false
                    ]);
                $form->end();

                $form->with('Form Link');

                    $link_url = ( !empty( $object->getAdditionalDataByKey( 'learner_screening_device_url' ) ) )
                        ? 'https://specialty.tuscaloosacityschools.com/learner-screening-device/'.
                        $object->getAdditionalDataByKey( 'learner_screening_device_url' )->getMetaValue()
                        : '';
                    $form->add( 'learner_screening_device_url', 'text', [
                        'label' => $link_url,
                        'required' => false,
                        'mapped' => false,
                        'data' => null,
                        'attr' => ['class' => 'hide']

                    ]);
                $form->end();

                $form->with('Print Form');

                    $pdf_url = ( !empty( $object->getAdditionalDataByKey( 'learner_screening_device_url' ) ) )
                        ? $this->generateUrl( 'print-learner-screening-device', ['url'=>$object->getAdditionalDataByKey( 'learner_screening_device_url' )->getMetaValue()])
                        : null;
                    $form
                        ->add( 'screening_device_pdf', 'text', array(
                                'label' => ' ',
                                'required' => false,
                                'mapped' => false,
                                'attr' => [ 'style' => 'display: none;'],
                                'help' => '<button '.
                                    'title="Print Learner Screening Device" '.
                                    'onclick="window.open(\''.$pdf_url.'\'); return false;" '.
                                    'type="button" class="btn btn-info" name="btn_print_applicant">'.
                                    '<i class="fa fa-file-pdf-o"></i> Print Learner Screening Device</button>'
                            ));
                $form->end();
            $form->end();
        }
    }
}

