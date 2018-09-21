<?php
/**
 * Company: Image In A Box
 * Date: 7/20/15
 * Time: 2:19 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use IIAB\MagnetBundle\Service\EligibilityRequirementsService;

class PlacementEligibilityType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

        $placement = $builder->getData();

	    $builder
			->add('saveSettings', 'submit', ['attr' => ['class' => 'btn btn-success btn-lg']])
            ->add('eligibility_settings', 'collection', [
                'label' => false,
                'entry_type' => EligibilitySettingType::class,
                'entry_options' => [
                    'eligibility_requirements_service' => $options['eligibility_requirements_service']
                ],
                'required' => false
            ]);

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
			'data_class' => 'IIAB\MagnetBundle\Entity\Placement' ,
		] );
	}

	/**
	 *
	 */
	public function getName() {
		return 'placement_eligibility';
	}
}