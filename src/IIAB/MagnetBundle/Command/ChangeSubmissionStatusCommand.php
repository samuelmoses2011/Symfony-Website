<?php
/**
 * Company: Image In A Box
 * Date: 08/09/16
 * Time: 12:45 PM
 * Copyright: 2016
 */

namespace IIAB\MagnetBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use IIAB\MagnetBundle\Entity\Offered;
use PDO;

class ChangeSubmissionStatusCommand extends ContainerAwareCommand {

	private $file;

	/** @var PDO */
	private $pdo;

	/**
	 * @inheritdoc
	 */
	protected function configure() {

		$this
			->setName( 'magnet:update:submission:status' )
			->setDescription( 'Updates submission status' )
			->addOption( 'submission' , null , InputOption::VALUE_REQUIRED , 'submission to change' )
			->addOption( 'status' , null , InputOption::VALUE_REQUIRED , 'desired status' )
			->addOption( 'school' , null , InputOption::VALUE_OPTIONAL , 'affected school' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command changes the submission status and adjusts the afterplacementpopulation correctly

<info>php %command.full_name% --submission=submission_id --status=submission_status_id --school=school_id</info>

EOF
			);
	}

	/**
	 * @inheritdoc
	 */
	protected function execute( InputInterface $input , OutputInterface $output ) {

		$submission = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->findOneBy([
			'id' => $input->getOption( 'submission' )
		]);

		if( empty( $submission ) ){
			$output->writeln( '<fg=red>Cannot find Submission ' . $input->getOption( 'submission' ) . '</fg=red>' );
			die;
		}

		$new_submission_status = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findOneBy([
			'id' => $input->getOption( 'status' )
		]);

		if( empty( $new_submission_status ) ){
			$output->writeln( '<fg=red>Cannot find Submission Status ' . $input->getOption( 'status' ) . '</fg=red>' );
			die;
		}
		$new_submission_status_id = $new_submission_status->getId();

		if( $input->getOption('school') ){
			$magnet_school = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findOneBy([
				'id' => $input->getOption( 'school' )
			]);

			if( empty( $magnet_school ) ){
				$output->writeln( '<fg=red>Cannot find Magnet School ' . $input->getOption( 'school' ) . '</fg=red>' );
				die;
			}

		}
		$magnet_school_id = ( isset( $magnet_school ) ) ? $magnet_school->getId() : 0;
		
		$current_submission_status = $submission->getSubmissionStatus();
		$current_submission_status_id = ( isset( $current_submission_status ) ) ? $current_submission_status->getId() : 0;

		$offer = $submission->getOffered();

		if( empty( $offer ) ) {
			$current_offered_school_id = 0;
		} else {
			$current_offered_school = $submission->getOffered()->getAwardedSchool();
			$current_offered_school_id = (isset($current_offered_school)) ? $current_offered_school->getId() : 0;
		}

		if( in_array( $new_submission_status_id, [6,7,8] ) && !$current_offered_school_id && !$magnet_school_id ){
			$output->writeln( '<fg=red>Trying to change to status that requires an Offer, but no magnet school provided</fg=red>' );
			die;
		}

		$em = $this->getContainer()->get( 'doctrine' )->getManager();

		if( in_array($new_submission_status_id, [6,7,8] ) ){

			if( $magnet_school_id && $magnet_school_id != $current_offered_school_id ){

				if( empty( $offer ) ){
					$offer = new Offered();
				}

				$url = $submission->getId() . '.' . rand(10, 999);

				$online_end_time =  new \DateTime( 'midnight +9 days' );
				$offline_end_time = new \DateTime( '16:00 +8 days' );
				$now = new \DateTime();

				$offer->setOpenEnrollment($submission->getOpenEnrollment());
				$offer->setSubmission($submission);
				$offer->setAwardedSchool($magnet_school);
				$offer->setUrl($url);
				$offer->setOnlineEndTime( $online_end_time );
				$offer->setOfflineEndTime( $offline_end_time );

				if( $new_submission_status_id = 7 ){
					$offer->setAccepted( 1 );
					$offer->setdeclined( 0 );
					$offer->setAcceptedBy( 'phone' );
					$offer->setChangedDateTime( $now );
				} else if( $new_submission_status_id = 8 ){
					$offer->setAccepted( 0 );
					$offer->setdeclined( 1 );
					$offer->setAcceptedBy( 'phone' );
					$offer->setChangedDateTime( $now );
				}

				$em->persist($offer);
			}
		}


		if(
			in_array( $current_submission_status_id, [6,7] ) && // Currently Offered or Offered and Accepted

			(
				!in_array( $new_submission_status_id, [6,7] ) || // Changing to non-offered status
				( $magnet_school_id && $magnet_school_id != $current_offered_school_id ) // Changing offered school
			)
		){

			$race = strtoupper( $submission->getRaceFormatted() );
			if( isset($current_offered_school) ) {
				$this->minusSlotForPopulation($submission, $current_offered_school, $race);
			}
		}



		if(
			in_array( $new_submission_status_id, [6,7] ) && // Changing status to Offered or Offered and Accepted
			(
				!in_array( $current_submission_status_id, [6,7] ) || // Changing from non-offered status
				( $magnet_school_id && $magnet_school_id != $current_offered_school_id ) // Changing offered school
			)
		){
			$race = strtoupper( $submission->getRaceFormatted() );
			$this->recordManualAwardPopulationChange( $submission , $magnet_school , $race );
		}

		$submission->setSubmissionStatus( $new_submission_status );
		$em->persist( $submission );

		$em->flush();
		die;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function interact( InputInterface $input , OutputInterface $output ) {

		$dialog = $this->getHelper( 'dialog' );
		foreach( $input->getOptions() as $option => $value ) {
			if( $value === null ) {
				$input->setOption( $option , $dialog->ask( $output ,
					sprintf( '<question>%s</question>: ' , ucfirst( $option ) )
				) );
			}
		}
	}

	/**
	 * Subtract an race slot from an After Population.
	 *
	 * @param Submission $submission
	 * @param MagnetSchool $magnet_school
	 * @param string $race
	 */
	private function minusSlotForPopulation( $submission , $magnet_school, $race ) {

		$em = $this->getContainer()->get( 'doctrine' )->getManager();

		$afterPopulation = $em->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
			'magnetSchool' => $magnet_school ,
			'openEnrollment' => $submission->getOpenEnrollment() ,
		) , array( 'lastUpdatedDateTime' => 'DESC' ) );

		switch( $race ) {
			case 'WHITE':
				$newWhite = $afterPopulation->getCPWhite();
				$newWhite--;
				$afterPopulation->setCPWhite( $newWhite );
				break;

			case 'BLACK':
				$newBlack = $afterPopulation->getCPBlack();
				$newBlack--;
				$afterPopulation->setCPBlack( $newBlack );
				break;

			default:
				$newOther = $afterPopulation->getCPOther();
				$newOther--;
				$afterPopulation->setCPOther( $newOther );
				break;
		}

		$em->persist( $afterPopulation );

	}

	/**
	 * Records the Manual awarding of a submission in the Current/After population.
	 *
	 * @param $submission
	 * @param $magnetSchool
	 * @param $race
	 */
	private function recordManualAwardPopulationChange( $submission , $magnetSchool , $race ) {

		$em = $this->getContainer()->get( 'doctrine' )->getManager();

		$afterPopulation = $em->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
			'magnetSchool' => $magnetSchool ,
			'openEnrollment' => $submission->getOpenEnrollment() ,
		) , array( 'lastUpdatedDateTime' => 'DESC' ) );

		//If the afterPopulation is Null, lets add one in.
		if( $afterPopulation == null ) {

			$currentPopulation = $em->getRepository('IIABMagnetBundle:CurrentPopulation' )->findOneBy( array(
				'magnetSchool' => $magnetSchool ,
				'openEnrollment' => $submission->getOpenEnrollment()
			) );

			$afterPopulation = new AfterPlacementPopulation();
			$afterPopulation->setOpenEnrollment( $submission->getOpenEnrollment() );
			$afterPopulation->setMagnetSchool( $magnetSchool );
			$afterPopulation->setCPBlack( $currentPopulation->getCPBlack() );
			$afterPopulation->setCPWhite( $currentPopulation->getCPWhite() );
			$afterPopulation->setCPOther( $currentPopulation->getCPSumOther() );
			$afterPopulation->setLastUpdatedDateTime( new \DateTime() );
			$afterPopulation->setMaxCapacity( $currentPopulation->getMaxCapacity() );

			$em->persist( $afterPopulation );
		}

		switch( $race ) {
			case 'WHITE':
				$newWhite = $afterPopulation->getCPWhite();
				$newWhite++;
				$afterPopulation->setCPWhite( $newWhite );
				break;

			case 'BLACK':
				$newBlack = $afterPopulation->getCPBlack();
				$newBlack++;
				$afterPopulation->setCPBlack( $newBlack );
				break;

			default:
				$newOther = $afterPopulation->getCPOther();
				$newOther++;
				$afterPopulation->setCPOther( $newOther );
				break;
		}

		$em->persist( $afterPopulation );
	}
	
}