<?php
/**
 * Created by PhpStorm.
 * User: michellegivens
 * Date: 3/10/15
 * Time: 11:35 PM
 */

namespace IIAB\MagnetBundle\Command;

use IIAB\MagnetBundle\Entity\Process;
use IIAB\MagnetBundle\Entity\SubmissionStatus;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCommand extends ContainerAwareCommand {

	protected function configure() {

		$this
			->setName( 'magnet:process' )
			->setDescription( 'Process any commands that need to run' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command runs any background processes.

<info>php %command.full_name%</info>

EOF
			);
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		ini_set('memory_limit','2048M');

		$env = $this->getContainer()->get( 'kernel' )->getEnvironment();
		$output->writeln( 'Running Processor in environment: ' . $env );

		$processes = $this->getContainer()->get( 'doctrine' )->getManager()->getRepository( 'IIABMagnetBundle:Process' )->findBy( array(
			'completed' => 0 ,
			'running' => 0 ,
		) , array( 'addDateTime' => 'ASC' ) );

		foreach( $processes as $process ) {

			$event = strtolower( $process->getEvent() );

			$process->setRunning( true );
			$this->getContainer()->get( 'doctrine' )->getManager()->flush();
			$output->writeln( "\tRunning {$event}." );

			switch( $event ) {

				case 'email':
					$output->writeln( 'Running Email Event. Type: ' . ucwords( $process->getType() ) );
					$process = $this->handleEmail( $process );
					break;

				case 'lottery':
					$output->writeln( 'Running Lottery Event. Type: ' . ucwords( $process->getType() ) );
					$process = $this->handleLottery( $process );
					break;

				case 'pdf':
					$output->writeln( 'Running PDF Event. Type: ' . ucwords( $process->getType() ) );
					$process = $this->handlePDF( $process );
					break;

				case 'download':
					$output->writeln( 'Running Download List Event. Type: ' . ucwords( $process->getType() ) );
					$process = $this->handleDownload( $process );
					break;
			}

			$now = new \DateTime();

			$mark_completed = true;
			if( $process->getType() == 'confirmation' ){

				$is_open_period = ( $now > $process->getOpenEnrollment()->getBeginningDate()
									&& $now < $process->getOpenEnrollment()->getEndingDate() );

				$is_late_period = ( $now > $process->getOpenEnrollment()->getLatePlacementBeginningDate()
									&& $now < $process->getOpenEnrollment()->getLatePlacementEndingDate() );

				if( $is_open_period || $is_late_period ){
					$mark_completed = false;
				}
			}

			$process->setRunning( false );
			$process->setCompleted( $mark_completed );
			$process->setCompletedDateTime( $now );
		}

		if( empty( $processes ) ){

			$confirmation_process = $this->getContainer()->get( 'doctrine' )->getManager()->getRepository( 'IIABMagnetBundle:Process' )->findBy( array(
				'completed' => 0 ,
				'running' => 1 ,
				'event' => 'email',
				'type' => 'confirmation'
			) , array( 'addDateTime' => 'ASC' ) );

			foreach( $confirmation_process as $process ){
				$process->setRunning( false );
				$this->getContainer()->get( 'doctrine' )->getManager()->persist($process);
			}
		}

		$this->getContainer()->get( 'doctrine' )->getManager()->flush();
		$output->writeln( 'Completed Processor in envinronment: ' . $env );
	}

	/**
	 * Handles Emails
	 *
	 * @param Process $process
	 *
	 * @return Process
	 */
	private function handleEmail( Process $process ) {

		$type = strtolower( $process->getType() );
		$mailer = $this->getContainer()->get( 'magnet.email' );

		switch( $type ) {

			case 'confirmation':

				$unConfirmedSubmissions = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->findAllUnconfirmed( $process->getOpenEnrollment() );
				foreach( $unConfirmedSubmissions as $submission ){
					$mailer->sendConfirmationEmail( $submission );
				}
				$process->setSubmissionsAffected( $process->getSubmissionsAffected() + count( $unConfirmedSubmissions ) );
				$unConfirmedSubmissions = null;

				$needRecommendations = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )
					->findAllNotSentRecommendationEmail( $process->getOpenEnrollment() );
				foreach( $needRecommendations as $submission ){
					$mailer->sendTeacherRecommendationFormsEmail( $submission );
				}
				$needRecommendations = null;

				$needWritingPrompt = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )
					->findAllNotSentWritingPromptEmail( $process->getOpenEnrollment() );
				foreach( $needWritingPrompt as $submission ){
					$mailer->sendStudentWritingPromptEmail( $submission );
				}
				$needWritingPrompt = null;

				$needScreeningDevice = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )
					->findAllNotSentLearnerScreeningDeviceEmail( $process->getOpenEnrollment() );
				foreach( $needScreeningDevice as $submission ){
					$mailer->sendLearnerScreeningDeviceEmail( $submission );
				}
				$needScreeningDevice = null;

				//findAllResendRecommendationEmails
				$needResend = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )
					->findAllResendRecommendationEmails( $process->getOpenEnrollment() );
				foreach( $needResend as $submission ){
					$mailer->sendTeacherRecommendationFormsEmail( $submission );
				}
				$needResend = null;

				break;

			case 'awarded':
			case 'awarded-wait-list':
				$offeredSubmissions = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Offered' )->findOfferedByOpenEnrollment( $process->getOpenEnrollment() );
				foreach( $offeredSubmissions as $offered ) {
					$mailer->sendAwardedEmail( $offered , $type );
				}
				$process->setSubmissionsAffected( count( $offeredSubmissions ) );
				$offeredSubmissions = null;
				break;

			case 'wait-list';
				$offeredSubmissions = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
					'openEnrollment' => $process->getOpenEnrollment() ,
					'submissionStatus' => 9
				) );
				foreach( $offeredSubmissions as $submission ) {
					$mailer->sendWaitListEmail( $submission );
				}
				$process->setSubmissionsAffected( count( $offeredSubmissions ) );
				$offeredSubmissions = null;
				break;

			case 'denied':
				$deniedSubmissions = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
					'openEnrollment' => $process->getOpenEnrollment() ,
					'submissionStatus' => 3 // status denied due to space
				) );

				$placement = $this->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
					'openEnrollment' => $process->getOpenEnrollment()
				) );

				foreach( $deniedSubmissions as $submission ) {
					$mailer->sendDeniedEmail( $submission , $placement->getNextSchoolYear() , $placement->getNextYear() );
				}
				$process->setSubmissionsAffected( count( $deniedSubmissions ) );
				$deniedSubmissions = null;
				$placement = null;
				break;

            case 'denied-no-transcripts':
                $deniedSubmissions = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
                    'openEnrollment' => $process->getOpenEnrollment() ,
                    'submissionStatus' => 14 // status Inactive Due To No Transcript
                ) );

                $placement = $this->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
                    'openEnrollment' => $process->getOpenEnrollment()
                ) );

                foreach( $deniedSubmissions as $submission ) {
                    $mailer->sendDeniedNoTranscriptsEmail( $submission , $placement->getNextSchoolYear() , $placement->getNextYear() );
                }
                $process->setSubmissionsAffected( count( $deniedSubmissions ) );
                $deniedSubmissions = null;
                $placement = null;
                break;

			case 'next-step':
				$activeSubmissions = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
					'openEnrollment' => $process->getOpenEnrollment() ,
					'submissionStatus' => 1
				) );
				foreach( $activeSubmissions as $submission ) {
					$mailer->sendNextStepEmail( $submission );
				}
				$process->setSubmissionsAffected( count( $activeSubmissions ) );
				$activeSubmissions = null;
				break;
		}

		$type = null;
		$mailer = null;

		return $process;
	}

	/**
	 * Handle Lottery
	 *
	 * @param Process $process
	 *
	 * @return Process
	 * @throws \Exception
	 */
	private function handleLottery( Process $process ) {

		$type = strtolower( $process->getType() );

		switch( $type ) {

			case "process":

				//Check before to see if any processing has already happened for this openEnrollment.
				$offers = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Offered' )->createQueryBuilder( 'o' )
					->where( 'o.openEnrollment = :enrollment' )
					->andWhere( 'o.accepted != 0 OR o.declined != 0' )
					->setParameter( 'enrollment' , $process->getOpenEnrollment() )
					->getQuery()
					->getResult();

				if( count( $offers ) > 0 ) {
					$process->setSubmissionsAffected( 0 );
					return $process;
				}
				$offers = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Offered' )->createQueryBuilder( 'o' )
					->where( 'o.openEnrollment = :enrollment' )
					->setParameter( 'enrollment' , $process->getOpenEnrollment() )
					->getQuery()
					->getResult();

				if( count( $offers ) > 0 ) {
					//Need to reset all offers, wait-list and denied.
					$this->getContainer()->get('doctrine')->getConnection()->query('DELETE FROM `offered` WHERE `openEnrollment` = ' . $process->getOpenEnrollment()->getId() . '; DELETE FROM `waitlist` WHERE `openEnrollment` = ' . $process->getOpenEnrollment()->getId() . '; UPDATE `submission` SET `submissionstatus` = 1 WHERE `submissionstatus` IN (3,6,9) AND `openEnrollment` = ' . $process->getOpenEnrollment()->getId() . '; DELETE FROM `afterplacementpopulation` WHERE `openEnrollment` = ' . $process->getOpenEnrollment()->getId() );
				}
				$offers = null;

				$total = $this->getContainer()->get( 'magnet.lottery' )->run_Lottery([
            			'lottery_type' => 'normal',
            			'clear_old_outcomes' => true,
        			],
        			$process->getOpenEnrollment()
        		);
				$process->setSubmissionsAffected( $total );
				break;

			case "wait-list":
				//runWaitList
				$waitList = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:WaitList' )->createQueryBuilder( 'w' )
					->where( 'w.openEnrollment = :enrollment' )
					->setParameter( 'enrollment' , $process->getOpenEnrollment() )
					->getQuery()
					->getResult();

				if( count( $waitList ) == 0 ) {
					$process->setSubmissionsAffected( 0 );
					return $process;
				}
				$waitList = null;

				$total = $this->getContainer()
					->get( 'magnet.lottery' )
					->run_Lottery([
            			'lottery_type' => 'waitlist',
            			'clear_old_outcomes' => true,
        			],
        			$process->getOpenEnrollment()
        		);
                $process->setSubmissionsAffected( $total );
				break;

			case "late-period":

				$submissions = (int)$this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
					->where( 's.openEnrollment = :enrollment')
					->andWhere( 's.submissionStatus = 1')
					->setParameter( 'enrollment' , $process->getOpenEnrollment() )
					->select( 'count( s.id )')
					->getQuery()
					->getSingleScalarResult();

				$waitList = (int)$this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:WaitList' )->createQueryBuilder( 'w' )
					->where( 'w.openEnrollment = :enrollment' )
					->setParameter( 'enrollment' , $process->getOpenEnrollment() )
					->select( 'count( w.id )')
					->getQuery()
					->getSingleScalarResult();

				if( !$submissions && !$waitList ){
					$process->setSubmissionsAffected( 0 );
					return $process;
				}

				$submissions = null;
				$waitList = null;

				$total = $this->getContainer()->get( 'magnet.lottery' )->run_Lottery([
            			'lottery_type' => 'late',
            			'clear_old_outcomes' => true,
        			],
        			$process->getOpenEnrollment()
        		);

				$process->setSubmissionsAffected( $total );
				break;

			case "commit":
				$total = $this->getContainer()->get( 'magnet.lottery' )->commit_Lottery( $process->getOpenEnrollment() );

				$process->setSubmissionsAffected( $total );
				break;
		}

		return $process;
	}

	/**
	 * Handle Download List
	 *
	 * @param Process $process
	 *
	 * @return Process
	 * @throws \Exception
	 */
	private function handleDownload( Process $process ) {

		$type = strtolower( $process->getType() );

		if( in_array( $type, array( 'simple-list', 'lottery-list', 'wait-list', 'late-period-list' ) ) ) {

			$this->getContainer()->get('magnet.lottery')->download_list($type, $process->getOpenEnrollment());

		}
		return $process;
	}

	/**
	 * Handles all the PDF Functions.
	 * @param Process $process
	 *
	 * @return Process
	 */
	private function handlePDF( Process $process ) {

		$type = strtolower( $process->getType() );

		switch( $type ) {

			case "awarded":
			case "awarded-wait-list":
				$fileLocation = $this->getContainer()->get('magnet.pdf')->awardedReport( $process->getOpenEnrollment() , $type );
				break;

			case "wait-list":
				$fileLocation = $this->getContainer()->get('magnet.pdf')->waitListReport( $process->getOpenEnrollment() );
				break;

			case "denied":
				$fileLocation = $this->getContainer()->get('magnet.pdf')->deniedReport( $process->getOpenEnrollment() );
				break;

            case "denied-no-transcripts":
                $fileLocation = $this->getContainer()->get('magnet.pdf')->deniedNoTranscriptsReport( $process->getOpenEnrollment() );
                break;

			case "next-step":
				$fileLocation = $this->getContainer()->get('magnet.pdf')->nextStepLetterReport( $process->getOpenEnrollment() );
				break;
		}

		return $process;
	}
}