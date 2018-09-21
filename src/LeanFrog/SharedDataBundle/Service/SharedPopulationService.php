<?php
namespace LeanFrog\SharedDataBundle\Service;

use LeanFrog\SharedDataBundle\Entity\Population;

class SharedPopulationService{

    /** @var EntityManager */
    private $shared_manager;

    private $population_history = [];

    private $date_format = 'Y-m-d H:i:s';

    private $spool = [];

    private $races = [
        'other' => 'Other',
        'none' => 'None',
        3 => 'Black',
        7 => 'White',
    ];

    function __construct( $doctrine ) {

        $this->shared_manager = $doctrine->getManager('shared');
    }

    public function getPopulationRecords( $school ){

        return $this->shared_manager
            ->getRepository('lfSharedDataBundle:Population')
            ->findBy([
                'programSchool' => $school,
            ],
            ['updateDateTime' => 'DESC']
        );
    }

    public function getCurrentPopulation( $school ){

        $population_records = $this->getPopulationRecords( $school );
        $current_population = [];

        foreach( $population_records as $population ){
            if( !isset( $current_population[$population->getTrackingColumn()][$this->races[ $population->getTrackingValue()] ] )
                || $current_population[$population->getTrackingColumn()][$this->races[$population->getTrackingValue()]]
                        ->getUpdateDateTime()
                    < $population->getUpdateDateTime()
            ){
                $current_population[$population->getTrackingColumn()][$this->races[$population->getTrackingValue()]] = $population;
           }
        }

        foreach( $this->races as $value ){
            if( empty( $current_population['Race'][$value] )){

                $new_population = new Population();
                $new_population
                    ->setProgramSchool( $school )
                    ->setTrackingColumn( 'Race' )
                    ->setTrackingValue( $value )
                    ->setCount( 0 );

                $current_population['Race'][$value] = $new_population;
            }
        }

        return $current_population;
    }

    public function getCurrentTotalPopulation( $school ){
        $current_population = $this->getCurrentPopulation( $school );

        $total = [];
        foreach( $current_population as $tracking_column => $populations ){
            $total[$tracking_column] = 0;
            foreach( $populations as $population ){
                $total[$tracking_column] += $population->getCount();
            }
        }

        return $total;
    }

    public function getPopulationHistoryReport( $school ){
        $now = new \DateTime();

        $population_row = [
            'date' => null,
            'type' => '',
            'race' => [
                'black' => 0,
                'white' => 0,
                'other' => 0,
                'none' => 0,
            ],
            'total' => 0,
        ];

        $academic_years = $this->shared_manager
            ->getRepository('lfSharedDataBundle:AcademicYear')
            ->findAll(
                ['startDate' => 'ASC']
            );


        $history = [];
        foreach( $academic_years as $academic_year ){
            $year = $academic_year->getName();
            $start[ $year ] =  $population_row;
            $start[ $year ]['type'] = 'Starting';
            $start[ $year ]['date'] = $academic_year->getStartDate();
            $start[ $year ]['academic_year'] = $academic_year;

            $final[ $year ] = $population_row;
            $final[ $year ]['type'] = 'Current';
            $final[ $year ]['date'] = $now;
            $final[ $year ]['academic_year'] = $academic_year;
        }

        $population_records = array_reverse( $this->getPopulationRecords( $school ) );

        foreach( $population_records as $record ){

            $year = $record->getAcademicYear()->getName();
            $key = $record->getUpdateDateTime()->format( 'Y-m-d H:i:s' ) . $record->getUpdateType();
            $race = strtolower( $this->races[ $record->getTrackingValue() ] );

            if( $record->getUpdateType() == 'Starting' ){
                $start[ $year ]['date'] = $record->getUpdateDateTime();
                $start[ $year ]['race'][ $race ] = $record->getCount();
                continue;
            }

            if( empty( $history[$year][ $key ] ) ){
                $history[$year][ $key ] = $population_row;
                $history[$year][ $key ][ 'type' ] = $record->getUpdateType();
                $history[$year][ $key ][ 'date' ] = $record->getUpdateDateTime();
            }

            $history[$year][ $key ]['race'][ $race ] += $record->getCount();
            $history[$year][ $key ][ 'total' ] += $record->getCount();

            $final[$year]['date'] = $record->getUpdateDateTime();
            $final[$year]['race'][$race] += $record->getCount();
            $final[$year]['total'] += $record->getCount();
        }

        $return_array = [];
        foreach( $start as $year => $starting_row ){
            $return_array[$year] = [
                'start' => $start[$year],
                'history' => ( isset( $history[$year] ) ) ? $history[$year] : [],
                'final' => $final[$year],
            ];
        }

        return $return_array;
    }


    public function getPopulationHistory( $school ){
        $population_records = $this->getPopulationRecords( $school );

        $history = [];
        foreach( $population_records as $population ){

            $date_time = $population->getUpdateDateTime();
            $date_key = $date_time->format( 'Y-m-d H:i:s' );
            if( !isset( $history[$date_key][$population->getTrackingColumn()][$population->getTrackingValue()] )){
                $history[$date_key][$population->getTrackingColumn()][$this->races[ $population->getTrackingValue()] ] = [];
            }
            $history[$date_key][$population->getTrackingColumn()][$this->races[ $population->getTrackingValue()] ][] = $population;
        }

        foreach( $history as $date_index => $row ){

            foreach( $this->races as $value ){

                if( empty( $row['Race'][$value][0] )){

                    $new_population = new Population();
                    $new_population
                        ->setProgramSchool( $school )
                        ->setTrackingColumn( 'Race' )
                        ->setTrackingValue( $value )
                        ->setCount( 0 );

                    $history[$date_index]['Race'][$value][] = $new_population;
                }
            }
        }

        return $history;
    }

    public function getRaceIndex( $race ){
        return array_search( $race, $this->races );
    }
}