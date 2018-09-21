<?php
/**
 * Company: Image In A Box
 * Date: 7/21/15
 * Time: 1:48 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use IIAB\MagnetBundle\Service\EligibilityRequirementsService;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormEvents;

class EligibilitySettingType extends AbstractType {

    private $eligibility_requirements_service;

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {
        $this->eligibility_requirements_service = $options['eligibility_requirements_service'];

        $builder->addEventListener( FormEvents::PRE_SET_DATA, function ( $event ) {
            $program = $event->getData();
            $form = $event->getForm();

            $eligibility_fields = $this->eligibility_requirements_service->getEligibilityFieldIDs();

            foreach( $eligibility_fields as $key => $details ) {

                $required_by = $this->eligibility_requirements_service->getEligibilityFieldRequiredBy($key, $program);
                $choices = ( is_array( $details['choices'] ) ) ? array_flip( $details['choices'] ) :$details['choices'];
                $display_only = ( isset( $details['display_only'] ) ) ? $details['display_only'] : false;

                if (is_array($choices)) {
                    unset($choices[0]);
                }
                if( is_null( $required_by ) ){
                    $form
                        ->add('program_' . $program->getId() . '_' . $key, 'hidden', array(
                            'data' => '',
                            'mapped' => false,
                        ));
                }

                if( $display_only ) {
                    if ($required_by == 'program') {

                        $form
                            ->add('program_' . $program->getId() . '_' . $key, 'button', array(
                                'label' => 'Display Only',
                            ));

                    } else if ($required_by == 'school') {

                        $thresholds = $this->eligibility_requirements_service->getEligibilityFieldThresholds($key, $program);

                        foreach ($program->getMagnetSchools() as $school) {
                            if(array_key_exists($school->getId(), $thresholds)) {

                                $form
                                    ->add('school_' . $school->getId() . '_' . $key, 'button', array(
                                        'label' => 'Display Only',
                                    ));
                            } else {
                                $form
                                    ->add('school_' . $school->getId() . '_' . $key, 'hidden', array(
                                        'data' => '',
                                        'mapped' => false,
                                    ));
                            }
                        }
                    }

                } else if ($choices == 'number') {

                    if ($required_by == 'program') {

                        $form
                            ->add('program_' . $program->getId() . '_' . $key, 'number', array(
                                'label' => $details['label'] . ' Required Value',
                                'required' => false,
                                'mapped' => false,
                                'data' => floatval( $this->eligibility_requirements_service->getEligibilityFieldThresholds($key, $program) ),
                            ));
                    } else if ($required_by == 'school') {

                        $thresholds = $this->eligibility_requirements_service->getEligibilityFieldThresholds($key, $program);

                        foreach ($program->getMagnetSchools() as $school) {
                            if(array_key_exists($school->getId(), $thresholds)) {

                                $form
                                    ->add('school_' . $school->getId() . '_' . $key, 'number', array(
                                        'label' => $details['label'] . ' Required Value',
                                        'required' => false,
                                        'mapped' => false,
                                        'data' => (isset($thresholds[$school->getId()])) ? floatval( $thresholds[$school->getId()] ) : null,
                                    ));
                            } else {
                                $form
                                    ->add('school_' . $school->getId() . '_' . $key, 'hidden', array(
                                        'data' => '',
                                        'mapped' => false,
                                    ));
                            }
                        }
                    }

                } else {

                    $choices[ 'Ignore Field' ] = 'ignore';

                    if ($required_by == 'program') {

                        $form
                            ->add('program_' . $program->getId() . '_' . $key, 'choice', array(
                                'label' => $details['label'] . ' Required Value',
                                'placeholder' => false,
                                'choices' => $choices,
                                'required' => false,
                                'mapped' => false,
                                'data' => $this->eligibility_requirements_service->getEligibilityFieldThresholds($key, $program),
                            ));
                    } else if ($required_by == 'school') {

                        $thresholds = $this->eligibility_requirements_service->getEligibilityFieldThresholds($key, $program);

                        foreach ($program->getMagnetSchools() as $school) {

                            if(array_key_exists($school->getId(), $thresholds)) {
                                $form
                                    ->add('school_' . $school->getId() . '_' . $key, 'choice', array(
                                        'label' => $details['label'] . ' Required Value',
                                        'placeholder' => false,
                                        'choices' => $choices,
                                        'required' => false,
                                        'mapped' => false,
                                        'data' => (isset($thresholds[$school->getId()])) ? $thresholds[$school->getId()] : null,
                                    ));
                            } else {
                                $form
                                    ->add('school_' . $school->getId() . '_' . $key, 'hidden', array(
                                        'data' => '',
                                        'mapped' => false,
                                    ));
                            }
                        }
                    }
                }
            }

        });
	}

    public function configureOptions( \Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
         $resolver->setRequired('eligibility_requirements_service');
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions( OptionsResolverInterface $resolver ) {

        $resolver->setDefaults( [
            'data_class' => 'IIAB\MagnetBundle\Entity\Program' ,
            'label' => false
        ] );
    }

	public function getName() {

		return 'eligibility_settings';
	}


}