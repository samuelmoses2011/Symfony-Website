<?php

namespace IIAB\MagnetBundle\Admin\Traits;

use IIAB\MagnetBundle\Form\Type\GradesType;

trait SubmissionAdminGradesTabTraits {

    protected $grades_tab = [];

    private function addGradesTabs( $form, $object ){

        $this->getGradesTabs( $object );

        if( $this->grades_tab
            && $this->grades_tab['is_needed']
        ){
            $form
                ->tab( 'Grades' )
                ->end();
        }
    }

    private function getGradesTabs( $object ){

        if( isset( MYPICK_CONFIG['eligibility_fields']['grades'] )
            && MYPICK_CONFIG['eligibility_fields']['grades']
        ){
            $this->grades_tab = [
                'is_needed' => $object->doesRequire( 'grades' ),
                'name' => MYPICK_CONFIG['eligibility_fields']['grades']['label'],
            ];
        }
    }

    private function addGradesTabForms( $form, $object ){

        if( $this->grades_tab
            && $this->grades_tab['is_needed']
        ){

            $form->tab( 'Grades' );

                $form->with( $this->grades_tab['name'] );

                    $form->add( 'grades' , 'sonata_type_collection' , array(
                        'type_options' => array(
                            'delete' => true,
                        ) ,
                    ) , array(
                        'edit' => 'inline' ,
                        'inline' => 'table' ,
                    ) );
                $form->end();
            $form->end();
        }
    }
}

