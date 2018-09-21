<?php
namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class WritingSampleType extends AbstractType {

    public function buildForm( FormBuilderInterface $builder , array $options ) {

        $submission = $options['submission'];

        $builder
            ->add( 'writing_prompt', 'hidden', [
                'data' => $options['prompt']
            ])

            ->add( 'writing_sample', 'textarea', [
                'label' => 'writing.prompt',
                'attr' => [ 'rows' => '10' ]
            ])

            ->add( 'submit_sample' , 'submit' , array(
                'label' => 'Submit Writing Sample',
                'attr' => array(
                    'class' => 'right radius medium-4 small-12 noMarginBottom',
                    'style' => 'border: 12px solid #FFFFFF;'
                ),
            ) );
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName() {

        return 'writing_sample';
    }

    public function configureOptions( \Symfony\Component\OptionsResolver\OptionsResolver $resolver){
         $resolver->setRequired('submission');
         $resolver->setRequired('prompt');
    }


}