<?php
/**
 * Company: Image In A Box
 * Date: 7/20/15
 * Time: 4:00 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class OpenEnrollmentType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder
			->add( 'year' , null , [ 'label' => 'School Year' ])
			->add( 'confirmationStyle' )
			->add( 'beginningDate' , null, [ 'label' => 'Beginning date (1st day to accept applications)' ] )
			->add( 'endingDate' , null , [ 'label' => 'Ending date (last day to accept applications)' ] )
			->add( 'latePlacementBeginningDate' , 'date' , [ 'label' => 'After Placement Beginning date (1st day to accept late applications)' ] )
			->add( 'latePlacementEndingDate' , 'date' , [ 'label' => 'After Placement Ending date (last day to accept applications)' ] )
			->add( 'HRCWhite' , null , [ 'required' => false , 'label' => 'HRC White (%)' ] )
			->add( 'HRCBlack' , null , [ 'required' => false , 'label' => 'HRC Black (%)' ] )
			->add( 'HRCOther' , null , [ 'required' => false , 'label' => 'HRC Other (%)' ] )
			->add( 'maxPercentSwing' , null , [ 'required' => false , 'label' => 'Max percent swing (+/- %)' ] )
		;
	}

	/**
     * {@inheritdoc}
     */
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
		$resolver->setDefaults( [
			'data_class' => 'IIAB\MagnetBundle\Entity\OpenEnrollment'
		] );
    }

	public function getName() {

		return 'openEnrollment';
	}
}