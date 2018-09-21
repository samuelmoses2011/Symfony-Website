<?php

namespace IIAB\MagnetBundle\Admin\Traits;

use IIAB\MagnetBundle\Form\Type\RecommendationTeacherType;
use IIAB\MagnetBundle\Form\Type\RecommendationCounselorType;

trait SubmissionAdminRecommendationTabTraits {

    protected $recommendation_tabs = [];
    protected $recommendation_is_needed = false;

    private function addRecommendationTabs( $form, $object ){

        $this->recommendation_tabs['is_needed'] = $object->doesRequire( 'recommendations' );

        if( $this->recommendation_tabs['is_needed'] ){

            $this->getRecommendationTabs( $object );

            $form
                ->tab( 'Recommendations' )
                ->end();
        }
    }

    private function getRecommendationTabs( $object ){

        foreach( MYPICK_CONFIG['eligibility_fields']['recommendations']['info_field'] as $recommendation_type => $recommendation_tab ){
            if( strpos( $recommendation_type, 'recommendation_') === 0 ){
                $this->recommendation_tabs[ $recommendation_type ] =[
                    'is_needed' => $object->doesRequire( $recommendation_type ),
                    'name' => $recommendation_tab['label'],
                ];
            }
        }
    }

    private function addRecommendationTabForms( $form, $object ){

        if( $this->recommendation_tabs['is_needed'] ){
            $form->tab( 'Recommendations' );

            foreach( $this->recommendation_tabs as $recommendation_type => $recommendation_tab ){

                if( $recommendation_type != 'is_needed' ){

                    $form->with( $recommendation_tab['name'] );

                    $email = $object->getAdditionalDataByKey( str_replace('recommendation_', '', $recommendation_type) .'_teacher_email' );
                    $email = ( $email != null ) ? $email->getMetaValue() : null;

                    $form->add('submission_'.$recommendation_type.'_email', 'text', array(
                        'label' => 'Email',
                        'mapped' => false,
                        'data' => $email,
                        'sonata_help' => 'Changes to this field will only affect new messages that go out.',
                        'help' => '<button '.
                                            'title="Resend Recommendation Email "'.
                                            'type="button" class="btn btn-info resend-email" data-email-type="'.$recommendation_type.'" data-submission-id="'.$object->getId().'">'.
                                            '<i class="fa fa-paper-plane"></i> Resend Recommendation Email <span></span></button>'
                    ));

                    if( $recommendation_type == 'recommendation_counselor' ){

                        $form->add( 'submission_'.$recommendation_type, RecommendationCounselorType::class, [
                            'submission' => $object,
                            'recommendation_type' => $recommendation_type,
                            'mapped' => false,
                            'required' => false,
                            'label' => false
                        ]);

                    } else {

                        $form->add( 'submission_'.$recommendation_type, RecommendationTeacherType::class, [
                            'submission' => $object,
                            'recommendation_type' => $recommendation_type,
                            'mapped' => false,
                            'required' => false,
                            'label' => false
                        ]);
                    }

                    $form->end();
                }
            }


            $urls = [
                'math' => ( !empty( $object->getAdditionalDataByKey( 'recommendation_math_url' ) ) )
                    ? $this->generateUrl( 'print-recommendation-math', ['url'=>$object->getAdditionalDataByKey( 'recommendation_math_url' )->getMetaValue()])
                    : null,
                'english' => ( !empty( $object->getAdditionalDataByKey( 'recommendation_english_url' ) ) )
                    ? $this->generateUrl( 'print-recommendation-english', ['url'=>$object->getAdditionalDataByKey( 'recommendation_english_url' )->getMetaValue()])
                    : null,
                'counselor' => ( !empty( $object->getAdditionalDataByKey( 'recommendation_counselor_url' ) ) )
                    ? $this->generateUrl( 'print-recommendation-counselor', ['url'=>$object->getAdditionalDataByKey( 'recommendation_counselor_url' )->getMetaValue()])
                    : null,
            ];

            $buttons = [];
            foreach( $urls as $recommendation_type => $url ){
                $buttons[] = '<button '.
                            'title="Print '. ucwords($recommendation_type) .' Recommendation" '.
                            'onclick="window.open(\''.$url.'\'); return false;" '.
                            'type="button" class="btn btn-info" name="btn_print_applicant">'.
                            '<i class="fa fa-file-pdf-o"></i> Print '. ucwords($recommendation_type) .' Recommendation</button>';
            }
            $help = join( '&nbsp;&nbsp;&nbsp;', $buttons );

            $form->with('Form Links');

            $link_urls = [
                'math' => ( !empty( $object->getAdditionalDataByKey( 'recommendation_math_url' ) ) )
                    ? 'https://specialty.tuscaloosacityschools.com/recommendation/'.
                        $object->getAdditionalDataByKey( 'recommendation_math_url' )->getMetaValue()
                    : '',
                'english' => ( !empty( $object->getAdditionalDataByKey( 'recommendation_english_url' ) ) )
                    ? 'https://specialty.tuscaloosacityschools.com/recommendation/'.
                        $object->getAdditionalDataByKey( 'recommendation_english_url' )->getMetaValue()
                    : '',
                'counselor' => ( !empty( $object->getAdditionalDataByKey( 'recommendation_counselor_url' ) ) )
                    ? 'https://specialty.tuscaloosacityschools.com/recommendation/'.
                        $object->getAdditionalDataByKey( 'recommendation_counselor_url' )->getMetaValue()
                    : '',
            ];

            foreach( $link_urls as $key => $link_url){

                $form->add( $key.'_recommendation_url', 'text', [
                    'label' => ucwords($key) .': '. $link_url,
                    'required' => false,
                    'mapped' => false,
                    'data' => null,
                    'attr' => ['class' => 'hide']
                ])


                ;
            }
            $form->end();

            $form->with( 'Print Forms' );
                $form
                    ->add( 'links_pdf', 'text', array(
                            'label' => ' ',
                            'required' => false,
                            'mapped' => false,
                            'attr' => [ 'style' => 'display: none;'],
                            'help' => $help,
                        ));
            $form->end();
            $form->end();
        }
    }
}

