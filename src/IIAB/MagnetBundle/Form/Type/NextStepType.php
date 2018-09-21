<?php
/**
 * Company: Image In A Box
 * Date: 3/10/15
 * Time: 11:37 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class NextStepType extends AbstractType {

    /**
     * {@inheritdoc}
     */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder->add('placementMessaging' , 'collection' , array(
			'entry_type' => PlacementMessageType::class ,
			'label' => false
		) );
	}

	public function getName() {

		return 'nextStep';
	}
}