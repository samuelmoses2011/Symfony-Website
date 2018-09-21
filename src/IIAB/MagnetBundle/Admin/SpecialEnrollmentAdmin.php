<?php
/**
 * Company: Image In A Box
 * Date: 12/31/14
 * Time: 2:54 PM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Admin;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use IIAB\MagnetBundle\Entity\AfterPlacementPopulation;
use IIAB\MagnetBundle\Entity\Offered;
use IIAB\MagnetBundle\Entity\Submission;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;

class SpecialEnrollmentAdmin extends AbstractAdmin {

	/**
	 * @var string
	 */
	protected $baseRouteName = 'admin_special_enrollment';

	/**
	 * Default Datagrid values
	 *
	 * @var array
	 */
	protected $datagridValues = array(
		'_page' => 1 ,            // display the first page (default = 1)
		'_sort_order' => 'DESC' , // reverse order (default = 'ASC')
		'_sort_by' => 'createdAt'  // name of the ordered field
		// (default = the model's id field, if any)

		// the '_sort_by' key can be of the form 'mySubModel.mySubSubModel.myField'.
	);

	/**
	 * @var string
	 */
	protected $baseRoutePattern = 'special-enrollment';

	/**
	 * @inheritdoc
	 */
	protected function configureFormFields( FormMapper $form ) {
		global $object;

		$object = $this->getSubject();

		$openEnrollment = ( isset( $object->getOpenEnrollment ) ) ? $object->getOpenEnrollment :
			$this->getConfigurationPool()->getContainer()->get('doctrine')->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findOneBy( array(
			'active' => '1' ,
		) );

		if( $openEnrollment == null ) {
			$openEnrollment = $this->getConfigurationPool()->getContainer()->get('doctrine')->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findOneBy( [], ['id' => 'DESC'] );
		}

		$schools = $this->getConfigurationPool()->getContainer()->get('doctrine')->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findBy( ['openEnrollment' => $openEnrollment, 'active' => 1] );

		$form
			->with( 'Special Enrollment Dates' )
			->add('title', null, array( 'label' => 'Period Name' ))
			->add('openEnrollment', null, [
				'label' => 'Open Enrollment '. $openEnrollment->getYear(),
				'attr' => ['readonly'=>'readonly', 'class'=>'hide'],
				'data' => $openEnrollment
			])
			->add('beginningDate' , 'datetime' , array(
				'label' => 'Starting Date'
			) )
			->add('endingDate' , 'datetime' , array(
				'label' => 'Ending Date'
			) )
			->add('schools', 'entity', array(
				'label' => 'Schools accepting submissions during this period. (leave empty for all schools)',
				'required' => false,
				'expanded' => true,
				'multiple' => true,
				'choices' => $schools,
				'data' => ( count($object->getSchools() ) ) ? $object->getSchools() : $schools,
				'class' => 'IIABMagnetBundle:MagnetSchool'
			) )
			->end()
		;
	}

	protected function configureListFields( ListMapper $list ) {
		$list
			->addIdentifier('id')
			->add('title', null, array( 'label' => 'Period Name' ) )
			->add('openEnrollment', 'entity')
			->add('beginningDate' , null , array( 'label' => 'Start Date' ) )
			->add('endingDate' , null , array( 'label' => 'End Date' ) )
		;
	}

	protected function configureDatagridFilters( DatagridMapper $filter ) {
		$filter
			->add('openEnrollment')
			->add('schools')
		;
	}
}