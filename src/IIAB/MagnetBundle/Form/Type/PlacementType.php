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

class PlacementType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder
			->add('openEnrollment', OpenEnrollmentType::class )
			//->add( 'emailAddress' ) -- holding off until all other development is completed.
			->add('onlineEndTime')
			->add('offlineEndTime')
			->add('waitListExpireTime', null, ['label' => 'Wait List Expiration Date'])
			->add('preKDateCutOff', null, [
				'label' => 'PreK Birthday Cut Off',
				'required' => true ,
				'years' => range(Date('Y') - 6, Date('Y')),
				'help' => 'Submissions for children born after this date applying for Pre Kindergarten will not be accepted.'
			])
			->add('kindergartenDateCutOff', null, [
				'label' => 'Kindergarten Birthday Cut Off',
				'required' => true ,
				'years' => range(Date('Y') - 7, Date('Y')),
				'help' => 'Submissions for children born after this date applying for Kindergarten will not be accepted.'
			])
			->add('firstGradeDateCutOff', null, [
				'label' => 'First Grade Birthday Cut Off',
				'required' => true ,
				'years' => range(Date('Y') - 8, Date('Y')),
				'help' => 'Submissions for children born after this date applying for First Grade will not be accepted.'
			])
            ->add('transcriptDueDate', null, ['label' => 'Transcript Due Date', 'help' => 'After this date, submissions that have no grades, will be changed to Denied Due to no Transcript.'])
			->add('waitListOnlineEndTime')
			->add('waitListOfflineEndTime')
			->add('registrationNewStartDate' , null , [ 'label' => 'Registration for New Students' ])
			->add('registrationCurrentStartDate' , null , [ 'label' => 'Registration for Current Students' ] )
			->add('nextSchoolYear')
			->add('nextYear')
			->add('saveSettings', 'submit', ['attr' => ['class' => 'btn btn-success btn-lg']]);

		// If we're not createing a new Placement add the settings
		if( $options['data']->getId() ) {
			// This is a very large form.  Increase the memory limit if there isn't enough
			if( preg_replace( "/[^0-9,.]/", "", ini_get( 'memory_limit' ) ) < 512 ){
				ini_set('memory_limit','512M');
			}

			$builder
				->add( 'eligibility' , null , [ 'required' => false , 'label' => 'Enable eligibility requirement for this enrollment?' , 'help' => 'Does this Enrollment require Eligibility settings?' ] )

				->add('committee_settings', 'collection', [
					'label' => false,
					'entry_type' => MagnetSchoolSettingType::class
				])

				->add('eligibility_settings', 'collection', [
					'label' => false,
					'entry_type' => EligibilitySettingType::class,
				])
                ->add('gpa_settings', 'collection', [
                	'label' => false,
                	'entry_type' => GpaSettingType::class
                ])
				->add('next_step', 'collection', [
					'label' => false,
					'entry_type' => PlacementMessageType::class
				]);
		} else {
			$builder
				->add('selectedSchools', 'collection', [
					'label' => false,
					'entry_type' => SelectedSchoolsType::class
				]);
			;
		}
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
		return 'placement';
	}
}