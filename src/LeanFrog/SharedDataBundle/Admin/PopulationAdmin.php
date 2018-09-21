<?php

namespace LeanFrog\SharedDataBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use LeanFrog\SharedDataBundle\Service\SharedPopulationService;
use LeanFrog\SharedDataBundle\Entity\Population;

class PopulationAdmin extends AbstractAdmin {

    /**
     * @var string
     */
    protected $baseRouteName = 'admin_population';

    /**
     * @var string
     */
    protected $baseRoutePattern = 'admin_population';

    /**
     * @var array
     */
    protected $datagridValues = array(
        '_page' => 1 ,            // display the first page (default = 1)
        '_sort_order' => 'DESC' , // reverse order (default = 'ASC')
        '_sort_by' => 'id'  // name of the ordered field
        // (default = the model's id field, if any)

        // the '_sort_by' key can be of the form 'mySubModel.mySubSubModel.myField'.
    );

    /**
     * @param FormMapper $form
     */
    protected function configureFormFields( FormMapper $form ) {

        $doctrine = $this->getConfigurationPool()->getContainer()->get('doctrine');
        $shared_manager = $doctrine->getEntityManager('shared');

        $object = $this->getSubject();

        $population_service = new SharedPopulationService( $doctrine );

        $history = $population_service->getPopulationHistoryReport( $object->getProgramSchool() );

        //$current_population = $population_service->getCurrentPopulation( $object->getProgramSchool() );
        //$history = $population_service->getPopulationHistory( $object->getProgramSchool() );

        // $current_population_data = [
        //     'academicYear' => $object->getAcademicYear(),
        //     'updateDateTime' => $object->getUpdateDateTime(),
        //     'updateType' => $object->getUpdateType(),
        //     'Black' => $current_population['Race']['Black']->getCount(),
        //     'White' => $current_population['Race']['White']->getCount(),
        //     'Other' => $current_population['Race']['Other']->getCount(),
        //     'None' => $current_population['Race']['None']->getCount(),
        // ];

        $current_year_key = array_keys( $history );
        $current_year_key = end( $current_year_key );

        $current_population_data = [
            'academicYear' => $history[$current_year_key]['final']['academic_year'],
            'updateDateTime' => $history[$current_year_key]['final']['date'],
            'updateType' => $history[$current_year_key]['final']['type'],
            'Black' => $history[$current_year_key]['final']['race']['black'],
            'White' => $history[$current_year_key]['final']['race']['white'],
            'Other' => $history[$current_year_key]['final']['race']['other'],
            'None' => $history[$current_year_key]['final']['race']['none'],
        ];


        $history_data = [];
        foreach( $history[$current_year_key]['history'] as $population ){

            $data = [
                'academicYear' => null,
                'updateDateTime' => $population['date'],
                'updateType' => $population['type'],
                'Black' => $population['race']['black'],
                'White' => $population['race']['white'],
                'Other' => $population['race']['other'],
                'None' => $population['race']['none'],
            ];

            $history_data[] = $data;
        }
        $history_data = ( $history_data ) ? array_reverse( $history_data ) : null;

        $form
            ->add( 'schoolName', 'text', [
                'attr' => ['readonly' => 'readonly'],
                'mapped' => false,
                'data' => $object->getProgramSchool(),
            ])

            ->add( 'current_population', PopulationRowType::class, [
                'label' => 'Projected Population Changes',
                'mapped' => false,
                'required' => false,
                'data' => $current_population_data,
                'display_date' => false,
                'display_type' => false,
                'attr' => ['readonly' => 'readonly'],
            ])

            ->add( 'history' , 'collection' , array(
                'entry_type' => PopulationRowType::class ,
                'allow_delete' => false ,
                'allow_add' => false ,
                'delete_empty' => false ,
                'prototype_name' => 'Population' ,
                'label' => 'Population History' ,
                'data' => $history_data,
                'mapped' => false,
                'required' => false,
                'entry_options' => [
                    'attr' => ['readonly' => 'readonly'],
                    'display_year' => false,
                ] ,
            ))
        ;
    }

    /**
     * @param ListMapper $list
     */
    protected function configureListFields( ListMapper $list ) {

        $list
            ->addIdentifier( 'programSchool' );
    }

    /**
     * @param string $context
     *
     * @return \Sonata\AdminBundle\Datagrid\ProxyQueryInterface
     */
    public function createQuery( $context = 'list' ) {

        $query = parent::createQuery( $context );
        $query->orderBy( $query->getRootAlias() . '.id', 'DESC' );
        $results = $query->execute();

        $programSchools = [];
        foreach( $results as $population ){

            if( $population->getProgramSchool() != null ){
                if( empty( $programSchools[$population->getProgramSchool()->getId()])
                    || $population->getId() > $programSchools[$population->getProgramSchool()->getId()]
                ){
                    $programSchools[$population->getProgramSchool()->getId()] = $population->getId();
                }
            }
        }

        $query = parent::createQuery( $context );
        $query
            ->andWhere( $query->getRootAlias() . '.id IN (:schools)' )
            ->setParameter( 'schools', $programSchools );

        return $query;
    }

    /**
     * Sets the ProgramSchool data.
     *
     * @param \LeanFrog\SharedDataBundle\Entity\ProgramSchool $object
     *
     * @return void
     */
    public function preUpdate( $object ) {

        $uniqid = $this->getRequest()->query->get( 'uniqid' );
        $formData = $this->getRequest()->request->get( $uniqid );

        $em = $this->getConfigurationPool()
        ->getContainer()->get('doctrine')->getManager('shared');

        $now = new \DateTime();

        $uow = $em->getUnitOfWork();
        $OriginalEntityData = $uow->getOriginalEntityData( $object );

        if( isset( $formData['current_population'] ) ){
            $population_service = new SharedPopulationService( $this->getConfigurationPool()->getContainer()->get('doctrine') );
            $current_population = $population_service->getCurrentPopulation( $object->getProgramSchool() );

            $current_population_data = [
                'updateDateTime' => $object->getUpdateDateTime(),
                'Black' => $current_population['Race']['Black']->getCount(),
                'White' => $current_population['Race']['White']->getCount(),
                'Other' => $current_population['Race']['Other']->getCount(),
            ];

            foreach( array_keys( $current_population['Race'] ) as $race ){

                if( $formData['current_population'][$race] != $current_population['Race'][$race]->getCount() ){
                    $race_index = $population_service->getRaceIndex( $race );

                    $new_population = new Population();
                    $new_population
                        ->setProgramSchool( $object->getProgramSchool() )
                        ->setUpdateType('adjustment')
                        ->setUpdateDateTime( $now )
                        ->setTrackingColumn( 'Race' )
                        ->setTrackingValue( $race_index )
                        ->setCount( $formData['current_population'][$race] );
                        $em->persist( $new_population );
                    }
            }
        }

        $em->flush();
    }

    protected function configureRoutes( RouteCollection $collection ) {

        //Clear all routes except list.
        $collection->remove( 'batch' );
        $collection->remove( 'delete' );
        $collection->remove( 'export' );
    }
}