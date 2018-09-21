<?php

namespace IIAB\MagnetBundle\Service\Population;

use IIAB\MagnetBundle\Entity\Population;

class HomeZonePopulation {

    private $magnet_manager;
    private $shared_manager;

    private $tracking_column = 'HomeZone';

    private $population_columns = [
        //'none' => [ 'label' => 'None' ],
    ];

    public function __construct( $magnet_manager, $shared_manager ){

        $this->magnet_manager = $magnet_manager;
        $this->shared_manager = $shared_manager;

        $address_bound_schools = $this->magnet_manager
            ->getRepository( 'IIABMagnetBundle:AddressBoundSchool' )
            ->findAll();

        foreach( $address_bound_schools as $address_bound_school ){
            $this->population_columns[$address_bound_school->getId()] = [
                'label' => $address_bound_school->getName(),
                'start_grade' => $address_bound_school->getStartGrade(),
                'end_grade' => $address_bound_school->getEndGrade(),
            ];
        }
    }

    public function getTrackingValues( $school ){
        return array_keys( $this->getPopulationColumns( $school ) );
    }

    public function initializePopulation( $school, $not_used = null ){

        if( get_class( $school ) == 'IIAB\MagnetBundle\Entity\AddressBoundSchool' ){
            return [];
        }

        $zones = $this->getPopulationColumns( $school );

        $now = new \DateTime();
        $population_records = [];
        foreach( array_keys( $zones ) as $zone_id ){

            $population = $this->createPopulationRecord([
                'type' => 'initial',
                'date_time' => $now,
                'school' => $school,
                'tracking_value' => $zone_id,
                'count' => 0
            ], false);
            $population_records[$zone_id] = $population;
        }

        return $population_records;
    }

    public function getSubmissionHomeZone( $submission ){

        $address_bound_school = $this->magnet_manager->getRepository( 'IIABMagnetBundle:AddressBoundSchool' )->findBy([
            //'name' => $submission->getZonedSchool()
            'alias' => ( !empty( $submission->getStateId() ) )
                ? $submission->getCurrentSchool()
                : 'none'
        ]);

        return (count($address_bound_school)) ? $address_bound_school[0] : 'none';
    }

    public function getSubmissionTrackingValue( $submission ){
        $home_zone = $this->getSubmissionHomeZone( $submission );
        return ( $home_zone != 'none' ) ? $home_zone->getId() : 'none';
    }

    public function getSubmissionTrackingValueName( $submission ){
        $home_zone = $this->getSubmissionHomeZone( $submission );
        return ( $home_zone != 'none' ) ? $home_zone : 'none';
    }

    public function createPopulationRecord( $data ){
        $population = new Population();

        if( strpos( get_class( $data['school'] ), 'IIAB\MagnetBundle\Entity\MagnetSchool' ) !== false ){
            $population
                ->setUpdateType($data['type'])
                ->setUpdateDateTime( $data['date_time'] )
                ->setMagnetSchool( $data['school'] )
                ->setTrackingColumn( $this->tracking_column )
                ->setTrackingValue( $data['tracking_value'] )
                ->setCount( $data['count'] );
        } else if ( strpos( get_class( $data['school'] ), 'IIAB\MagnetBundle\Entity\AddressBoundSchool' ) !== false ){
            $population
                ->setUpdateType($data['type'])
                ->setUpdateDateTime( $data['date_time'] )
                ->setAddressBoundSchool( $data['school'] )
                ->setTrackingColumn( $this->tracking_column )
                ->setTrackingValue( $data['tracking_value'] )
                ->setCount( $data['count'] );
        }
        return $population;
    }

    public function getPopulationColumns( $school = null ){

        if( get_class( $school ) == 'IIAB\MagnetBundle\Entity\AddressBoundSchool' ){
            return [];
        }

        if( empty($school) ){
            return $this->population_columns;
        }

        if( isset( $this->school_hash[$school->getId()] ) ){
            return $this->school_hash[$school->getId()];
        }

        $use_columns = [];
        foreach( $this->population_columns as $column_id => $column ){

            if( $column_id == 'none'
                || ( ( $column['start_grade'] == 99
                        || $column['start_grade'] <= $school->getGrade()
                     )
                     && $column['end_grade'] >= $school->getGrade()
                    )
            ){
                $use_columns[ $column_id ] = $column['label'];
            }
        }

        return $use_columns;
    }

    public function getTrackingColumn(){
        return $this->tracking_column;
    }

    public function getTrackingColumnLabels(){

        $column_labels = [];
        foreach( $this->population_columns as $id => $column ){
            $column_labels[$id] = $column['label'];
        }

        return $column_labels;
    }
}