<?php
namespace IIAB\MagnetBundle\Service;

use IIAB\MagnetBundle\Entity\Population;
use IIAB\MagnetBundle\Entity\AddressBoundSchoolEnrollment;
use IIAB\MagnetBundle\Service\Population\HomeZonePopulation;
use IIAB\MagnetBundle\Service\Population\RacePopulation;

class PopulationService{

    /** @var EntityManager */
    private $magnet_manager;
    private $shared_manager;
    private $tracker = [];

    private $population_history = [];

    private $date_format = 'Y-m-d H:i:s';

    private $spool = [];

    function __construct(  $doctrine ) {

        $this->shared_manager = $doctrine->getManager('shared');
        $this->magnet_manager = $doctrine->getManager();

        $available_trackers = MYPICK_CONFIG['population_tracking'];

        foreach( MYPICK_CONFIG['lottery']['types'] as $type ){
            if( $type['enabled'] ){
                $available_trackers[] = $type['tracking_column'];
            }
        }
        $available_trackers = array_values($available_trackers);

        foreach( $available_trackers as $track ){
            switch( $track ){
                case 'HomeZone':
                    $this->tracker[$track] = new HomeZonePopulation(
                        $this->magnet_manager,
                        $this->shared_manager
                    );
                    break;
                case 'Race':
                    $this->tracker[$track] = new RacePopulation(
                        $this->magnet_manager,
                        $this->shared_manager
                    );
                    break;
                default:

            }
        }

        if( empty( $this->tracker ) ){
            var_dump('No Population Tracking Set');
            die;
        }
    }

    public function getPopulationRecords( $school, $focus_area = null ){

        if( strpos( get_class( $school ), 'IIAB\MagnetBundle\Entity\MagnetSchool' ) !== false ){
            return $this->magnet_manager
                ->getRepository('IIABMagnetBundle:Population')
                ->findBy([
                    'magnetSchool' => $school,
                    'focusArea' => $focus_area,
                ],
                ['updateDateTime' => 'DESC']
            );
        } else if ( strpos( get_class( $school ), 'IIAB\MagnetBundle\Entity\AddressBoundSchool' ) !== false ){
            return $this->magnet_manager
                ->getRepository('IIABMagnetBundle:Population')
                ->findBy([
                    'addressBoundSchool' => $school
                ],
                ['updateDateTime' => 'DESC']
            );
        }

        return [];
    }

    public function getCurrentPopulation( $school, $focus_area = null ){

        $population_records = $this->getPopulationRecords( $school, $focus_area );
        $current_population = [];

        foreach( $population_records as $population ){

            if( $population->getUpdateType() == 'starting' ){

                if( !isset( $current_population[$population->getTrackingColumn()][$population->getTrackingValue()] )
                    || $current_population[$population->getTrackingColumn()][$population->getTrackingValue()]
                            ->getUpdateDateTime()
                        < $population->getUpdateDateTime()
                ){
                    $current_population[$population->getTrackingColumn()][$population->getTrackingValue()] = $population;
                }
            }
        }

        foreach( $population_records as $population ){

            if( $population->getUpdateType() != 'starting' ){

                $current = (isset( $current_population[$population->getTrackingColumn()][$population->getTrackingValue()] ))
                    ? $current_population[$population->getTrackingColumn()][$population->getTrackingValue()]
                    : null;
                if( $current == null || $current->getUpdateType() != 'current' ){

                    $count = ( !empty( $current ) ) ? $current->getCount() : 0;

                    $new_population = new Population();
                    $new_population
                        ->setMagnetSchool( $population->getMagnetSchool() )
                        ->setFocusArea( $population->getFocusArea() )
                        ->setTrackingColumn( $population->getTrackingColumn() )
                        ->setTrackingValue( $population->getTrackingValue() )
                        ->setCount( $count );
                    $current = $new_population;
                }

                $current->setCount( $current->getCount() + $population->getCount() );
                $current_population[$population->getTrackingColumn()][$population->getTrackingValue()] = $current;

           }
        }

        foreach( $current_population as $tracking_column => $values ){
            foreach( $values as $tracking_value => $population_array ){

                $new_population = new Population();
                $new_population
                    ->setMagnetSchool( $school )
                    ->setFocusArea( $focus_area )
                    ->setTrackingColumn( $tracking_column )
                    ->setTrackingValue( $tracking_value )
                    ->setCount( $population_array->getCount() );

                $current_population[$tracking_column][$tracking_value] = $new_population;
            }
        }

        if( strpos( get_class( $school ), 'IIAB\MagnetBundle\Entity\MagnetSchool' ) !== false ){
            foreach( $this->tracker as $tracker ){
                $column = $tracker->getTrackingColumn();
                $values = $tracker->getTrackingValues( $school );

                foreach( $values as $value ){
                    if( empty( $current_population[$column][$value] )){

                        $new_population = new Population();
                        $new_population
                            ->setMagnetSchool( $school )
                            ->setFocusArea( $focus_area )
                            ->setTrackingColumn( $column )
                            ->setTrackingValue( $value )
                            ->setCount( 0 );

                        $current_population[$column][$value] = $new_population;
                    }
                }
            }
        }

        return $current_population;
    }

    public function getCurrentTotalPopulation( $school, $focus_area = null ){
        $current_population = $this->getCurrentPopulation( $school, $focus_area);

        $total = [];
        foreach( $current_population as $tracking_column => $populations ){
            $total[$tracking_column] = 0;
            foreach( $populations as $population ){
                $total[$tracking_column] += $population->getCount();
            }
        }
        return $total;
    }

    public function getMaxCapacity( $school, $focus_area = null ){

        if( strpos( get_class( $school ), 'IIAB\MagnetBundle\Entity\MagnetSchool' ) === false ){
            null;
        }

        $max = $this->magnet_manager->getRepository('IIABMagnetBundle:Capacity')->findBy([
                'school' => $school,
                'focusArea' => $focus_area,
            ],
            ['id' => 'DESC'], 1
        );

        return ( isset( $max[0] ) ) ? $max[0]->getMax() : 0;
    }

    public function initializePopulation( $school, $focus_area = null ){
        $current_population = $this->getCurrentPopulation( $school );

        $initial = [];
        foreach( $this->tracker as $tracker_key => $tracker ){

            if( $this->doesSchoolUseTracker( $school, $tracker_key ) ){

                $initial[$tracker->getTrackingColumn()] = $tracker->initializePopulation( $school, $focus_area );

                foreach( array_keys( $initial[$tracker->getTrackingColumn()] ) as $value ){
                    $column = $tracker->getTrackingColumn();
                    $count = ( isset( $current_population[$column][$value] ) ) ? $current_population[$column][$value] : 0;
                    $initial[$column][$value]->setCount( $count );
                }
            }
        }
        return $initial;
    }

    public function offer( $data ){

        if( !isset( $data['submission'] ) ){
            var_dump( 'NO submission in data');
            die;
        }

        $data = array_merge([
            'type' => 'offer',
            'count' => 1,
            'submission' => null
        ], $data);

        return $this->adjustPopulation( $data );
    }

    public function decline( $data ){

        if( !isset( $data['submission'] ) ){
            var_dump( 'NO submission in data');
            die;
        }

        $data = array_merge([
            'type' => 'decline',
            'count' => -1,
            'submission' => null
        ], $data);

        return $this->adjustPopulation( $data );
    }

    public function withdraw( $data ){

        if( !isset( $data['tracking_column'] )
            || !isset( $data['tracking_value'] )
         ){
            var_dump( 'NO tracking data provided');
            die;
        }

        $data = array_merge([
            'type' => 'withdrawal',
            'count' => -1,
            'tracking_column' => '',
            'tracking_value' => ''
        ], $data );

        if( $data['count'] > 0 ){
            $data['count'] = $data['count'] * -1;
        }

        return $this->adjustPopulation( $data );
    }

    public function create( $data ){
        return $this->adjustPopulation( $data, true );
    }

    public function adjustPopulation($input, $is_create_new = false ){

        $now = new \DateTime();
        $new_record = null;

        foreach( $this->tracker as $trx => $tracker ){

            if( isset( $input['submission'] )
                || $input['tracking_column'] == $tracker->getTrackingColumn()
            ){
                $data = array_merge([
                    'type' => 'adjustment',
                    'date_time' => $now,
                    'school' => null,
                    'count' => 1,
                    'submission' => null
                ], $input );

                // Get Tracking Value from Submission
                if( !empty( $data['submission'] ) ){
                    $data['tracking_value'] = $tracker->getSubmissionTrackingValue( $data['submission'] );
                }

                // If Creating a New Record Do so and Return
                if( $is_create_new ){
                    $new_record = $tracker->createPopulationRecord( $data );
                    $this->spool[ $data['school']->getId() ][ $tracker->getTrackingColumn() ][ $data['tracking_value'] ][] = $new_record;
                    continue;
                    //return $new_record;
                }

                // Check to see if we are spooling this record
                $previous_count = 0;
                if( isset( $this->spool[ $data['school']->getId() ][ $tracker->getTrackingColumn() ][ $data['tracking_value'] ] ) ){

                    $last_index = count( $this->spool[ $data['school']->getId() ][ $tracker->getTrackingColumn() ][ $data['tracking_value'] ] )
                        - 1;

                    $previous_count = $this->spool[ $data['school']->getId() ][ $tracker->getTrackingColumn() ][ $data['tracking_value'] ][ $last_index ]
                        ->getCount();

                    // if spooling update and return
                    if( $this->spool[ $data['school']->getId() ][ $tracker->getTrackingColumn() ][ $data['tracking_value'] ][ $last_index ]
                            ->getUpdateType() == $data['type']
                    ){
                        $this->spool[ $data['school']->getId() ][ $tracker->getTrackingColumn() ][ $data['tracking_value'] ][ $last_index ]
                            ->setCount( $previous_count + $data['count'] );
                        //return $this->spool[ $data['school']->getId() ][ $data['tracking_value'] ][ $last_index ];
                            $new_record = $this->spool[ $data['school']->getId() ][ $tracker->getTrackingColumn() ][ $data['tracking_value'] ][ $last_index ];
                        continue;
                    }
                }

                // There is no spool so get previous count from current population
                if( !$previous_count ){

                    $current_population = $this->getCurrentPopulation( $data['school'], $focus_area = null );
                    if( isset( $current_population[ $data['tracking_value'] ] ) ){
                        $current_record = $current_population[ $data['tracking_value'] ];

                        $current_count = $current_record->getCount();
                    }
                }

                // Create the new population record
                $new_record = $tracker->createPopulationRecord( $data );
                if( $previous_count ){
                    $new_record->setCount( $previous_count + $data['count'] );
                }

                // Add the new record to the spool and return
                $this->spool[ $data['school']->getId() ][ $tracker->getTrackingColumn() ][ $data['tracking_value'] ][]
                    = $new_record;
                }
            }

        return $new_record;
    }

    public function persist_and_flush(){
        foreach( $this->spool as $school ){
            foreach( $school as $columns ){
                foreach( $columns as $inner_spool ){
                    foreach( $inner_spool as $population_record) {

                        $this->magnet_manager->persist( $population_record );
                    }
                }
            }
        }
        $this->magnet_manager->flush();
        $this->spool = [];
    }

    public function getSubmissionTrackingValue( $submission, $tracking_column = null ){

        if($tracking_column != null){
            return $this->tracker[$tracking_column]->getSubmissionTrackingValue( $submission );
        }

        $tracking_values = [];
        foreach( $this->tracker as $tracker ){
            $tracking_values[ $tracking_column ] = $tracker->getSubmissionTrackingValue( $submission );
        }
        return $tracking_values;
    }

    public function getSubmissionTrackingValueName( $submission, $tracking_column = null ){
        $tracking_value_name = [];
        foreach( $this->tracker as $tracker ){
            if( empty( $tracking_column)
                || $tracking_column == $tracker->getTrackingColumn()
            )
            $tracking_value_name[ $tracking_column ] = $tracker->getSubmissionTrackingValueName( $submission );
        }
        return ( count( $tracking_value_name ) == 1 ) ? array_values( $tracking_value_name )[0] : $tracking_value_name;
    }

    public function getPopulationHistory( $school, $focus_area = null ){
        $population_records = $this->getPopulationRecords( $school, $focus_area );

        $history = [];
        foreach( $population_records as $population ){

            $date_time = $population->getUpdateDateTime();
            $date_key = $date_time->format( 'Y-m-d H:i:s' );
            if( !isset( $history[$date_key][$population->getTrackingColumn()][$population->getTrackingValue()] )){
                $history[$date_key][$population->getTrackingColumn()][$population->getTrackingValue()] = [];
            }
            $history[$date_key][$population->getTrackingColumn()][$population->getTrackingValue()][] = $population;
        }

        return $history;
    }

    public function doesSchoolUseTracker( $school, $tracker ){

        if( in_array( $tracker, MYPICK_CONFIG['population_tracking'] ) ){
            return true;
        }

        if( strpos( get_class( $school ), 'IIAB\MagnetBundle\Entity\MagnetSchool' ) === false ){
            return false;
        }

        $program_slotting_method = $school->getProgram()->getAdditionalData('slotting_method');
        $program_slotting_method = ( count( $program_slotting_method ) )
            ? $program_slotting_method[0]->getMetaValue()
            : '';

        $slotting_method = ( $program_slotting_method && isset( MYPICK_CONFIG['lottery']['types'][$program_slotting_method]) )
            ? MYPICK_CONFIG['lottery']['types'][$program_slotting_method]['tracking_column']
            : $program_slotting_method;

        return ( $slotting_method == $tracker || $program_slotting_method == $tracker);
    }

    public function getTrackingColumnLabels(){
        $column_labels = [];
        foreach( $this->tracker as $tracker ){
            $column_labels[ $tracker->getTrackingColumn() ]
                = $tracker->getTrackingColumnLabels();
        }
        return $column_labels;
    }

    public function getTrackingValues( $school, $tracker ){

        if( $this->doesSchoolUseTracker( $school, $tracker ) ){
            return $this->tracker[$tracker]->getTrackingValues( $school );
        }
        return [];
    }

}