<?php
/**
 * Company: Image In A Box
 * Date: 12/31/14
 * Time: 2:41 PM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;

class OpenEnrollmentAdmin extends AbstractAdmin {

	/**
	 * @var string
	 */
	protected $baseRouteName = 'admin_open_enrollment';

	/**
	 * @var string
	 */
	protected $baseRoutePattern = 'open-enrollment';

	protected function configureFormFields( FormMapper $form ) {

		$form
			->with( 'Year & Style' , array( 'class' => 'col-md-6' ) )->end()
			->with( 'Open Date/Time' , array( 'class' => 'col-md-6' , 'description' => 'The Date &amp; Time the Open Enrollment is allowed to accept applications. Time is also in 24 hour format.' ) )->end()
		;
		$form
			->with( 'Year & Style' )
			->add( 'year' )
			->add( 'confirmationStyle' , null , array(
				'sonata_help' => 'This is used in the confirmation number') )
			->end()
			->with( 'Open Date/Time' )
			->add( 'beginningDate' , 'datetime' , array(
				'date_format' => IntlDateFormatter::LONG ,
			) )
			->add( 'endingDate' , null , array(
				'date_format' => IntlDateFormatter::LONG ,
			) )
			->add( 'latePlacementBeginningDate' , 'datetime' , array(
				'date_format' => IntlDateFormatter::LONG ,
			) )
			->add( 'latePlacementEndingDate' , null , array(
				'date_format' => IntlDateFormatter::LONG ,
			) )
			->end();



			/*
			->with( 'Racial Composition' )
			->add( 'HRCWhite' , null , array( 'label' => 'White Racial Composition' ) )
			->add( 'HRCBlack' , null , array( 'label' => 'Black Racial Composition' ) )
			->add( 'HRCOther' , null , array( 'label' => 'Other Racial Composition' ) )
			->add( 'maxPercentSwing' , null , array( 'label' => 'Max Racial Composition Swing (ex: +-15)' ) )
			->end()
			*/
	}

	protected function configureListFields( ListMapper $list ) {

		$list
			->addIdentifier( 'year' )
			->add( 'confirmationStyle' )
			->add( 'beginningDate' )
			->add( 'endingDate' );
	}

	protected function configureDatagridFilters( DatagridMapper $filter ) {

		$filter
			->add( 'year' )
			->add( 'confirmationStyle' )
			->add( 'beginningDate' )
			->add( 'endingDate' );
	}

	protected function configureRoutes( RouteCollection $collection ) {

		$collection->remove( 'delete' );
	}

}