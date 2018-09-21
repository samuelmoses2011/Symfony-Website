<?php

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use IIAB\MagnetBundle\Service\LearnerScreeningDeviceService;

class LearnerScreeningDeviceType extends AbstractType {

    /**
     * {@inheritdoc}
     */
    public function buildForm( FormBuilderInterface $builder , array $options ) {

        $additional_data = $options['submission']->getAdditionalData(true);

        $data = [];
        foreach( $additional_data as $datum ){
            $data[ $datum->getMetaKey() ] = $datum->getMetaValue();
        }

        $maybe_disabled = false;
        if( isset( $options['mapped'] ) && $options['mapped'] === false ){
            $maybe_disabled = true;
        }

        $rating_choices = LearnerScreeningDeviceService::getRatingFormChoices();

        if( $options['scoring'] ){
            $choices = [];
            foreach( $rating_choices as $score ){
                $choices[ $score ] = $score;
            }
            $rating_choices = $choices;
        }

        $builder

            ->add( 'name', 'text', [
                'label' => '',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['homeroom_teacher_name']) ) ? $data['homeroom_teacher_name'] : null,
                'attr' => [ 'style' => 'display: inline-block; width: auto;' ],
            ])


            ->add( 'visual_arts', 'choice', [
                'label' => 'Ability in the Visual Arts',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'sonata_help' => 'The ability to draw, paint, script, photograph, or arrange media in a way that suggests unusual talent.',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['learner_screening_device_visual_arts']) ) ? $data['learner_screening_device_visual_arts'] : null,
            ])

            ->add( 'performing_arts', 'choice', [
                'label' => 'Ability in the Performance Arts',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'sonata_help' => 'General Definition: The ability to create or perform in the areas of music or drama which suggests unusual talent.',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['learner_screening_device_performing_arts']) ) ? $data['learner_screening_device_performing_arts'] : null,
            ])

            ->add( 'leadership', 'choice', [
                'label' => 'Leadership Qualities-Organizing-Decision Making',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'sonata_help' => 'An unusual ability to relate and to motivate other people. ',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['learner_screening_device_leadership']) ) ? $data['learner_screening_device_leadership'] : null,
            ])

            ->add( 'psychomotor', 'choice', [
                'label' => 'Psychomotor Skills and Abilities',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'sonata_help' => 'A student who is exceptionally advanced in his/her physical motor abilities.',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['learner_screening_device_psychomotor']) ) ? $data['learner_screening_device_psychomotor'] : null,
            ])

            ->add( 'citizenship', 'choice', [
                'label' => 'Citizenship and/or Behavior',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'sonata_help' => 'A student who is exceptionally advanced in his/her physical ability to connect with others socially, who exemplifies good citizenship and promotes social justice.',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['learner_screening_device_citizenship']) ) ? $data['learner_screening_device_citizenship'] : null,
            ])

            ->add( 'creative_thinking', 'choice', [
                'label' => 'Creative or Productive Thinking',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'sonata_help' => 'An unusual ability to use divergent/evaluative thinking often evidenced by frequent and exploratory questioning.',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['learner_screening_device_creative_thinking']) ) ? $data['learner_screening_device_creative_thinking'] : null,
            ])

            ->add( 'abstract_thinking', 'choice', [
                'label' => 'Use of Spatial and Abstract Thinking ',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'sonata_help' => 'Unusual talent for visualizing spatial and abstract ideas without reference to a concrete thought process.',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['learner_screening_device_abstract_thinking']) ) ? $data['learner_screening_device_abstract_thinking'] : null,
            ])

            ->add( 'general_intellect', 'choice', [
                'label' => 'General Intellectual Ability ',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'sonata_help' => 'A demonstrated excellence in most academic areas or in one or more areas.',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['learner_screening_device_general_intellect']) ) ? $data['learner_screening_device_general_intellect'] : null,
            ])

            ->add( 'cultural', 'choice', [
                'label' => 'Talent Associated with Cultural Heritage ',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'sonata_help' => 'Given his/her cultural background the student demonstrates unusual ability to cope with his/her present environment (ethnic, economic, social).',
                'disabled' => $maybe_disabled,
                'data' => (isset($data['learner_screening_device_cultural']) ) ? $data['learner_screening_device_cultural'] : null,
            ]);

        if( isset( $options['mapped'] ) && $options['mapped'] !== false ){
            $builder
            ->add( 'submit', 'submit', [
                'label' => 'Submit Learner Screening Device',
                'attr' => ['class' => 'btn btn-success btn-lg']
            ]);
        }
    }

    public function configureOptions( \Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'scoring' => []
        ]);

         $resolver->setRequired('submission');
    }

}