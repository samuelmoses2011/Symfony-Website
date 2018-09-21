<?php
/**
 * Company: Image In A Box
 * Date: 1/6/15
 * Time: 2:41 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Form\FormMapper;

class SubmissionCommentAdmin extends AbstractAdmin {

	protected function configureFormFields( FormMapper $form ) {

		$object = $this->getSubject();
		$choices = (is_object( $object )) ? [$object->getUser()] : null;

		$form
			->add('comment')
			->add( 'createdAt', 'sonata_type_date_picker', array(
				'format' => 'yyyy-MM-dd',
				'disabled' => true,
				'required' => false,
				'view_timezone' => 'UTC' ,
				'model_timezone' => 'UTC'
			) )
			->add( 'user' , null, array(
				'label' => 'Created by',
				'disabled' => true,
				'choice_label' => function( $user ) {
					return $user->getFirstName() .' '. $user->getLastName();
				},
				'choices' => $choices
			) );
	}

	public function getNewInstance()
	{
		$user = $this->getConfigurationPool()->getContainer()->get( 'security.token_storage' )->getToken()->getUser();
		$instance = parent::getNewInstance();
		$instance->setUser( $user );

		return $instance;
	}
}