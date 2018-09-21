<?php

namespace IIAB\MagnetBundle\Service\Population;

use LeanFrog\SharedDataBundle\Entity\Population;

class SharedPopulation {

    private $magnet_manager;
    private $shared_manager;

    private $tracking_column = 'Race';

    private $race_categories = [
        'other' => 'Other',
        'none' => 'Not Specified'
    ];
    private $race_hash = [];

    public function __construct( $magnet_manager, $shared_manager ){

        $this->magnet_manager = $magnet_manager;
        $this->shared_manager = $shared_manager;

        $races = $this->magnet_manager
            ->getRepository( 'IIABMagnetBundle:Race' )
            ->findAll();

        $race_categories = [];
        foreach( $races as $race ){
            if( $race->getReportAsOther() ){
                $this->race_hash[ $race->getId() ] = 'other';
            } else if( $race->getReportAsNoAnswer() ){
                $this->race_hash[ $race->getId() ] = 'none';
            } else {
                $this->race_categories[ $race->getId() ] = $race->getShortName();
                $this->race_hash[ $race->getId() ] = $race->getId();
            }
        }
    }

    public function getTrackingValues( $school ){
        return array_keys( $this->race_categories );
    }

    public function initializePopulation( $school, $not_used = null ){

        $now = new \DateTime();
        $population_records = [];
        foreach( array_keys( $this->race_categories ) as $race_id ){
            $population = $this->createPopulationRecord([
                'type' => 'initial',
                'date_time' => $now,
                'school' => $school,
                'tracking_value' => $race_id,
                'count' => 0
            ], false);
            $population_records[$race_id] = $population;
        }

        return $population_records;
    }

    public function getSubmissionTrackingValue( $submission ){
        return $this->race_hash[ $submission->getRace()->getId() ];
    }

    public function getSubmissionTrackingValueName( $submission ){
        return $this->race_categories[ $this->getSubmissionTrackingValue($submission) ];
    }

    public function convertToSharedSchool( $school ){

        if( strpos( get_class( $data['school'] ), 'IIAB\MagnetBundle\Entity' ) !== false ){
            $key = strpos( get_class( $data['school'] ), 'IIAB\MagnetBundle\Entity\MagnetSchool' ) !== false )
                ? 'mpw_magnet_school'
                : 'mpw_bound_school'

            $shared_school = $this->shared_manager
                ->getRepository( 'LeanFrogSharedBundle:ProgramSchoolData' )
                ->findOneBy([
                    'metaKey' => 'mpw_magnet_school'
                    'metaValue' => $school->getId()
                ]);
            }
            $school = $shared_school->getProgramSchool();
        }
        return $school;
    }

    public function createPopulationRecord( $data ){
        $population = new Population();

        $shared_school = $this->convertToSharedSchool( $school ;)

        $shared_school = $this->shared_manager
            ->getRepository( 'LeanFrogSharedBundle:ProgramSchoolData' )
            ->findOneBy([
                'metaKey' => 'mpw_magnet_school'
                'metaValue' => $school->getId()
            ]);
        }
        $shared_school = $shared_school->getProgramSchool();

        $population
            ->setUpdateType($data['type'])
            ->setUpdateDateTime( $data['date_time'] )
            ->setProgramSchool( $shared_school )
            ->setTrackingColumn( $this->tracking_column )
            ->setTrackingValue( ( is_object( $data['tracking_value'] ) ) ? $data['tracking_value']->getId() : $data['tracking_value'] )
            ->setCount( $data['count'] );
        }
        return $population;
    }

    public function getTrackingColumn(){
        return $this->tracking_column;
    }

    public function getTrackingColumnLabels(){
        return $this->race_categories;
    }
}