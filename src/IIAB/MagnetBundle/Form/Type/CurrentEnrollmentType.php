<?php

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class CurrentEnrollmentType extends AbstractType {

    public function buildForm( FormBuilderInterface $builder , array $options ) {

        foreach( $options['enrollment_columns'] as $id => $enrollment ){
            $builder
                ->add( $enrollment->getSchool()->getId().'_'.$enrollment->getGrade() , 'integer' , array(
                    'label' => MYPICK_CONFIG['grade_labels'][ $enrollment->getGrade() ],
                    'required' => false,
                    'data' => $enrollment->getCount()
                ) );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
        $resolver->setDefaults( array(
            'enrollment_columns' => [],
        ) );
    }

    public function getName() {

        return 'current_enrollment';
    }

}