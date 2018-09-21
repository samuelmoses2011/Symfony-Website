<?php

namespace IIAB\MagnetBundle\Service;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use IIAB\MagnetBundle\Entity\OpenEnrollment;
use IIAB\MagnetBundle\Entity\LotteryOutcomeSubmission;
use IIAB\MagnetBundle\Entity\LotteryOutcomePopulation;
use IIAB\MagnetBundle\Entity\Offered;
use IIAB\MagnetBundle\Entity\Waitlist;
use IIAB\MagnetBundle\Entity\SubmissionData;
use IIAB\MagnetBundle\Entity\Process;
use IIAB\MagnetBundle\Service\PopulationService;
use IIAB\MagnetBundle\Service\Lottery\DesegregationLottery;
use IIAB\MagnetBundle\Service\Lottery\SimpleLottery;
use IIAB\MagnetBundle\Service\Lottery\ZonedLottery;
use IIAB\MagnetBundle\Service\Lottery\ScoredLottery;

class LotteryService {

    protected $entity_manager;
    protected $container;
    protected $population_service;
    protected $eligibility_service;

    protected $logFile = false;

    protected $open_enrollment;
    protected $placement;
    protected $submissions = [
        'before' => [],
        'after' => [
            'awarded' => [],
            'waitlist' => [],
            'denied' => [],
        ],
        'groups' => [],
    ];
    protected $submission_status = [
        'active' => 1,
        'offered' => 6,
        'waitlist' => 9,
        'denied' => 3,

    ];

    protected $grade_processing_order = [];
    protected $slotting_methods = [];
    protected $available_slots = [
        'before' => [],
        'current' => [],
    ];
    protected $magnet_school_settings = [];
    protected $population_by_program_school = [];
    protected $population_max_by_program_school = [];

    public function __construct(
        ContainerInterface $container = null ,
        EntityManager $entity_manager ) {

        $this->container = $container;
        $this->entity_manager = $entity_manager;
        $this->population_service = ( !empty( $container ) )
            ? $container->get('magnet.population')
            : null;

        $this->eligibility_service = ( !empty( $container ) )
            ? $container->get('magnet.eligibility')
            : null;

        $status_objects = $this->entity_manager->getRepository( 'IIABMagnetBundle:SubmissionStatus' )
            ->findBy( [
                'id' => $this->submission_status
            ]
        );

        foreach( $status_objects as $status_object ){

            $key = '';
            foreach( $this->submission_status as $maybe_key => $status ){
                if( !is_object( $status )
                    && $status == $status_object->getId()){
                    $key = $maybe_key;
                }
            }

            $this->submission_status[ $key ] = $status_object;

        }

        foreach( MYPICK_CONFIG['lottery']['types'] as $lottery_type => $maybe_process ){

            if( $maybe_process['enabled'] ){

                switch( $lottery_type ){
                    case 'desegregation':
                        $lottery = new DesegregationLottery( $this->container );
                        break;
                    case 'simple':
                        $lottery = new SimpleLottery( $this->container );
                        break;
                    case 'zoned':
                        $lottery = new ZonedLottery( $this->container );
                        break;
                    case 'scored':
                        $lottery = new ScoredLottery( $this->container );
                        break;
                }

                $lottery_class = ucfirst( $lottery_type ).'Lottery';

                $this->slotting_methods[ $lottery_type ] = [
                    'strictly_enforce' => $maybe_process['strictly_enforce'],
                    'lottery' => $lottery,
                ];
            }
        }

        $this->grade_processing_order = MYPICK_CONFIG['lottery']['grade_processing_order'];
    }

    /**
     * Generates a Lottery Number to be used in the Submission.
     *
     * @param OpenEnrollment $openEnrollment
     *
     * @return int
     * @throws \Exception
     */
    public function getLotteryNumber( OpenEnrollment $openEnrollment ) {

        $lotteryNumbers = Array( 0 , 0 , 0 , 0 , 0 , 0 );
        $lotteryNumber = 1;
        $uniqueLottoFlag = true;
        $breakOutIfInfiniteLoopCounter = 0;

        while( $uniqueLottoFlag ) {

            //create an array of 6 random numbers that range in value from 1 to 64
            for ($i = 0; $i < 6; $i++) {
                $lotteryNumbers[$i] = rand(0, 64);
            }

            //concatenate all lottery numbers into one lottery number
            $lotteryNumber = intval(implode($lotteryNumbers));

            //check to see if the lottery number has already been assigned during another submission
            $submission = $this->entity_manager->getRepository( 'IIABMagnetBundle:Submission' )->findOneBy( array(
                'lotteryNumber' => $lotteryNumber ,
                'openEnrollment' => $openEnrollment ,
            ) );
            //if not already assigned, break out of loop
            if( $submission == null ) {
                $uniqueLottoFlag = false;
            }

            //make sure we do not get in an infinite loop; report error if so
            $breakOutIfInfiniteLoopCounter++;
            if( $breakOutIfInfiniteLoopCounter == 500 ) {
                sleep( 1 );
            }
            if( $breakOutIfInfiniteLoopCounter == 1000 ) {
                sleep( 1 );
            }
            if( $breakOutIfInfiniteLoopCounter == 1250 ) {
                sleep( 1 );
            }
            if( $breakOutIfInfiniteLoopCounter == 1400 ) {
                sleep( 1 );
            }
            if( $breakOutIfInfiniteLoopCounter > 1500 ) {
                return 0;
                throw new \Exception( "We are sorry, there was an error with your submission. Please visit Student Support Services for assistance, or try your submission again later." );
            }
        }

        return $lotteryNumber;
    }

    public function generateLotteryNumber() {

        $used_lottery_numbers = $this->entity_manager
            ->getRepository('IIABMagnetBundle:Submission')
            ->createQueryBuilder('s')
            ->select('s.lotteryNumber')
            ->distinct( true )
            ->where('s.openEnrollment = :openEnrollment')
            ->setParameter( 'openEnrollment' , $this->open_enrollment )
            ->getQuery()
            ->getResult();

        $digits = 9;
        $min = pow(10, $digits - 1);
        $max = pow(10, $digits) - 1;

        $maybe_lottery_number = rand( $min, $max );
        while( in_array($maybe_lottery_number, $used_lottery_numbers ) ){
            $maybe_lottery_number = rand( $min, $max );
        }
        return $maybe_lottery_number;
    }

    protected function log() {

        if( $this->container->get( 'kernel' )->getEnvironment() != 'dev' ) {
            return;
        }

        $args = func_get_args();

        foreach( $args as $arg ) {

            if( is_string ( $arg ) || is_numeric( $arg ) ){
                echo $arg ."\n\r";

                if( $this->logFile ){
                    fwrite( $this->logFile, $arg."\n\r" );
                }

            } else {
                print_r( $arg );

                if( $this->logFile ){
                    $log_arg = print_r( $arg, true );
                    fwrite( $this->logFile, $log_arg."\n\r" );
                }
            }
        }
    }

    public function run_Lottery( $options = [], $open_enrollment ){

        $dir = substr( $this->container->get( 'kernel' )->getRootDir(), 0, -3) . 'web/reports/lottery-run/';
        if( !file_exists( $dir ) ) {
            mkdir( $dir, 0777, true );
        }

        $this->logFile = fopen($dir . 'lottery-run-' . date( 'Y-m-d-H-i' ) . '.txt', 'w+');

        $this->open_enrollment = $open_enrollment;
        $this->placement = $this->entity_manager->getRepository( 'IIABMagnetBundle:Placement' )
            ->findOneBy( [ 'openEnrollment' => $open_enrollment ], [ 'round' => 'DESC' ] );

        $options = array_merge( [
            'lottery_type' => 'normal',
            'clear_old_outcomes' => true,
        ], $options );

        $this->log( 'Running '. $options['lottery_type'] .' Lottery '. $this->open_enrollment );
        $this->log( 'Start: ' . memory_get_usage() .' '. date( 'H:i:s') );

        $changedSubmissions = 0;

        $this->get_AvailableSlots();

        if( $options['lottery_type'] == 'waitlist' ){
            $this->apply_Withdrawals();
        }

        $this->clear_OldOutcomes( $options['clear_old_outcomes'] );

        $this->available_slots['current'] = $this->available_slots['before'];

        $this->save_Outcome_Populations('before');

        $this->get_MagnetSchoolSettings();

        gc_enable();
        gc_collect_cycles();

        switch( $options['lottery_type'] ){
            case 'normal':
                $this->build_LotteryGroupingArray(
                    $this->submission_status['active']
                );
                break;
            case 'late':
                $this->build_LotteryGroupingArray([
                    $this->submission_status['waitlist'],
                    $this->submission_status['active']
                ]);
                break;
            case 'waitlist':
                $this->build_LotteryGroupingArray(
                    $this->submission_status['waitlist']
                );
                break;
            default:
                $this->log('No Lottery Type Selected');
                return;
        }

        $this->process_LotteryRounds();

        $this->save_Outcome_Submissions('waitlist');

        $this->save_Outcome_Submissions('awarded' );

        $this->save_Outcome_Submissions('denied');

        $this->save_Outcome_Populations('after');

        $this->entity_manager->flush();
        gc_collect_cycles();

        $this->log( 'Finished: ' . memory_get_usage() , date('H:i:s') );

        return count( $this->submissions['after']['awarded'] ) +
            count( $this->submissions['after']['waitlist'] ) +
            count( $this->submissions['after']['denied'] );
    }

    private function get_AvailableSlots() {

        $this->log( 'Getting Available Slots');

        //Return Array for all the settings needed.
        $totalAvailableSlots = [
            'programs' => []
        ];

        $programs = $this->entity_manager->getRepository( 'IIABMagnetBundle:Program' )
            ->findBy([
                'openEnrollment' => $this->open_enrollment,
            ],
            ['name' => 'ASC']
        );

        foreach( $programs as $program ) {

            $program_population = [];
            $slotting_method = $program->getAdditionalData('slotting_method');
            $slotting_method = ( count($slotting_method) ) ? $slotting_method[0]->getMetaValue() : 'simple';
            $tracking_column = $this->slotting_methods[$slotting_method]['lottery']->getTrackingColumn();

            $focus_areas = $program->getAdditionalData( 'focus' );

            $capacity_by_focus = $program->getAdditionalData('capacity_by');
            $capacity_by_focus = ( isset( $capacity_by_focus[0] ) && $capacity_by_focus[0]->getMetaValue() == 'focus' );

            $magnetSchools = $program->getMagnetSchools();

            foreach( $magnetSchools as $magnetSchool ) {

                $school_focus_areas = (
                    $magnetSchool->getGrade() > 1
                    && $magnetSchool->getGrade() < 99
                    && ( $magnetSchool->doesRequire( 'combined_audition_score' ) || $magnetSchool->doesRequire( 'audition_score' ) )
                    && $capacity_by_focus
                    && count( $focus_areas ) > 0
                ) ? $focus_areas : [ 0 ];

                foreach ($school_focus_areas as $focus_area) {

                    $focus_area = ( $focus_area ) ? $focus_area->getMetaValue() : null;
                    $current_population = $this->population_service->getCurrentPopulation( $magnetSchool, $focus_area );
                    $current_population = ( isset($current_population[$tracking_column]) ) ? $current_population[$tracking_column] : [];

                    $current_total_population = $this->population_service->getCurrentTotalPopulation( $magnetSchool, $focus_area );
                    $current_total_population = ( isset($current_total_population[$tracking_column]) ) ? $current_total_population[$tracking_column] : 0;
                    $max_capacity = $this->population_service->getMaxCapacity( $magnetSchool, $focus_area );

                    //Get the total Number of slots available.
                    $totalSlots = $max_capacity - $current_total_population;

                    //Ensure we have a zero or positive number.
                    if( $totalSlots < 0 ) {
                        $totalSlots = 0;
                    }

                    $slotting_data = [
                        'TAS' => $totalSlots ,
                        'originalTAS' => $totalSlots ,
                        'lastTAS' => $totalSlots ,
                        'maxCapacity' => $max_capacity ,
                        'changed' => false ,
                        'program' => $program->getId(),
                        'slotting_method' => $slotting_method,
                        'tracking_column' => $tracking_column,
                        'slots_by_method' => $this->slotting_methods[$slotting_method]['lottery']
                            ->getSchoolSlotsByMethod(
                                $magnetSchool,
                                $focus_area,
                                $current_population
                            ),
                    ];

                    //$this->log( $slotting_data['slots_by_method'] );

                    foreach( $current_population as $key => $population ){
                        $slotting_data[$key] = $population->getCount();

                        if( !isset( $program_population[$key] ) ){
                            $program_population[$key] = 0;
                        }
                        $program_population[$key] += $population->getCount();
                    }

                    if( !empty( $focus_area ) ){
                        $totalAvailableSlots[$magnetSchool->getId()] [$focus_area] = $slotting_data;
                    } else {
                        $totalAvailableSlots[$magnetSchool->getId()] [0] = $slotting_data;
                    }
                }
            }

            $totalAvailableSlots['programs'][$program->getId()] = $program_population;
            $totalAvailableSlots['programs'][$program->getId()]['capacity_by'] = ( $capacity_by_focus && count( $focus_areas ) > 0 ) ? 'focus' : 'school';
        }
        $this->available_slots['before'] = $totalAvailableSlots;
    }

    protected function clear_OldOutcomes( $truncate = true ){

        $this->log( 'Clearing Old Outcomes' );

        if( $truncate ) {

            // Truncate the outcome tables
            $outcome_submission = $this->entity_manager->getClassMetadata( 'IIABMagnetBundle:LotteryOutcomeSubmission' );
            $outcome_population = $this->entity_manager->getClassMetadata( 'IIABMagnetBundle:LotteryOutcomePopulation' );
            $connection = $this->entity_manager->getConnection();
            $dbPlatform = $connection->getDatabasePlatform();
            $connection->query('SET FOREIGN_KEY_CHECKS=0');

            $q = $dbPlatform->getTruncateTableSql($outcome_submission->getTableName());
            $connection->executeUpdate($q);

            $q = $dbPlatform->getTruncateTableSql($outcome_population->getTableName());
            $connection->executeUpdate($q);

            $connection->query('SET FOREIGN_KEY_CHECKS=1');

            $non_population_keys = [
                'TAS',
                'originalTAS',
                'lastTAS',
                'maxCapacity',
                'changed',
                'program',
                'slotting_method',
                'tracking_column',
                'slots_by_method'
            ];

            // set the starting populations in the outcome table
            foreach( $this->available_slots['before'] as $magnetID => $availableSlots ){

                $magnetSchool = $this->entity_manager->getRepository( 'IIABMagnetBundle:MagnetSchool' )
                    ->find( $magnetID );

                //Ensures we found a Magnet School
                if( $magnetSchool != null ) {

                    foreach( $availableSlots as $focus => $slots ){
                        if( !in_array( $focus, $non_population_keys ) ){

                            $populationOutcome = new LotteryOutcomePopulation();
                            $populationOutcome->setMagnetSchool( $magnetSchool );
                            $populationOutcome->setOpenEnrollment( $this->open_enrollment );
                            $populationOutcome->setPlacement( $this->placement );

                            $populationOutcome->setTrackingValue( $slots );
                            $populationOutcome->setTrackingColumn( $availableSlots['tracking_column'] );
                            $populationOutcome->setMaxCapacity( $availableSlots['max_capacity'] );
                            $populationOutcome->setType( 'before' );

                            $this->entity_manager->persist( $populationOutcome );
                        }
                    }
                }
                $this->entity_manager->flush();
                $magnetSchool = null;
            }
        } else {
            $population_outcome_changes = $this->entity_manager->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findBy([ 'type' => 'changed' ] );
            foreach( $population_outcome_changes as $changes ){

                $focus_area = $changes->getFocusArea();
                $focus_area = ( !empty( $focus_area ) ) ? $focus_area : 0;
                $schoolID = $changes->getMagnetSchool()->getId();
                $this->available_slots['before'][$schoolID][$focus_area][$changes->getTrackingValue()] += $changes->getCount();
                $this->available_slots['before'][$schoolID][$focus_area]['TAS'] -= $changes->getTrackingValue();

                $this->available_slots['before']['programs'][ $changes->getMagnetSchool()->getProgram()->getId() ][$changes->getTrackingValue()] += $changes->getCount();
            }
        }
    }

    private function get_MagnetSchoolSettings() {

        $this->log( 'Get Magnet School Settings' );

        $magnetSchoolSettings = $this->entity_manager->getRepository( 'IIABMagnetBundle:MagnetSchoolSetting' )->findBy( array(
            'openEnrollment' => $this->open_enrollment ,
        ) );

        $formatedData = array();
        foreach( $magnetSchoolSettings as $setting ) {
            $formatedData[$setting->getMagnetSchool()->getId()] = $setting;
        }
        //$this->log( $formatedData );

        $this->magnet_school_settings = $formatedData;
    }

    private function build_LotteryGroupingArray( $submissionStatus_priority_list ){

        $this->log( 'Build Lottery Grouping Array' );

        $submissionStatus_priority_list = ( is_array( $submissionStatus_priority_list ) ) ? $submissionStatus_priority_list : array( $submissionStatus_priority_list );

        foreach( $submissionStatus_priority_list as $submissionStatus ){
            if( get_class( $submissionStatus ) != 'IIAB\MagnetBundle\Entity\SubmissionStatus' ){
                return false;
            }
        }

        $submissions = $this->entity_manager->getRepository( 'IIABMagnetBundle:Submission' )
            ->findBy([
                'submissionStatus' => $submissionStatus_priority_list,
                'openEnrollment' => $this->open_enrollment,
                'lotteryNumber' => 0
            ]
        );

        foreach( $submissions as $submission ){
            $lotteryNumber = $this->getlotteRynumbeR($this->open_enrollment);

            $submission->setLotteryNumber( $lotteryNumber );
            $this->entity_manager->persist($submission);
        }
        $this->entity_manager->flush();

        $magnetSchools = $this->entity_manager->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findBy( [
            'openEnrollment' => $this->open_enrollment,
            'active' => 1
        ]);

        $choices = [
            1 => 'first',
            2 => 'second',
            3 => 'third'
        ];

        $grouping_array = [];

        foreach( $choices as $choice ) {

            $choiceNumber = 0;
            if ( strtolower( $choice ) == 'first' ){
                $choiceNumber = 1;
            } else if( strtolower( $choice ) == 'second' ){
                $choiceNumber = 2;
            } else if( strtolower( $choice ) == 'third' ){
                $choiceNumber = 3;
            }

            $submissions = $this->entity_manager->getRepository('IIABMagnetBundle:Submission')->findBy([
                'submissionStatus' => $submissionStatus_priority_list,
                $choice . 'Choice' => $magnetSchools,
            ], ['lotteryNumber' => 'DESC']);

            $grouped_by_school = [];
            foreach( $submissions as $submission ){

                $magnetSchool = $submission->{'get'. ucfirst($choice) .'Choice'}();

                if( !isset( $grouped_by_school[ $magnetSchool->getId() ] ) ){
                    $grouped_by_school[ $magnetSchool->getId() ] = [];
                }

                $grouped_by_school[ $magnetSchool->getId() ][] = $submission;
            }
            krsort($grouped_by_school);

            foreach( $grouped_by_school as $magnetSchoolID => $group ){

                $magnetSchool = $this->entity_manager->getRepository('IIABMagnetBundle:MagnetSchool')->find($magnetSchoolID);

                $slotting_method = $magnetSchool->getProgram()->getAdditionalData('slotting_method');
                $slotting_method = ( count($slotting_method) ) ? $slotting_method[0]->getMetaValue() : 'simple';

                usort( $group, function($a, $b) {
                    return ($a->getLotteryNumber() > $b->getLotteryNumber()) ? -1 : 1;
                });

                $group = $this->slotting_methods[ $slotting_method ]['lottery']->sortSubmissions( $group );
                $grouped_by_school[ $magnetSchoolID ] = $group;
            }

            $ordered_submissions = [];
            foreach( $grouped_by_school as $magnetSchoolID => $group ){
                foreach( $group as $submission ){
                    $ordered_submissions[] = [
                        'submission' => $submission,
                        'school' => $this->entity_manager->getRepository('IIABMagnetBundle:MagnetSchool')->find($magnetSchoolID),
                        'focus_area' => $submission->{'get' . ucfirst($choice) . 'ChoiceFirstChoiceFocus'}(),
                        'choiceNumber' => $choiceNumber
                    ];
                }
            }
            $grouping_array[$choice] = $ordered_submissions;
        }

        gc_enable();
        gc_collect_cycles();
        $this->submissions['groups'] = $grouping_array;
    }

    private function process_LotteryRounds(){

        $this->log( 'Process Lottery Rounds' );

        $rounds_required = 1;
        foreach( $this->slotting_methods as $slotter ){
            $slotter_rounds = $slotter['lottery']->getRoundsRequired();
            $rounds_required = max([ $rounds_required, $slotter_rounds ]);
        }

        $loop_count = 1;
        for( $round = 1; $round <= $rounds_required; $round ++ ){

            $this->log( 'Process Lottery Round # '. $round .' of '. $rounds_required );
            $maybe_repeat = $this->process_LotteryGroupingArray( $round );

            while( $maybe_repeat ){
                $loop_count++;
                $this->log( 'Repeat Lottery #'. $loop_count );
                $maybe_repeat = $this->process_LotteryGroupingArray( $round );
            }
            $this->log( 'END WHILE '. $round );
        }
    }

    private function process_LotteryGroupingArray( $round ){

        $this->log( 'Process Lottery Grouping Array' );

        foreach( $this->submissions['groups'] as $group => $submissions ){

            $this->log( 'Total '. $group .' Submissions to Evaluate '. count($submissions) );

            foreach( $submissions as $key => $submissionData ) {

                $submission = $submissionData['submission'];
                $magnetSchoolID = $submissionData['school']->getId();
                $focus = $submissionData['focus_area'];

                if( isset( $this->submissions['after']['awarded'][$submission->getId()] )
                ){
                    $this->log( 'Already Awarded Submission ID: ' . $submission->getId() .' -- '. $submissionData['choiceNumber'] .' '. $submissionData['school']->getId() );
                    continue;
                }

                $slot_by_focus = ( isset( $this->available_slots['current'][$magnetSchoolID][0] ) )
                    ? 0
                    : $focus;

                //$this->log( 'School ID: ' . $magnetSchoolID .' '. $slot_by_focus );

                $passes_eligibility = $this->eligibility_service->doesSubmissionHaveAllEligibility(
                        $submission,
                        $submissionData['school'],
                        $focus
                );

                if( !$passes_eligibility ){
                    if( !isset($this->submissions['after']['denied'][$submission->getId()][$magnetSchoolID]) ){
                        $this->log( 'Deny Submission ID: ' . $submission->getId() .' -- '. $submissionData['choiceNumber'] .' '. $submissionData['school']->getId() );
                    }
                    $this->submissions['after']['denied'][$submission->getId()][$magnetSchoolID] = $submissionData;
                    continue;
                }

                $slot_available = (
                    isset( $this->available_slots['current'][$magnetSchoolID][$slot_by_focus] )
                    && $this->available_slots['current'][$magnetSchoolID][$slot_by_focus]['TAS'] > 0
                );
                $this->log( $submission->getID() .' slot ', ($slot_available) ?'true':'false' );

                $slotting_method = $this->available_slots['current'][$magnetSchoolID][$slot_by_focus]['slotting_method'];

                $this->log( 'USE SLOTS: '. $this->slotting_methods[$slotting_method]['lottery']->useSlotsInRound( $round ) );
                //$this->log( $slot_available );
                if( $slot_available
                    && $this->slotting_methods[$slotting_method]['lottery']->useSlotsInRound( $round )
                    && !empty( $this->available_slots['current'][$magnetSchoolID][$slot_by_focus]['slots_by_method'] )
                ){
                    $tracking_column = $this->slotting_methods[$slotting_method]['lottery']->getTrackingColumn();
                    $tracking_value = $this->slotting_methods[$slotting_method]['lottery']->getTrackingValue($submission);
                    $this->log( 'CHECK Method Slots '.$tracking_column .' '. $tracking_value );
                    //$this->log( $this->available_slots['current'][$magnetSchoolID][$slot_by_focus]['slots_by_method'][$tracking_value] );
                    $slot_available = (
                        isset( $this->available_slots['current'][$magnetSchoolID][$slot_by_focus]['slots_by_method'][$tracking_value] )
                        && $this->available_slots['current'][$magnetSchoolID][$slot_by_focus]['slots_by_method'][$tracking_value] > 0
                    );
                }
                $this->log( $submission->getID() .' slot2 ', ($slot_available) ?'true':'false' );

                $passes_slotting_method = $this->slotting_methods[$slotting_method]['lottery']->doesSubmissionPassRequirements(
                    $submission,
                    $submissionData['school'],
                    $focus,
                    $round
                );
                $this->log( $submission->getID() .' pass ', ($passes_slotting_method[0]) ?'true':'false' );

                if( !$slot_available
                    || !$passes_slotting_method[0]
                ){
                    if( !isset( $this->submissions['after']['waitlist'][$submission->getId()][$magnetSchoolID] )){
                        $this->log( 'Waitlist Submission ID: ' . $submission->getId() .' -- '. $submissionData['choiceNumber'] .' '. $submissionData['school']->getId() );
                    }
                    $this->submissions['after']['waitlist'][$submission->getId()][$magnetSchoolID] = $submissionData;
                    continue;
                }

                $this->log( 'Awarded Submission ID: ' . $submission->getId() .' -- '. $submissionData['choiceNumber']);

                //if( isset( $tracking_value ) && $tracking_value != null ){$this->log( $tracking_value ); }
                $this->submissions['after']['awarded'][$submission->getId()][$magnetSchoolID] = $submissionData;

                unset( $this->submissions['after']['waitlist'][$submission->getId()][$magnetSchoolID] );
                unset( $this->submissions['after']['denied'][$submission->getId()][$magnetSchoolID] );

                $magnetSchool = $submissionData['school'];

                $tracking_column = $this->slotting_methods[$slotting_method]['lottery']->getTrackingColumn();
                $tracking_value = $this->slotting_methods[$slotting_method]['lottery']->getTrackingValue($submission);

                $magnet_id = $magnetSchool->getId();

                if( !isset($this->available_slots['current'][$magnet_id][$slot_by_focus][$tracking_value] ) ){
                    $this->available_slots['current'][$magnet_id][$slot_by_focus][$tracking_value] = 0;
                }

                $program_id = $magnetSchool->getProgram()->getId();
                if( !isset( $this->available_slots['current']['programs'][$program_id][$tracking_value] )){
                    $this->available_slots['current']['programs'][$program_id][$tracking_value] = 0;
                }

                if( isset( $this->available_slots['current'][$magnetSchoolID][$slot_by_focus]['slots_by_method'][$tracking_value] ) ){
                    $this->available_slots['current'][$magnetSchoolID][$slot_by_focus]['slots_by_method'][$tracking_value] -= 1;
                }

                $this->available_slots['current'][$magnetSchool->getId()][$slot_by_focus]['TAS'] -= 1;
                $this->available_slots['current'][$magnetSchool->getId()][$slot_by_focus][$tracking_value] += 1;
                $this->available_slots['current'][$magnetSchool->getId()][$slot_by_focus]['changed'] = true;
                $this->available_slots['current']['programs'][$magnetSchool->getProgram()->getId()][$tracking_value] += 1;

                $maybe_repeat = $this->slotting_methods[$slotting_method]['lottery']
                        ->maybeRestartRound( $round );

                $this->log( 'END INNER LOOP ROUND '. $round );

                if( $maybe_repeat ){
                    return true; //Restart Round
                }
            }

            $maybe_repeat = $this->slotting_methods[$slotting_method]['lottery']
                        ->maybeRestartRound( $round, 'finished list' ) ;
            $this->log( 'END OUTER LOOP ROUND '. $round, $maybe_repeat );

            if( $maybe_repeat ){
                return true; //Restart Round
            }
        }
        return false; //Do NOT Restart Round
    }

    private function save_Outcome_Submissions( $type ){

        $this->log( 'Save Outcome Submissions '. $type .' '. count($this->submissions['after'][$type]) );

        foreach( $this->submissions['after'][$type] as $submissionResults ){
            foreach( $submissionResults as $submissionData ){

                $slot_by_focus = ( isset( $this->available_slots['current'][$submissionData['school']->getId()][0] ) )
                    ? 0
                    : $submissionData['focus_area'];

                $submissionOutcome = new LotteryOutcomeSubmission();
                $submissionOutcome->setType($type);
                $submissionOutcome->setSubmission($submissionData['submission']);
                $submissionOutcome->setMagnetSchool($submissionData['school']);
                $submissionOutcome->setFocusArea($slot_by_focus);
                $submissionOutcome->setChoiceNumber( $submissionData['choiceNumber'] );
                $submissionOutcome->setLotteryNumber($submissionData['submission']->getLotteryNumber());
                $submissionOutcome->setOpenEnrollment($this->open_enrollment);
                $submissionOutcome->setPlacement($this->placement);
                $this->entity_manager->persist($submissionOutcome);
            }
        }
        $this->entity_manager->flush();
    }

    private function save_Outcome_Populations( $type ){

        $non_population_keys = [
            'TAS',
            'originalTAS',
            'lastTAS',
            'maxCapacity',
            'changed',
            'program',
            'slotting_method',
            'tracking_column',
            'slots_by_method'
        ];

        $available_slot_key = ( $type == 'after' ) ? 'current' : $type;

        foreach( $this->available_slots[$available_slot_key] as $magnetID => $availableSlotsForSchool ) {

            $magnetSchool = $this->entity_manager->getRepository('IIABMagnetBundle:MagnetSchool')->find($magnetID);

            foreach( $availableSlotsForSchool as $focus_area => $availableSlots ) {

                $focus_value = ($focus_area) ? $focus_area : null;

                //Ensures we found a Magnet School
                if ($magnetSchool != null) {

                    $tracking_column = $availableSlots['tracking_column'];

                    foreach( $availableSlots as $tracking_value => $count ){
                        if( !in_array( $tracking_value, $non_population_keys ) ){

                            $slot_by_focus = ( !empty( $focus_value ) ) ? $focus_value : 0;

                            if( $type =='after' ){

                                if( !isset( $this->available_slots['before'][$magnetSchool->getId()][$focus_area][$tracking_value] )){
                                    $this->available_slots['before'][$magnetSchool->getId()][$focus_area][$tracking_value] = 0;
                                }

                                $change_amount = $this->available_slots['current'][$magnetSchool->getId()][$focus_area][$tracking_value]
                                    - $this->available_slots['before'][$magnetSchool->getId()][$focus_area][$tracking_value];

                                $populationChanges = $this->entity_manager->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy(['type' => 'changed', 'magnetSchool' => $magnetSchool, 'focusArea' => $focus_value ] );

                                $populationChanges = (isset($populationChanges)) ? $populationChanges : new LotteryOutcomePopulation();
                                $populationChanges->setType('changed');
                                $populationChanges->setMagnetSchool($magnetSchool);
                                $populationChanges->setOpenEnrollment($this->open_enrollment);
                                $populationChanges->setPlacement($this->placement);
                                $populationChanges->setTrackingColumn( $tracking_column );
                                $populationChanges->setTrackingValue( $tracking_value );
                                $populationChanges->setCount( $change_amount );
                                $populationChanges->setFocusArea( $focus_value );
                                $populationChanges->setMaxCapacity($this->available_slots['current'][$magnetSchool->getId()][$focus_area]['maxCapacity']);
                                $this->entity_manager->persist($populationChanges);
                            }

                            $populationOutcome = $this->entity_manager->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')
                                ->findOneBy([
                                    'type' => $type,
                                    'magnetSchool' => $magnetSchool,
                                    'focusArea' => $focus_value
                                ]
                            );

                            $populationOutcome = (isset($populationOutcome)) ? $populationOutcome : new LotteryOutcomePopulation();
                            $populationOutcome->setType($type);
                            $populationOutcome->setMagnetSchool($magnetSchool);
                            $populationOutcome->setOpenEnrollment($this->open_enrollment);
                            $populationOutcome->setPlacement($this->placement);
                            $populationOutcome->setTrackingColumn( $tracking_column );
                            $populationOutcome->setTrackingValue( $tracking_value );
                            $populationOutcome->setCount( $count );
                            $populationOutcome->setFocusArea( $focus_value );
                            $populationOutcome->setMaxCapacity($this->available_slots['current'][$magnetSchool->getId()][$focus_area]['maxCapacity']);

                            $this->entity_manager->persist($populationOutcome);
                        }
                    }
                }
            }
        }
    }

    public function commit_Lottery( OpenEnrollment $openEnrollment ) {

        $submissionsAffected = 0;
        $has_outcomes = $this->entity_manager->getRepository( 'IIABMagnetBundle:LotteryOutcomeSubmission' )->findOneBy([], ['id'=>'DESC'] );

        if( !isset( $has_outcomes ) ){
            return $submissionsAffected;
        }

        $now = new \DateTime();

        $placement = $has_outcomes->getPlacement();
        $this->get_AvailableSlots();

        //Commit Withdrawals
        $pending_withdrawals = $this->entity_manager
            ->getRepository( 'IIABMagnetBundle:LotteryOutcomePopulation' )
            ->findBy( [ 'type' => 'withdrawal' ] );

        $this->log( 'Commit Withdrawals '. count( $pending_withdrawals ));

        foreach( $pending_withdrawals as $pending_withdrawal ){

            $withdrawal_record = $this->population_service->withdraw([
                'date_time' => $now,
                'school' => $pending_withdrawal->getMagnetSchool(),
                'tracking_value' => $pending_withdrawal->getTrackingValue(),
                'count' => $pending_withdrawal->getCount(),
            ]);
        }
        $this->population_service->persist_and_flush();

        //Commit Offers
        $submission_outcomes = $this->entity_manager->getRepository( 'IIABMagnetBundle:LotteryOutcomeSubmission' )
            ->findBy( [ 'type' => 'awarded' ] );

        $offered_status = $this->entity_manager->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( [ 'id' => 6 ] );
        $offers = [];
        $offers_with_waitlist = false;

        $this->log( 'Commit Offers '. count( $submission_outcomes ));
        foreach( $submission_outcomes as $outcome ){
            $submission = $outcome->getSubmission();

            if( isset( $submission ) && in_array( $submission->getSubmissionStatus()->getId(), [1,9] ) ) {

                $submission->setSubmissionStatus( $offered_status );

                $waitListItems = $this->entity_manager->getRepository('IIABMagnetBundle:WaitList')->findBy( array(
                    'submission' => $submission->getId(),
                ) );

                $clear_choices = [];
                if( !empty( $submission->getFirstChoice() ) && $submission->getFirstChoice()->getId() == $outcome->getMagnetSchool()->getId() ){
                    $clear_choices = ['First', 'Second', 'Third'];
                } else if( !empty( $submission->getSecondChoice() ) && $submission->getSecondChoice()->getId() == $outcome->getMagnetSchool()->getId() ){
                    $clear_choices = ['Second', 'Third'];
                } else if( !empty( $submission->getThirdChoice() ) && $submission->getThirdChoice()->getId() == $outcome->getMagnetSchool()->getId() ){
                    $clear_choices = ['Third'];
                }

                foreach( $waitListItems as $waitList ) {

                    $cleared = false;
                    if( $waitList != null ) {

                        foreach( $clear_choices as $clear ){
                            if( !empty($submission->{'get'. $clear .'Choice'}() ) && $waitList->getChoiceSchool()->getId() == $submission->{'get'. $clear .'Choice'}()->getId() ){
                                $this->entity_manager->remove($waitList);
                                $cleared = true;
                            }
                        }

                        if( !$cleared ){
                            $offers_with_waitlist = true;
                        }
                    }
                }

                $url = $submission->getId() . '.' . rand( 10 , 999 );

                $offer = new Offered();
                $offer->setSubmission( $submission );
                $offer->setUrl( $url );
                $offer->setAwardedSchool( $outcome->getMagnetSchool() );
                $offer->setAwardedFocusArea( $outcome->getFocusArea() );
                $offer->setOpenEnrollment( $outcome->getOpenEnrollment() );
                $offer->setOfflineEndTime( $placement->getOfflineEndTime() );
                $offer->setOnlineEndTime( $placement->getOnlineEndTime() );

                $this->entity_manager->persist( $submission );
                $this->entity_manager->persist( $offer );

                $offer_population = $this->population_service->offer([
                    'date_time' => $now,
                    'school' => $outcome->getMagnetSchool(),
                    'submission' => $submission
                ]);
                $offers[$outcome->getSubmission()->getId()] = true;
            }
        }
        $this->log('persist');
        $this->population_service->persist_and_flush();
        $submissionsAffected = count( $offers );

        //Commit Waitlist
        $waitlist_status = $this->entity_manager->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( [ 'id' => 9 ] );
        $submission_outcomes = $this->entity_manager->getRepository( 'IIABMagnetBundle:LotteryOutcomeSubmission' )->findBy( [ 'type' => 'waitlist', 'placement' => $placement ] );
        $waitlists = [];
        $this->log( 'Commit Waitlist '. count( $submission_outcomes ));
        foreach( $submission_outcomes as $outcome ){
            $submission = $outcome->getSubmission();

            if( isset( $submission )
                && in_array( $submission->getSubmissionStatus()->getId(), [1,6,9] )
            ) {

                if( !isset( $offers[ $submission->getId() ] )
                    && !isset( $waitlists[ $submission->getId() ] ) ) {
                    $submission->setSubmissionStatus( $waitlist_status );
                    $submissionsAffected ++;
                }

                $has_waitlist_entry = $this->entity_manager->getRepository( 'IIABMagnetBundle:WaitList' )->findBy([
                    'submission' => $submission,
                    'choiceSchool' => $outcome->getMagnetSchool(),
                    'choiceFocusArea' => $outcome->getFocusArea()
                ]);

                if( !$has_waitlist_entry ) {
                    $waitList = new WaitList();
                    $waitList->setChoiceSchool($outcome->getMagnetSchool());
                    $waitList->setChoiceFocusArea( $outcome->getFocusArea() );
                    $waitList->setOpenEnrollment($outcome->getOpenEnrollment());
                    $waitList->setSubmission($submission);
                    $this->entity_manager->persist($waitList);

                    $waitlisted_date = new \DateTime();
                    $extra_data = new SubmissionData();
                    $extra_data->setSubmission($submission);
                    $extra_data->setMetaKey('waitlisted date');
                    $extra_data->setMetaValue($waitlisted_date->format('M-d-Y'));
                    $this->entity_manager->persist($extra_data);
                    $waitlists[$outcome->getSubmission()->getId()] = true;
                }
            }
        }

        //Commit Denied
        $denied_status = $this->entity_manager->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( [ 'id' => 3 ] );
        $submission_outcomes = $this->entity_manager->getRepository( 'IIABMagnetBundle:LotteryOutcomeSubmission' )->findBy( [ 'type' => 'denied' ] );
        $denials = false;
        $this->log( 'Commit Denied '. count( $submission_outcomes ));
        foreach( $submission_outcomes as $outcome ){
            $submission = $outcome->getSubmission();
            if( isset( $submission )
                && empty($offers[ $submission->getId() ])
                && empty($waitlists[ $submission->getId() ] )
                && in_array( $submission->getSubmissionStatus()->getId(), [1,9] )
            ) {
                $denials = true;
                $submission->setSubmissionStatus( $denied_status );
                $submissionsAffected ++;
            }
        }
        unset( $submission_outcomes );

        $today = new \DateTime();

        if( $offers ) {
            if( $placement->getAwardedMailedDate() < $today ) {
                $placement->setAwardedMailedDate( $today );
            }

            $awardedPDF = new Process();
            $awardedPDF->setOpenEnrollment($openEnrollment);
            $awardedPDF->setEvent('pdf');
            $awardedPDF->setType('awarded');
            $this->entity_manager->persist($awardedPDF);
        }

        if( $waitlists ) {
            if ($placement->getWaitListMailedDate() < $today) {
                $placement->setWaitListMailedDate($today);
            }

            $waitlistPDF = new Process();
            $waitlistPDF->setOpenEnrollment($openEnrollment);
            $waitlistPDF->setEvent('pdf');
            $waitlistPDF->setType('wait-list');
            $this->entity_manager->persist($waitlistPDF);
        }

        if( ( $offers && $waitlists ) || $offers_with_waitlist ) {
            if ($placement->getWaitListMailedDate() < $today) {
                $placement->setWaitListMailedDate($today);
            }
            $awardedWaitlistPDF = new Process();
            $awardedWaitlistPDF->setOpenEnrollment($openEnrollment);
            $awardedWaitlistPDF->setEvent('pdf');
            $awardedWaitlistPDF->setType('awarded-wait-list');
            $this->entity_manager->persist($awardedWaitlistPDF);
        }

        if( $denials ) {
            if( $placement->getDeniedMailedDate() < $today ) {
                $placement->setDeniedMailedDate( $today );
            }

            $deniedPDF = new Process();
            $deniedPDF->setOpenEnrollment($openEnrollment);
            $deniedPDF->setEvent('pdf');
            $deniedPDF->setType('denied');
            $this->entity_manager->persist($deniedPDF);
        }

        $placement->setCompleted( true );
        $placement->setRunning( false );
        $this->entity_manager->flush();

        //clear outcomes
        $clear_outcomes = $this->entity_manager->createQuery('DELETE IIABMagnetBundle:LotteryOutcomeSubmission');
        $clear_outcomes->execute();
        $clear_outcomes = $this->entity_manager->createQuery('DELETE IIABMagnetBundle:LotteryOutcomePopulation');
        $clear_outcomes->execute();

        $before_file = '';
        $after_file = '';

        // Copy debugging file to reporting directory
        $debuggingDir = $this->container->get( 'kernel' )->getRootDir() . '/../web/debugging/';

        if( !file_exists( $debuggingDir ) ) {
            mkdir( $debuggingDir );
        }

        $debuggingFiles = array_diff( scandir( $debuggingDir, SCANDIR_SORT_DESCENDING ) , array( '..' , '.' , '.DS_Store' ) );
        rsort( $debuggingFiles );

        foreach( $debuggingFiles as $file ){
            if( strpos( $file, '-debug-after' ) ){
                $after_file = $file;
                break;
            }
        }

        if( $after_file ) {

            $before_file = str_replace('-after-', '-before-', $after_file);
            $before_file = (in_array($before_file, $debuggingFiles)) ? $before_file : '';

            $reportingDIR = $this->container->get('kernel')->getRootDir() . '/../web/reports/lottery-list/' . $openEnrollment->getId() . '/';
            if (!file_exists($reportingDIR)) {
                mkdir($reportingDIR, 0755, true);
            }

            if ($before_file) {
                copy($debuggingDir . $before_file, $reportingDIR . str_replace('-debug-', '-', $before_file));
            }
            copy($debuggingDir . $after_file, $reportingDIR . str_replace('-debug-', '-', $after_file));
        }

        return $submissionsAffected;
    }

    public function download_list( $list_type, OpenEnrollment $open_enrollment ){

        $this->open_enrollment = $open_enrollment;
        $this->get_AvailableSlots();

        $this->available_slots['current'] = $this->available_slots['before'];

        $this->get_MagnetSchoolSettings();

        gc_enable();
        gc_collect_cycles();

        switch( $list_type ){
            case 'lottery-list':
                $this->build_LotteryGroupingArray(
                    $this->submission_status['active']
                );
                $this->write_LoggingFile( 'before' , 'lottery' , 'Lottery List', 'download' );
                break;
            case 'late-period-list':
                $this->build_LotteryGroupingArray([
                    $this->submission_status['waitlist'],
                    $this->submission_status['active']
                ]);
                $this->write_LoggingFile( 'before' , 'late-lottery' , 'Late Lottery List', 'download' );
                break;
            case 'wait-list':
                $this->build_LotteryGroupingArray(
                    $this->submission_status['waitlist']
                );
                $this->write_LoggingFile( 'before' , 'wait-list' , 'Wait List', 'download' );
                break;
            default:
                $this->log('No Lottery Type Selected');
                return;
        }
    }

    private function write_LoggingFile( $list = '' , $fileName = 'lottery' , $title = 'Lottery List', $for_download = false ) {

        if( $for_download ){
            $file_list = '-preview';
            $dir = substr( $this->container->get( 'kernel' )->getRootDir(), 0, -3) . 'web/reports/lottery-list/preview/';

            if (file_exists($dir)) {
                array_map('unlink', glob( $dir . "*.csv"));
            }

        } else {
            $file_list = '-debug-'.$list;
            $dir = substr( $this->container->get( 'kernel' )->getRootDir(), 0, -3) . 'web/debugging/';
        }

        if( !file_exists( $dir ) ) {
            mkdir( $dir, 0777, true );
        }

        if( $list == 'before' ) {

            $beforeData = $this->submissions['groups'];

            if( !empty( $beforeData ) ) {

                $handle = fopen($dir . $fileName . $file_list . '-' . date( 'Y-m-d-H-i' ) . '.csv', 'w+');

                $now = new \DateTime();
                $generationDate = $now->format( 'm/d/Y g:i:s a' );
                fputcsv($handle, ['Before ' . $title .' '. $generationDate] );

                fputcsv($handle, [
                    'Submission ID',
                    'State ID',
                    'Race',
                    'Selected School',
                    'Grade',
                    'Choice Number',
                    '1st SubChoice',
                    '2nd SubChoice',
                    '3rd SubChoice',
                    'Current School',
                    'Zoned School',
                    'Lottery Number',
                    'Meets all Eligibility Requirements'
                ]);

                foreach( $beforeData as $choice => $submissions ) {

                    $choice = ucfirst($choice);

                    foreach( $submissions as $submissionData ){

                        $submission = $submissionData['submission'];

                        fputcsv($handle, [
                            $submission->getId(),
                            $submission->getStateId(),
                            $submission->getRace(),
                            $submission->{'get'. $choice .'Choice'}()->getName(),
                            $submission->getNextGrade(),
                            $choice,
                            $submission->{'get'. $choice .'ChoiceFirstChoiceFocus'}(),
                            $submission->{'get'. $choice .'ChoiceSecondChoiceFocus'}(),
                            $submission->{'get'. $choice .'ChoiceThirdChoiceFocus'}(),
                            $submission->getCurrentSchool(),
                            $submission->getZonedSchool(),
                            $submission->getLotteryNumber(),
                            ($this->eligibility_service->doesSubmissionHaveAllEligibility(
                                $submission,
                                $submission->{'get'. $choice .'Choice'}(),
                                $submission->{'get'. $choice .'ChoiceFirstChoiceFocus'}()
                            ) ) ? 'Eligible' : 'Not Eligible',
                        ]);
                    }
                }
                fclose($handle);
            }
        }
        return true;
    }

    private function apply_Withdrawals(){
        $this->log( 'Applying Withdrawals');

        $latestGroupDateTime = $this->getLatestWaitListProcessingDate( $this->open_enrollment );

        $waitlist_processing_records = $this->entity_manager
            ->getRepository('IIABMagnetBundle:WaitListProcessing')
            ->findBy([
                'addedDateTimeGroup' => $latestGroupDateTime,
            ]);

        foreach( $this->available_slots['before'] as $magnetID => $settings ) {
            //Skip over the Programs Index.
            if( $magnetID == 'programs' ) {
                continue;
            }
        }

        $magnet_school_hash = [];
        foreach( $waitlist_processing_records as $waitlist_record ){

            $magnet_school = $waitlist_record->getMagnetSchool();
            $focus = $waitlist_record->getFocusArea();
            $focus = ($focus != null) ? $focus : 0;

            switch( $waitlist_record->getTrackingColumn() ){
                case 'slotsToAward':
                    $magnet_school_hash
                        [ $magnet_school->getId() ]
                        [ $focus ]
                        [ 'TAS' ] = $waitlist_record->getCount();
                break;

                default:
                    $magnet_school_hash
                        [ $magnet_school->getId() ]
                        [ $focus ]
                        [ $waitlist_record->getTrackingColumn() ]
                        [ $waitlist_record->getTrackingValue() ] = $waitlist_record->getCount();
                break;
            }
        }

        foreach( $this->available_slots['before'] as $magnet_id => $focus_areas ){

            if( $magnet_id == 'programs' ){
                continue;
            }

            foreach( $focus_areas as $focus => $settings ){

                if( empty( $magnet_school_hash[ $magnet_id ][ $focus ] ) ){
                    $settings['TAS'] = 0;
                    $settings['originalTAS'] = 0;
                    $settings['lastTAS'] = 0;
                } else {
                    //WaitListProcessing was found, adjust the numbers as needed.
                    $settings['TAS'] = $magnet_school_hash[ $magnet_id ][$focus]['TAS'];
                    $settings['originalTAS'] = $magnet_school_hash[ $magnet_id ][$focus]['TAS'];
                    $settings['lastTAS'] = $magnet_school_hash[ $magnet_id ][$focus]['TAS'];

                    $reserved_keys = ['TAS','originalTAS','lastTAS','maxCapacity', 'changed', 'program', 'slotting_method', 'tracking_column', 'slots_by_method' ];

                    foreach( $magnet_school_hash[ $magnet_id ][$focus] as $slotting_method => $values ){
                        if( $slotting_method == 'TAS' ){
                            continue;
                        }

                        foreach( $values as $value => $count ){

                            if( !in_array($value, $reserved_keys ) ){
                                if( $slotting_method == 'Race' ){
                                    $settings[ 'Race' ][ $value ] = ( isset( $settings[ 'Race' ][ $value ] ) ) ? $settings[ 'Race' ][ $value ] : 0;
                                    $settings[ 'Race' ][ $value ] = $settings[ 'Race' ][ $value ] - $count;
                                } else {
                                    $settings[ 'slots_by_method' ][ $value ] = $settings[ 'slots_by_method' ][ $value ] - $count;
                                }
                            }
                        }
                    }

                    foreach( array_keys( $settings ) as $key ){
                        if( in_array($key, $reserved_keys ) ){

                        }
                    }
                }
                $this->available_slots['before'][$magnet_id][$focus] = $settings;
            }
        }
    }

    /**
     * Find the latest WaitListProcessing Entry and return the dateTime of added.
     *
     * @param OpenEnrollment $openEnrollment
     *
     * @return \DateTime
     * @throws \Exception
     */
    public function getLatestWaitListProcessingDate( $openEnrollment ) {

        $waitListItem = $this->entity_manager
            ->getRepository('IIABMagnetBundle:WaitListProcessing')
            ->findOneBy([
                'openEnrollment' => $openEnrollment
            ], [
                'addedDateTimeGroup' => 'DESC'
            ]);

        if( $waitListItem == null ) {
            throw new \Exception( 'Trying to process the WaitList without an WaitListProcessing Entity Found' , '1000' );
        }

        return $waitListItem->getAddedDateTimeGroup();
    }
}