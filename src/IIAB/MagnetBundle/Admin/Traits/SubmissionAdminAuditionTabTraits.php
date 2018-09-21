<?php

namespace IIAB\MagnetBundle\Admin\Traits;

trait SubmissionAdminAuditionTabTraits {

    protected $audition_tab = [];

    private function addAuditionTabs( $form, $object ){

        $this->getAuditionTabs( $object );

        if( $this->audition_tab
            && $this->audition_tab['is_needed']
        ){
            $form
                ->tab( 'Audition' )
                ->end();
        }
    }

    private function getAuditionTabs( $object ){

        if( isset( MYPICK_CONFIG['eligibility_fields']['audition'] )
            && MYPICK_CONFIG['eligibility_fields']['audition']
        ){
            $this->audition_tab = [
                'is_needed' => $object->doesRequire( 'audition' ),
                'name' => MYPICK_CONFIG['eligibility_fields']['audition']['label'],
            ];
        }
    }

    private function addAuditionTabForms( $form, $object ){
        if( $this->audition_tab
            && $this->audition_tab['is_needed']
        ){
            $form->tab( 'Audition' );

                $form->with( $this->audition_tab['name'] );

                    $score = $object->getAdditionalDataByKey('audition_total');
                    $score = (empty($score)) ? null : $score->getMetaValue();

                    $form->add('audition_total', 'choice', array(
                        'label' => 'Audition',
                        'required' => false,
                        'mapped' => false,
                        'data' => $score,
                        'choices' => [
                            'Ready' => 1,
                            'Not Ready' => 0,
                        ],
                    ));
                $form->end();
            $form->end();
        }
    }
}

