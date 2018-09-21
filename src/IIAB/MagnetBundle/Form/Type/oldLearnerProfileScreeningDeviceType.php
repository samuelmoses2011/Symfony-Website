<?php

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use IIAB\MagnetBundle\Service\EligibilityRequirementsService;

class LearnerProfileScreeningDeviceType extends AbstractType {

    /**
     * {@inheritdoc}
     */
    public function buildForm( FormBuilderInterface $builder , array $options ) {

        $data = $builder->getData();

        $rating_choices = [
            'In this category the child is among the very lowest in frequency, intensity and/or quality of the behavior in comparison to the reference group' => 1,

            'Behavior is significantly less frequent, etc.' => 2,

            'Behavior is somewhat less frequent, etc.' => 3,

            'Behavior is typical or commonly observed in the reference group' => 4,

            'Behavior is somewhat more frequent, etc.' => 5,

            'Behavior is significantly more frequent, etc.' => 6,

            'In this category, the child is among the very highest in frequency, intensity, and/or quality of the behavior in comparison to the reference group' => 7,


        ]


        $builder
            ->add( 'visual_arts', 'choice', [
                'label' => 'Ability in the Visual Arts',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'help' => 'The ability to draw, paint, script, photograph, or arrange media in a way that suggests unusual talent.'
            ])

            ->add( 'performing_arts', 'choice', [
                'label' => 'Ability in the Performance Arts',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'help' => 'General Definition: The ability to create or perform in the areas of music or drama which suggests unusual talent.'
            ])

            ->add( 'leadership', 'choice', [
                'label' => 'Leadership Qualities-Organizing-Decision Making',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'help' => 'An unusual ability to relate and to motivate other people. '
            ])

            ->add( 'psychomotor', 'choice', [
                'label' => 'Psychomotor Skills and Abilities',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'help' => 'A student who is exceptionally advanced in his/her physical motor abilities.'
            ])

            ->add( 'citizenship', 'choice', [
                'label' => 'Citizenship and/or Behavior',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'help' => 'A student who is exceptionally advanced in his/her physical ability to connect with others socially, who exemplifies good citizenship and promotes social justice.'
            ])

            ->add( 'xx', 'choice', [
                'label' => 'xxx',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'help' => 'xxxx'
            ])

            ->add( 'xx', 'choice', [
                'label' => 'xxx',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'help' => 'xxxx'
            ])

            ->add( 'xx', 'choice', [
                'label' => 'xxx',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'help' => 'xxxx'
            ])

            ->add( 'xx', 'choice', [
                'label' => 'xxx',
                'placeholder' => 'form.option.choose' ,
                'choices' => $rating_choices,
                'help' => 'xxxx'
            ])

            ->add( 'submit', 'submit', [
                'label' => 'Submit Recommendation',
                'attr' => ['class' => 'btn btn-success btn-lg']
            ])
            ;
    }

    public function configureOptions( \Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
         $resolver->setRequired('submission');
    }
}

}