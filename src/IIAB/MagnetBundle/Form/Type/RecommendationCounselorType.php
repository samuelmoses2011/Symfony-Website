<?php

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use IIAB\MagnetBundle\Service\EligibilityRequirementsService;

class RecommendationCounselorType extends AbstractType {

    /**
     * {@inheritdoc}
     */
    public function buildForm( FormBuilderInterface $builder , array $options ) {

        $recommendation_type = ( strpos( $options['recommendation_type'], 'recommendation_' ) === 0 )
            ? $options['recommendation_type']
            : 'recommendation_' . $options['recommendation_type'];

        $additional_data = $options['submission']->getAdditionalData(true);

        $data = [];
        foreach( $additional_data as $datum ){
            $data[ $datum->getMetaKey() ] = $datum->getMetaValue();
        }

        $maybe_disabled = false;
        if( isset( $options['mapped'] ) && $options['mapped'] === false ){
            $maybe_disabled = true;
        }

        //$builder

            // ->add( 'plan504', 'choice', [
            //     'label' => '504 (Accommodation Plan)',
            //     'placeholder' => 'form.option.choose' ,
            //     'required' => true,
            //     'choices' => [
            //         'No' => 0,
            //         'Yes' => 1,
            //     ],
            //     'disabled' => $maybe_disabled,
            //     'data' => (isset($data[$recommendation_type.'_overall_recommendation']) ) ? $data[$recommendation_type.'_overall_recommendation'] : null,
            // ])

            // ->add( 'planGEP', 'choice', [
            //     'label' => 'GEP (Gifted Education Plan)',
            //     'placeholder' => 'form.option.choose' ,
            //     'required' => true,
            //     'choices' => [
            //         'No' => 0,
            //         'Yes' => 1,
            //     ],
            //     'disabled' => $maybe_disabled,
            //     'data' => (isset($data[$recommendation_type.'_overall_recommendation']) ) ? $data[$recommendation_type.'_overall_recommendation'] : null,
            // ])

            // ->add( 'planIEP', 'choice', [
            //     'label' => 'IEP (Individualized Education Plan)',
            //     'placeholder' => 'form.option.choose' ,
            //     'required' => true,
            //     'choices' => [
            //         'No' => 0,
            //         'Yes' => 1,
            //     ],
            //     'disabled' => $maybe_disabled,
            //     'data' => (isset($data[$recommendation_type.'_overall_recommendation']) ) ? $data[$recommendation_type.'_overall_recommendation'] : null,
            // ])

            // ->add( 'disciplineInfractions', 'choice', [
            //     'label' => 'Has this student received a major disciplinary infraction, including any Class III infractions?',
            //     'placeholder' => 'form.option.choose' ,
            //     'required' => true,
            //     'choices' => [
            //         'No' => 0,
            //         'Yes' => 1,
            //     ],
            //     'disabled' => $maybe_disabled,
            //     'data' => (isset($data[$recommendation_type.'_overall_recommendation']) ) ? $data[$recommendation_type.'_overall_recommendation'] : null,
            // ]);

            // if( isset( $options['mapped'] ) && $options['mapped'] !== false ){
            //     $builder
            //     ->add( 'supportFiles' , CollectionType::class , [
            //             'required' => false,
            //             'entry_type' => FilePDFType::class,
            //             'allow_add' => true,
            //             'by_reference' => false,
            //             'entry_options' => [
            //                 'label' => false,
            //                 'usage_choices' => [
            //                     '504 (Accommodation Plan)' => '504',
            //                     'GEP (Gifted Education Plan)'=>'GEP',
            //                     'IEP (Individualized Education Plan)' => 'IEP',
            //                     'Disciplinary Infraction' => 'discipline'
            //                 ]
            //             ],
            //         ] );
            // } else {

            //     foreach( $data as $meta_key => $meta_value ){

            //         if( strpos($meta_key, 'recommendation_counselor_support_file') === 0 ){

            //             $label = str_replace('recommendation_counselor_support_file', '', $meta_key );
            //             $label = str_replace('_', ' ', $label );
            //             $label = 'View '. ucwords( $label ) .' Support File';

            //             $server_url = explode('/', $_SERVER['REQUEST_URI'] );

            //             $url = '';
            //             foreach( $server_url as $url_part ){
            //                 if( in_array($url_part, [ 'admin', 'app.php', 'app_dev.php'] ) ){
            //                     break;
            //                 }
            //                 $url .= $url_part.'/';
            //             }
            //             $url .= $meta_value;

            //             $builder
            //                 ->add( 'submission_'. $meta_key, 'button', array(
            //                     'label' => $label,
            //                     'attr' => [
            //                         'onclick' => "window.open('".$url."'); return false;",
            //                         'style' => 'margin-bottom: 15px;',
            //                     ]
            //                 ));
            //         }
            //     }
            // }

            $builder
            ->add( 'name', 'text', [
                'label' => '',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['counselor_name']) ) ? $data['counselor_name'] : null,
            ])

            ->add( 'attendance', 'choice', [
                'label' => 'Attendance',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Below Average' => 0,
                    'Barely Average' => 1,
                    'Average' => 2,
                    'Above Average' => 3,
                    'Steller' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_attendance']) ) ? $data[$recommendation_type.'_attendance'] : null,
            ])

            ->add( 'workEthic', 'choice', [
                'label' => 'Work Ethic',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Below Average' => 0,
                    'Barely Average' => 1,
                    'Average' => 2,
                    'Above Average' => 3,
                    'Steller' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_workEthic']) ) ? $data[$recommendation_type.'_workEthic'] : null,
            ])

            ->add( 'maturity', 'choice', [
                'label' => 'Maturity to handle difficult tasks',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Below Average' => 0,
                    'Barely Average' => 1,
                    'Average' => 2,
                    'Above Average' => 3,
                    'Steller' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_maturity']) ) ? $data[$recommendation_type.'_maturity'] : null,
            ])

            ->add( 'peerInteraction', 'choice', [
                'label' => 'Peer interaction',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Below Average' => 0,
                    'Barely Average' => 1,
                    'Average' => 2,
                    'Above Average' => 3,
                    'Steller' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_peerInteraction']) ) ? $data[$recommendation_type.'_peerInteraction'] : null,
            ])

            ->add( 'overall_recommendation', 'choice', [
                'label' => 'Overall Recommendation',
                'placeholder' => 'form.option.choose' ,
                'choices' => [
                    'Do Not Recommend' => 0,
                    'Recommend' => 1,
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_overall_recommendation']) ) ? $data[$recommendation_type.'_overall_recommendation'] : null,
            ])

            ->add( 'comments', 'textarea',[
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_comments']) ) ? $data[$recommendation_type.'_comments'] : null,
            ] );

            if( isset( $options['mapped'] ) && $options['mapped'] !== false ){
                $builder
                ->add( 'submit', 'submit', [
                    'label' => 'Submit Recommendation',
                    'attr' => ['class' => 'btn btn-success btn-lg']
                ]);
            }

    }

    public function configureOptions( \Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
         $resolver->setRequired('submission');
         $resolver->setRequired('recommendation_type');
    }
}