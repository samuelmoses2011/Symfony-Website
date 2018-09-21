<?php

namespace IIAB\MagnetBundle\Service\Lottery;

use IIAB\MagnetBundle\Service\PopulationService;

class ZonedLottery {

    private $magnet_manager;
    private $shared_manager;
    private $strickly_enforce;
    private $tracking_value_populations = [];
    public $variance = [];
    private $tracking_values_to_slot = [];
    private $tracking_values_to_skip = [];
    private $pending_slot = [];
    private $current_round = 1;

    public function __construct( $container = null ){

        if( !empty( $container ) ){
            $this->magnet_manager = $container->get( 'doctrine' )->getManager();
            $this->shared_manager = $container->get( 'doctrine' )->getManager('shared');

            $this->population_service = new PopulationService( $container->get( 'doctrine' ) );
        }

        $this->strickly_enforce = MYPICK_CONFIG['lottery']['types']['zoned']['strictly_enforce'];
    }

    public function getRoundsRequired(){
        return 3;
    }

    public function useSlotsInRound( $round ){
        return $round < $this->getRoundsRequired();
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

        $this->pending_slot = [];

        $tracking_value = $this->getTrackingValue( $submission );
        $program_id = $magnetSchool->getProgram()->getId();

        if( $round == 1 || $round == 3 ){

            $this->pending_slot = [
                'tracking_value' => $tracking_value,
                'program' => $program_id,
            ];

            return [true,''];
        }

        if( $this->tracking_values_to_slot[$program_id] == $tracking_value ){

            if( $this->strickly_enforce
                && $this->variance[ $program_id ][$tracking_value]['current']
                    >= $this->variance[ $program_id ][$tracking_value]['target_slots']
            ){
                return [false, $round .'Miss '. $this->tracking_values_to_slot[$program_id] .' '. $tracking_value ];
            }

            $this->pending_slot = [
                'tracking_value' => $tracking_value,
                'program' => $program_id,
            ];
            return [true, $round .'Match '. $this->tracking_values_to_slot[$program_id] .' '. $tracking_value ];
        }

        return [false, $round .'Miss '. $this->tracking_values_to_slot[$program_id] .' '. $tracking_value ];
    }

    public function getSchoolSlotsByMethod( $magnetSchool, $focus_area, $current_population){

        $available_slots = [];

        $program_id = $magnetSchool->getProgram()->getId();

        if( empty( $this->variance[ $program_id ] ) ){
            $this->variance[ $program_id ] = [];
            $this->tracking_values_to_skip[ $program_id ] = [];
        }

        foreach( $current_population as $tracking_value => $current_count ){

            $rising_zoned_students = $this->getRisingZonedStudents(
                $magnetSchool->getGrade(),
                $tracking_value
            );

            $this->tracking_value_populations[$tracking_value] = ( isset( $this->tracking_value_populations[$tracking_value]) )
                ? $rising_zoned_students + $this->tracking_value_populations[$tracking_value]
                : $rising_zoned_students;

            $max_slots = $rising_zoned_students * MYPICK_CONFIG['lottery']['types']['zoned']['per_school_percent'];
            $max_slots = round( $max_slots );

            if( empty( $this->variance[ $program_id ][$tracking_value] ) ){
                $this->variance[ $program_id ][$tracking_value] = [
                    'target_slots' => 0,
                    'starting' => 0,
                    'current' => 0,
                    'variance' => 0,
                ];
            }

            $this->variance[ $program_id ][ $tracking_value ]['starting'] += $rising_zoned_students;
            $this->variance[ $program_id ][ $tracking_value ]['current'] += $current_count->getCount();

            $available_slots[$tracking_value] = $max_slots - $current_count->getCount();
            $available_slots['total_pop'][$tracking_value] = $this->tracking_value_populations[$tracking_value];
            $available_slots['variance'] = $this->variance;
        }

        $this->findValuesToSlot();
        return $available_slots;
    }

    public function getRisingZonedStudents( $grade, $address_bound_school = null ){

        if( $address_bound_school != 'none' ){
            $enrollment = $this->magnet_manager
                ->getRepository('IIABMagnetBundle:AddressBoundEnrollment')
                ->findOneBy([
                    'school' => $address_bound_school,
                    'grade' => $grade
                ],
                ['updateDateTime' => 'DESC']
            );

            return ( $enrollment ) ? $enrollment->getCount() : 0;
        }

        $none_school = $this->magnet_manager
            ->getRepository('IIABMagnetBundle:AddressBoundSchool')
            ->findOneBy([
                'alias' => 'none'
            ]
        );

        $enrollment = $this->magnet_manager
            ->getRepository('IIABMagnetBundle:AddressBoundEnrollment')
            ->findOneBy([
                'school' => $none_school,
                'grade' => $grade
            ],
            ['updateDateTime' => 'DESC']
        );

        return ( $enrollment ) ? $enrollment->getCount() : 0;
    }

    public function getStudentsAttendingMagnet( $magnetSchool ){
        $inow_names = $magnetSchool->getProgram()->getINowNames();

        $search_schools[] = $magnetSchool->getProgram()->getName();
        foreach( $inow_names as $inow_name ){
            $search_schools[] = $inow_name->getINowName();
        }

        return $this->shared_manager->getRepository( 'lfSharedDataBundle:Student' )->findBy([
            'nextSchool' => $search_schools,
            'gradeLevel' => $magnetSchool->getGrade(),
        ]);
    }

    public function sortSubmissions( $submissions ){

        usort( $submissions, function($a, $b) {

            if( $a->getNonHSVStudent() != $b->getNonHSVStudent() ){
                return ($a->getNonHSVStudent() < $b->getNonHSVStudent() )
                    ? -1 : 1;
            }

            $a_score = $a->getAdditionalDataByKey( 'student_profile_percentage' );
            $b_score = $b->getAdditionalDataByKey( 'student_profile_percentage' );

            $a_score = ( !empty( $a_score ) ) ? $a_score->getMetaValue() : -1;
            $b_score = ( !empty( $b_score ) ) ? $b_score->getMetaValue() : -1;

            if ($a_score == $b_score) {
                return ($a->getLotteryNumber() > $b->getLotteryNumber()) ? -1 : 1;
            }
            return ($a_score > $b_score) ? -1 : 1;
        });
        return $submissions;
    }

    public function maybeRestartRound( $round, $finished_list = false ){

        if( $finished_list ){

            $still_trying = false;

            if( $round != $this->current_round ){
                $still_trying = true;
                $this->tracking_values_to_skip = [];
                foreach( $this->tracking_values_to_slot as $program => $tracking_value ){
                    $this->tracking_values_to_slot[$program] = 0;
                }
            }

            $this->pending_slot = [];
            $this->findValuesToSlot();

            foreach( $this->tracking_values_to_slot as $program => $tracking_value ){
                if( $round == $this->current_round
                    && ( !isset( $this->tracking_values_to_skip[$program] )
                         || !in_array('none', $this->tracking_values_to_skip[$program] )
                    )
                ){
                    $this->tracking_values_to_skip[$program][] = $tracking_value;
                    $still_trying = true;
                    break;
                }
            }
            $this->current_round = $round;

            return $still_trying;
        }

        if( $this->pending_slot ){

            $program_id = $this->pending_slot['program'];
            $tracking_value = $this->pending_slot['tracking_value'];

            $this->variance[ $program_id ][ $tracking_value ]['current'] ++;
            $this->pending_slot = [];

            $this->findValuesToSlot();
            $this->tracking_values_to_skip = [];
            return true;
        }

        return false;
    }

    public function findValuesToSlot(){

        foreach( $this->variance as $program => $tracking_values ){

            foreach( array_keys( $tracking_values ) as $tracking_value ){
                $current_slots = $this->variance[ $program ][$tracking_value]['current'];

                $this->variance[$program][$tracking_value]['target_slots']
                    = round( $this->tracking_value_populations[$tracking_value]
                        * MYPICK_CONFIG['lottery']['types']['zoned']['per_school_percent'] );

                $target_slots = $this->variance[$program][$tracking_value]['target_slots'];

                if( $this->strickly_enforce && $target_slots <= $current_slots ){
                    if( !isset( $this->tracking_values_to_skip[$program] ) ){
                        $this->tracking_values_to_skip[$program] = [];
                    }
                    $this->tracking_values_to_skip[$program][] = $tracking_value;
                    $this->variance[$program][$tracking_value]['variance'] = 99999;
                } else {
                    if( !$target_slots ){
                        $this->variance[$program][$tracking_value]['variance']
                            = $current_slots / 0.0001;
                    } else {
                        $this->variance[$program][$tracking_value]['variance']
                            = $current_slots / $target_slots;
                    }
                }
            }

            $smallest_variance = 9999;
            $smallest_variance_tracking_value = 0;

            foreach( array_keys( $tracking_values ) as $tracking_value ) {
                if( $tracking_value != 'none' ){
                    $variance = $this->variance[$program][$tracking_value]['variance'];

                    if(
                        isset( $this->tracking_values_to_skip[$program] )
                        && in_array( $tracking_value, $this->tracking_values_to_skip[$program] )
                    ){
                        //var_dump( 'skip' );

                    } else if( $variance < $smallest_variance ){
                        $smallest_variance = $variance;
                        $smallest_variance_tracking_value = $tracking_value;
                    }
                }
            }

            if( $smallest_variance_tracking_value ){
                $this->tracking_values_to_slot[$program] = $smallest_variance_tracking_value;
            } else {
                $this->tracking_values_to_slot[$program] = 'none';
            }
        }
    }
}