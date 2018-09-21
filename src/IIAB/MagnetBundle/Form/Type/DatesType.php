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

class DatesType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder
			->add('openEnrollment', OpenEnrollmentType::class)
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

		//var_dump($options['data']->getId()); die;
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