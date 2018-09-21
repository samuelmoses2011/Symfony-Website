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

class SubmissionDataAdmin extends AbstractAdmin {

	protected function configureFormFields( FormMapper $form ) {

		$form
            ->add( 'metaKey' , null , array(
                'label' => 'Key',
                'disabled' => true,
            ))
			->add( 'metaValue' , null , array(
				'label' => 'Value',
                'disabled' => true,
			))
		;
	}
}