<?php

namespace IIAB\MagnetBundle\Service\Lottery;

use IIAB\MagnetBundle\Service\PopulationService;

class ScoredLottery {

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
        return ( MYPICK_CONFIG['lottery']['types']['zoned']['strictly_enforce'] )
            ? 1 : 2;
    }

    public function useSlotsInRound( $round ){
        return $round >= $this->getRoundsRequired();
    }

    public function getTrackingColumn(){
        return MYPICK_CONFIG['lottery']['types']['zoned']['tracking_column'];
    }

    public function getTrackingValue($submission){
        return $this->population_service->getSubmissionTrackingValue( $submission, $this->getTrackingColumn() );
    }

    public function doesSubmissionPassRequirements(
        $submission,
        $magnetSchool,
        $focus,
        $round ){

        return [true,''];
    }

    public function sortSubmissions( $submissions ){

        usort( $submissions, function($a, $b){
            $a_score = $a->getAdditionalDataByKey( 'audition_total' );
            $b_score = $b->getAdditionalDataByKey( 'audition_total' );

            $a_score = ( !empty( $a_score ) ) ? $a_score->getMetaValue() : -1;
            $b_score = ( !empty( $b_score ) ) ? $b_score->getMetaValue() : -1;

            if ($a_score == $b_score) {
                return 0;
            }
            return ($a_score > $b_score) ? -1 : 1;
        });
        return $submissions;
    }

    public function getSchoolSlotsByMethod( $magnetSchool, $focus_area, $current_population ){
        return [];
    }

    public function maybeRestartRound( $round, $finished_list = false ){
        return false;
    }
}



