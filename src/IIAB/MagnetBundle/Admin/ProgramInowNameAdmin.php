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

class ProgramInowNameAdmin extends AbstractAdmin {

	/**
	 * @param FormMapper $form
	 */
	protected function configureFormFields( FormMapper $form ) {

		$form
			->add( 'iNowName' , null , array(
				'label' => 'iNow Name',
			))
		;
	}
}