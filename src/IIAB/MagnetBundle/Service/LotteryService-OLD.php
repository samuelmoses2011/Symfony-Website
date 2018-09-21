<?php

namespace IIAB\MagnetBundle\Service;

use Doctrine\ORM\EntityManager;
use IIAB\MagnetBundle\Entity\AfterPlacementPopulation;
use IIAB\MagnetBundle\Entity\CurrentPopulation;
use IIAB\MagnetBundle\Entity\LotteryOutcomePopulation;
use IIAB\MagnetBundle\Entity\LotteryOutcomeSubmission;
use IIAB\MagnetBundle\Entity\Offered;
use IIAB\MagnetBundle\Entity\OpenEnrollment;
use IIAB\MagnetBundle\Entity\Placement;
use IIAB\MagnetBundle\Entity\Process;
use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionData;
use IIAB\MagnetBundle\Entity\SubmissionStatus;
use IIAB\MagnetBundle\Entity\WaitList;
use IIAB\MagnetBundle\Entity\MagnetSchool;
use IIAB\MagnetBundle\Entity\MagnetSchoolSetting;
use IIAB\MagnetBundle\Entity\Withdrawals;
use Symfony\Component\DependencyInjection\ContainerInterface;
use IIAB\MagnetBundle\Service\EligibilityRequirementsService;

class LotteryService {

	/** @var ContainerInterface */
	private $container;

	/** @var EntityManager */
	private $emLookup;

	/** @var array Collection of Submission that have been awarded */
	private $awardedList;

	/** @var array Collection of the original submissions. */
	private $originalSubmissions;

	/** @var array Collection of the ADM data locally. */
	private $admData;

	/** @var array Collection of MagnetSchoolSettings. */
	private $magnetSchoolSettings;

	/** @var array List of grades in processing order */
	private $grade_order = array( 99 , 0 , 1 , 2 , 3 , 4 , 5 , 6 , 7 , 8 , 9 , 10 , 11 , 12 );

	/** @var array Submission Data sorted for export to excel */
	private $loggingOrderingArray = array(
		array(
			'before' => array() ,
			'after' => array() ,
		)
	);

	/** @var array Submission Data sorted for lottery processing */
	private $lotteryGroupingArray = array();

	/**
	 * Setting up all the defaults needed for the Class.
	 *
	 * @param ContainerInterface $container
	 * @param EntityManager      $emLookup
	 */
	function __construct( ContainerInterface $container = null , EntityManager $emLookup ) {

		$this->container = $container;
		$this->emLookup = $emLookup;
		$this->awardedList = array();
		$this->originalSubmissions = array();
		$this->admData = array();
		$this->magnetSchoolSettings = array();
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
			$submission = $this->emLookup->getRepository( 'IIABMagnetBundle:Submission' )->findOneBy( array(
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

	/**
	 * Commits the Outcome from the lottery and clears the Outcome tables
	 *
	 * @param OpenEnrollment $openEnrollment
	 * @return int
	 */
	public function commitLottery( OpenEnrollment $openEnrollment ) {

		$submissionsAffected = 0;
		$has_outcomes = $this->emLookup->getRepository( 'IIABMagnetBundle:LotteryOutcomeSubmission' )->findOneBy([], ['id'=>'DESC'] );

		if( !isset( $has_outcomes ) ){
			return $submissionsAffected;
		}

		$now = new \DateTime();

        $placement = $has_outcomes->getPlacement();
        $available_slots = $this->getTotalAvailableSlotsWithFocusAreas( $openEnrollment );
		//Commit Withdrawals
		$pending_withdrawals = $this->emLookup->getRepository( 'IIABMagnetBundle:LotteryOutcomePopulation' )->findBy( [ 'type' => 'withdrawal' ] );

		foreach( $pending_withdrawals as $pending_withdrawal ){
			$withdrawal = new Withdrawals();
			$withdrawal->setOpenEnrollment( $pending_withdrawal->getOpenEnrollment() );
			$withdrawal->setWithdrawalDateTime( $now );
			$withdrawal->setMagnetSchool( $pending_withdrawal->getMagnetSchool() );
			$withdrawal->setFocusArea( $pending_withdrawal->getFocusArea() );
			$withdrawal->setMaxCapacity( $pending_withdrawal->getMaxCapacity() );
			$withdrawal->setCPBlack( $pending_withdrawal->getCPBlack() );
			$withdrawal->setCPWhite( $pending_withdrawal->getCPWhite() );
			$withdrawal->setCPOther( $pending_withdrawal->getCPOther() );
			$this->emLookup->persist( $withdrawal );

            if( !empty( $withdrawal->getFocusArea() ) ){
                $available_slots[ $withdrawal->getMagnetSchool()->getId() ][ $withdrawal->getFocusArea() ]['CPBlack'] -= $pending_withdrawal->getCPBlack();
                $available_slots[ $withdrawal->getMagnetSchool()->getId() ][ $withdrawal->getFocusArea() ]['CPWhite'] -= $pending_withdrawal->getCPWhite();
                $available_slots[ $withdrawal->getMagnetSchool()->getId() ][ $withdrawal->getFocusArea() ]['CPOther'] -= $pending_withdrawal->getCPOther();
                $available_slots[ $withdrawal->getMagnetSchool()->getId() ][ $withdrawal->getFocusArea() ]['changed'] = true;
            } else {

                $available_slots[ $withdrawal->getMagnetSchool()->getId() ][ 0 ]['CPBlack'] -= $pending_withdrawal->getCPBlack();
                $available_slots[ $withdrawal->getMagnetSchool()->getId() ][ 0 ]['CPWhite'] -= $pending_withdrawal->getCPWhite();
                $available_slots[ $withdrawal->getMagnetSchool()->getId() ][ 0 ]['CPOther'] -= $pending_withdrawal->getCPOther();
                $available_slots[ $withdrawal->getMagnetSchool()->getId() ][ 0 ]['changed'] = true;
            }
		}

		//Commit Offers
		$submission_outcomes = $this->emLookup->getRepository( 'IIABMagnetBundle:LotteryOutcomeSubmission' )->findBy( [ 'type' => 'offer' ] );

		$offered_status = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( [ 'id' => 6 ] );
		$offers = [];
		$offers_with_waitlist = false;

		foreach( $submission_outcomes as $outcome ){
			$submission = $outcome->getSubmission();
			if( isset( $submission ) && in_array( $submission->getSubmissionStatus()->getId(), [1,9] ) ) {

				$submission->setSubmissionStatus( $offered_status );

				$waitListItems = $this->emLookup->getRepository('IIABMagnetBundle:WaitList')->findBy( array(
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
                                $this->emLookup->remove($waitList);
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

				$this->emLookup->persist( $submission );
				$this->emLookup->persist( $offer );

				switch( strtoupper( $submission->getRaceFormatted() ) ) {

					case 'WHITE':
						$race = 'CPWhite';
						break;

					case 'BLACK':
						$race = 'CPBlack';
						break;

					default:
						$race = 'CPOther';
				}

                if( !empty( $outcome->getFocusArea() ) ){
                    $available_slots[ $outcome->getMagnetSchool()->getId() ][ $outcome->getFocusArea() ][$race] += 1;
                    $available_slots[ $outcome->getMagnetSchool()->getId() ][ $outcome->getFocusArea() ]['changed'] = true;
                } else {
                    $available_slots[ $outcome->getMagnetSchool()->getId() ][0][$race] += 1;
                    $available_slots[ $outcome->getMagnetSchool()->getId() ][0]['changed'] = true;
                }

				$offers[$outcome->getSubmission()->getId()] = true;
			}
		}
		$submissionsAffected = count( $offers );

		//Commit Waitlist
		$waitlist_status = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( [ 'id' => 9 ] );
		$submission_outcomes = $this->emLookup->getRepository( 'IIABMagnetBundle:LotteryOutcomeSubmission' )->findBy( [ 'type' => 'waitlist', 'placement' => $placement ] );
		$waitlists = [];
		foreach( $submission_outcomes as $outcome ){
			$submission = $outcome->getSubmission();
			if( isset( $submission ) && in_array( $submission->getSubmissionStatus()->getId(), [1,6,9] ) ) {

				if( !isset( $offers[ $submission->getId() ] ) && !isset( $waitlists[ $submission->getId() ] ) ) {
					$submission->setSubmissionStatus( $waitlist_status );
					$submissionsAffected ++;
				}

				$has_waitlist_entry = $this->emLookup->getRepository( 'IIABMagnetBundle:WaitList' )->findBy([
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
					$this->emLookup->persist($waitList);

					$waitlisted_date = new \DateTime();
					$extra_data = new SubmissionData();
					$extra_data->setSubmission($submission);
					$extra_data->setMetaKey('waitlisted date');
					$extra_data->setMetaValue($waitlisted_date->format('M-d-Y'));
					$this->emLookup->persist($extra_data);
				}
			}
		}

		//Commit Denied
		$denied_status = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( [ 'id' => 3 ] );
		$submission_outcomes = $this->emLookup->getRepository( 'IIABMagnetBundle:LotteryOutcomeSubmission' )->findBy( [ 'type' => 'denied' ] );
		$denials = false;
		foreach( $submission_outcomes as $outcome ){
			$submission = $outcome->getSubmission();
			if( isset( $submission ) && empty($offers[ $submission->getId() ]) && in_array( $submission->getSubmissionStatus()->getId(), [1,9] ) ) {
				$denials = true;
				$submission->setSubmissionStatus( $denied_status );
				$submissionsAffected ++;
			}
		}
		unset( $submission_outcomes );

		foreach( $available_slots as $school_id => $focus_areas ){

		    foreach( $focus_areas as $focus => $slots ) {
                if (isset($slots['changed']) && $slots['changed']) {
                    $magnetSchool = $this->emLookup->getRepository('IIABMagnetBundle:MagnetSchool')->findOneBy(['id' => $school_id]);

                    $focus_value = ($focus)? $focus : null;

                    //Ensures we found a Magnet School
                    if (isset($magnetSchool)) {
                        $afterPopulation = new AfterPlacementPopulation();
                        $afterPopulation->setMagnetSchool($magnetSchool);
                        $afterPopulation->setFocusArea( $focus_value );
					$afterPopulation->setOpenEnrollment($openEnrollment);

					$afterPopulation->setCPBlack($slots['CPBlack']);
					$afterPopulation->setCPWhite($slots['CPWhite']);
					$afterPopulation->setCPOther($slots['CPOther']);
					$afterPopulation->setMaxCapacity($slots['maxCapacity']);
					$afterPopulation->setLastUpdatedDateTime(new \DateTime());

					$this->emLookup->persist($afterPopulation);
				}
                    $magnetSchool = null;
                }
            }
		}

		$today = new \DateTime();

		if( $offers ) {
			if( $placement->getAwardedMailedDate() < $today ) {
				$placement->setAwardedMailedDate( $today );
			}

			$awardedPDF = new Process();
			$awardedPDF->setOpenEnrollment($openEnrollment);
			$awardedPDF->setEvent('pdf');
			$awardedPDF->setType('awarded');
			$this->emLookup->persist($awardedPDF);
		}

		if( $waitlists ) {
            if ($placement->getWaitListMailedDate() < $today) {
                $placement->setWaitListMailedDate($today);
            }

            $waitlistPDF = new Process();
            $waitlistPDF->setOpenEnrollment($openEnrollment);
            $waitlistPDF->setEvent('pdf');
            $waitlistPDF->setType('wait-list');
            $this->emLookup->persist($waitlistPDF);
        }

        if( ( $offers && $waitlists ) || $offers_with_waitlist ) {
            if ($placement->getWaitListMailedDate() < $today) {
                $placement->setWaitListMailedDate($today);
            }
            $awardedWaitlistPDF = new Process();
            $awardedWaitlistPDF->setOpenEnrollment($openEnrollment);
            $awardedWaitlistPDF->setEvent('pdf');
            $awardedWaitlistPDF->setType('awarded-wait-list');
            $this->emLookup->persist($awardedWaitlistPDF);
		}

		if( $denials ) {
			if( $placement->getDeniedMailedDate() < $today ) {
				$placement->setDeniedMailedDate( $today );
			}

			$deniedPDF = new Process();
			$deniedPDF->setOpenEnrollment($openEnrollment);
			$deniedPDF->setEvent('pdf');
			$deniedPDF->setType('denied');
			$this->emLookup->persist($deniedPDF);
		}

		$placement->setCompleted( true );
		$placement->setRunning( false );
		$this->emLookup->flush();

		//clear outcomes
		$clear_outcomes = $this->emLookup->createQuery('DELETE IIABMagnetBundle:LotteryOutcomeSubmission');
		$clear_outcomes->execute();
		$clear_outcomes = $this->emLookup->createQuery('DELETE IIABMagnetBundle:LotteryOutcomePopulation');
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


    /**
     * Runs the Simple lottery and awards, denies and wait-lists submissions.
     *
     * @param OpenEnrollment $openEnrollment
     *
     * @return integer
     */
    public function runSimpleLottery( OpenEnrollment $openEnrollment, $clear_old_outcomes = true ) {

        $this->logging( 'Running Lottery for ' . $openEnrollment );
        $this->logging( '1: ' . memory_get_usage() , time() );

        $changedSubmissions = 0;
        //$totalAvailableSlots = $this->getTotalAvailableSlots( $openEnrollment );
        $totalAvailableSlots = $this->getTotalAvailableSlotsWithFocusAreas( $openEnrollment );
        $placement = $this->emLookup->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( [ 'openEnrollment' => $openEnrollment ], [ 'round' => 'DESC' ] );

        if( $clear_old_outcomes ) {

            //clear outcomes
            $clear_outcomes = $this->emLookup->createQuery('DELETE IIABMagnetBundle:LotteryOutcomeSubmission');
            $clear_outcomes->execute();
            $clear_outcomes = $this->emLookup->createQuery("DELETE IIABMagnetBundle:LotteryOutcomePopulation");
            $clear_outcomes->execute();
            $this->emLookup->flush();

            //set the starting populations in the outcome table
            foreach( $totalAvailableSlots as $magnetID => $availableSlots ){
                $magnetSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $magnetID );
                //Ensures we found a Magnet School
                if( $magnetSchool != null ) {

                    foreach ($availableSlots as $focus => $slot) {

                        $focus_value = ( $focus ) ? $focus : null;

                        $populationOutcome = new LotteryOutcomePopulation();
                        $populationOutcome->setMagnetSchool($magnetSchool);
                        $populationOutcome->setOpenEnrollment($openEnrollment);
                        $populationOutcome->setPlacement($placement);
                        $populationOutcome->setFocusArea( $focus_value );
                        $populationOutcome->setCPBlack($availableSlots[$focus]['CPBlack']);
                        $populationOutcome->setCPWhite($availableSlots[$focus]['CPWhite']);
                        $populationOutcome->setCPOther($availableSlots[$focus]['CPOther']);
                        $populationOutcome->setMaxCapacity($availableSlots[$focus]['maxCapacity']);
                        $populationOutcome->setType('before');

                        $this->emLookup->persist($populationOutcome);
                    }
                }
                $this->emLookup->flush();
                $magnetSchool = null;
            }
        } else {
            $population_outcome_changes = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findBy([ 'type' => 'changed' ] );
            foreach( $population_outcome_changes as $changes ){

                $schoolID = $changes->getMagnetSchool()->getId();
                $focus = ( !empty( $changes->getFocusArea() ) ) ? $changes->getFocusArea() : 0;

                $totalAvailableSlots[$schoolID][$focus]['CPWhite'] += $changes->getCPWhite();
                $totalAvailableSlots[$schoolID][$focus]['CPBlack'] += $changes->getCPBlack();
                $totalAvailableSlots[$schoolID][$focus]['CPOther'] += $changes->getCPOther();
                $totalAvailableSlots[$schoolID][$focus]['TAS'] -=  $changes->getCPWhite() + $changes->getCPBlack() + $changes->getCPOther();

                $totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPWhite'] += $changes->getCPWhite();
                $totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPBlack'] += $changes->getCPBlack();
                $totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPOther'] += $changes->getCPOther();

            }
        }
        $beforeLotteryAvailableSlots = $totalAvailableSlots;

        $this->magnetSchoolSettings = $this->getMagnetSchoolSettings( $openEnrollment );

        gc_enable();
        gc_collect_cycles();

        $activeStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( array(
            'id' => 1
        ) );

        $this->build_simpleLotteryGroupingArray( $activeStatus, $openEnrollment );

        $eligibility_requirements_service = new EligibilityRequirementsService( $this->emLookup );

        $awarded_submissions = [];
        $ineligible_submissions = [];
        $waitlist_submissions = [];

        $id = 0;

        foreach( $this->lotteryGroupingArray as $choice => $submissions ){

            foreach( $submissions as $submission ){

                $focus_list = [null];
                if( empty( $awarded_submissions[ $submission->getId() ] ) ) {

                    $chosen_school = $submission->{'get' . ucfirst($choice) . 'Choice'}();

                    if (
                        $chosen_school->getGrade() > 1
                        && $chosen_school->getGrade() < 99
                        && $totalAvailableSlots['programs'][ $chosen_school->getProgram()->getId() ][ 'capacity_by'] == 'focus'
                    ){

                        $focus_list = [
                            'first' => $submission->{'get' . ucfirst($choice) . 'ChoiceFirstChoiceFocus'}(),
                            //'second' => $submission->{'get' . ucfirst($choice) . 'ChoiceSecondChoiceFocus'}(),
                            //'third' => $submission->{'get' . ucfirst($choice) . 'ChoiceThirdChoiceFocus'}()
                        ];

                        foreach( $focus_list as $index => $focus ){
                            if( empty( $focus ) ){
                                unset( $focus_list[ $index ] );
                            }
                        }
                    }
                    $focus_list = ( !empty( $focus_list ) ) ? $focus_list : [null];

                    if( $submission->getId() == $id){ var_dump($submission->getId(), $submission->{'get' . ucfirst($choice) . 'ChoiceFirstChoiceFocus'}(), $focus_list ); }

                    foreach( $focus_list as $focus_chosen => $focus ) {

                        $focus_index = (!empty($focus)) ? $focus : 0;
                        if( $focus_index &&
                            !isset( $totalAvailableSlots[$chosen_school->getId()][ $focus_index ] )){
                            continue;
                        }

                        if ($eligibility_requirements_service->doesSubmissionHaveAllEligibility($submission, $chosen_school, $focus) ) {

                            $focus = (!empty($focus)) ? $focus : 0;

                            if ( $totalAvailableSlots[$chosen_school->getId()][$focus]['TAS'] < 1 ) {

                                if( $submission->getId() == $id){ var_dump( 'wait' ); }

                                $waitlist_submissions[$submission->getId()][$choice][$focus] = [
                                //$waitlist_submissions[$submission->getId()][$focus] = [
                                    'submission' => $submission,
                                    'choice' => $choice,
                                    'school' => $chosen_school,
                                    'focus_area' => $focus
                                ];

                            } else if (empty($awarded_submissions[$submission->getId()])) {

                                if( $submission->getId() == $id){ var_dump( 'yes' ); }

                                $awarded_submissions[$submission->getId()] = [
                                    'submission' => $submission,
                                    'choice' => $choice,
                                    'school' => $chosen_school
                                ];


                                if ($totalAvailableSlots['programs'][$chosen_school->getProgram()->getId()]['capacity_by'] == 'focus') {

                                    $awarded_submissions[$submission->getId()]['focus_area'] = $focus;
                                }
                                $totalAvailableSlots[$chosen_school->getId()][$focus]['TAS']--;
                                $totalAvailableSlots[$chosen_school->getId()][$focus]['changed'] = true;
                                $totalAvailableSlots[$chosen_school->getId()][$focus]['CP' . $submission->getRaceFormatted()]++;
                            }
                        } else {
                            $ineligible_submissions[] = [
                                'submission' => $submission,
                                'choice' => $choice,
                                'school' => $chosen_school
                            ];
                        }

                    }
                }
            }
        }

        $choices = [
            1 => 'first',
            2 => 'second',
            3 => 'third'
        ];

        foreach( $waitlist_submissions as $waiting_choices ){

            foreach( $waiting_choices as $choice => $focus_choices ) {

                foreach ($focus_choices as $focus => $waiting) {

                    $focus = ($waiting['focus_area']) ? $waiting['focus_area'] : null;

                    $submissionOutcome = new LotteryOutcomeSubmission();
                    $submissionOutcome->setType('waitlist');
                    $submissionOutcome->setSubmission($waiting['submission']);
                    $submissionOutcome->setMagnetSchool($waiting['school']);
                    $submissionOutcome->setFocusArea($focus);
                    $submissionOutcome->setChoiceNumber(array_search($waiting['choice'], $choices ) );
                    $submissionOutcome->setLotteryNumber($waiting['submission']->getLotteryNumber());
                    $submissionOutcome->setOpenEnrollment($openEnrollment);
                    $submissionOutcome->setPlacement($placement);
                    $this->emLookup->persist($submissionOutcome);
                }
            }
        }

        foreach( $awarded_submissions as $awarded ){
            $submissionOutcome = new LotteryOutcomeSubmission();
                $submissionOutcome->setType('offer');
                $submissionOutcome->setSubmission($awarded['submission']);
                $submissionOutcome->setMagnetSchool($awarded['school']);
                $submissionOutcome->setChoiceNumber(array_search( $awarded['choice'], $choices ) );
                $submissionOutcome->setFocusArea( ( isset( $awarded[ 'focus_area' ] ) ) ? $awarded[ 'focus_area' ] : '' );
                $submissionOutcome->setLotteryNumber($awarded['submission']->getLotteryNumber() );
                $submissionOutcome->setOpenEnrollment($openEnrollment);
                $submissionOutcome->setPlacement( $placement );
                $this->emLookup->persist($submissionOutcome);
        }

        foreach( $ineligible_submissions as $denied ){
            $submissionOutcome = new LotteryOutcomeSubmission();
            $submissionOutcome->setType('denied');
            $submissionOutcome->setSubmission($denied['submission']);
            $submissionOutcome->setMagnetSchool($denied['school']);
            $submissionOutcome->setChoiceNumber(array_search( $denied['choice'], $choices ) );
            $submissionOutcome->setLotteryNumber($denied['submission']->getLotteryNumber() );
            $submissionOutcome->setOpenEnrollment($openEnrollment);
            $submissionOutcome->setPlacement( $placement );
            $this->emLookup->persist($submissionOutcome);
        }

        $this->logging( "Adding After Population Data. If needed." );
        foreach( $totalAvailableSlots as $magnetID => $availableSlotsForSchool ) {

            foreach( $availableSlotsForSchool as $focus_area => $availableSlots ) {

                $focus_value = ($focus_area) ? $focus_area : null;

                $magnetSchool = $this->emLookup->getRepository('IIABMagnetBundle:MagnetSchool')->find($magnetID);
                //Ensures we found a Magnet School
                if ($magnetSchool != null) {

                    $black_changes = $availableSlots['CPBlack'] - $beforeLotteryAvailableSlots[$magnetID][$focus_area]['CPBlack'];
                    $white_changes = $availableSlots['CPWhite'] - $beforeLotteryAvailableSlots[$magnetID][$focus_area]['CPWhite'];
                    $other_changes = $availableSlots['CPOther'] - $beforeLotteryAvailableSlots[$magnetID][$focus_area]['CPOther'];

                    $populationOutcome = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy(['type' => 'after', 'magnetSchool' => $magnetSchool, 'focusArea' => $focus_value ] );
                    $populationChanges = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy(['type' => 'changed', 'magnetSchool' => $magnetSchool, 'focusArea' => $focus_value ] );

                    $populationChanges = (isset($populationChanges)) ? $populationChanges : new LotteryOutcomePopulation();
                    $populationChanges->setType('changed');
                    $populationChanges->setMagnetSchool($magnetSchool);
                    $populationChanges->setOpenEnrollment($openEnrollment);
                    $populationChanges->setPlacement($placement);
                    $populationChanges->setCPBlack( $black_changes );
                    $populationChanges->setCPWhite( $white_changes );
                    $populationChanges->setCPOther( $other_changes );
                    $populationChanges->setFocusArea( $focus_value );
                    $populationChanges->setMaxCapacity($availableSlots['maxCapacity']);
                    $this->emLookup->persist($populationChanges);

                    $populationOutcome = (isset($populationOutcome)) ? $populationOutcome : new LotteryOutcomePopulation();
                    $populationOutcome->setType('after');
                    $populationOutcome->setMagnetSchool($magnetSchool);
                    $populationOutcome->setOpenEnrollment($openEnrollment);
                    $populationOutcome->setPlacement($placement);
                    $populationOutcome->setCPBlack($availableSlots['CPBlack']);
                    $populationOutcome->setCPWhite($availableSlots['CPWhite']);
                    $populationOutcome->setCPOther($availableSlots['CPOther']);
                    $populationOutcome->setFocusArea( $focus_value );
                    $populationOutcome->setMaxCapacity($availableSlots['maxCapacity']);
                    $this->emLookup->persist($populationOutcome);
                }
                $magnetSchool = null;
            }
        }

        $this->emLookup->flush();

        gc_collect_cycles();

        $this->logging( '99: ' . memory_get_usage() , time() );
        $this->logging( 'Lottery Completed' );

        $this->awardedList = null;
        $totalAvailableSlots = null;

        return count( $awarded_submissions) + count( $waitlist_submissions ) + count( $ineligible_submissions );
    }

	/**
	 * Runs the lottery and awards, denies and wait-lists submissions.
	 *
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return integer
	 */
	public function runLottery( OpenEnrollment $openEnrollment, $clear_old_outcomes = true ) {

		$this->logging( 'Running Lottery for ' . $openEnrollment );
		$this->logging( '1: ' . memory_get_usage() , time() );

		$changedSubmissions = 0;
		$totalAvailableSlots = $this->getTotalAvailableSlots( $openEnrollment );
		$placement = $this->emLookup->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( [ 'openEnrollment' => $openEnrollment ], [ 'round' => 'DESC' ] );

		if( $clear_old_outcomes ) {
			//clear outcomes
			$clear_outcomes = $this->emLookup->createQuery('DELETE IIABMagnetBundle:LotteryOutcomeSubmission');
			$clear_outcomes->execute();
			$clear_outcomes = $this->emLookup->createQuery("DELETE IIABMagnetBundle:LotteryOutcomePopulation");
			$clear_outcomes->execute();
			$this->emLookup->flush();

			//set the starting populations in the outcome table
			foreach( $totalAvailableSlots as $magnetID => $availableSlots ){
				$magnetSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $magnetID );
				//Ensures we found a Magnet School
				if( $magnetSchool != null ) {
					$populationOutcome = new LotteryOutcomePopulation();
					$populationOutcome->setMagnetSchool( $magnetSchool );
					$populationOutcome->setOpenEnrollment( $openEnrollment );
					$populationOutcome->setPlacement( $placement );

					$populationOutcome->setCPBlack( $availableSlots['CPBlack'] );
					$populationOutcome->setCPWhite( $availableSlots['CPWhite'] );
					$populationOutcome->setCPOther( $availableSlots['CPOther'] );
					$populationOutcome->setMaxCapacity( $availableSlots['maxCapacity'] );
					$populationOutcome->setType( 'before' );

					$this->emLookup->persist( $populationOutcome );
				}
				$this->emLookup->flush();
				$magnetSchool = null;
			}
		} else {
			$population_outcome_changes = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findBy([ 'type' => 'changed' ] );
			foreach( $population_outcome_changes as $changes ){

				$schoolID = $changes->getMagnetSchool()->getId();
				$totalAvailableSlots[$schoolID]['CPWhite'] += $changes->getCPWhite();
				$totalAvailableSlots[$schoolID]['CPBlack'] += $changes->getCPBlack();
				$totalAvailableSlots[$schoolID]['CPOther'] += $changes->getCPOther();
				$totalAvailableSlots[$schoolID]['TAS'] -=  $changes->getCPWhite() + $changes->getCPBlack() + $changes->getCPOther();

				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPWhite'] += $changes->getCPWhite();
				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPBlack'] += $changes->getCPBlack();
				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPOther'] += $changes->getCPOther();
			}
		}
		$beforeLotteryAvailableSlots = $totalAvailableSlots;

		$this->admData = $this->getADMData( $openEnrollment );
		$this->magnetSchoolSettings = $this->getMagnetSchoolSettings( $openEnrollment );

		gc_enable();
		gc_collect_cycles();

		$localAwardedList = array();

		$activeStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( array(
			'id' => 1
		) );

		$this->build_lotteryGroupingArray( array( $activeStatus ), $openEnrollment );

		//Setting up the logging array.

		for( $gradeCounter = 0; $gradeCounter < 14; ) {

			//Walking the GradesOrder Variable (above)
			foreach( $this->grade_order as $grade ) {

				list( $submissions , $totalAvailableSlots ) = $this->walkList( $this->lotteryGroupingArray[$grade] , $totalAvailableSlots );
				$this->lotteryGroupingArray[$grade] = $submissions;

				//If awarded a submission, then lets update it's statuses
				if( count( $this->awardedList ) > 0 ) {

					$this->logging( 'Added in Offered Submissions.' );
					/**
					 * Looping over the Awarded Submissions and Awarding them.
					 * @var string     $key
					 * @var array $awardedSubmission
					 */
					foreach( $this->awardedList as $key => $submissionData ) {

						/** @var Submission $awardedSubmission */
						$awardedSubmission = $submissionData['submission'];

						$testRace = strtoupper( $awardedSubmission->getRaceFormatted() );
						switch( $testRace ) {

							case 'WHITE':
								$race = 'CPWhite';
								break;

							case 'BLACK':
								$race = 'CPBlack';
								break;

							default:
								$race = 'CPOther';
						}

						//Do not give them multiple awards
						if( !isset( $localAwardedList[$awardedSubmission->getId()] ) ) {
							$submissionID = $awardedSubmission->getId();
							$schoolID = $submissionData['choice']->getId();

							$awardedSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $schoolID ); //The magnet school that has been awarded.

							//Ensure we have an actual awarded school
							if( $awardedSchool != null ) {
								$this->loggingOrderingArray['after']["Grade-" . $awardedSubmission->getNextGrade()]['awarded'][$awardedSubmission->getId()] = array( 'submission' => $awardedSubmission , 'choice' => array( $awardedSchool ) );

								$submissionOutcome = new LotteryOutcomeSubmission();
								$submissionOutcome->setType('offer');
								$submissionOutcome->setSubmission($awardedSubmission);
								$submissionOutcome->setMagnetSchool($awardedSchool);
								$submissionOutcome->setChoiceNumber($submissionData['choiceNumber']);
								$submissionOutcome->setLotteryNumber($submissionData['lottery']);
								$submissionOutcome->setOpenEnrollment($openEnrollment);
								$submissionOutcome->setPlacement( $placement );
								$this->emLookup->persist($submissionOutcome);

								$localOutComeList[ $awardedSubmission->getId() ] = $submissionOutcome;
								$localAwardedList[ $awardedSubmission->getId() ] = $submissionData;

								$totalAvailableSlots[$schoolID]['TAS']--;
								$totalAvailableSlots[$schoolID][$race]++;
								$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']][$race]++;
								$totalAvailableSlots[$schoolID]['changed'] = true;

								$changedSubmissions++;

								$gradeCounter = -1; //Awarded submission, reset to negative one (-1) other wise, let the FOR Loop count up.
							}
						} else {
							//Already been awarded Once but need to see if this choice is HIGHER

							//Get the hold data to see if choice is of Higher Value.
							$alreadyAwardedOfferData = $localAwardedList[$awardedSubmission->getId()];

							//Submission ID has already been Waiting but it could be a lower Choice. See if
							if( $submissionData['choiceNumber'] < $alreadyAwardedOfferData['choiceNumber'] ) {

								//New Choice is HIGHER so we need to try and see if removal of old will pass.
								if( $this->passesRemovalAwardedRacialComposition( $awardedSubmission , $totalAvailableSlots , $alreadyAwardedOfferData['choice']->getId() , $race ) ) {
									$submissionID = $awardedSubmission->getId();
									$schoolID = $submissionData['choice']->getId();

									$awardedSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $schoolID ); //The magnet school that has been awarded.

									//Ensure we have an actual awarded school
									if( $awardedSchool != null ) {
										$this->loggingOrderingArray['after']["Grade-" . $awardedSubmission->getNextGrade()]['awarded'][$awardedSubmission->getId()] = array( 'submission' => $awardedSubmission , 'choice' => array( $awardedSchool ) );

										$this->emLookup->remove( $localOutComeList[ $submissionID ] );

										$submissionOutcome = new LotteryOutcomeSubmission();
										$submissionOutcome->setType('offer');
										$submissionOutcome->setSubmission($awardedSubmission);
										$submissionOutcome->setMagnetSchool($awardedSchool);
										$submissionOutcome->setChoiceNumber($submissionData['choiceNumber']);
										$submissionOutcome->setLotteryNumber($submissionData['lottery']);
										$submissionOutcome->setOpenEnrollment($openEnrollment);
										$submissionOutcome->setPlacement( $placement );
										$this->emLookup->persist($submissionOutcome);

										$localAwardedList[$awardedSubmission->getId()] = $submissionData;

										$totalAvailableSlots[$schoolID]['TAS']--;
										$totalAvailableSlots[$schoolID][$race]++;
										$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']][$race]++;
										$totalAvailableSlots[$schoolID]['changed'] = true;


										//Updated the TAS variables with the minus of the Original Offered Choice.
										//Add back an available Slot.
										//Decrease the overall Programs Race.
										$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()]['TAS']++;
										$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()][$race]--;
										$totalAvailableSlots['programs'][$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()]['program']][$race]--;
										$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()]['changed'] = true;

										$gradeCounter = -1; //Awarded submission, reset to negative one (-1) other wise, let the FOR Loop count up.
									}
								}
							}
						}
					}
				} else {
					//Did not awarded, increment gradeCounter.
					$gradeCounter++;
				}
				$this->awardedList = array();
				gc_collect_cycles();
			}
		}
		$this->emLookup->flush();

		foreach( $this->grade_order as $grade ) {

			//Need to grab all active submissions because they were NOT awarded.
			$submissions = $this->emLookup->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->where( 's.openEnrollment = :enrollment' )
				->andWhere( 's.submissionStatus = 1' )//Submission Status of Active
				->andWhere( 's.nextGrade = :grade' )//Next Grade
				->setParameters( array(
					'enrollment' => $openEnrollment ,
					'grade' => $grade
				) )
				->getQuery()
				->getResult();

			//All submissions are required to have a first submission

			$waitList = [];
			if( count( $submissions ) > 0 ) {

				//TODO: Save this list, need to make waitList by Program/MagnetSchool. How do we handle if one Program does not do Waiting Lists?????

				$this->logging( "Added Wait-list or Denied." );

				/** @var Submission $waitingListSubmission */
				foreach( $submissions as $waitingListSubmission ) {
					//Loop over the left-overs and update status to waiting list.

					if( $waitingListSubmission != null && !isset( $localAwardedList[$waitingListSubmission->getId()] ) ) {

						$addedToWaitList = false;
						if( $waitingListSubmission->getFirstChoice() != null ) {

							//This Choice exists, and now we need to see if there is a setting defined for it.
							if( isset( $this->magnetSchoolSettings[$waitingListSubmission->getFirstChoice()->getId()] ) ) {
								/** @var MagnetSchoolSetting $magnetSchoolSettings */
								$magnetSchoolSettings = $this->magnetSchoolSettings[$waitingListSubmission->getFirstChoice()->getId()];
							} else {
								$magnetSchoolSettings = null;
							}

							if( $magnetSchoolSettings != null ) {

								$passesCommitteeScoring = true;
								$score = $waitingListSubmission->getCommitteeReviewScoreFirstChoice();

								if( $magnetSchoolSettings->getCommitteeScoreRequired() ) {

									//Confirmed: This is greater than equal too. Need to makes sure this is minimum score
									if( $score >= $magnetSchoolSettings->getMinimumCommitteeScore() ) {
										$passesCommitteeScoring = true;
									} else {
										$passesCommitteeScoring = false;
									}
								}

								//Add a new Waitlist Entry and flip the flag to make sure the status is added to the WaitList.
								if( $passesCommitteeScoring ) {
									$addedToWaitList = true;
									$waitList[ $waitingListSubmission->getId() ] = true;

									$submissionOutcome = new LotteryOutcomeSubmission();
									$submissionOutcome->setType('waitlist');
									$submissionOutcome->setSubmission($waitingListSubmission);
									$submissionOutcome->setMagnetSchool( $waitingListSubmission->getFirstChoice());
									$submissionOutcome->setChoiceNumber( 1 );
									$submissionOutcome->setLotteryNumber($waitingListSubmission->getLotteryNumber() );
									$submissionOutcome->setOpenEnrollment($openEnrollment);
									$submissionOutcome->setPlacement( $placement );
									$this->emLookup->persist($submissionOutcome);

									//Adding into the logging array.
									if( !isset( $this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] ) ) {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] = array(
											'submission' => $waitingListSubmission ,
											'choice' => array( '1' => $waitingListSubmission->getFirstChoice() , '2' => '' , '3' => '' )
										);
									} else {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()]['choice']['1'] = $waitingListSubmission->getFirstChoice();
									}
								}
							}
						}
						if( $waitingListSubmission->getSecondChoice() != null ) {
							//This Choice exists, and now we need to see if there is a setting defined for it.
							if( isset( $this->magnetSchoolSettings[$waitingListSubmission->getSecondChoice()->getId()] ) ) {
								$magnetSchoolSettings = $this->magnetSchoolSettings[$waitingListSubmission->getSecondChoice()->getId()];
							} else {
								$magnetSchoolSettings = null;
							}

							if( $magnetSchoolSettings != null ) {

								$passesCommitteeScoring = true;
								$score = $waitingListSubmission->getCommitteeReviewScoreSecondChoice();

								if( $magnetSchoolSettings->getCommitteeScoreRequired() ) {

									//Confirmed: This is greater than equal too. Need to makes sure this is minimum score
									if( $score >= $magnetSchoolSettings->getMinimumCommitteeScore() ) {
										$passesCommitteeScoring = true;
									} else {
										$passesCommitteeScoring = false;
									}
								}

								//Add a new Waitlist Entry and flip the flag to make sure the status is added to the WaitList.
								if( $passesCommitteeScoring ) {
									$addedToWaitList = true;
									$waitList[ $waitingListSubmission->getId() ] = true;

									$submissionOutcome = new LotteryOutcomeSubmission();
									$submissionOutcome->setType('waitlist');
									$submissionOutcome->setSubmission($waitingListSubmission);
									$submissionOutcome->setMagnetSchool( $waitingListSubmission->getSecondChoice());
									$submissionOutcome->setChoiceNumber( 2 );
									$submissionOutcome->setLotteryNumber( $waitingListSubmission->getLotteryNumber() );
									$submissionOutcome->setOpenEnrollment($openEnrollment);
									$submissionOutcome->setPlacement( $placement );
									$this->emLookup->persist($submissionOutcome);

									//Adding into the logging array.
									if( !isset( $this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] ) ) {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] = array(
											'submission' => $waitingListSubmission ,
											'choice' => array( '1' => '' , '2' => $waitingListSubmission->getSecondChoice() , '3' => '' )
										);
									} else {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()]['choice']['1'] = $waitingListSubmission->getSecondChoice();
									}
								}
							}
						}
						if( $waitingListSubmission->getThirdChoice() != null ) {
							//This Choice exists, and now we need to see if there is a setting defined for it.
							if( isset( $this->magnetSchoolSettings[$waitingListSubmission->getThirdChoice()->getId()] ) ) {
								$magnetSchoolSettings = $this->magnetSchoolSettings[$waitingListSubmission->getThirdChoice()->getId()];
							} else {
								$magnetSchoolSettings = null;
							}

							if( $magnetSchoolSettings != null ) {

								$passesCommitteeScoring = true;
								$score = $waitingListSubmission->getCommitteeReviewScoreThirdChoice();

								if( $magnetSchoolSettings->getCommitteeScoreRequired() ) {

									//Confirmed: This is greater than equal too. Need to makes sure this is minimum score
									if( $score >= $magnetSchoolSettings->getMinimumCommitteeScore() ) {
										$passesCommitteeScoring = true;
									} else {
										$passesCommitteeScoring = false;
									}
								}

								//Add a new Waitlist Entry and flip the flag to make sure the status is added to the WaitList.
								if( $passesCommitteeScoring ) {
									$addedToWaitList = true;
									$waitList[ $waitingListSubmission->getId() ] = true;

									$submissionOutcome = new LotteryOutcomeSubmission();
									$submissionOutcome->setType('waitlist');
									$submissionOutcome->setSubmission($waitingListSubmission);
									$submissionOutcome->setMagnetSchool( $waitingListSubmission->getThirdChoice());
									$submissionOutcome->setChoiceNumber( 3 );
									$submissionOutcome->setLotteryNumber( $waitingListSubmission->getLotteryNumber() );
									$submissionOutcome->setOpenEnrollment($openEnrollment);
									$submissionOutcome->setPlacement( $placement );
									$this->emLookup->persist($submissionOutcome);

									//Adding into the logging array.
									if( !isset( $this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] ) ) {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] = array(
											'submission' => $waitingListSubmission ,
											'choice' => array( '1' => '' , '2' => '' , '3' => $waitingListSubmission->getThirdChoice() )
										);
									} else {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()]['choice']['3'] = $waitingListSubmission->getThirdChoice();
									}
								}
							}
						}

						//Change the status of the submission to the waitLIST IF they were added for a specific CHOICE above.
						if( !$addedToWaitList && !isset( $waitlist[ $waitingListSubmission->getId() ] ) && !isset( $localAwardedList[ $waitingListSubmission->getId() ] ) ) {
							$submissionOutcome = new LotteryOutcomeSubmission();
							$submissionOutcome->setType('denied');
							$submissionOutcome->setSubmission($waitingListSubmission);
							$submissionOutcome->setLotteryNumber( $waitingListSubmission->getLotteryNumber() );
							$submissionOutcome->setOpenEnrollment($openEnrollment);
							$submissionOutcome->setPlacement( $placement );
							$this->emLookup->persist($submissionOutcome);

							//Adding into the logging array.
							$this->loggingOrderingArray['after']["Grade-{$grade}"]['denied'][] = array( 'submission' => $waitingListSubmission , 'choice' => array( '1' => $waitingListSubmission->getFirstChoice() , '2' => $waitingListSubmission->getSecondChoice() , '3' => $waitingListSubmission->getThirdChoice() ) );
						}
						$changedSubmissions++;
					}
				}
			}
		}

		//Write out the logging file.
		$this->writeLoggingFile( $this->loggingOrderingArray , 'before' );
		$this->writeLoggingFile( $this->loggingOrderingArray , 'after' );
		$this->loggingOrderingArray = null;
		$this->emLookup->flush();

		/**
		 * Saving the changes into AfterPopulation to ensure we capture any changes.
		 *
		 * @var integer $key
		 * @var array   $availableSlots
		 */
		$this->logging( "Adding After Population Data. If needed." );
		foreach( $totalAvailableSlots as $magnetID => $availableSlots ) {

			$magnetSchool = $this->emLookup->getRepository('IIABMagnetBundle:MagnetSchool')->find($magnetID);
			//Ensures we found a Magnet School
			if ($magnetSchool != null) {

				$populationOutcome = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy([ 'type' => 'after', 'magnetSchool' => $magnetSchool ]);
				$populationChanges = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy([ 'type' => 'changed', 'magnetSchool' => $magnetSchool ]);

				$populationChanges = ( isset( $populationChanges ) ) ? $populationChanges : new LotteryOutcomePopulation();
				$populationChanges->setType('changed');
				$populationChanges->setMagnetSchool($magnetSchool);
				$populationChanges->setOpenEnrollment($openEnrollment);
				$populationChanges->setPlacement($placement);
				$populationChanges->setCPBlack($availableSlots['CPBlack'] - $beforeLotteryAvailableSlots[$magnetID]['CPBlack']);
				$populationChanges->setCPWhite($availableSlots['CPWhite'] - $beforeLotteryAvailableSlots[$magnetID]['CPWhite']);
				$populationChanges->setCPOther($availableSlots['CPOther'] - $beforeLotteryAvailableSlots[$magnetID]['CPOther']);
				$populationChanges->setMaxCapacity($availableSlots['maxCapacity']);
				$this->emLookup->persist($populationChanges);

				$populationOutcome = ( isset( $populationOutcome ) ) ? $populationOutcome :  new LotteryOutcomePopulation();
				$populationOutcome->setType('after');
				$populationOutcome->setMagnetSchool($magnetSchool);
				$populationOutcome->setOpenEnrollment($openEnrollment);
				$populationOutcome->setPlacement($placement);
				$populationOutcome->setCPBlack($availableSlots['CPBlack']);
				$populationOutcome->setCPWhite($availableSlots['CPWhite']);
				$populationOutcome->setCPOther($availableSlots['CPOther']);
				$populationOutcome->setMaxCapacity($availableSlots['maxCapacity']);
				$this->emLookup->persist($populationOutcome);
			}
			$magnetSchool = null;
		}

		$this->emLookup->flush();


		gc_collect_cycles();

		$this->logging( '99: ' . memory_get_usage() , time() );
		$this->logging( 'Lottery Completed' );

		$this->awardedList = null;
		$totalAvailableSlots = null;

		return $changedSubmissions;
	}

	/**
	 * Runs the late lottery and awards, denies and wait-lists submissions.
	 *
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return integer
	 */
	public function runLateLottery( OpenEnrollment $openEnrollment, $clear_old_outcomes = true ) {

		$this->logging( 'Running Lottery for ' . $openEnrollment );
		$this->logging( '1: ' . memory_get_usage() , time() );

		$changedSubmissions = 0;
		$totalAvailableSlots = $this->getTotalAvailableSlots( $openEnrollment );
		$placement = $this->emLookup->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( [ 'openEnrollment' => $openEnrollment ], [ 'round' => 'DESC' ] );
		$skipped_submissions = [];

		/**
		 * Update the totalAvailableSlots with the withdrawal information
		 */
		$process_schools = [];
		$withdrawals = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findBy([ 'type' => 'withdrawal' ] );
		foreach( $withdrawals as $changes ){
			$schoolID = $changes->getMagnetSchool()->getId();

			$process_schools[] = $schoolID;
			if( isset( $totalAvailableSlots[$schoolID] ) ) {
				$totalAvailableSlots[$schoolID]['TAS'] = $changes->getMaxCapacity();
				$totalAvailableSlots[$schoolID]['originalTAS'] = $changes->getMaxCapacity();
				$totalAvailableSlots[$schoolID]['lastTAS'] = $changes->getMaxCapacity();

				//Adjust the Racial Composition as needed
				$totalAvailableSlots[$schoolID]['CPBlack'] -= $changes->getCPBlack();
				$totalAvailableSlots[$schoolID]['CPWhite'] -= $changes->getCPWhite();
				$totalAvailableSlots[$schoolID]['CPOther'] -= $changes->getCPOther();

				//Adjust the Programs Racial Composition as needed
				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPBlack'] -= $changes->getCPBlack();
				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPWhite'] -= $changes->getCPWhite();
				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPOther'] -= $changes->getCPOther();
			}
		}

		if( $process_schools ){

			foreach( $totalAvailableSlots as $schoolID => $test ){
				if(  $schoolID != 'programs' && !in_array( $schoolID, $process_schools ) ){
					$totalAvailableSlots[$schoolID]['TAS'] = 0;
					$totalAvailableSlots[$schoolID]['originalTAS'] = 0;
					$totalAvailableSlots[$schoolID]['lastTAS'] = 0;
				}
			}
		}

		/**
		 * Clear the outcomes table by default
		 * Otherwise update the totalAvailableSlots from the outcomes table
		 */
		if( $clear_old_outcomes ) {
			//clear outcomes
			$clear_outcomes = $this->emLookup->createQuery('DELETE IIABMagnetBundle:LotteryOutcomeSubmission');
			$clear_outcomes->execute();
			$clear_outcomes = $this->emLookup->createQuery("DELETE IIABMagnetBundle:LotteryOutcomePopulation AS e WHERE e.type !='withdrawal'");
			$clear_outcomes->execute();
			$this->emLookup->flush();

			//set the starting populations in the outcome table
			foreach( $totalAvailableSlots as $magnetID => $availableSlots ){
				$magnetSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $magnetID );
				//Ensures we found a Magnet School
				if( $magnetSchool != null ) {
					$populationOutcome = new LotteryOutcomePopulation();
					$populationOutcome->setMagnetSchool( $magnetSchool );
					$populationOutcome->setOpenEnrollment( $openEnrollment );
					$populationOutcome->setPlacement( $placement );

					$populationOutcome->setCPBlack( $availableSlots['CPBlack'] );
					$populationOutcome->setCPWhite( $availableSlots['CPWhite'] );
					$populationOutcome->setCPOther( $availableSlots['CPOther'] );
					$populationOutcome->setMaxCapacity( $availableSlots['maxCapacity'] );
					$populationOutcome->setType( 'before' );

					$this->emLookup->persist( $populationOutcome );
				}
				$this->emLookup->flush();
				$magnetSchool = null;
			}
		} else {
			$population_outcome_changes = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findBy([ 'type' => 'changed' ] );
			foreach( $population_outcome_changes as $changes ){

				$schoolID = $changes->getMagnetSchool()->getId();
				$totalAvailableSlots[$schoolID]['CPWhite'] += $changes->getCPWhite();
				$totalAvailableSlots[$schoolID]['CPBlack'] += $changes->getCPBlack();
				$totalAvailableSlots[$schoolID]['CPOther'] += $changes->getCPOther();
				$totalAvailableSlots[$schoolID]['TAS'] -=  $changes->getCPWhite() + $changes->getCPBlack() + $changes->getCPOther();

				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPWhite'] += $changes->getCPWhite();
				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPBlack'] += $changes->getCPBlack();
				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPOther'] += $changes->getCPOther();
			}
		}
		$beforeLotteryAvailableSlots = $totalAvailableSlots;

		$this->admData = $this->getADMData( $openEnrollment );
		$this->magnetSchoolSettings = $this->getMagnetSchoolSettings( $openEnrollment );

		gc_enable();
		gc_collect_cycles();

		$localAwardedList = array();

		$activeStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( array(
			'id' => 1
		) );

		$waitListStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( array(
			'id' => 9
		) );

		$this->build_lotteryGroupingArray( array( $waitListStatus, $activeStatus ) , $openEnrollment);

		for( $gradeCounter = 0; $gradeCounter < 14; ) {

			//Walking the GradesOrder Variable (above)
			foreach( $this->grade_order as $grade ) {

				list( $submissions , $totalAvailableSlots ) = $this->walkList( $this->lotteryGroupingArray[$grade] , $totalAvailableSlots );
				$this->lotteryGroupingArray[$grade] = $submissions;

				//If awarded a submission, then lets update it's statuses
				if( count( $this->awardedList ) > 0 ) {

					$this->logging( 'Added in Offered Submissions.' );
					/**
					 * Looping over the Awarded Submissions and Awarding them.
					 * @var string     $key
					 * @var array $awardedSubmission
					 */
					foreach( $this->awardedList as $key => $submissionData ) {

						/** @var Submission $awardedSubmission */
						$awardedSubmission = $submissionData['submission'];

						$testRace = strtoupper( $awardedSubmission->getRaceFormatted() );
						switch( $testRace ) {

							case 'WHITE':
								$race = 'CPWhite';
								break;

							case 'BLACK':
								$race = 'CPBlack';
								break;

							default:
								$race = 'CPOther';
						}

						//Do not give them multiple awards
						if( !isset( $localAwardedList[$awardedSubmission->getId()] ) ) {
							$schoolID = $submissionData['choice']->getId();

							$awardedSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $schoolID ); //The magnet school that has been awarded.

							//Ensure we have an actual awarded school
							if( $awardedSchool != null ) {
								$this->loggingOrderingArray['after']["Grade-" . $awardedSubmission->getNextGrade()]['awarded'][$awardedSubmission->getId()] = array( 'submission' => $awardedSubmission , 'choice' => array( $awardedSchool ) );

								$submissionOutcome = new LotteryOutcomeSubmission();
								$submissionOutcome->setType('offer');
								$submissionOutcome->setSubmission($awardedSubmission);
								$submissionOutcome->setMagnetSchool($awardedSchool);
								$submissionOutcome->setChoiceNumber($submissionData['choiceNumber']);
								$submissionOutcome->setLotteryNumber($submissionData['lottery']);
								$submissionOutcome->setOpenEnrollment($openEnrollment);
								$submissionOutcome->setPlacement( $placement );
								$this->emLookup->persist($submissionOutcome);

								$localOutComeList[ $awardedSubmission->getId() ] = $submissionOutcome;
								$localAwardedList[ $awardedSubmission->getId() ] = $submissionData;

								$totalAvailableSlots[$schoolID]['TAS']--;
								$totalAvailableSlots[$schoolID][$race]++;
								$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']][$race]++;
								$totalAvailableSlots[$schoolID]['changed'] = true;

								$changedSubmissions++;

								$gradeCounter = -1; //Awarded submission, reset to negative one (-1) other wise, let the FOR Loop count up.
							}
						} else {
							//Already been awarded Once but need to see if this choice is HIGHER

							//Get the hold data to see if choice is of Higher Value.
							$alreadyAwardedOfferData = $localAwardedList[$awardedSubmission->getId()];

							//Submission ID has already been Waiting but it could be a lower Choice. See if
							if( $submissionData['choiceNumber'] < $alreadyAwardedOfferData['choiceNumber'] ) {

								//New Choice is HIGHER so we need to try and see if removal of old will pass.
								if( $this->passesRemovalAwardedRacialComposition( $awardedSubmission , $totalAvailableSlots , $alreadyAwardedOfferData['choice']->getId() , $race ) ) {
									$submissionID = $awardedSubmission->getId();
									$schoolID = $submissionData['choice']->getId();

									$awardedSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $schoolID ); //The magnet school that has been awarded.

									//Ensure we have an actual awarded school
									if( $awardedSchool != null ) {
										$this->loggingOrderingArray['after']["Grade-" . $awardedSubmission->getNextGrade()]['awarded'][$awardedSubmission->getId()] = array( 'submission' => $awardedSubmission , 'choice' => array( $awardedSchool ) );

										$this->emLookup->remove( $localOutComeList[ $submissionID ] );

										$submissionOutcome = new LotteryOutcomeSubmission();
										$submissionOutcome->setType('offer');
										$submissionOutcome->setSubmission($awardedSubmission);
										$submissionOutcome->setMagnetSchool($awardedSchool);
										$submissionOutcome->setChoiceNumber($submissionData['choiceNumber']);
										$submissionOutcome->setLotteryNumber($submissionData['lottery']);
										$submissionOutcome->setOpenEnrollment($openEnrollment);
										$submissionOutcome->setPlacement( $placement );
										$this->emLookup->persist($submissionOutcome);

										$localAwardedList[$awardedSubmission->getId()] = $submissionData;

										$totalAvailableSlots[$schoolID]['TAS']--;
										$totalAvailableSlots[$schoolID][$race]++;
										$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']][$race]++;
										$totalAvailableSlots[$schoolID]['changed'] = true;


										//Updated the TAS variables with the minus of the Original Offered Choice.
										//Add back an available Slot.
										//Decrease the overall Programs Race.
										$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()]['TAS']++;
										$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()][$race]--;
										$totalAvailableSlots['programs'][$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()]['program']][$race]--;
										$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()]['changed'] = true;

										$gradeCounter = -1; //Awarded submission, reset to negative one (-1) other wise, let the FOR Loop count up.
									}
								}
							}
						}
					}
				} else {
					//Did not awarded, increment gradeCounter.
					$gradeCounter++;
				}
				$this->awardedList = array();
				gc_collect_cycles();
			}
		}
		$this->emLookup->flush();

		foreach( $this->grade_order as $grade ) {

			//Need to grab all active submissions because they were NOT awarded.
			$submissions = $this->emLookup->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->where( 's.openEnrollment = :enrollment' )
				->andWhere( 's.submissionStatus = 1' )//Submission Status of Active
				->andWhere( 's.nextGrade = :grade' )//Next Grade
				->setParameters( array(
					'enrollment' => $openEnrollment ,
					'grade' => $grade
				) )
				->getQuery()
				->getResult();

			//All submissions are required to have a first submission

			$waitList = [];
			if( count( $submissions ) > 0 ) {

				//TODO: Save this list, need to make waitList by Program/MagnetSchool. How do we handle if one Program does not do Waiting Lists?????

				$this->logging( "Added Wait-list or Denied." );

				/** @var Submission $waitingListSubmission */
				foreach( $submissions as $waitingListSubmission ) {
					//Loop over the left-overs and update status to waiting list.

					if( $waitingListSubmission != null && !isset( $localAwardedList[$waitingListSubmission->getId()] ) && !in_array($waitingListSubmission->getId(), $skipped_submissions ) ) {
						$addedToWaitList = false;
						if( $waitingListSubmission->getFirstChoice() != null ) {

							//This Choice exists, and now we need to see if there is a setting defined for it.
							if( isset( $this->magnetSchoolSettings[$waitingListSubmission->getFirstChoice()->getId()] ) ) {
								/** @var MagnetSchoolSetting $magnetSchoolSettings */
								$magnetSchoolSettings = $this->magnetSchoolSettings[$waitingListSubmission->getFirstChoice()->getId()];
							} else {
								$magnetSchoolSettings = null;
							}

							if( $magnetSchoolSettings != null ) {

								$passesCommitteeScoring = true;
								$score = $waitingListSubmission->getCommitteeReviewScoreFirstChoice();

								if( $magnetSchoolSettings->getCommitteeScoreRequired() ) {

									//Confirmed: This is greater than equal too. Need to makes sure this is minimum score
									if( $score >= $magnetSchoolSettings->getMinimumCommitteeScore() ) {
										$passesCommitteeScoring = true;
									} else {
										$passesCommitteeScoring = false;
									}
								}

								//Add a new Waitlist Entry and flip the flag to make sure the status is added to the WaitList.
								if( $passesCommitteeScoring ) {
									$addedToWaitList = true;
									$waitList[ $waitingListSubmission->getId() ] = true;

									$submissionOutcome = new LotteryOutcomeSubmission();
									$submissionOutcome->setType('waitlist');
									$submissionOutcome->setSubmission($waitingListSubmission);
									$submissionOutcome->setMagnetSchool( $waitingListSubmission->getFirstChoice());
									$submissionOutcome->setChoiceNumber( 1 );
									$submissionOutcome->setLotteryNumber($waitingListSubmission->getLotteryNumber() );
									$submissionOutcome->setOpenEnrollment($openEnrollment);
									$submissionOutcome->setPlacement( $placement );
									$this->emLookup->persist($submissionOutcome);

									//Adding into the logging array.
									if( !isset( $this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] ) ) {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] = array(
											'submission' => $waitingListSubmission ,
											'choice' => array( '1' => $waitingListSubmission->getFirstChoice() , '2' => '' , '3' => '' )
										);
									} else {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()]['choice']['1'] = $waitingListSubmission->getFirstChoice();
									}
								}
							}
						}
						if( $waitingListSubmission->getSecondChoice() != null ) {
							//This Choice exists, and now we need to see if there is a setting defined for it.
							if( isset( $this->magnetSchoolSettings[$waitingListSubmission->getSecondChoice()->getId()] ) ) {
								$magnetSchoolSettings = $this->magnetSchoolSettings[$waitingListSubmission->getSecondChoice()->getId()];
							} else {
								$magnetSchoolSettings = null;
							}

							if( $magnetSchoolSettings != null ) {

								$passesCommitteeScoring = true;
								$score = $waitingListSubmission->getCommitteeReviewScoreSecondChoice();

								if( $magnetSchoolSettings->getCommitteeScoreRequired() ) {

									//Confirmed: This is greater than equal too. Need to makes sure this is minimum score
									if( $score >= $magnetSchoolSettings->getMinimumCommitteeScore() ) {
										$passesCommitteeScoring = true;
									} else {
										$passesCommitteeScoring = false;
									}
								}

								//Add a new Waitlist Entry and flip the flag to make sure the status is added to the WaitList.
								if( $passesCommitteeScoring ) {
									$addedToWaitList = true;
									$waitList[ $waitingListSubmission->getId() ] = true;

									$submissionOutcome = new LotteryOutcomeSubmission();
									$submissionOutcome->setType('waitlist');
									$submissionOutcome->setSubmission($waitingListSubmission);
									$submissionOutcome->setMagnetSchool( $waitingListSubmission->getSecondChoice());
									$submissionOutcome->setChoiceNumber( 2 );
									$submissionOutcome->setLotteryNumber( $waitingListSubmission->getLotteryNumber() );
									$submissionOutcome->setOpenEnrollment($openEnrollment);
									$submissionOutcome->setPlacement( $placement );
									$this->emLookup->persist($submissionOutcome);

									//Adding into the logging array.
									if( !isset( $this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] ) ) {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] = array(
											'submission' => $waitingListSubmission ,
											'choice' => array( '1' => '' , '2' => $waitingListSubmission->getSecondChoice() , '3' => '' )
										);
									} else {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()]['choice']['1'] = $waitingListSubmission->getSecondChoice();
									}
								}
							}
						}
						if( $waitingListSubmission->getThirdChoice() != null ) {
							//This Choice exists, and now we need to see if there is a setting defined for it.
							if( isset( $this->magnetSchoolSettings[$waitingListSubmission->getThirdChoice()->getId()] ) ) {
								$magnetSchoolSettings = $this->magnetSchoolSettings[$waitingListSubmission->getThirdChoice()->getId()];
							} else {
								$magnetSchoolSettings = null;
							}

							if( $magnetSchoolSettings != null ) {

								$passesCommitteeScoring = true;
								$score = $waitingListSubmission->getCommitteeReviewScoreThirdChoice();

								if( $magnetSchoolSettings->getCommitteeScoreRequired() ) {

									//Confirmed: This is greater than equal too. Need to makes sure this is minimum score
									if( $score >= $magnetSchoolSettings->getMinimumCommitteeScore() ) {
										$passesCommitteeScoring = true;
									} else {
										$passesCommitteeScoring = false;
									}
								}

								//Add a new Waitlist Entry and flip the flag to make sure the status is added to the WaitList.
								if( $passesCommitteeScoring ) {
									$addedToWaitList = true;
									$waitList[ $waitingListSubmission->getId() ] = true;

									$submissionOutcome = new LotteryOutcomeSubmission();
									$submissionOutcome->setType('waitlist');
									$submissionOutcome->setSubmission($waitingListSubmission);
									$submissionOutcome->setMagnetSchool( $waitingListSubmission->getThirdChoice());
									$submissionOutcome->setChoiceNumber( 3 );
									$submissionOutcome->setLotteryNumber( $waitingListSubmission->getLotteryNumber() );
									$submissionOutcome->setOpenEnrollment($openEnrollment);
									$submissionOutcome->setPlacement( $placement );
									$this->emLookup->persist($submissionOutcome);

									//Adding into the logging array.
									if( !isset( $this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] ) ) {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()] = array(
											'submission' => $waitingListSubmission ,
											'choice' => array( '1' => '' , '2' => '' , '3' => $waitingListSubmission->getThirdChoice() )
										);
									} else {
										$this->loggingOrderingArray['after']["Grade-{$grade}"]['wait-list'][$waitingListSubmission->getId()]['choice']['3'] = $waitingListSubmission->getThirdChoice();
									}
								}
							}
						}

						//Change the status of the submission to the waitLIST IF they were added for a specific CHOICE above.
						if( !$addedToWaitList && !isset( $waitlist[ $waitingListSubmission->getId() ] ) && !isset( $localAwardedList[ $waitingListSubmission->getId() ] ) ) {

							$process_submission = ( !in_array( $waitingListSubmission->getId(), $skipped_submissions ) );

							if( $process_submission ){
								$submissionOutcome = new LotteryOutcomeSubmission();
								$submissionOutcome->setType('denied');
								$submissionOutcome->setSubmission($waitingListSubmission);
								$submissionOutcome->setLotteryNumber( $waitingListSubmission->getLotteryNumber() );
								$submissionOutcome->setOpenEnrollment($openEnrollment);
								$submissionOutcome->setPlacement( $placement );
								$this->emLookup->persist($submissionOutcome);

								//Adding into the logging array.
								$this->loggingOrderingArray['after']["Grade-{$grade}"]['denied'][] = array( 'submission' => $waitingListSubmission , 'choice' => array( '1' => $waitingListSubmission->getFirstChoice() , '2' => $waitingListSubmission->getSecondChoice() , '3' => $waitingListSubmission->getThirdChoice() ) );
							}
						}
						$changedSubmissions++;
					}
				}
			}
		}

		//Write out the logging file.
		$this->writeLoggingFile( $this->loggingOrderingArray , 'before' , 'late-lottery' , 'Late Lottery List');
		$this->writeLoggingFile( $this->loggingOrderingArray , 'after' , 'late-lottery' , 'Late Lottery List');
		$this->loggingOrderingArray = null;
		$this->emLookup->flush();

		/**
		 * Saving the changes into AfterPopulation to ensure we capture any changes.
		 *
		 * @var integer $key
		 * @var array   $availableSlots
		 */
		$this->logging( "Adding After Population Data. If needed." );
		foreach( $totalAvailableSlots as $magnetID => $availableSlots ) {

			$magnetSchool = $this->emLookup->getRepository('IIABMagnetBundle:MagnetSchool')->find($magnetID);
			//Ensures we found a Magnet School
			if ($magnetSchool != null) {

				$populationOutcome = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy([ 'type' => 'after', 'magnetSchool' => $magnetSchool ]);
				$populationChanges = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy([ 'type' => 'changed', 'magnetSchool' => $magnetSchool ]);

				$populationChanges = ( isset( $populationChanges ) ) ? $populationChanges : new LotteryOutcomePopulation();
				$populationChanges->setType('changed');
				$populationChanges->setMagnetSchool($magnetSchool);
				$populationChanges->setOpenEnrollment($openEnrollment);
				$populationChanges->setPlacement($placement);
				$populationChanges->setCPBlack($availableSlots['CPBlack'] - $beforeLotteryAvailableSlots[$magnetID]['CPBlack']);
				$populationChanges->setCPWhite($availableSlots['CPWhite'] - $beforeLotteryAvailableSlots[$magnetID]['CPWhite']);
				$populationChanges->setCPOther($availableSlots['CPOther'] - $beforeLotteryAvailableSlots[$magnetID]['CPOther']);
				$populationChanges->setMaxCapacity($availableSlots['maxCapacity']);
				$this->emLookup->persist($populationChanges);

				$populationOutcome = ( isset( $populationOutcome ) ) ? $populationOutcome :  new LotteryOutcomePopulation();
				$populationOutcome->setType('after');
				$populationOutcome->setMagnetSchool($magnetSchool);
				$populationOutcome->setOpenEnrollment($openEnrollment);
				$populationOutcome->setPlacement($placement);
				$populationOutcome->setCPBlack($availableSlots['CPBlack']);
				$populationOutcome->setCPWhite($availableSlots['CPWhite']);
				$populationOutcome->setCPOther($availableSlots['CPOther']);
				$populationOutcome->setMaxCapacity($availableSlots['maxCapacity']);
				$this->emLookup->persist($populationOutcome);
			}
			$magnetSchool = null;
		}

		$this->emLookup->flush();


		gc_collect_cycles();

		$this->logging( '99: ' . memory_get_usage() , time() );
		$this->logging( 'Lottery Completed' );

		$this->awardedList = null;
		$totalAvailableSlots = null;

		return $changedSubmissions;
	}

	/**
	 * Processes the Wait List to try and award new Slots.
	 *
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return integer
	 */
	public function runWaitList( OpenEnrollment $openEnrollment, $clear_old_outcomes = true ) {

		$this->logging( 'Running Wait List for ' . $openEnrollment );
		$this->logging( '1: ' . memory_get_usage() , time() );

		$changedSubmissions = 0;
		$placement = $this->emLookup->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( [ 'openEnrollment' => $openEnrollment ], [ 'round' => 'DESC' ] );

		$totalAvailableSlots = $this->getTotalAvailableSlots( $openEnrollment );

		//This will look for the latest WaitListProcessing Entities and make the necessary updates/changes.
		$totalAvailableSlots = $this->updatedFromWaitListSlots( $totalAvailableSlots , $openEnrollment );

		if( $clear_old_outcomes ) {
			//clear outcomes
			$clear_outcomes = $this->emLookup->createQuery('DELETE IIABMagnetBundle:LotteryOutcomeSubmission');
			$clear_outcomes->execute();
			$clear_outcomes = $this->emLookup->createQuery("DELETE IIABMagnetBundle:LotteryOutcomePopulation'");
			$clear_outcomes->execute();
			$this->emLookup->flush();

			//set the starting populations in the outcome table
			foreach( $totalAvailableSlots as $magnetID => $availableSlots ){
				$magnetSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $magnetID );
				//Ensures we found a Magnet School
				if( $magnetSchool != null ) {
					$populationOutcome = new LotteryOutcomePopulation();
					$populationOutcome->setMagnetSchool( $magnetSchool );
					$populationOutcome->setOpenEnrollment( $openEnrollment );
					$populationOutcome->setPlacement($placement);
					$populationOutcome->setCPBlack( $availableSlots['CPBlack'] );
					$populationOutcome->setCPWhite( $availableSlots['CPWhite'] );
					$populationOutcome->setCPOther( $availableSlots['CPOther'] );
					$populationOutcome->setMaxCapacity( $availableSlots['maxCapacity'] );
					$populationOutcome->setType( 'before' );

					$this->emLookup->persist( $populationOutcome );
				}
				$this->emLookup->flush();
				$magnetSchool = null;
			}
		} else {
			$population_outcome_changes = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findBy([ 'type' => 'changed' ] );
			foreach( $population_outcome_changes as $changes ){

				$schoolID = $changes->getMagnetSchool()->getId();
				$totalAvailableSlots[$schoolID]['CPWhite'] += $changes->getCPWhite();
				$totalAvailableSlots[$schoolID]['CPBlack'] += $changes->getCPBlack();
				$totalAvailableSlots[$schoolID]['CPOther'] += $changes->getCPOther();
				$totalAvailableSlots[$schoolID]['TAS'] -=  $changes->getCPWhite() + $changes->getCPBlack() + $changes->getCPOther();

				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPWhite'] += $changes->getCPWhite();
				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPBlack'] += $changes->getCPBlack();
				$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPOther'] += $changes->getCPOther();
			}
		}
		$beforeLotteryAvailableSlots = $totalAvailableSlots;

		$this->admData = $this->getADMData( $openEnrollment );
		$this->magnetSchoolSettings = $this->getMagnetSchoolSettings( $openEnrollment );

		gc_enable();
		gc_collect_cycles();

		$localAwardedList = array();

		$waitListStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( array(
			'id' => 9
		) );

		$this->build_lotteryGroupingArray( array( $waitListStatus ) , $openEnrollment);

		$this->writeLoggingFile( $this->loggingOrderingArray , 'before' , 'wait-list' , 'Wait List' );

		for( $gradeCounter = 0; $gradeCounter < 14; ) {

			//Walking the GradesOrder Variable (above)
			foreach( $this->grade_order as $grade ) {

				list( $submissions , $totalAvailableSlots ) = $this->walkList( $this->lotteryGroupingArray[$grade] , $totalAvailableSlots );
				$this->lotteryGroupingArray[$grade] = $submissions;

				if( count( $this->awardedList ) > 0 ) {

					$this->logging( 'Added in Offered Submissions.' );
					/**
					 * Looping over the Awarded Submissions and Awarding them.
					 * @var string     $key
					 * @var array $awardedSubmission
					 */
					foreach( $this->awardedList as $key => $submissionData ) {

						/** @var \IIAB\MagnetBundle\Entity\Submission $awardedSubmission */
						$awardedSubmission = $submissionData['submission'];

						$testRace = strtoupper( $awardedSubmission->getRaceFormatted() );
						switch( $testRace ) {

							case 'WHITE':
								$race = 'CPWhite';
								break;

							case 'BLACK':
								$race = 'CPBlack';
								break;

							default:
								$race = 'CPOther';
						}

						//Do not give them multiple awards
						if( !isset( $localAwardedList[$awardedSubmission->getId()] ) ) {

							$submissionID = $awardedSubmission->getId();
							$schoolID = $submissionData['choice']->getId();

							$awardedSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $schoolID ); //The magnet school that has been awarded.

							//Ensure we have an actual awarded school
							if( $awardedSchool != null ) {

								$this->loggingOrderingArray['after']["Grade-" . $awardedSubmission->getNextGrade()]['awarded'][$awardedSubmission->getId()] = array( 'submission' => $awardedSubmission , 'choice' => array( $awardedSchool ) );

								$submissionOutcome = new LotteryOutcomeSubmission();
								$submissionOutcome->setType('offer');
								$submissionOutcome->setSubmission($awardedSubmission);
								$submissionOutcome->setMagnetSchool($awardedSchool);
								$submissionOutcome->setChoiceNumber($submissionData['choiceNumber']);
								$submissionOutcome->setLotteryNumber($submissionData['lottery']);
								$submissionOutcome->setOpenEnrollment($openEnrollment);
								$submissionOutcome->setPlacement($placement);
								$this->emLookup->persist($submissionOutcome);

								$localOutComeList[ $awardedSubmission->getId() ] = $submissionOutcome;
								$localAwardedList[$awardedSubmission->getId()] = $submissionData;

								$totalAvailableSlots[$schoolID]['TAS']--;
								$totalAvailableSlots[$schoolID][$race]++;
								$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']][$race]++;
								$totalAvailableSlots[$schoolID]['changed'] = true;

								$changedSubmissions++;

								$gradeCounter = -1; //Awarded submission, reset to negative one (-1) other wise, let the FOR Loop count up.
							}
						} else {
							//Already been awarded Once but need to see if this choice is HIGHER

							//Get the hold data to see if choice is of Higher Value.
							$alreadyAwardedOfferData = $localAwardedList[$awardedSubmission->getId()];
							//Submission ID has already been Waiting but it could be a lower Choice. See if
							if( $submissionData['choiceNumber'] < $alreadyAwardedOfferData['choiceNumber'] ) {

								//New Choice is HIGHER so we need to try and see if removal of old will pass.
								if( $this->passesRemovalAwardedRacialComposition( $awardedSubmission , $totalAvailableSlots , $alreadyAwardedOfferData['choice']->getId() , $race ) ) {

									$submissionID = $awardedSubmission->getId();
									$schoolID = $submissionData['choice']->getId();

									$awardedSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $schoolID ); //The magnet school that has been awarded.

									//Ensure we have an actual awarded school
									if( $awardedSchool != null ) {

										$this->loggingOrderingArray['after']["Grade-" . $awardedSubmission->getNextGrade()]['awarded'][$awardedSubmission->getId()] = array( 'submission' => $awardedSubmission , 'choice' => array( $awardedSchool ) );

										$this->emLookup->remove( $localOutComeList[ $submissionID ] );

										$submissionOutcome = new LotteryOutcomeSubmission();
										$submissionOutcome->setType('offer');
										$submissionOutcome->setSubmission($awardedSubmission);
										$submissionOutcome->setMagnetSchool($awardedSchool);
										$submissionOutcome->setChoiceNumber($submissionData['choiceNumber']);
										$submissionOutcome->setLotteryNumber($submissionData['lottery']);
										$submissionOutcome->setOpenEnrollment($openEnrollment);
										$submissionOutcome->setPlacement($placement);
										$this->emLookup->persist($submissionOutcome);

										$localAwardedList[$awardedSubmission->getId()] = $submissionData;

										$totalAvailableSlots[$schoolID]['TAS']--;
										$totalAvailableSlots[$schoolID][$race]++;
										$totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']][$race]++;
										$totalAvailableSlots[$schoolID]['changed'] = true;


										//Updated the TAS variables with the minus of the Original Offered Choice.
										//Add back an available Slot.
										//Decrease the overall Programs Race.
										$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()]['TAS']++;
										$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()][$race]--;
										$totalAvailableSlots['programs'][$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()]['program']][$race]--;
										$totalAvailableSlots[$alreadyAwardedOfferData['choice']->getId()]['changed'] = true;

										$gradeCounter = -1; //Awarded submission, reset to negative one (-1) other wise, let the FOR Loop count up.
									}
								}
							}
						}
					}
				} else {
					//Did not awarded, increment gradeCounter.
					$gradeCounter++;
				}
				$this->awardedList = array();
				gc_collect_cycles();
			}
		}
		$this->emLookup->flush();

		$this->writeLoggingFile( $this->loggingOrderingArray , 'after' , 'wait-list' , 'Wait List' );

		$this->logging( "Adding After Population Data. If needed." );
		//Add in any After Population Numbers that have CHANGED.
		foreach( $totalAvailableSlots as $magnetID => $availableSlots ) {

			$magnetSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $magnetID );
			//Ensures we found a Magnet School
			if( $magnetSchool != null) {

				$populationOutcome = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy([ 'type' => 'after', 'magnetSchool' => $magnetSchool ]);
				$populationChanges = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy([ 'type' => 'changed', 'magnetSchool' => $magnetSchool ]);
                $populationWithdrawal = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy([ 'type' => 'withdrawal', 'magnetSchool' => $magnetSchool ]);

				$populationChanges = ( isset( $populationChanges ) ) ? $populationChanges : new LotteryOutcomePopulation();
				$populationChanges->setType('changed');
				$populationChanges->setMagnetSchool($magnetSchool);
				$populationChanges->setOpenEnrollment($openEnrollment);
				$populationChanges->setPlacement($placement);
				$populationChanges->setCPBlack($availableSlots['CPBlack'] - $beforeLotteryAvailableSlots[$magnetID]['CPBlack']);
				$populationChanges->setCPWhite($availableSlots['CPWhite'] - $beforeLotteryAvailableSlots[$magnetID]['CPWhite']);
				$populationChanges->setCPOther($availableSlots['CPOther'] - $beforeLotteryAvailableSlots[$magnetID]['CPOther']);
				$populationChanges->setMaxCapacity($availableSlots['maxCapacity']);
				$this->emLookup->persist($populationChanges);

				$populationOutcome = ( isset( $populationOutcome ) ) ? $populationOutcome :  new LotteryOutcomePopulation();
				$populationOutcome->setType('after');
				$populationOutcome->setMagnetSchool($magnetSchool);
				$populationOutcome->setOpenEnrollment($openEnrollment);
				$populationOutcome->setPlacement($placement);
				$populationOutcome->setCPBlack($availableSlots['CPBlack']);
				$populationOutcome->setCPWhite($availableSlots['CPWhite']);
				$populationOutcome->setCPOther($availableSlots['CPOther']);
				$populationOutcome->setMaxCapacity($availableSlots['maxCapacity']);
				$this->emLookup->persist($populationOutcome);

                $populationWithdrawal = ( isset( $populationWithdrawal ) ) ? $populationWithdrawal :  new LotteryOutcomePopulation();
                $populationWithdrawal->setType('withdrawal');
                $populationWithdrawal->setMagnetSchool($magnetSchool);
                $populationWithdrawal->setOpenEnrollment($openEnrollment);
                $populationWithdrawal->setPlacement($placement);
                $populationWithdrawal->setMaxCapacity($availableSlots['maxCapacity']);
                $populationWithdrawal->setCPBlack( isset( $availableSlots['withdrawBlack'] ) ? $availableSlots['withdrawBlack'] : 0);
                $populationWithdrawal->setCPWhite( isset( $availableSlots['withdrawWhite'] ) ? $availableSlots['withdrawWhite'] : 0);
                $populationWithdrawal->setCPOther( isset( $availableSlots['withdrawOther'] ) ? $availableSlots['withdrawOther'] : 0);
                $this->emLookup->persist($populationWithdrawal);

			}
			$magnetSchool = null;
		}


		$this->emLookup->flush();

		return $changedSubmissions;
	}


    /**
     * Processes the Wait List to try and award new Slots.
     *
     * @param OpenEnrollment $openEnrollment
     *
     * @return integer
     */
    public function runSimpleWaitList( OpenEnrollment $openEnrollment, $clear_old_outcomes = true ) {

        $this->logging( 'Running Wait List for ' . $openEnrollment );
        $this->logging( '1: ' . memory_get_usage() , time() );

        $changedSubmissions = 0;
        $placement = $this->emLookup->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( [ 'openEnrollment' => $openEnrollment ], [ 'round' => 'DESC' ] );

        $totalAvailableSlots = $this->getTotalAvailableSlotsWithFocusAreas( $openEnrollment );

        //This will look for the latest WaitListProcessing Entities and make the necessary updates/changes.
        $totalAvailableSlots = $this->updatedFromWaitListSlotsWithFocus( $totalAvailableSlots , $openEnrollment );

        if( $clear_old_outcomes ) {

            //clear outcomes
            $clear_outcomes = $this->emLookup->createQuery('DELETE IIABMagnetBundle:LotteryOutcomeSubmission');
            $clear_outcomes->execute();
            $clear_outcomes = $this->emLookup->createQuery("DELETE IIABMagnetBundle:LotteryOutcomePopulation");
            $clear_outcomes->execute();
            $this->emLookup->flush();

            //set the starting populations in the outcome table
            foreach( $totalAvailableSlots as $magnetID => $availableSlots ){
                $magnetSchool = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $magnetID );
                //Ensures we found a Magnet School
                if( $magnetSchool != null ) {

                    foreach ($availableSlots as $focus => $slot) {

                        $focus_value = ( $focus ) ? $focus : null;

                        $populationOutcome = new LotteryOutcomePopulation();
                        $populationOutcome->setMagnetSchool($magnetSchool);
                        $populationOutcome->setOpenEnrollment($openEnrollment);
                        $populationOutcome->setPlacement($placement);
                        $populationOutcome->setFocusArea( $focus_value );
                        $populationOutcome->setCPBlack($availableSlots[$focus]['CPBlack']);
                        $populationOutcome->setCPWhite($availableSlots[$focus]['CPWhite']);
                        $populationOutcome->setCPOther($availableSlots[$focus]['CPOther']);
                        $populationOutcome->setMaxCapacity($availableSlots[$focus]['maxCapacity']);
                        $populationOutcome->setType('before');

                        $this->emLookup->persist($populationOutcome);
                    }
                }
                $this->emLookup->flush();
                $magnetSchool = null;
            }
        } else {
            $population_outcome_changes = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findBy([ 'type' => 'changed' ] );
            foreach( $population_outcome_changes as $changes ){

                $schoolID = $changes->getMagnetSchool()->getId();
                $focus = ( !empty( $changes->getFocusArea() ) ) ? $changes->getFocusArea() : 0;

                $totalAvailableSlots[$schoolID][$focus]['CPWhite'] += $changes->getCPWhite();
                $totalAvailableSlots[$schoolID][$focus]['CPBlack'] += $changes->getCPBlack();
                $totalAvailableSlots[$schoolID][$focus]['CPOther'] += $changes->getCPOther();
                $totalAvailableSlots[$schoolID][$focus]['TAS'] -=  $changes->getCPWhite() + $changes->getCPBlack() + $changes->getCPOther();

                $totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPWhite'] += $changes->getCPWhite();
                $totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPBlack'] += $changes->getCPBlack();
                $totalAvailableSlots['programs'][$totalAvailableSlots[$schoolID]['program']]['CPOther'] += $changes->getCPOther();

            }
        }
        $beforeLotteryAvailableSlots = $totalAvailableSlots;

        $this->admData = $this->getADMData( $openEnrollment );
        $this->magnetSchoolSettings = $this->getMagnetSchoolSettings( $openEnrollment );

        gc_enable();
        gc_collect_cycles();

        $localAwardedList = array();

        $waitListStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( array(
            'id' => 9
        ) );

        $this->build_simpleLotteryGroupingArray( [$waitListStatus], $openEnrollment );
        $eligibility_requirements_service = new EligibilityRequirementsService( $this->emLookup );

        $awarded_submissions = [];
        $ineligible_submissions = [];
        $waitlist_submissions = [];

        $id = 0;

        $choices = [
            1 => 'first',
            2 => 'second',
            3 => 'third'
        ];

        foreach( $this->lotteryGroupingArray as $choice => $submissions ){

            foreach( $submissions as $submission ){

                if( empty( $awarded_submissions[ $submission->getId() ] ) ) {

                    $waiting_schools = $submission->getWaitList();

                    foreach( $waiting_schools as $waiting_school ) {

                        $chosen_school = $waiting_school->getChoiceSchool();
                        if( $chosen_school->getId() == $submission->{'get'.ucfirst( $choice )  .'Choice'}()->getId() ) {

                            $focus = $waiting_school->getChoiceFocusArea();

                            $focus_index = (!empty($focus)) ? $focus : 0;
                            if ($focus_index && !isset($totalAvailableSlots[$chosen_school->getId()][$focus_index])) {
                                continue;
                            }

                            if ($eligibility_requirements_service->doesSubmissionHaveAllEligibility($submission, $chosen_school, $focus)) {

                                if ($totalAvailableSlots[$chosen_school->getId()][$focus_index]['TAS'] >= 1) {

                                    $awarded_submissions[$submission->getId()] = [
                                        'submission' => $submission,
                                        'choice' => $choice,
                                        'school' => $chosen_school
                                    ];

                                    $submission->removeWaitList($waiting_school);

                                    if ($totalAvailableSlots['programs'][$chosen_school->getProgram()->getId()]['capacity_by'] == 'focus') {

                                        $awarded_submissions[$submission->getId()]['focus_area'] = $focus;
                                    }
                                    $totalAvailableSlots[$chosen_school->getId()][$focus_index]['TAS']--;
                                    $totalAvailableSlots[$chosen_school->getId()][$focus_index]['changed'] = true;
                                    $totalAvailableSlots[$chosen_school->getId()][$focus_index]['CP' . $submission->getRaceFormatted()]++;
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach( $awarded_submissions as $awarded ){
            $submissionOutcome = new LotteryOutcomeSubmission();
            $submissionOutcome->setType('offer');
            $submissionOutcome->setSubmission($awarded['submission']);
            $submissionOutcome->setMagnetSchool($awarded['school']);
            $submissionOutcome->setChoiceNumber(array_search( $awarded['choice'], $choices ) );
            $submissionOutcome->setFocusArea( ( isset( $awarded[ 'focus_area' ] ) ) ? $awarded[ 'focus_area' ] : '' );
            $submissionOutcome->setLotteryNumber($awarded['submission']->getLotteryNumber() );
            $submissionOutcome->setOpenEnrollment($openEnrollment);
            $submissionOutcome->setPlacement( $placement );
            $this->emLookup->persist($submissionOutcome);
        }

        $this->emLookup->flush();

        $this->logging( "Adding After Population Data. If needed." );
        foreach( $totalAvailableSlots as $magnetID => $availableSlotsForSchool ) {

            foreach( $availableSlotsForSchool as $focus_area => $availableSlots ) {

                $focus_value = ($focus_area) ? $focus_area : null;

                $magnetSchool = $this->emLookup->getRepository('IIABMagnetBundle:MagnetSchool')->find($magnetID);
                //Ensures we found a Magnet School
                if ($magnetSchool != null) {

                    $black_changes = $availableSlots['CPBlack'] - $beforeLotteryAvailableSlots[$magnetID][$focus_area]['CPBlack'];
                    $white_changes = $availableSlots['CPWhite'] - $beforeLotteryAvailableSlots[$magnetID][$focus_area]['CPWhite'];
                    $other_changes = $availableSlots['CPOther'] - $beforeLotteryAvailableSlots[$magnetID][$focus_area]['CPOther'];

                    $populationOutcome = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy(['type' => 'after', 'magnetSchool' => $magnetSchool, 'focusArea' => $focus_value ] );
                    $populationChanges = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy(['type' => 'changed', 'magnetSchool' => $magnetSchool, 'focusArea' => $focus_value ] );
                    $populationWithdrawal = $this->emLookup->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findOneBy(['type' => 'withdrawal', 'magnetSchool' => $magnetSchool, 'focusArea' => $focus_value ] );

                    $populationChanges = (isset($populationChanges)) ? $populationChanges : new LotteryOutcomePopulation();
                    $populationChanges->setType('changed');
                    $populationChanges->setMagnetSchool($magnetSchool);
                    $populationChanges->setOpenEnrollment($openEnrollment);
                    $populationChanges->setPlacement($placement);
                    $populationChanges->setCPBlack( $black_changes );
                    $populationChanges->setCPWhite( $white_changes );
                    $populationChanges->setCPOther( $other_changes );
                    $populationChanges->setFocusArea( $focus_value );
                    $populationChanges->setMaxCapacity($availableSlots['maxCapacity']);
                    $this->emLookup->persist($populationChanges);

                    $populationOutcome = (isset($populationOutcome)) ? $populationOutcome : new LotteryOutcomePopulation();
                    $populationOutcome->setType('after');
                    $populationOutcome->setMagnetSchool($magnetSchool);
                    $populationOutcome->setOpenEnrollment($openEnrollment);
                    $populationOutcome->setPlacement($placement);
                    $populationOutcome->setCPBlack($availableSlots['CPBlack']);
                    $populationOutcome->setCPWhite($availableSlots['CPWhite']);
                    $populationOutcome->setCPOther($availableSlots['CPOther']);
                    $populationOutcome->setFocusArea( $focus_value );
                    $populationOutcome->setMaxCapacity($availableSlots['maxCapacity']);
                    $this->emLookup->persist($populationOutcome);

                    $populationWithdrawal = (isset($populationWithdrawal)) ? $populationWithdrawal : new LotteryOutcomePopulation();
                    $populationWithdrawal->setType('withdrawal');
                    $populationWithdrawal->setMagnetSchool($magnetSchool);
                    $populationWithdrawal->setOpenEnrollment($openEnrollment);
                    $populationWithdrawal->setPlacement($placement);
                    $populationWithdrawal->setMaxCapacity($availableSlots['maxCapacity']);
                    $populationWithdrawal->setCPBlack( isset( $availableSlots['withdrawBlack'] ) ? $availableSlots['withdrawBlack'] : 0);
                    $populationWithdrawal->setCPWhite( isset( $availableSlots['withdrawWhite'] ) ? $availableSlots['withdrawWhite'] : 0);
                    $populationWithdrawal->setCPOther( isset( $availableSlots['withdrawOther'] ) ? $availableSlots['withdrawOther'] : 0);
                    $populationWithdrawal->setFocusArea( $focus_value );
                    $this->emLookup->persist($populationWithdrawal);
                }
                $magnetSchool = null;
            }
        }

        $this->emLookup->flush();

        return count( $awarded_submissions );
    }

	/**
	 * Walk the list and try and awarding placements
	 *
	 * @param array $submissions List of Submissions
	 * @param array $totalAvailableSlots
	 *
	 * @return array
	 */
	private function walkList( $submissions = array() , $totalAvailableSlots = array() ) {

		$removeKey = -1;
		$this->logging( 'Total Submissions to Walk: ' . count( $submissions ) );

		/**
		 * @var integer    $key
		 * @var Submission $submission
		 */
		foreach( $submissions as $key => $submissionData ) {

			$magnetSchoolID = '';
			$score = 0;

			/** @var Submission $submission */
			$submission = $submissionData['submission'];

			switch( $submissionData['choiceNumber'] ) {

				case 1:
					if( $submission->getFirstChoice() != null ) {
						$magnetSchoolID = $submission->getFirstChoice()->getId();
						$score = $submission->getCommitteeReviewScoreFirstChoice();
					}
					break;

				case 2:
					if( $submission->getSecondChoice() != null ) {
						$magnetSchoolID = $submission->getSecondChoice()->getId();
						$score = $submission->getCommitteeReviewScoreSecondChoice();
					}
					break;

				case 3:
					if( $submission->getThirdChoice() != null ) {
						$magnetSchoolID = $submission->getThirdChoice()->getId();
						$score = $submission->getCommitteeReviewScoreThirdChoice();
					}
					break;
			}

			//Make sure there is a totalAvailableSlots index this for school.
			//Example the school might not have the data for the system to run, so it would be null.
			//So this check ensures the magnetSchoolID is in the array.
			//TODO we probably need to put a prompt into place to notify the backend user that this is the case before running


			if( isset( $totalAvailableSlots[$magnetSchoolID] ) && $magnetSchoolID != '' ) {
				$this->logging( 'School ID: ' . $magnetSchoolID );
				if( $totalAvailableSlots[$magnetSchoolID]['TAS'] > 0 ) {
					//There is an available Slot.

					//Before checking RacialComposition. Check to see if current choice has committeeScoring enabled.
					//and if so, the submission passes the minimum requirement.
					//First lets get the magnetSchoolSetting...If it is set first.
					if( isset( $this->magnetSchoolSettings[$magnetSchoolID] ) ) {
						/** @var MagnetSchoolSetting $magnetSchoolSettings */
						$magnetSchoolSettings = $this->magnetSchoolSettings[$magnetSchoolID];
					} else {
						$magnetSchoolSettings = null;
					}

					$passesCommitteeScoring = true;

					if( $magnetSchoolSettings != null ) {

						if( $magnetSchoolSettings->getCommitteeScoreRequired() ) {

							//Confirmed: This is greater than equal too. Need to makes sure this is minimum score
							if( $score >= $magnetSchoolSettings->getMinimumCommitteeScore() ) {
								$passesCommitteeScoring = true;
							} else {
								$this->logging( 'Does not pass committee score: ' . $submission->getId() . '. Score Required: ' . $magnetSchoolSettings->getMinimumCommitteeScore() . ' -- Submission Score: ' . $score . '.' );
								$passesCommitteeScoring = false;
							}
						}
					}

					$testRace = strtoupper( $submission->getRaceFormatted() );
					switch( $testRace ) {

						case 'WHITE':
							$race = 'CPWhite';
							break;

						case 'BLACK':
							$race = 'CPBlack';
							break;

						default:
							$race = 'CPOther';
					}
					if( $passesCommitteeScoring && $this->passesRacialComposition( $submission , $totalAvailableSlots , $magnetSchoolID , $race ) ) {
						//Do not allow a submission to be awarded twice.
						if( !isset( $this->awardedList[$submission->getId()] ) ) {

							//Need to see if they have already been Awarded Before.
							$alreadyOffered = $this->emLookup->getRepository( 'IIABMagnetBundle:Offered' )->findOneBy( array(
								'submission' => $submission
							) );

							if( $alreadyOffered == null ) {
								$this->logging( 'Awarded Submission ID: ' . $submission->getId() . ' - Awarded Race: ' . $submission->getRaceFormatted() . ' -- ' . $submissionData['choiceNumber'] );
								$this->awardedList[$submission->getId()] = $submissionData;

								//Need to remove lower choices because an higher choice has been awarded.
								switch( $submissionData['choiceNumber'] ) {
									case 1:
										if( isset( $submissions[$submission->getLotteryNumber() . '.2'] ) ) {
											unset( $submissions[$submission->getLotteryNumber() . '.2'] );
										}
										if( isset( $submissions[$submission->getLotteryNumber() . '.3'] ) ) {
											unset( $submissions[$submission->getLotteryNumber() . '.3'] );
										}
										break;

									case 2:
										if( isset( $submissions[$submission->getLotteryNumber() . '.3'] ) ) {
											unset( $submissions[$submission->getLotteryNumber() . '.3'] );
										}
										break;

									case 3:
										//Do nothing because choice 2 or 1 could be still be awarded at the before ending.
										break;

								}

								$removeKey = $key;
								break;
							}
						}
					}
					if( $totalAvailableSlots[$magnetSchoolID]['lastTAS'] == $totalAvailableSlots[$magnetSchoolID]['TAS'] ) {
						$this->logging( 'same lastTAS and TAS' );
					}
				}
				$totalAvailableSlots[$magnetSchoolID]['lastTAS'] = $totalAvailableSlots[$magnetSchoolID]['TAS'];
			}
		}
		if( $removeKey != -1 ) {
			$this->logging( 'Remove KEY: ' . $removeKey . ' - walking list again' );
			unset( $submissions[$removeKey] );

			//Now recall WalkList.
			//list( $submissions , $totalAvailableSlots ) = $this->walkList( $choiceNumber , $submissions , $totalAvailableSlots );
			return array( $submissions , $totalAvailableSlots );
		}
		return array( $submissions , $totalAvailableSlots );
	}

	/**
	 * Gets the Total Available Slots Array and Programs Racial Composition.
	 *
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return array
	 */
	private function getTotalAvailableSlots( OpenEnrollment $openEnrollment ) {

		//Return Array for all the settings needed.
		$totalAvailableSlots = array();

		$programs = $this->emLookup->getRepository( 'IIABMagnetBundle:Program' )->findBy( array( 'openEnrollment' => $openEnrollment ) , array( 'name' => 'ASC' ) );

		foreach( $programs as $program ) {
			$magnetSchools = $program->getMagnetSchools();

			//TODO: Added in Feeder Pattern Check.

			$programCPWhite = 0;
			$programCPBlack = 0;
			$programCPOther = 0;

			foreach( $magnetSchools as $magnetSchool ) {

				$currentPopulation = $this->emLookup->getRepository( 'IIABMagnetBundle:CurrentPopulation' )->findOneBy( array(
					'openEnrollment' => $openEnrollment ,
					'magnetSchool' => $magnetSchool
				) );

				$afterPopulation = $this->emLookup->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
					'magnetSchool' => $magnetSchool ,
					'openEnrollment' => $openEnrollment ,
				) , array( 'lastUpdatedDateTime' => 'DESC' ) );

				//Found an AfterPopulation. This means the lottery ran already on this MagnetSchool.
				//Use the Updated Counts.
				if( $afterPopulation != null ) {
					$currentPopulation = $afterPopulation;
				}

				if( $currentPopulation == null ) {
					//Default CurrentPopulation to Zero if it doesn't exist.
					$currentPopulation = new CurrentPopulation();
					$currentPopulation->setOpenEnrollment( $openEnrollment );
					$currentPopulation->setMagnetSchool( $magnetSchool );
					$this->emLookup->persist( $currentPopulation );
					$this->emLookup->flush();
				}
				$currentPopulationSum = $currentPopulation->getCPSum();

				$maxCapacity = $currentPopulation->getMaxCapacity();

				//Get the total Number of slots available.
				$totalSlots = $maxCapacity - $currentPopulationSum;

				//Ensure we have a zero or positive number.
				if( $totalSlots < 0 ) {
					$totalSlots = 0;
				}

				$totalAvailableSlots[$currentPopulation->getMagnetSchool()->getId()] = array(
					'TAS' => $totalSlots ,
					'originalTAS' => $totalSlots ,
					'lastTAS' => $totalSlots ,
					'maxCapacity' => $maxCapacity ,
					'CPWhite' => $currentPopulation->getCPWhite() ,
					'CPBlack' => $currentPopulation->getCPBlack() ,
					'CPOther' => $currentPopulation->getCPSumOther() ,
					'changed' => false ,
					'program' => $program->getId()
				);

				$programCPWhite += $currentPopulation->getCPWhite();
				$programCPBlack += $currentPopulation->getCPBlack();
				$programCPOther += $currentPopulation->getCPSumOther();

			}

			$totalAvailableSlots['programs'][$program->getId()] = array(
				'CPWhite' => $programCPWhite ,
				'CPBlack' => $programCPBlack ,
				'CPOther' => $programCPOther
			);
		}

		return $totalAvailableSlots;
	}

    /**
     * Gets the Total Available Slots Array and Programs Racial Composition.
     *
     * @param OpenEnrollment $openEnrollment
     *
     * @return array
     */
    private function getTotalAvailableSlotsWithFocusAreas( OpenEnrollment $openEnrollment ) {

        //Return Array for all the settings needed.
        $totalAvailableSlots = array();

        $programs = $this->emLookup->getRepository( 'IIABMagnetBundle:Program' )->findBy( array( 'openEnrollment' => $openEnrollment ) , array( 'name' => 'ASC' ) );

        foreach( $programs as $program ) {

            $focus_areas = $program->getAdditionalData( 'focus' );

            $capacity_by_focus = $program->getAdditionalData('capacity_by');
            $capacity_by_focus = ( isset( $capacity_by_focus[0] ) && $capacity_by_focus[0]->getMetaValue() == 'focus');

            $magnetSchools = $program->getMagnetSchools();

            //TODO: Added in Feeder Pattern Check.

            $programCPWhite = 0;
            $programCPBlack = 0;
            $programCPOther = 0;

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

                    $currentPopulation = $this->emLookup->getRepository('IIABMagnetBundle:CurrentPopulation')->findOneBy(array(
                        'openEnrollment' => $openEnrollment,
                        'magnetSchool' => $magnetSchool,
                        'focusArea' => $focus_area
                    ));

                    $afterPopulation = $this->emLookup->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
                        'magnetSchool' => $magnetSchool ,
                        'openEnrollment' => $openEnrollment ,
                        'focusArea' => $focus_area
                    ) , array( 'lastUpdatedDateTime' => 'DESC' ) );

                    //Found an AfterPopulation. This means the lottery ran already on this MagnetSchool.
                    //Use the Updated Counts.
                    if( $afterPopulation != null ) {
                        $currentPopulation = $afterPopulation;
                    }

                    if( $currentPopulation == null ) {
                        //Default CurrentPopulation to Zero if it doesn't exist.
                        $currentPopulation = new CurrentPopulation();
                        $currentPopulation->setOpenEnrollment( $openEnrollment );
                        $currentPopulation->setMagnetSchool( $magnetSchool );
                        $currentPopulation->setFocusArea( $focus_area );
                        $this->emLookup->persist( $currentPopulation );
                        $this->emLookup->flush();
                    }

                    $currentPopulationSum = $currentPopulation->getCPSum();

                    $maxCapacity = $currentPopulation->getMaxCapacity();

                    //Get the total Number of slots available.
                    $totalSlots = $maxCapacity - $currentPopulationSum;

                    //Ensure we have a zero or positive number.
                    if( $totalSlots < 0 ) {
                        $totalSlots = 0;
                    }

                    $slotting_data = [
                        'TAS' => $totalSlots ,
                        'originalTAS' => $totalSlots ,
                        'lastTAS' => $totalSlots ,
                        'maxCapacity' => $maxCapacity ,
                        'CPWhite' => $currentPopulation->getCPWhite() ,
                        'CPBlack' => $currentPopulation->getCPBlack() ,
                        'CPOther' => $currentPopulation->getCPSumOther() ,
                        'changed' => false ,
                        'program' => $program->getId()
                    ];

                    if( !empty( $focus_area ) ){
                        $totalAvailableSlots[$currentPopulation->getMagnetSchool()->getId()] [$focus_area] = $slotting_data;
                    } else {
                        $totalAvailableSlots[$currentPopulation->getMagnetSchool()->getId()] [0] = $slotting_data;
                    }

                    $programCPWhite += $currentPopulation->getCPWhite();
                    $programCPBlack += $currentPopulation->getCPBlack();
                    $programCPOther += $currentPopulation->getCPSumOther();
                }
            }

            $totalAvailableSlots['programs'][$program->getId()] = array(
                'CPWhite' => $programCPWhite ,
                'CPBlack' => $programCPBlack ,
                'CPOther' => $programCPOther ,
                'capacity_by' => ( $capacity_by_focus && count( $focus_areas ) > 0 ) ? 'focus' : 'school',
            );
        }

        return $totalAvailableSlots;
    }


	/**
	 * Grab the latest WaitListProcess items and update the Total Available Slots to works on these numbers instead.
	 *
	 * @param array $totalAvailableSlots
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return mixed
	 */
	private function updatedFromWaitListSlots( $totalAvailableSlots , $openEnrollment ) {

		$latestGroupDateTime = $this->getLatestWaitListProcessingDate( $openEnrollment );

		foreach( $totalAvailableSlots as $magnetID => $settings ) {
			//Skip over the Programs Index.
			if( $magnetID == 'programs' ) {
				continue;
			}

			$waitListProcessingItem = $this->emLookup->getRepository( 'IIABMagnetBundle:WaitListProcessing' )->findOneBy( array( 'openEnrollment' => $openEnrollment , 'addedDateTimeGroup' => $latestGroupDateTime , 'magnetSchool' => $magnetID ) );

			if( $waitListProcessingItem == null ) {
				//Meaning there was no WaitList Processing for this MagnetSchool, so we need to set this Available Slots to ZEro.
				//This would happen when individual Processing was used.
				$settings['TAS'] = 0;
				$settings['originalTAS'] = 0;
				$settings['lastTAS'] = 0;
			} else {
				//WaitListProcessing was found, adjust the numbers as needed.
				$settings['TAS'] = $waitListProcessingItem->getAvailableSlots();
				$settings['originalTAS'] = $waitListProcessingItem->getAvailableSlots();
				$settings['lastTAS'] = $waitListProcessingItem->getAvailableSlots();

				//Adjust the Racial Composition as needed
				$settings['CPBlack'] -= $waitListProcessingItem->getBlack();
				$settings['CPWhite'] -= $waitListProcessingItem->getWhite();
				$settings['CPOther'] -= $waitListProcessingItem->getOther();

                $settings['withdrawBlack'] = $waitListProcessingItem->getBlack();
                $settings['withdrawWhite'] = $waitListProcessingItem->getWhite();
                $settings['withdrawOther'] = $waitListProcessingItem->getOther();

				//Adjust the Programs Racial Composition as needed
				$totalAvailableSlots['programs'][$settings['program']]['CPBlack'] -= $waitListProcessingItem->getBlack();
				$totalAvailableSlots['programs'][$settings['program']]['CPWhite'] -= $waitListProcessingItem->getWhite();
				$totalAvailableSlots['programs'][$settings['program']]['CPOther'] -= $waitListProcessingItem->getOther();

			}

			$totalAvailableSlots[$magnetID] = $settings;
		}

		return $totalAvailableSlots;
	}

    /**
     * Grab the latest WaitListProcess items and update the Total Available Slots to works on these numbers instead.
     *
     * @param array $totalAvailableSlots
     * @param OpenEnrollment $openEnrollment
     *
     * @return mixed
     */
    private function updatedFromWaitListSlotsWithFocus( $totalAvailableSlots , $openEnrollment ) {

        $latestGroupDateTime = $this->getLatestWaitListProcessingDate( $openEnrollment );

        foreach( $totalAvailableSlots as $magnetID => $settingsSchool ) {
            //Skip over the Programs Index.
            if( $magnetID == 'programs' ) {
                continue;
            }

            foreach( $settingsSchool as $focus_area => $settings ) {

                $waitListProcessingItem = $this->emLookup->getRepository('IIABMagnetBundle:WaitListProcessing')->findOneBy([
                    'openEnrollment' => $openEnrollment,
                    'addedDateTimeGroup' => $latestGroupDateTime,
                    'magnetSchool' => $magnetID,
                    'focusArea' => ( !empty($focus_area) ) ? $focus_area : null,
                ]);

                if ($waitListProcessingItem == null) {
                    //Meaning there was no WaitList Processing for this MagnetSchool, so we need to set this Available Slots to ZEro.
                    //This would happen when individual Processing was used.
                    $settings['TAS'] = 0;
                    $settings['originalTAS'] = 0;
                    $settings['lastTAS'] = 0;
                } else {

                    //WaitListProcessing was found, adjust the numbers as needed.
                    $settings['TAS'] = $waitListProcessingItem->getAvailableSlots();
                    $settings['originalTAS'] = $waitListProcessingItem->getAvailableSlots();
                    $settings['lastTAS'] = $waitListProcessingItem->getAvailableSlots();

                    //Adjust the Racial Composition as needed
                    $settings['CPBlack'] -= $waitListProcessingItem->getBlack();
                    $settings['CPWhite'] -= $waitListProcessingItem->getWhite();
                    $settings['CPOther'] -= $waitListProcessingItem->getOther();

                    $settings['withdrawBlack'] = $waitListProcessingItem->getBlack();
                    $settings['withdrawWhite'] = $waitListProcessingItem->getWhite();
                    $settings['withdrawOther'] = $waitListProcessingItem->getOther();

                    //Adjust the Programs Racial Composition as needed
                    $totalAvailableSlots['programs'][$settings['program']]['CPBlack'] -= $waitListProcessingItem->getBlack();
                    $totalAvailableSlots['programs'][$settings['program']]['CPWhite'] -= $waitListProcessingItem->getWhite();
                    $totalAvailableSlots['programs'][$settings['program']]['CPOther'] -= $waitListProcessingItem->getOther();

                }

                $totalAvailableSlots[$magnetID][$focus_area] = $settings;
            }
        }

        return $totalAvailableSlots;
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

		$waitListItem = $this->emLookup->getRepository('IIABMagnetBundle:WaitListProcessing')->findOneBy( array( 'openEnrollment' => $openEnrollment ) , array( 'addedDateTimeGroup' => 'DESC' ) );

		if( $waitListItem == null ) {
			throw new \Exception( 'Trying to process the WaitList without an WaitListProcessing Entity Found' , '1000' );
		}

		return $waitListItem->getAddedDateTimeGroup();
	}

	/**
	 * Gets all the ADM data need for the lottery.
	 *
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return array
	 */
	private function getADMData( OpenEnrollment $openEnrollment ) {

		$admData = $this->emLookup->getRepository( 'IIABMagnetBundle:ADMData' )->findBy( array(
			'openEnrollment' => $openEnrollment ,
		) , array( 'school' => 'ASC' ) );

		$formatedData = array();

		foreach( $admData as $adm ) {
			$formatedData[$adm->getSchool()] = $adm;
		}

		//$this->logging( $formatedData );

		return $formatedData;
	}

	/**
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return array
	 */
	private function getMagnetSchoolSettings( OpenEnrollment $openEnrollment ) {

		$magnetSchoolSettings = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchoolSetting' )->findBy( array(
			'openEnrollment' => $openEnrollment ,
		) );

		$formatedData = array();
		foreach( $magnetSchoolSettings as $setting ) {
			$formatedData[$setting->getMagnetSchool()->getId()] = $setting;
		}
		$this->logging( $formatedData );

		return $formatedData;
	}

	/**
	 * Checks to see if the new possible awarded will be within the racial composition.
	 *
	 * @param Submission $submission
	 * @param array      $totalAvailableSlots
	 * @param int        $magnetSchoolID
	 * @param string     $race
	 *
	 * @return bool
	 */
	private function passesRacialComposition( Submission $submission , $totalAvailableSlots = array() , $magnetSchoolID = 0 , $race ) {

		$magnetSchoolAvailableArray = $totalAvailableSlots[$magnetSchoolID];
		$programRacialCompositionArray = $totalAvailableSlots['programs'][$magnetSchoolAvailableArray['program']];

		$openEnrollment = $submission->getOpenEnrollment();

		$maxThresholdWhite = $openEnrollment->getMaxHRCWhite();
		$minThresholdWhite = $openEnrollment->getMinHRCWhite();
		$maxThresholdBlack = $openEnrollment->getMaxHRCBlack();
		$minThresholdBlack = $openEnrollment->getMinHRCBlack();
		$maxThresholdOther = $openEnrollment->getMaxHRCOther();
		$minThresholdOther = $openEnrollment->getMinHRCOther();

		$CPTotal = $programRacialCompositionArray['CPWhite'] + $programRacialCompositionArray['CPBlack'] + $programRacialCompositionArray['CPOther'];

		//$maxThreshold = '';
		$minThreshold = '';
		$tempCPWhite = '';
		$tempCPBlack = '';
		$tempCPOther = '';
		$currSubmissionUnderMinThreshold = false;
		$currSubmissionAndOneRaceInBoundsOneRacePastMax1 = false;
		$currSubmissionAndOneRaceInBoundsOneRacePastMax2 = false;
		$submissionRC4 = false;
		$currSubmissionRCInBounds = false;
		$currentEnrollmentZeroAndFirstAward = false;

		//$beforeRCWhite = $programRacialCompositionArray['CPWhite']/$CPTotal;
		//$beforeRCBlack = $programRacialCompositionArray['CPBlack']/$CPTotal;
		//$beforeRCOther = $programRacialCompositionArray['CPOther']/$CPTotal;

		switch( $race ) {

			case 'CPWhite':
				$tempCPWhite = $programRacialCompositionArray['CPWhite'] + 1;
				$tempCPBlack = $programRacialCompositionArray['CPBlack'];
				$tempCPOther = $programRacialCompositionArray['CPOther'];
				$currSubmissionUnderMinThreshold = ( ( ( $tempCPWhite / ( $CPTotal + 1 ) ) * 100 ) <= $minThresholdWhite );

				$RCWhite = ( $tempCPWhite / ( $CPTotal + 1 ) ) * 100;
				$RCBlack = ( $tempCPBlack / ( $CPTotal + 1 ) ) * 100;
				$RCOther = ( $tempCPOther / ( $CPTotal + 1 ) ) * 100;

				$currSubmissionRCInBounds = ( $RCWhite <= $maxThresholdWhite && $RCWhite > $minThresholdWhite );

				if( $CPTotal < 10 ) {
					//allow some awards to happen to get us out of the steep swing because our current population is so low
					//add +/-10% to each RC bounds to handle steep swing
					$currSubmissionRCInBounds = $RCWhite < ( $maxThresholdWhite + 10 ) && $RCWhite > ( $minThresholdWhite - 10 );
				}

				$currSubmissionAndOneRaceInBoundsOneRacePastMax1 = ( $RCBlack > $maxThresholdBlack && ( $RCOther > $minThresholdOther && $RCOther < $maxThresholdOther ) && $currSubmissionRCInBounds );
				$currSubmissionAndOneRaceInBoundsOneRacePastMax2 = ( $RCOther > $maxThresholdOther && ( $RCBlack > $minThresholdBlack && $RCBlack < $maxThresholdBlack ) && $currSubmissionRCInBounds );

				break;

			case 'CPBlack':
				$tempCPWhite = $programRacialCompositionArray['CPWhite'];
				$tempCPBlack = $programRacialCompositionArray['CPBlack'] + 1;
				$tempCPOther = $programRacialCompositionArray['CPOther'];
				$currSubmissionUnderMinThreshold = ( ( ( $tempCPBlack / ( $CPTotal + 1 ) ) * 100 ) <= $minThresholdBlack );

				$RCWhite = ( $tempCPWhite / ( $CPTotal + 1 ) ) * 100;
				$RCBlack = ( $tempCPBlack / ( $CPTotal + 1 ) ) * 100;
				$RCOther = ( $tempCPOther / ( $CPTotal + 1 ) ) * 100;

				$currSubmissionRCInBounds = ( $RCBlack <= $maxThresholdBlack && $RCBlack > $minThresholdBlack );

				if( $CPTotal < 10 ) {
					//allow some awards to happen to get us out of the steep swing because our current population is so low
					//add +/-10% to each RC bounds to handle steep swing
					$currSubmissionRCInBounds = $RCBlack < ( $maxThresholdBlack + 10 ) && $RCBlack > ( $minThresholdBlack - 10 );
				}

				$currSubmissionAndOneRaceInBoundsOneRacePastMax1 = ( $RCOther > $maxThresholdOther && ( $RCWhite > $minThresholdWhite && $RCWhite < $maxThresholdWhite ) && $currSubmissionRCInBounds );
				$currSubmissionAndOneRaceInBoundsOneRacePastMax2 = ( $RCWhite > $maxThresholdWhite && ( $RCOther > $minThresholdOther && $RCOther < $maxThresholdOther ) && $currSubmissionRCInBounds );

				break;

			default:
				$tempCPWhite = $programRacialCompositionArray['CPWhite'];
				$tempCPBlack = $programRacialCompositionArray['CPBlack'];
				$tempCPOther = $programRacialCompositionArray['CPOther'] + 1;
				$currSubmissionUnderMinThreshold = ( ( ( $tempCPOther / ( $CPTotal + 1 ) ) * 100 ) <= $minThresholdOther );

				$RCWhite = ( $tempCPWhite / ( $CPTotal + 1 ) ) * 100;
				$RCBlack = ( $tempCPBlack / ( $CPTotal + 1 ) ) * 100;
				$RCOther = ( $tempCPOther / ( $CPTotal + 1 ) ) * 100;

				$currSubmissionRCInBounds = ( $RCOther <= $maxThresholdOther && $RCOther > $minThresholdOther );

				if( $CPTotal < 10 ) {
					//allow some awards to happen to get us out of the steep swing because our current population is so low
					//add +/-10% to each RC bounds to handle steep swing
					$currSubmissionRCInBounds = $RCOther < ( $maxThresholdOther + 10 ) && $RCOther > ( $minThresholdOther - 10 );
				}

				$currSubmissionAndOneRaceInBoundsOneRacePastMax1 = ( $RCBlack > $maxThresholdBlack && ( $RCWhite > $minThresholdWhite && $RCWhite < $maxThresholdWhite ) && $currSubmissionRCInBounds );
				$currSubmissionAndOneRaceInBoundsOneRacePastMax2 = ( $RCWhite > $maxThresholdWhite && ( $RCBlack > $minThresholdBlack && $RCBlack < $maxThresholdBlack ) && $currSubmissionRCInBounds );

				break;

		}

		$this->logging( 'Current CP Value: ' . $race );

		$currentEnrollmentZeroAndFirstAward = ( ( $CPTotal == 0 ) && ( $magnetSchoolAvailableArray['TAS'] == $magnetSchoolAvailableArray['originalTAS'] ) );

		$allRCInBounds = ( $RCWhite < $maxThresholdWhite && $RCWhite > $minThresholdWhite ) &&
			( $RCBlack < $maxThresholdBlack && $RCBlack > $minThresholdBlack ) &&
			( $RCOther < $maxThresholdOther && $RCOther > $minThresholdOther );

		if( $CPTotal < 10 ) {
			$allRCInBounds = ( $RCWhite < ( $maxThresholdWhite + 10 ) && $RCWhite > ( $minThresholdWhite - 10 ) ) &&
				( $RCBlack < ( $maxThresholdBlack + 10 ) && $RCBlack > ( $minThresholdBlack - 10 ) ) &&
				( $RCOther < ( $maxThresholdOther + 10 ) && $RCOther > ( $minThresholdOther - 10 ) );

		}


		//if greater than max threshold and RCBefore > RCAfter then allow
		if( $allRCInBounds ) {
			//Everything is in bounds, so lets add it.
			return true;
		} else if( $currSubmissionUnderMinThreshold ) {
			//This RC is way under the min threshold, so lets add.
			$this->logging( 'Submission RC Value: ' . $currSubmissionUnderMinThreshold );
			return true;
		} else if( ( $currSubmissionAndOneRaceInBoundsOneRacePastMax1 || $currSubmissionAndOneRaceInBoundsOneRacePastMax2 ) ) {
			//One of two races that are not the submission's race are maxed out but by adding our submission our race will be in bounds and bring down the maxed out racial composition
			return true;
		} else if( $currentEnrollmentZeroAndFirstAward ) {
			//Means we are just starting out we need to allow the first submission to be awarded a slot to prime the current enrollment numbers.
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Checks to see if the new possible awarded will be within the racial composition.
	 *
	 * @param Submission $submission
	 * @param array      $totalAvailableSlots
	 * @param int        $magnetSchoolID
	 * @param string     $race
	 *
	 * @return bool
	 */
	private function passesRemovalAwardedRacialComposition( Submission $submission , $totalAvailableSlots = array() , $magnetSchoolID = 0 , $race ) {
		$magnetSchoolAvailableArray = $totalAvailableSlots[$magnetSchoolID];
		$programRacialCompositionArray = $totalAvailableSlots['programs'][$magnetSchoolAvailableArray['program']];

		$openEnrollment = $submission->getOpenEnrollment();

		$maxThresholdWhite = $openEnrollment->getMaxHRCWhite();
		$minThresholdWhite = $openEnrollment->getMinHRCWhite();
		$maxThresholdBlack = $openEnrollment->getMaxHRCBlack();
		$minThresholdBlack = $openEnrollment->getMinHRCBlack();
		$maxThresholdOther = $openEnrollment->getMaxHRCOther();
		$minThresholdOther = $openEnrollment->getMinHRCOther();

		$CPTotal = $programRacialCompositionArray['CPWhite'] + $programRacialCompositionArray['CPBlack'] + $programRacialCompositionArray['CPOther'];

		//$maxThreshold = '';
		$minThreshold = '';
		$tempCPWhite = '';
		$tempCPBlack = '';
		$tempCPOther = '';
		$currSubmissionUnderMinThreshold = false;
		$currSubmissionAndOneRaceInBoundsOneRacePastMax1 = false;
		$currSubmissionAndOneRaceInBoundsOneRacePastMax2 = false;
		$submissionRC4 = false;
		$currSubmissionRCInBounds = false;
		$currentEnrollmentZeroAndFirstAward = false;

		//$beforeRCWhite = $programRacialCompositionArray['CPWhite']/$CPTotal;
		//$beforeRCBlack = $programRacialCompositionArray['CPBlack']/$CPTotal;
		//$beforeRCOther = $programRacialCompositionArray['CPOther']/$CPTotal;

		$divider = ( $CPTotal > 1 ) ? $CPTotal - 1 : 1;
		switch( $race ) {

			case 'CPWhite':

				$tempCPWhite = $programRacialCompositionArray['CPWhite'] - 1;
				$tempCPBlack = $programRacialCompositionArray['CPBlack'];
				$tempCPOther = $programRacialCompositionArray['CPOther'];

				$currSubmissionUnderMinThreshold = ( ( ( $tempCPWhite / $divider ) * 100 ) < $minThresholdWhite );

				$RCWhite = ( $tempCPWhite / $divider ) * 100;
				$RCBlack = ( $tempCPBlack / $divider ) * 100;
				$RCOther = ( $tempCPOther / $divider ) * 100;

				$currSubmissionRCInBounds = ( $RCWhite < $maxThresholdWhite && $RCWhite > $minThresholdWhite );

				//$submissionRC4 = ( $magnetSchoolAvailableArray['changed'] == false && $magnetSchoolAvailableArray['TAS'] > 2 && $RCWhite == 100 );

				if( $CPTotal < 10 ) {
					//allow some awards to happen to get us out of the steep swing because our current population is so low
					//add +/-10% to each RC bounds to handle steep swing
					$currSubmissionRCInBounds = $RCWhite < ( $maxThresholdWhite + 10 ) && $RCWhite > ( $minThresholdWhite - 10 );
				}

				$currSubmissionAndOneRaceInBoundsOneRacePastMax1 = ( $RCBlack > $maxThresholdBlack && ( $RCOther > $minThresholdOther && $RCOther < $maxThresholdOther ) && $currSubmissionRCInBounds );
				$currSubmissionAndOneRaceInBoundsOneRacePastMax2 = ( $RCOther > $maxThresholdOther && ( $RCBlack > $minThresholdBlack && $RCBlack < $maxThresholdBlack ) && $currSubmissionRCInBounds );

				break;

			case 'CPBlack':

				$tempCPWhite = $programRacialCompositionArray['CPWhite'];
				$tempCPBlack = $programRacialCompositionArray['CPBlack'] - 1;
				$tempCPOther = $programRacialCompositionArray['CPOther'];

				$currSubmissionUnderMinThreshold = ( ( ( $tempCPBlack / $divider ) * 100 ) < $minThresholdBlack );

				$RCWhite = ( $tempCPWhite / $divider ) * 100;
				$RCBlack = ( $tempCPBlack / $divider ) * 100;
				$RCOther = ( $tempCPOther / $divider ) * 100;

				$currSubmissionRCInBounds = ( $RCBlack < $maxThresholdBlack && $RCBlack > $minThresholdBlack );

				//$submissionRC4 = ( $magnetSchoolAvailableArray['changed'] == false && $magnetSchoolAvailableArray['TAS'] > 2 && $RCBlack == 100 );

				if( $CPTotal < 10 ) {
					//allow some awards to happen to get us out of the steep swing because our current population is so low
					//add +/-10% to each RC bounds to handle steep swing
					$currSubmissionRCInBounds = $RCBlack < ( $maxThresholdBlack + 10 ) && $RCBlack > ( $minThresholdBlack - 10 );
				}

				$currSubmissionAndOneRaceInBoundsOneRacePastMax1 = ( $RCOther > $maxThresholdOther && ( $RCWhite > $minThresholdWhite && $RCWhite < $maxThresholdWhite ) && $currSubmissionRCInBounds );
				$currSubmissionAndOneRaceInBoundsOneRacePastMax2 = ( $RCWhite > $maxThresholdWhite && ( $RCOther > $minThresholdOther && $RCOther < $maxThresholdOther ) && $currSubmissionRCInBounds );

				break;

			default:

				$tempCPWhite = $programRacialCompositionArray['CPWhite'];
				$tempCPBlack = $programRacialCompositionArray['CPBlack'];
				$tempCPOther = $programRacialCompositionArray['CPOther'] - 1;
				$currSubmissionUnderMinThreshold = ( ( ( $tempCPOther / $divider ) * 100 ) < $minThresholdOther );

				$RCWhite = ( $tempCPWhite / $divider ) * 100;
				$RCBlack = ( $tempCPBlack / $divider ) * 100;
				$RCOther = ( $tempCPOther / $divider ) * 100;

				$currSubmissionRCInBounds = ( $RCOther < $maxThresholdOther && $RCOther > $minThresholdOther );

				//$submissionRC4 = ( $magnetSchoolAvailableArray['changed'] == false && $magnetSchoolAvailableArray['TAS'] > 2 && $RCOther == 100 );

				if( $CPTotal < 10 ) {
					//allow some awards to happen to get us out of the steep swing because our current population is so low
					//add +/-10% to each RC bounds to handle steep swing
					$currSubmissionRCInBounds = $RCOther < ( $maxThresholdOther + 10 ) && $RCOther > ( $minThresholdOther - 10 );
				}

				$currSubmissionAndOneRaceInBoundsOneRacePastMax1 = ( $RCBlack > $maxThresholdBlack && ( $RCWhite > $minThresholdWhite && $RCWhite < $maxThresholdWhite ) && $currSubmissionRCInBounds );
				$currSubmissionAndOneRaceInBoundsOneRacePastMax2 = ( $RCWhite > $maxThresholdWhite && ( $RCBlack > $minThresholdBlack && $RCBlack < $maxThresholdBlack ) && $currSubmissionRCInBounds );

				break;

		}

		$this->logging( 'Current CP Value: ' . $race );

		//if( $submission->getId() == 2393 ) {
		//	var_dump( $programRacialCompositionArray , $RCWhite , $RCBlack , $RCOther , $submissionRC , $submissionRC2 , $maxThresholdBlack , $minThresholdBlack , $maxThresholdWhite , $minThresholdWhite , $maxThresholdOther , $minThresholdOther , ( ( $RCWhite < $maxThresholdWhite && $RCWhite > $minThresholdWhite ) && ( $RCBlack < $maxThresholdBlack && $RCBlack > $minThresholdBlack ) && ( $RCOther < $maxThresholdOther && $RCOther > $minThresholdOther ) ) );
		//}

		$currentEnrollmentZeroAndFirstAward = ( ( $CPTotal == 0 ) && ( $magnetSchoolAvailableArray['TAS'] == $magnetSchoolAvailableArray['originalTAS'] ) );

		$allRCInBounds = ( $RCWhite < $maxThresholdWhite && $RCWhite > $minThresholdWhite ) &&
			( $RCBlack < $maxThresholdBlack && $RCBlack > $minThresholdBlack ) &&
			( $RCOther < $maxThresholdOther && $RCOther > $minThresholdOther );

		if( $CPTotal < 10 ) {
			$allRCInBounds = ( $RCWhite < ( $maxThresholdWhite + 10 ) && $RCWhite > ( $minThresholdWhite - 10 ) ) &&
				( $RCBlack < ( $maxThresholdBlack + 10 ) && $RCBlack > ( $minThresholdBlack - 10 ) ) &&
				( $RCOther < ( $maxThresholdOther + 10 ) && $RCOther > ( $minThresholdOther - 10 ) );

		}


		//if greater than max threshold and RCBefore > RCAfter then allow
		if( $allRCInBounds ) {
			//Everything is in bounds, so lets add it.
			return true;
		} else if( $currSubmissionUnderMinThreshold ) {
			//This RC is way under the min threshold, so lets add.
			$this->logging( 'Submission RC Value: ' . $currSubmissionUnderMinThreshold );
			return true;
		} else if( ( $currSubmissionAndOneRaceInBoundsOneRacePastMax1 || $currSubmissionAndOneRaceInBoundsOneRacePastMax2 ) ) {
			//One of two races that are not the submission's race are maxed out but by adding our submission our race will be in bounds and bring down the maxed out racial composition
			return true;
		} else if( $currentEnrollmentZeroAndFirstAward ) {
			//Means we are just starting out we need to allow the first submission to be awarded a slot to prime the current enrollment numbers.
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Filter Submission based on currentSchool Priority.
	 *
	 * Returns CurrentSchool Priority Array and non priority array (the left over).
	 *
	 * @param array   $submissions
	 *
	 * @return array
	 */
	private function currentSchoolPriority( $submissions ) {

		$priorityArray = array();
		$keysToRemove = array();

		foreach( $submissions as $key => $submissionData ) {

			$schoolName = '';

			/** @var Submission $submission */
			$submission = $submissionData['submission'];

			switch( $submissionData['choiceNumber'] ) {

				case 1:
					if( $submission->getFirstChoice() != null ) {
						$schoolName = $submission->getFirstChoice()->getName();
					}
					break;

				case 2:
					if( $submission->getSecondChoice() != null ) {
						$schoolName = $submission->getSecondChoice()->getName();
					}
					break;

				case 3:
					if( $submission->getThirdChoice() != null ) {
						$schoolName = $submission->getThirdChoice()->getName();
					}
					break;

				default:
					break;
			}
			if( $schoolName != '' ) {
				$currentSchool = trim( strtoupper( $submission->getCurrentSchool() ) );
				$choiceSchoolName = trim( strtoupper( $schoolName ) );

				//TODO: Check this.
				//AAA to Lee School Priority
				if( preg_match( '/(\bLee School of Creative and Performing Arts\b)/i' , $choiceSchoolName ) == 1 ||
					preg_match( '/(\bLee Creative and Performing Arts\b)/i' , $choiceSchoolName ) == 1 ) {

				/*if( $choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Creative Writing' ) ||
					$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Dance' ) ||
					$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Orchestra' ) ||
					$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Photography' ) ||
					$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Technical Theatre' ) ||
					$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Theatre Performance' ) ||
					$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Video/Broadcast Journalism' ) ||
					$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Visual Art' ) ||
					$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Vocal Performance' )
				) {*/

					if( $currentSchool == strtoupper( 'Academy For Academics And Arts' ) ) {
						$keysToRemove[] = $key;
						$priorityArray[] = $submissionData;
					}
				}

				//TODO: Check this.
				//Williams Middle to New Century Priority
				if( preg_match( '/(\bNew Century Technology\b)/i' , $choiceSchoolName ) == 1 ) {

					if( $currentSchool == strtoupper( 'Williams Middle-Magnet' ) ) {
						$keysToRemove[] = $key;
						$priorityArray[] = $submissionData;
					}
				}

				$currentSchool = null;
				$choiceSchoolName = null;
			}
			$schoolName = null;
		}

		foreach( $keysToRemove as $key ) {
			unset( $submissions[$key] );
		}
		$keysToRemove = null;

		return array( $priorityArray , $submissions );
	}

	/**
	 * Filters Submissions based on majority race Priority
	 *
	 * Returns Majority Race array and non priority array (the left over);
	 *
	 * @param array $submissions
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function majorityRacePriority( $submissions ) {

		$priorityArray = array();
		$keysToRemove = array();

		foreach( $submissions as $key => $submissionData ) {

			$schoolName = '';

			/** @var Submission $submission */
			$submission = $submissionData['submission'];

			if( $submission->getZonedSchool() != null ) {

				$schoolName = $submission->getZonedSchool();

				$race = '';
				switch( strtoupper( $submission->getRaceFormatted() ) ) {
					case "WHITE":
						$race = 'white';
						break;

					case "BLACK":
						$race = 'black';
						break;

					default:
						$race = 'other';
						break;
				}

				if( isset( $this->admData[$schoolName] ) ) {
					$ADMSchoolData = $this->admData[$schoolName];
				} else {
					$ADMSchoolData = null;
				}

				if( $ADMSchoolData != null ) {

					//ADM Data was found.
					$black = $ADMSchoolData->getBlack();
					$white = $ADMSchoolData->getWhite();
					$other = $ADMSchoolData->getOther();
					$majorityRace = '';

					$total = $black + $white + $other;

					$majorityRace = '';

					if( $total > 0 ) {
						if ($black / $total > 0.5) {
							$majorityRace = 'black';
						} else if ($white / $total > 0.5) {
							$majorityRace = 'white';
						} else {
							//No check for OTHER RACE priority due to OTHER being a group of races.
							continue;
						}
					}

					if( $race == $majorityRace ) {
						//Found Priority. Lets pull them out.
						$priorityArray[] = $submissionData;
						$keysToRemove[] = $key;
					}
					$black = null;
					$white = null;
					$other = null;
				}
				$ADMSchoolData = null;
			}
			$addressBound = null;
			$student = null;
		}

		foreach( $keysToRemove as $key ) {
			unset( $submissions[$key] );
		}

		$checkAddress = null;
		$keysToRemove = null;

		return array( $priorityArray , $submissions );
	}

	/**
	 * Filters Submissions based on sibling attend same school.
	 *
	 * Returns Sibling Attend array and non priority array (the left over).
	 *
	 * @param array $submissions
	 *
	 * @return array
	 */
	private function siblingAttendPriority( $submissions ) {

		$priorityArray = array();
		$keysToRemove = array();

		$validator = new ValidateSiblingService( $this->emLookup );

		foreach( $submissions as $key => $submissionData ) {

			$siblingID = 0;
			$schoolID = 0;

			/** @var Submission $submission */
			$submission = $submissionData['submission'];

			switch( $submissionData['choiceNumber'] ) {

				case 1:
					$siblingID = $submission->getFirstSiblingValue();
					if( $submission->getFirstChoice() != null ) {
						$schoolID = $submission->getFirstChoice()->getId();
					}
					break;

				case 2:
					$siblingID = $submission->getSecondSiblingValue();
					if( $submission->getSecondChoice() != null ) {
						$schoolID = $submission->getSecondChoice()->getId();
					}
					break;

				case 3:
					$siblingID = $submission->getThirdSiblingValue();
					if( $submission->getThirdChoice() != null ) {
						$schoolID = $submission->getThirdChoice()->getId();
					}
					break;

				default:
					break;
			}
			$valid = $validator->validateSiblingAttendsSchool( $siblingID , $schoolID );
			if( $valid ) {
				$priorityArray[] = $submissionData;
				$keysToRemove[] = $key;
			}
			$valid = null;
			$siblingID = null;
			$schoolID = null;
		}
		$validator = null;

		foreach( $keysToRemove as $key ) {
			unset( $submissions[$key] );
		}

		$keysToRemove = null;

		return array( $priorityArray , $submissions );
	}

	/**
	 * The final sorting of the groups and with the conditional middle/high school.
	 *
	 * @param array $submissions
	 * @param array $loggingArray
	 *
	 * @return array
	 */
	private function handleSortingPriority( $submissions , $loggingArray ) {

		/**
			'First' => array() ,
			'Second' => array() ,
			'Third' => array() ,
			'Fourth' => array() ,
			'Fifth' => array() ,
			'Sixth' => array() ,
			'Seventh' => array() ,
			'Eighth' => array() ,
		 */
		$finalSorting = array();

		//$this->logging( 'Beginning Committee Score Priority Total: ' . count( $submissions ) );

		//Get the committeeScore of 4s
		//list( $submissionsCommitteeScoreFour , $submissions[4] ) = $this->magnetCommitteeScoreSorting( 4 , $submissions[4] , $choiceNumber );
		list( $firstPriority , $submissionsLeftOver ) = $this->getFirstPriority( $submissions[4] );
		list( $secondPriority , $submissionsLeftOver ) = $this->getSecondPriority( $submissionsLeftOver );
		list( $thirdPriority , $submissionsLeftOver ) = $this->getThirdPriority( $submissionsLeftOver );
		list( $fourthPriority , $submissionsLeftOver ) = $this->getFourthPriority( $submissionsLeftOver );
		list( $fifthPriority , $submissionsLeftOver ) = $this->getFifthPriority( $submissionsLeftOver );
		list( $sixthPriority , $submissionsLeftOver ) = $this->getSixthPriority( $submissionsLeftOver );
		list( $seventhPriority , $submissionsLeftOver ) = $this->getSeventhPriority( $submissionsLeftOver );

		$loggingArray['4']['First'] = $firstPriority;
		$loggingArray['4']['Second'] = $secondPriority;
		$loggingArray['4']['Third'] = $thirdPriority;
		$loggingArray['4']['Fourth'] = $fourthPriority;
		$loggingArray['4']['Fifth'] = $fifthPriority;
		$loggingArray['4']['Sixth'] = $sixthPriority;
		$loggingArray['4']['Seventh'] = $seventhPriority;
		$loggingArray['4']['Eighth'] = $submissionsLeftOver;

		$finalSorting = array_merge( $finalSorting , $firstPriority , $secondPriority , $thirdPriority , $fourthPriority , $fifthPriority , $sixthPriority , $seventhPriority , $submissionsLeftOver );

		$firstPriority = null;
		$secondPriority = null;
		$thirdPriority = null;
		$fourthPriority = null;
		$fifthPriority = null;
		$sixthPriority = null;
		$seventhPriority = null;
		$submissionsLeftOver = null;







		//Get the committeeScores of 3s
		//list( $submissionsCommitteeScoreThree , $submissions[3] ) = $this->magnetCommitteeScoreSorting( 3 , $submissions[3] , $choiceNumber );

		list( $firstPriority , $submissionsLeftOver ) = $this->getFirstPriority( $submissions[3] );
		list( $secondPriority , $submissionsLeftOver ) = $this->getSecondPriority( $submissionsLeftOver );
		list( $thirdPriority , $submissionsLeftOver ) = $this->getThirdPriority( $submissionsLeftOver );
		list( $fourthPriority , $submissionsLeftOver ) = $this->getFourthPriority( $submissionsLeftOver );
		list( $fifthPriority , $submissionsLeftOver ) = $this->getFifthPriority( $submissionsLeftOver );
		list( $sixthPriority , $submissionsLeftOver ) = $this->getSixthPriority( $submissionsLeftOver );
		list( $seventhPriority , $submissionsLeftOver ) = $this->getSeventhPriority( $submissionsLeftOver );

		$loggingArray['3']['First'] = $firstPriority;
		$loggingArray['3']['Second'] = $secondPriority;
		$loggingArray['3']['Third'] = $thirdPriority;
		$loggingArray['3']['Fourth'] = $fourthPriority;
		$loggingArray['3']['Fifth'] = $fifthPriority;
		$loggingArray['3']['Sixth'] = $sixthPriority;
		$loggingArray['3']['Seventh'] = $seventhPriority;
		$loggingArray['3']['Eighth'] = $submissionsLeftOver;

		$finalSorting = array_merge( $finalSorting , $firstPriority , $secondPriority , $thirdPriority , $fourthPriority , $fifthPriority , $sixthPriority , $seventhPriority , $submissionsLeftOver );

		$firstPriority = null;
		$secondPriority = null;
		$thirdPriority = null;
		$fourthPriority = null;
		$fifthPriority = null;
		$sixthPriority = null;
		$seventhPriority = null;
		$submissionsLeftOver = null;







		//Get the committeeScores of 2s
		//list( $submissionsCommitteeScoreTwo , $submissions[2] ) = $this->magnetCommitteeScoreSorting( 2 , $submissions[2] , $choiceNumber );

		list( $firstPriority , $submissionsLeftOver ) = $this->getFirstPriority( $submissions[2] );
		list( $secondPriority , $submissionsLeftOver ) = $this->getSecondPriority( $submissionsLeftOver );
		list( $thirdPriority , $submissionsLeftOver ) = $this->getThirdPriority( $submissionsLeftOver );
		list( $fourthPriority , $submissionsLeftOver ) = $this->getFourthPriority( $submissionsLeftOver );
		list( $fifthPriority , $submissionsLeftOver ) = $this->getFifthPriority( $submissionsLeftOver );
		list( $sixthPriority , $submissionsLeftOver ) = $this->getSixthPriority( $submissionsLeftOver );
		list( $seventhPriority , $submissionsLeftOver ) = $this->getSeventhPriority( $submissionsLeftOver );

		$loggingArray['2']['First'] = $firstPriority;
		$loggingArray['2']['Second'] = $secondPriority;
		$loggingArray['2']['Third'] = $thirdPriority;
		$loggingArray['2']['Fourth'] = $fourthPriority;
		$loggingArray['2']['Fifth'] = $fifthPriority;
		$loggingArray['2']['Sixth'] = $sixthPriority;
		$loggingArray['2']['Seventh'] = $seventhPriority;
		$loggingArray['2']['Eighth'] = $submissionsLeftOver;

		$finalSorting = array_merge( $finalSorting , $firstPriority , $secondPriority , $thirdPriority , $fourthPriority , $fifthPriority , $sixthPriority , $seventhPriority , $submissionsLeftOver );

		$firstPriority = null;
		$secondPriority = null;
		$thirdPriority = null;
		$fourthPriority = null;
		$fifthPriority = null;
		$sixthPriority = null;
		$seventhPriority = null;
		$submissionsLeftOver = null;







		//Get the committeeScores of 1s
		//list( $submissionsCommitteeScoreOne , $submissions[1] ) = $this->magnetCommitteeScoreSorting( 1 , $submissions[1] , $choiceNumber );

		list( $firstPriority , $submissionsLeftOver ) = $this->getFirstPriority( $submissions[1] );
		list( $secondPriority , $submissionsLeftOver ) = $this->getSecondPriority( $submissionsLeftOver );
		list( $thirdPriority , $submissionsLeftOver ) = $this->getThirdPriority( $submissionsLeftOver );
		list( $fourthPriority , $submissionsLeftOver ) = $this->getFourthPriority( $submissionsLeftOver );
		list( $fifthPriority , $submissionsLeftOver ) = $this->getFifthPriority( $submissionsLeftOver );
		list( $sixthPriority , $submissionsLeftOver ) = $this->getSixthPriority( $submissionsLeftOver );
		list( $seventhPriority , $submissionsLeftOver ) = $this->getSeventhPriority( $submissionsLeftOver );

		$loggingArray['1']['First'] = $firstPriority;
		$loggingArray['1']['Second'] = $secondPriority;
		$loggingArray['1']['Third'] = $thirdPriority;
		$loggingArray['1']['Fourth'] = $fourthPriority;
		$loggingArray['1']['Fifth'] = $fifthPriority;
		$loggingArray['1']['Sixth'] = $sixthPriority;
		$loggingArray['1']['Seventh'] = $seventhPriority;
		$loggingArray['1']['Eighth'] = $submissionsLeftOver;

		$finalSorting = array_merge( $finalSorting , $firstPriority , $secondPriority , $thirdPriority , $fourthPriority , $fifthPriority , $sixthPriority , $seventhPriority , $submissionsLeftOver );

		$firstPriority = null;
		$secondPriority = null;
		$thirdPriority = null;
		$fourthPriority = null;
		$fifthPriority = null;
		$sixthPriority = null;
		$seventhPriority = null;
		$submissionsLeftOver = null;

		//Get the committeeScores of 0s
		//list( $submissionsCommitteeScoreZero , $submissions[0] ) = $this->magnetCommitteeScoreSorting( 0 , $submissions[0] , $choiceNumber );

		list( $firstPriority , $submissionsLeftOver ) = $this->getFirstPriority( $submissions[0] );
		list( $secondPriority , $submissionsLeftOver ) = $this->getSecondPriority( $submissionsLeftOver );
		list( $thirdPriority , $submissionsLeftOver ) = $this->getThirdPriority( $submissionsLeftOver );
		list( $fourthPriority , $submissionsLeftOver ) = $this->getFourthPriority( $submissionsLeftOver );
		list( $fifthPriority , $submissionsLeftOver ) = $this->getFifthPriority( $submissionsLeftOver );
		list( $sixthPriority , $submissionsLeftOver ) = $this->getSixthPriority( $submissionsLeftOver );
		list( $seventhPriority , $submissionsLeftOver ) = $this->getSeventhPriority( $submissionsLeftOver );

		$loggingArray['0']['First'] = $firstPriority;
		$loggingArray['0']['Second'] = $secondPriority;
		$loggingArray['0']['Third'] = $thirdPriority;
		$loggingArray['0']['Fourth'] = $fourthPriority;
		$loggingArray['0']['Fifth'] = $fifthPriority;
		$loggingArray['0']['Sixth'] = $sixthPriority;
		$loggingArray['0']['Seventh'] = $seventhPriority;
		$loggingArray['0']['Eighth'] = $submissionsLeftOver;

		$finalSorting = array_merge( $finalSorting , $firstPriority , $secondPriority , $thirdPriority , $fourthPriority , $fifthPriority , $sixthPriority , $seventhPriority , $submissionsLeftOver );

		$firstPriority = null;
		$secondPriority = null;
		$thirdPriority = null;
		$fourthPriority = null;
		$fifthPriority = null;
		$sixthPriority = null;
		$seventhPriority = null;
		$submissionsLeftOver = null;


		//Submissions leftover contain null values for the committeeScores.
		list( $firstPriority , $submissionsLeftOver ) = $this->getFirstPriority( $submissions['Empty'] );
		list( $secondPriority , $submissionsLeftOver ) = $this->getSecondPriority( $submissionsLeftOver );
		list( $thirdPriority , $submissionsLeftOver ) = $this->getThirdPriority( $submissionsLeftOver );
		list( $fourthPriority , $submissionsLeftOver ) = $this->getFourthPriority( $submissionsLeftOver );
		list( $fifthPriority , $submissionsLeftOver ) = $this->getFifthPriority( $submissionsLeftOver );
		list( $sixthPriority , $submissionsLeftOver ) = $this->getSixthPriority( $submissionsLeftOver );
		list( $seventhPriority , $submissionsLeftOver ) = $this->getSeventhPriority( $submissionsLeftOver );

		$loggingArray['Empty']['First'] = $firstPriority;
		$loggingArray['Empty']['Second'] = $secondPriority;
		$loggingArray['Empty']['Third'] = $thirdPriority;
		$loggingArray['Empty']['Fourth'] = $fourthPriority;
		$loggingArray['Empty']['Fifth'] = $fifthPriority;
		$loggingArray['Empty']['Sixth'] = $sixthPriority;
		$loggingArray['Empty']['Seventh'] = $seventhPriority;
		$loggingArray['Empty']['Eighth'] = $submissionsLeftOver;

		$finalSorting = array_merge( $finalSorting , $firstPriority , $secondPriority , $thirdPriority , $fourthPriority , $fifthPriority , $sixthPriority , $seventhPriority , $submissionsLeftOver );

		$firstPriority = null;
		$secondPriority = null;
		$thirdPriority = null;
		$fourthPriority = null;
		$fifthPriority = null;
		$sixthPriority = null;
		$seventhPriority = null;
		$submissionsLeftOver = null;

		return array( $finalSorting , $loggingArray );
	}


	/**
	 * First Priority is Current School, Majority Race, and Sibling Attending.
	 *
	 * @param array $submissions
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getFirstPriority( $submissions = array() ) {

		list( $firstPriority , $submissionsLeftOver ) = $this->currentSchoolPriority( $submissions );

		list( $secondPriority , $submissionsLeft1 ) = $this->majorityRacePriority( $firstPriority );

		list ( $priority , $submissionsLeft2 ) = $this->siblingAttendPriority( $secondPriority );

		$submissionsLeftOver = array_merge( $submissionsLeftOver , $submissionsLeft1 , $submissionsLeft2 );

		$submissionsLeft1 = null;
		$submissionsLeft2 = null;

		$priority = $this->lotterySorting( $priority );

		return array( $priority , $submissionsLeftOver );
	}


	/**
	 * Second Priority is Current School and Majority Race.
	 *
	 * @param array $submissions
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getSecondPriority( $submissions = array() ) {

		list( $firstPriority , $submissionsLeftOver ) = $this->currentSchoolPriority( $submissions );

		list( $priority , $submissionsLeft1 ) = $this->majorityRacePriority( $firstPriority );

		$submissionsLeftOver = array_merge( $submissionsLeftOver , $submissionsLeft1 );

		$priority = $this->lotterySorting( $priority );

		return array( $priority , $submissionsLeftOver );
	}


	/**
	 * Third Priority is Current School and Sibling Attending.
	 *
	 * @param array $submissions
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getThirdPriority( $submissions = array() ) {

		list( $firstPriority , $submissionsLeftOver ) = $this->currentSchoolPriority( $submissions );

		list ( $priority , $submissionsLeft2 ) = $this->siblingAttendPriority( $firstPriority );

		$submissionsLeftOver = array_merge( $submissionsLeftOver , $submissionsLeft2 );

		$submissionsLeft2 = null;

		$priority = $this->lotterySorting( $priority );

		return array( $priority , $submissionsLeftOver );
	}

	/**
	 * Fourth Priority is Current School Feeder.
	 *
	 * @param array $submissions
	 *
	 * @return array
	 */
	private function getFourthPriority( $submissions = array() ) {

		list( $priority , $submissionsLeftOver ) = $this->currentSchoolPriority( $submissions );

		$priority = $this->lotterySorting( $priority );

		return array( $priority , $submissionsLeftOver );
	}

	/**
	 * Fifth Priority is Majority Race and Sibling Attending.
	 *
	 * @param array $submissions
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getFifthPriority( $submissions = array() ) {

		list( $secondPriority , $submissionsLeftOver ) = $this->majorityRacePriority( $submissions );

		list ( $priority , $submissionsLeft2 ) = $this->siblingAttendPriority( $secondPriority );

		$submissionsLeftOver = array_merge( $submissionsLeftOver , $submissionsLeft2 );

		$submissionsLeft2 = null;

		$priority = $this->lotterySorting( $priority );

		return array( $priority , $submissionsLeftOver );
	}

	/**
	 * Sixth Priority is Majority Race Priority
	 *
	 * @param array $submissions
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getSixthPriority( $submissions = array() ) {

		list( $priority , $submissionsLeftOver ) = $this->majorityRacePriority( $submissions );

		$priority = $this->lotterySorting( $priority );

		return array( $priority , $submissionsLeftOver );
	}

	/**
	 * Seventh Priority is Sibling Attends Choice
	 *
	 * @param array $submissions
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getSeventhPriority( $submissions = array() ) {

		list ( $priority , $submissionsLeftOver ) = $this->siblingAttendPriority( $submissions );

		$priority = $this->lotterySorting( $priority );

		$submissionsLeftOver = $this->lotterySorting( $submissionsLeftOver );//Sorting the last Priority because there is no other filtering.

		return array( $priority , $submissionsLeftOver );
	}


	/**
	 * Sorts the groups by CommitteeScore 4 - 1 (4 being the Highest)
	 *
	 * @param integer $committeeScoreCheck
	 * @param array   $submissions
	 * @param integer $choiceNumber
	 *
	 * @return array
	 */
	private function magnetCommitteeScoreSorting( $committeeScoreCheck = 0 , $submissions , $choiceNumber ) {

		$priorityArray = array();
		$keysToRemove = array();

		/**
		 * @var $key string
		 * @var $submission Submission
		 */
		foreach( $submissions as $key => $submission ) {

			$score = 0;
			switch( $choiceNumber ) {
				case '1':
					$score = $submission->getCommitteeReviewScoreFirstChoice();
					break;

				case '2':
					$score = $submission->getCommitteeReviewScoreSecondChoice();
					break;

				case '3':
					$score = $submission->getCommitteeReviewScoreThirdChoice();
					break;
			}

			//If the CommitteeScore equals the committee Check Score they are added to the priority list
			//OR
			//The Grade is less than 6, they are added to the priority list because Grades less than 6 DO NOT USE CommitteeScores.
			if( $score == $committeeScoreCheck && $score != null ) {
				$priorityArray[] = $submission;
				$keysToRemove[] = $key;
			}
		}

		foreach( $keysToRemove as $key ) {
			unset( $submissions[$key] );
		}

		return array( $priorityArray , $submissions );

	}

	/**
	 * Sort the submissions based on Lottery Number and return them in sorted order.
	 *
	 * @param $submissions
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function lotterySorting( $submissions ) {

		$newOrderArray = array();

		foreach( $submissions as $submissionData ) {

			/** @var Submission $submission */
			$submission = $submissionData['submission'];

			//Using Lottery DOT Choice Number to Build the list. This will ensure they are still giving lottery number priority.
			//
			if( !isset( $newOrderArray[$submission->getLotteryNumber() . '.' . $submissionData['choiceNumber']] ) ) {
				$newOrderArray[$submission->getLotteryNumber() . '.' . $submissionData['choiceNumber']] = $submissionData;
			} else {
				throw new \Exception( 'Lottery number is showing up twice: ' . $submission->getLotteryNumber() );
			}
		}
		$submission = null;

		ksort( $newOrderArray ); //Sort by the key index.

		return $newOrderArray;
	}

	/**
	 * Tries to see if the two name match or close to match.
	 *
	 * @param string $currentSchool
	 * @param string $choiceSchoolName
	 *
	 * @return bool
	 */
	private function doesCurrentSchoolMatchChoice( $currentSchool = '' , $choiceSchoolName = '' ) {

		if( $currentSchool == '' || $choiceSchoolName == '' ) {
			return false;
		}
		$currentSchool = trim( strtoupper( $currentSchool ) );
		$choiceSchoolName = trim( strtoupper( $choiceSchoolName ) );
		$match = false;

		//TODO: Because this is hardcoded and very badddddd. :D

		if( $currentSchool == $choiceSchoolName ) {
			$match = true;
		} elseif( $choiceSchoolName == strtoupper( 'Academy for Science and Foreign Language' ) ) {
			if( $currentSchool == strtoupper( 'Academy For Science & Foreign Lang.' ) || $currentSchool == strtoupper( 'ASFL' ) ) {
				$match = true;
			}
		} elseif( $choiceSchoolName == strtoupper( 'Columbia High School DP' ) || $choiceSchoolName == strtoupper( 'Columbia High School IBCP' ) || $choiceSchoolName == strtoupper( 'Columbia High School MYP' ) ) {
			if( $currentSchool == strtoupper( 'Columbia High School' ) || $currentSchool == strtoupper( 'Academy For Science & Foreign Lang.' ) ) { //ASFL is a Feeder Pattern into Columbia HIGH.
				$match = true;
			}
		} elseif( $choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Creative Writing' ) || $choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Dance' ) ||
			$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Orchestra' ) || $choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Photography' ) ||
			$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Technical Theatre' ) || $choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Theatre Performance' ) ||
			$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Video/Broadcast Journalism' ) || $choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Visual Art' ) ||
			$choiceSchoolName == strtoupper( 'Lee School of Creative and Performing Arts - Vocal Performance' )
		) {
			if( $currentSchool == strtoupper( 'Lee High School' ) ) {
				$match = true;
			}
		} elseif( $choiceSchoolName == strtoupper( 'Williams Technology Middle School' ) ) {
			if( $currentSchool == strtoupper( 'Williams Middle School' ) ) {
				$match = true;
			}
		}

		return $match;
	}

	/**
	 * Logging function to enabled us to turn on and off very easily.
	 */
	private function logging() {

		if( $this->container->get( 'kernel' )->getEnvironment() != 'dev' ) {
			return;
		}

		$args = func_get_args();

		foreach( $args as $arg ) {
			//var_dump( $arg );
		}
	}

	/**
	 * Writes out the Lottery Logging File before anything was awarded or denied.
	 *
	 * @param array  $lotteryData
	 * @param string $list
	 * @param string $fileName
	 * @param string $title
	 *
	 * @return boolean
	 */
	private function writeLoggingFile( $lotteryData = array() , $list = '' , $fileName = 'lottery' , $title = 'Lottery List', $for_download = false ) {

		$phpExcelObject = $this->container->get( 'phpexcel' )->createPHPExcelObject();
		$phpExcelObject->getProperties()->setCreator( "Lean Frog" )
			->setLastModifiedBy( "Lean Frog" )
			->setSubject( "Lottery" )
			->setDescription( "Document the lottery" )
			->setKeywords( "mymagnetapp" )
			->setCategory( "lottery" );

		$activeSheet = $phpExcelObject->getActiveSheet();

		if( $list == 'before' ) {

			$beforeData = $lotteryData[$list];

			if( !empty( $beforeData ) ) {

				$lastGrade = '';

				foreach( $beforeData as $grade => $committeeScoreData ) {

					if( $lastGrade != $grade ) {
						if( !empty( $lastGrade ) ) {
							$activeSheet = $phpExcelObject->createSheet();
						}
						$lastGrade = $grade;
						$activeSheet->setTitle( $grade );
					}

					//Find Sheet by Name.

					$row = 3;

					foreach( $committeeScoreData as $committeeScore => $committeeData ) {

						//Committee Scores, then priorityData.
						foreach( $committeeData as $priority => $priorityData ) {

							$startingColumn = 0;
							$activeSheet->mergeCellsByColumnAndRow( 0 , 1 , 6 , 1 );
							$activeSheet->setCellValueByColumnAndRow( 0 , 1 , 'Before ' . $title . ' ' . $grade );
							$activeSheet->setCellValueByColumnAndRow( 0 , 2 , 'Submission ID' );
							$activeSheet->setCellValueByColumnAndRow( 1 , 2 , 'State ID' );
							$activeSheet->setCellValueByColumnAndRow( 2 , 2 , 'Race' );
							$activeSheet->setCellValueByColumnAndRow( 3 , 2 , 'Choice' );
							$activeSheet->setCellValueByColumnAndRow( 4 , 2 , 'Choice Number' );
							$activeSheet->setCellValueByColumnAndRow( 5 , 2 , 'Current School');
							$activeSheet->setCellValueByColumnAndRow( 6 , 2 , 'Zoned School' );
							$activeSheet->setCellValueByColumnAndRow( 7 , 2 , 'Sibling ID');
							$activeSheet->setCellValueByColumnAndRow( 8 , 2 , 'Lottery' );
							$activeSheet->setCellValueByColumnAndRow( 9 , 2 , 'Committee Score' );
							$activeSheet->setCellValueByColumnAndRow( 10 , 2 , 'Priority Level' );
							$startingColumn = 0;
							$originalStartingColumn = $startingColumn;

							//Groups of columns
							foreach( $priorityData as $lotteryNumber => $submissionData ) {

								//List of Submissions

								//foreach( $submissionList as $submissionData ) {


									/** @var Submission $submission */
									$submission = $submissionData['submission'];

									$startingColumn = $originalStartingColumn;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getId() );
									$startingColumn++;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getStateID() );
									$startingColumn++;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getRaceFormatted() );
									$startingColumn++;

									$siblingID = null;
									switch( $submissionData['choiceNumber'] ) {
										case 1:
											if( $submission->getFirstChoice() != null ) {
												$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getFirstChoice()->__toString() );
												$siblingID = $submission->getFirstSiblingValue();
											}
											break;
										case 2:
											if( $submission->getSecondChoice() != null ) {
												$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getSecondChoice()->__toString() );
												$siblingID = $submission->getSecondSiblingValue();
											}
											break;
										case 3:
											if( $submission->getThirdChoice() != null ) {
												$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getThirdChoice()->__toString() );
												$siblingID = $submission->getThirdSiblingValue();
											}
											break;
									}
									$startingColumn++;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submissionData['choiceNumber'] );
									$startingColumn++;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn, $row, $submission->getCurrentSchool() );
									$startingColumn++;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getZonedSchool() );
									$startingColumn++;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn, $row, $siblingID );
									$startingColumn++;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getLotteryNumber() );
									$startingColumn++;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $committeeScore );
									$startingColumn++;

									$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $priority );

									$row++;
								//}
							}
						}
					}
				}
			}
			$lotteryData[$list] = null;
		}

		if( $list == 'after' ) {

			$afterData = $lotteryData[$list];

			if( $afterData != null ) {

				$lastGrade = '';

				/**
				 * Looping over the grades.
				 * @var string $grade
				 * @var array  $listType
				 */
				foreach( $afterData as $grade => $listType ) {

					if( $lastGrade != $grade ) {
						if( !empty( $lastGrade ) ) {
							$activeSheet = $phpExcelObject->createSheet();
						}
						$lastGrade = $grade;
						$activeSheet->setTitle( $grade );
					}

					/**
					 * Looping over grades now.
					 *
					 * @var string $status
					 * @var array  $submissions
					 */
					foreach( $listType as $status => $submissions ) {

						//Looping over status (awarded, denied, etc..)
						$startingColumn = 0;
						$row = 3;

						switch( $status ) {

							case 'awarded':
								$activeSheet->mergeCellsByColumnAndRow( 0 , 1 , 6 , 1 );
								$activeSheet->setCellValueByColumnAndRow( 0 , 1 , 'Awarded Submissions' );
								$activeSheet->setCellValueByColumnAndRow( 0 , 2 , 'Submission ID' );
								$activeSheet->setCellValueByColumnAndRow( 1 , 2 , 'State ID' );
								$activeSheet->setCellValueByColumnAndRow( 2 , 2 , 'Race' );
								$activeSheet->setCellValueByColumnAndRow( 3 , 2 , 'Zoned School' );
								$activeSheet->setCellValueByColumnAndRow( 4 , 2 , 'Lottery' );
								$activeSheet->setCellValueByColumnAndRow( 5 , 2 , 'Awarded School' );
								$startingColumn = 0;
								break;

							case 'wait-list':
								$activeSheet->mergeCellsByColumnAndRow( 7 , 1 , 14 , 1 );
								$activeSheet->setCellValueByColumnAndRow( 7 , 1 , 'Wait Listed Submissions' );
								$activeSheet->setCellValueByColumnAndRow( 7 , 2 , 'Submission ID' );
								$activeSheet->setCellValueByColumnAndRow( 8 , 2 , 'State ID' );
								$activeSheet->setCellValueByColumnAndRow( 9 , 2 , 'Race' );
								$activeSheet->setCellValueByColumnAndRow( 10 , 2 , 'Zoned School' );
								$activeSheet->setCellValueByColumnAndRow( 11 , 2 , 'Lottery' );
								$activeSheet->setCellValueByColumnAndRow( 12 , 2 , 'First Choice School' );
								$activeSheet->setCellValueByColumnAndRow( 13 , 2 , 'Second Choice School' );
								$activeSheet->setCellValueByColumnAndRow( 14 , 2 , 'Third Choice School' );
								$startingColumn = 7;
								break;

							case 'denied':
								$activeSheet->mergeCellsByColumnAndRow( 16 , 1 , 22 , 1 );
								$activeSheet->setCellValueByColumnAndRow( 16 , 1 , 'Denied Submissions' );
								$activeSheet->setCellValueByColumnAndRow( 16 , 2 , 'Submission ID' );
								$activeSheet->setCellValueByColumnAndRow( 17 , 2 , 'State ID' );
								$activeSheet->setCellValueByColumnAndRow( 18 , 2 , 'Race' );
								$activeSheet->setCellValueByColumnAndRow( 19 , 2 , 'Zoned School' );
								$activeSheet->setCellValueByColumnAndRow( 20 , 2 , 'Lottery' );
								$activeSheet->setCellValueByColumnAndRow( 21 , 2 , 'First Choice School' );
								$activeSheet->setCellValueByColumnAndRow( 22 , 2 , 'Second Choice School' );
								$activeSheet->setCellValueByColumnAndRow( 23 , 2 , 'Third Choice School' );
								$startingColumn = 16;
								break;
						}

						$originalStartingColumn = $startingColumn;

						/** @var array $submission */
						foreach( $submissions as $submissionArray ) {

							//Looping over the submissions.
							/** @var Submission $submission */
							$submission = $submissionArray['submission'];

							/** @var array $choices */
							$choices = $submissionArray['choice'];

							$startingColumn = $originalStartingColumn;

							$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getId() );
							$startingColumn++;

							$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getStateID() );
							$startingColumn++;

							$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getRaceFormatted() );
							$startingColumn++;

							$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getZonedSchool() );
							$startingColumn++;

							$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $submission->getLotteryNumber() );
							$startingColumn++;

							try {
								/** @var MagnetSchool $magnetSchool */
								foreach( $choices as $magnetSchool ) {
									if( !empty( $magnetSchool ) ) {
										$activeSheet->setCellValueByColumnAndRow( $startingColumn , $row , $magnetSchool->__toString() );
									}
									$startingColumn++;
								}
							} catch( \Exception $e ) {
								var_dump( $choices , $e );
								die ( 'here' );
							}

							$row++;

						}

					}
				}
			}
			//$this->logging( $afterData );
			$lotteryData[$list] = null;
		}

		//Write out the file to save it to the system.
		$writer = $this->container->get( 'phpexcel' )->createWriter( $phpExcelObject , 'Excel2007' );

		if( $for_download ){
			$list = '-preview';
			$dir = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/lottery-list/preview/';

			if (file_exists($dir)) {
				array_map('unlink', glob( $dir . "*.xlsx"));
			}

		} else {
			$list = '-debug-'.$list;
			$dir = $this->container->get( 'kernel' )->getRootDir() . '/../web/debugging/';
		}
		if( !file_exists( $dir ) ) {
			mkdir( $dir );
		}

		$writer->save( $dir . $fileName . $list . '-' . date( 'Y-m-d-H-i' ) . '.xlsx' );

		//Null out to release Memory.
		$writer = null;

		return true;
	}

    /**
     * Writes out the Simple Lottery Logging File before anything was awarded or denied.
     *
     * @param array  $lotteryData
     * @param string $list
     * @param string $fileName
     * @param string $title
     *
     * @return boolean
     */
    private function writeSimpleLoggingFile( $lotteryData = array() , $list = '' , $fileName = 'lottery' , $title = 'Lottery List', $for_download = false ) {

        if( $for_download ){
            $file_list = '-preview';
            $dir = substr( $this->container->get( 'kernel' )->getRootDir(), 0, -3) . 'web/reports/lottery-list/preview/';

            if (file_exists($dir)) {
                array_map('unlink', glob( $dir . "*.csv"));
            }

        } else {
            $file_list = '-debug-'.$list;
            $dir = substr( $this->container->get( 'kernel' )->getRootDir(), 0, -3) . 'web/debugging/';
            //$dir = $this->container->get( 'kernel' )->getRootDir() . '/../web/debugging/';
        }

        if( !file_exists( $dir ) ) {
            mkdir( $dir, 0777, true );
        }

        $eligibility_requirements_service = new EligibilityRequirementsService( $this->emLookup );

        if( $list == 'before' ) {

            $beforeData = $lotteryData;

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

                    foreach( $submissions as $submission ){

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
                            ( $eligibility_requirements_service->doesSubmissionHaveAllEligibility(
                                $submission,
                                $submission->{'get'. $choice .'Choice'}(),
                                $submission->{'get'. $choice .'ChoiceFirstChoiceFocus'}()
                            ) ) ? 'Eligible' : 'Not Eligible',
                        ]);
                    }
                }
            }
        }
        return true;
    }


    /**
	 * Build submission list and export in excel format
	 *
	 * @param $list_type
	 * @param OpenEnrollment $openEnrollment
	 */
	public function download_list( $list_type, OpenEnrollment $openEnrollment ){

		$this->admData = $this->getADMData( $openEnrollment );
		$this->magnetSchoolSettings = $this->getMagnetSchoolSettings( $openEnrollment );

		$activeStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( array(
			'id' => 1
		) );

		$waitListStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy( array(
			'id' => 9
		) );

		switch( strtolower( $list_type ) ){
			case 'lottery-list':
				$this->build_lotteryGroupingArray( $activeStatus, $openEnrollment );
				$this->writeLoggingFile( $this->loggingOrderingArray , 'before' , 'lottery' , 'Lottery List', 'download' );
			break;

			case 'late-period-list':
				$this->build_lotteryGroupingArray( array( $waitListStatus, $activeStatus) , $openEnrollment );
				$this->writeLoggingFile( $this->loggingOrderingArray , 'before' , 'late-lottery' , 'Late Lottery List', 'download' );
			break;

			case 'wait-list':
				$this->build_lotteryGroupingArray( $waitListStatus, $openEnrollment );
				$this->writeLoggingFile( $this->loggingOrderingArray , 'before' , 'wait-list' , 'Wait List', 'download' );
			break;

            case 'simple-list':
                $this->build_simpleLotteryGroupingArray( $activeStatus, $openEnrollment );
                $this->writeSimpleLoggingFile( $this->lotteryGroupingArray, 'before' , 'lottery' , 'Lottery List', 'download' );
            break;
		}
	}

    /**
     * Build the Simple Lottery Submission Lists sorted for processing and export
     *
     * @param $submissionStatus_priority_list
     * @param OpenEnrollment $openEnrollment
     * @return bool
     */
	public function build_simpleLotteryGroupingArray( $submissionStatus_priority_list, OpenEnrollment $openEnrollment ){

        $submissionStatus_priority_list = ( is_array( $submissionStatus_priority_list ) ) ? $submissionStatus_priority_list : array( $submissionStatus_priority_list );

        foreach( $submissionStatus_priority_list as $submissionStatus ){
            if( get_class( $submissionStatus ) != 'IIAB\MagnetBundle\Entity\SubmissionStatus' ){
                return false;
            }
        }

        $submissions = $this->emLookup->getRepository( 'IIABMagnetBundle:Submission' )->findBy([
            'submissionStatus' => $submissionStatus_priority_list,
            'openEnrollment' => $openEnrollment,
            'lotteryNumber' => 0
        ]);
        foreach( $submissions as $submission ){
            $lotteryNumber = $submission->getLotteryNumber();
            while( $lotteryNumber == 0 ){
                $lotteryNumber = $this->getLotteryNumber( $openEnrollment );
            }
            $submission->setLotteryNumber( $lotteryNumber );
            $this->emLookup->persist($submission);
        }
        $this->emLookup->flush();

        $magnetSchools = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findBy( [
            'openEnrollment' => $openEnrollment,
            'active' => 1
        ]);

        $choices = [
            1 => 'first',
            2 => 'second',
            3 => 'third'
        ];

        $grouping_array = [];

        foreach( $choices as $choice ) {

            $submissions = $this->emLookup->getRepository('IIABMagnetBundle:Submission')->findBy([
                'submissionStatus' => $submissionStatus_priority_list,
                $choice . 'Choice' => $magnetSchools,
            ], ['lotteryNumber' => 'ASC']);

            $grouping_array[$choice] = $submissions;
        }

        gc_enable();
        gc_collect_cycles();
        $this->lotteryGroupingArray = $grouping_array;

        return true;
    }

    /**
	 * Build the Lottery Submission Lists sorted for processing and export
	 *
	 * @param $submissionStatus_priority_list
	 * @param OpenEnrollment $openEnrollment
	 * @return bool
	 */
	public function build_lotteryGroupingArray( $submissionStatus_priority_list, OpenEnrollment $openEnrollment ){

		$submissionStatus_priority_list = ( is_array( $submissionStatus_priority_list ) ) ? $submissionStatus_priority_list : array( $submissionStatus_priority_list );

		foreach( $submissionStatus_priority_list as $submissionStatus ){
			if( get_class( $submissionStatus ) != 'IIAB\MagnetBundle\Entity\SubmissionStatus' ){
				return false;
			}
		}

		gc_enable();
		gc_collect_cycles();

		//Setting up the logging array.
		foreach( $this->grade_order as $grade ) {

			$this->loggingOrderingArray['before']["Grade-{$grade}"] = array();
			$this->loggingOrderingArray['after']["Grade-{$grade}"] = array(
				'awarded' => array(),
				'denied' => array(),
				'wait-list' => array(),
			);

			$this->lotteryGroupingArray[$grade] = array();

			foreach( $submissionStatus_priority_list as $submissionStatus ) {

				//Check if we are processing the wait list
				$processing_wait_list = ($submissionStatus->getId() == 9);

				$this->logging("Searching for {$submissionStatus->getStatus()} Submissions in {$grade} grade.");

				$this->logging('2: ' . memory_get_usage(), time());

				$groupingArray = array(
					4 => array(),
					3 => array(),
					2 => array(),
					1 => array(),
					0 => array(),
					'Empty' => array(),
				);

				$grouping = array(
					'First' => array(),
					'Second' => array(),
					'Third' => array(),
					'Fourth' => array(),
					'Fifth' => array(),
					'Sixth' => array(),
					'Seventh' => array(),
					'Eighth' => array(),
				);

				$groupings = array();
				foreach (array_keys($groupingArray) as $key) {
					$groupings[$key] = $grouping;
				}


				//Grab all Active Submissions
				$submissions = $this->emLookup->getRepository('IIABMagnetBundle:Submission')->createQueryBuilder('s')
					->where('s.openEnrollment = :enrollment')
					->andWhere('s.nextGrade = :grade')
					->andWhere('s.submissionStatus = :status')
					->setParameters(array(
						'enrollment' => $openEnrollment,
						'grade' => $grade,
						'status' => $submissionStatus
					))
					->getQuery()
					->getResult();

				$this->logging('&nbsp;&nbsp;Found: ' . count($submissions));

				/**
				 * Looping over the found Submissions and added them to the group array.
				 * @var int $index
				 * @var Submission $submission
				 */
				foreach ($submissions as $index => $submission) {

					$submission_maybe_waitlist = ($processing_wait_list) ? $submission->getWaitList() : array(false);

					/** @var Waitlist or false $submission_maybe_waitlist */
					foreach ($submission_maybe_waitlist as $waitList) {

						if (($waitList && $waitList->getChoiceSchool() == $submission->getFirstChoice()) ||
							(!$waitList && $submission->getFirstChoice() != null)
						) {

							$committeeScore = $submission->getCommitteeReviewScoreFirstChoice();
							if ($committeeScore == null || $committeeScore == '') {
								$committeeScore = 'Empty';
							}

							$groupingArray[$committeeScore][] = array(
								'submission' => $submission,
								'choice' => $submission->getFirstChoice(),
								'choiceNumber' => 1,
								'lottery' => $submission->getLotteryNumber(),
							);
						}

						if (($waitList && $waitList->getChoiceSchool() == $submission->getSecondChoice()) ||
							(!$waitList && $submission->getSecondChoice() != null)
						) {

							$committeeScore = $submission->getCommitteeReviewScoreSecondChoice();
							if ($committeeScore == null || $committeeScore == '') {
								$committeeScore = 'Empty';
							}

							$groupingArray[$committeeScore][] = array(
								'submission' => $submission,
								'choice' => $submission->getSecondChoice(),
								'choiceNumber' => 2,
								'lottery' => $submission->getLotteryNumber(),
							);
						}

						if (($waitList && $waitList->getChoiceSchool() == $submission->getThirdChoice()) ||
							(!$waitList && $submission->getThirdChoice() != null)
						) {

							$committeeScore = $submission->getCommitteeReviewScoreThirdChoice();
							if ($committeeScore == null || $committeeScore == '') {
								$committeeScore = 'Empty';
							}

							$groupingArray[$committeeScore][] = array(
								'submission' => $submission,
								'choice' => $submission->getThirdChoice(),
								'choiceNumber' => 3,
								'lottery' => $submission->getLotteryNumber(),
							);
						}
					}
				}

				//Before Logging Data Array
				if (empty($this->loggingOrderingArray['before']["Grade-{$grade}"])) {
					$this->loggingOrderingArray['before']["Grade-{$grade}"] = $groupings;
				}

				//Combine the list into one big list with their sort order.
				list($submissionSorted, $logging) = $this->handleSortingPriority($groupingArray, $this->loggingOrderingArray['before']["Grade-{$grade}"]);

				$this->lotteryGroupingArray[$grade] = array_merge( $this->lotteryGroupingArray[$grade], $submissionSorted );
				$this->loggingOrderingArray['before']["Grade-{$grade}"] = array_merge_recursive( $this->loggingOrderingArray['before']["Grade-{$grade}"], $logging );

				$this->logging('7: ' . memory_get_usage(), time());

			}
		}

		return true;
	}
}