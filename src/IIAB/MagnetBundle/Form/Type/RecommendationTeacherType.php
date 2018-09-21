<?php

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use IIAB\MagnetBundle\Service\EligibilityRequirementsService;

class RecommendationTeacherType extends AbstractType {

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

        $builder

            ->add( 'name', 'text', [
                'label' => '',
                'disabled' => $maybe_disabled,
                'data' => (isset($data[ str_replace('recommendation_', '', $recommendation_type) .'_teacher_name']) ) ? $data[str_replace('recommendation_', '', $recommendation_type) .'_teacher_name'] : null,
                'attr' => [ 'style' => 'display: inline-block; width: auto;' ],
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
            ->add( 'class_assignments', 'choice', [
                'label' => '1. Does the student demonstrate excellence on class assignments and projects?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_class_assignments']) ) ? $data[$recommendation_type.'_class_assignments'] : null,
            ])
            ->add( 'homework', 'choice', [
                'label' => '2. Does the student complete homework and outside-of-class work in a timely and thorough manner?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_homework']) ) ? $data[$recommendation_type.'_homework'] : null,
            ])
            ->add( 'new_concepts', 'choice', [
                'label' => '3. Does the student grasp new or different concepts readily?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_new_concepts']) ) ? $data[$recommendation_type.'_new_concepts'] : null,
            ])
            ->add( 'unique_conclusions', 'choice', [
                'label' => '4. Does the student challenge, speculate, make unusual associations, or draw unique conclusions?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_unique_conclusions']) ) ? $data[$recommendation_type.'_unique_conclusions'] : null,
            ])
            ->add( 'initiative', 'choice', [
                'label' => '5. Does the student take the initiative for his/her own learning?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_initiative']) ) ? $data[$recommendation_type.'_initiative'] : null,
            ])
            ->add( 'communication', 'choice', [
                'label' => "6. Is the student's oral and written communication clear, mature, and correct?",
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_communication']) ) ? $data[$recommendation_type.'_communication'] : null,
            ])
            ->add( 'recall', 'choice', [
                'label' => '7. Does the student demonstrate quick recall and mastery of factual information?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_recall']) ) ? $data[$recommendation_type.'_recall'] : null,
            ])

            ->add( 'loves_learning', 'choice', [
                'label' => '1. Does the student show genuine excitement and enthusiasm for learning?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_loves_learning']) ) ? $data[$recommendation_type.'_loves_learning'] : null,
            ])

            ->add( 'self_correcting', 'choice', [
                'label' => '2. Is the student able to be self-critical and self-correcting?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_self_correcting']) ) ? $data[$recommendation_type.'_self_correcting'] : null,
            ])
            ->add( 'responsibility', 'choice', [
                'label' => '3. Does the student handle outside responsibilities as well as school demands?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_responsibility']) ) ? $data[$recommendation_type.'_responsibility'] : null,
            ])
            ->add( 'curiosity', 'choice', [
                'label' => '4. Is the student intellectually playful, curious, and imaginative?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_curiosity']) ) ? $data[$recommendation_type.'_curiosity'] : null,
            ])
            ->add( 'confidence', 'choice', [
                'label' => '5. Is the student self-confident and emotionally secure?',
                'placeholder' => 'form.option.choose' ,
                'required' => false,
                'choices' => [
                    'Never' => 0,
                    'Seldom' => 1,
                    'Sometimes' => 2,
                    'Usually' => 3,
                    'Always' => 4
                ],
                'disabled' => $maybe_disabled,
                'data' => (isset($data[$recommendation_type.'_confidence']) ) ? $data[$recommendation_type.'_confidence'] : null,
            ]);
            // ->add( 'comments', 'textarea', [
            //     'label' => 'Comments (optional)',
            //     'attr' => ['rows' => 5]
            // ]);

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