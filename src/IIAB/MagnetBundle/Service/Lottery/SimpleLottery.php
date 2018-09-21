<?php

namespace IIAB\MagnetBundle\Service\Lottery;

use IIAB\MagnetBundle\Service\PopulationService;

class SimpleLottery {

    private $magnet_manager;
    private $shared_manager;
    private $population_service;

    public function __construct( $container = null ){

        if( !empty( $container ) ){
            $this->magnet_manager = $container->get( 'doctrine' )->getManager();
            $this->shared_manager = $container->get( 'doctrine' )->getManager('shared');

            $this->population_service = new PopulationService( $container->get( 'doctrine' ) );
        }
    }

    public function getRoundsRequired(){
        return 1;
    }

    public function getTrackingColumn(){
        return MYPICK_CONFIG['lottery']['types']['simple']['tracking_column'];
    }

    public function doesSubmissionPassRequirements($submission, $magnetSchool, $focus, $round ){
        return [true,''];
    }

    public function sortSubmissions( $submissions ){
        return $submissions;
    }

    public function getTrackingValue($submission){
        return $this->population_service->getSubmissionTrackingValue( $submission, $this->getTrackingColumn() );
    }

    public function getSchoolSlotsByMethod( $magnetSchool, $focus_area, $current_population ){
        return [];
    }

    public function useSlotsInRound( $round ){
        return false;
    }

    public function maybeRestartRound( $round, $finished_list = false ){
        return false;
    }
}