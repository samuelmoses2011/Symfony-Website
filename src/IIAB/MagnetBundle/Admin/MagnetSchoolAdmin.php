<?php
/**
 * Company: Image In A Box
 * Date: 12/31/14
 * Time: 2:30 PM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Admin;


use IIAB\MagnetBundle\Entity\MagnetSchool;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;

class MagnetSchoolAdmin extends AbstractAdmin {

	protected $baseRouteName = 'admin_magnet_schools';

	protected $baseRoutePattern = 'magnet-schools';

	protected $datagridValues = array(
		'_page' => 1 ,            // display the first page (default = 1)
		'_sort_order' => 'DESC' , // reverse order (default = 'ASC')
		'_sort_by' => 'id'  // name of the ordered field
		// (default = the model's id field, if any)

		// the '_sort_by' key can be of the form 'mySubModel.mySubSubModel.myField'.
	);

	protected function configureFormFields( FormMapper $form ) {

		/** @var \IIAB\MagnetBundle\Entity\MagnetSchool $subject */
		$subject = $this->getSubject();

		$disabled = ( $subject->getId() ? true : false );

		$form
			->add( 'name' , null , array(
				'required' => true ,
			) )
			->add( 'grade' )
			->add( 'active' , null , array(
				'required' => false ,
			) )
			->add( 'address' , null , array(
				'sonata_help' => 'Ex: 123 Test Drive, Tuscaloosa, AL, 35401'
			) );
		if( $disabled ) {
			$form->add( 'program' , null , [
				'query_builder' => function ( $er ) {
					$subject = $this->getSubject();
					$qb = $er->createQueryBuilder( 'p' )
						->where( 'p.openEnrollment = :enrollment')
						->setParameter( 'enrollment' , $subject->getOpenEnrollment() )
						->orderBy( 'p.name' , 'ASC' );
					return $qb;
				}
			] );
		}
		$form->add( 'openEnrollment' , null , array(
				'label' => 'Open Enrollment' ,
				'required' => true ,
				'disabled' => $disabled ,
				'query_builder' => function ( $er ) {
					$qb = $er->createQueryBuilder( 'oe' )
						->orderBy( 'oe.year' , 'DESC' );
					return $qb;
				}
			) );
	}

	protected function configureListFields( ListMapper $list ) {

		$list
			->addIdentifier( 'name' )
			->add( 'grade' )
			->add( 'openEnrollment' )
			->add( 'active' , null , array( 'editable' => true ) );
	}

	protected function configureDatagridFilters( DatagridMapper $filter ) {

		$filter
			->add( 'name' )
			->add( 'grade' )
			->add( 'openEnrollment' )
			->add( 'active' );
	}

	protected function configureRoutes( RouteCollection $collection ) {

		$collection->remove( 'batch' );
	}


	/**
	 * {@inheritdoc}
	 */
	public function delete( $object ) {

		if( $this->tryToDelete( $object ) == false ) {
			throw new ModelManagerException( 'You cannot delete a Program that has Magnet Schools assign to it that already have submissions.' , 2001 );
		} else {
			parent::delete( $object );
		}
	}


	/**
	 * @param MagnetSchool $magnetSchool
	 *
	 * @return bool
	 */
	private function tryToDelete( MagnetSchool $magnetSchool ) {

		$submissions = $this->getConfigurationPool()->getContainer()->get( 'doctrine' )->getManager()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
			->where( 's.firstChoice = :school' )
			->orWhere( 's.secondChoice = :school' )
			->orWhere( 's.thirdChoice = :school' )
			->setParameter( 'school' , $magnetSchool )
			->getQuery()
			->getResult();

		//If there are submission tried to this Schools, we cannot allow deletion.
		if( count( $submissions ) > 0 ) {
			return false;
		}

		$submissions = null;

		return true;
	}
}