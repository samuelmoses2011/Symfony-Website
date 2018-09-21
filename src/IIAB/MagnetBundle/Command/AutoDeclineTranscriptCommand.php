<?php
/**
 * Company: Image In A Box
 * Date: 3/31/15
 * Time: 11:35 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AutoDeclineTranscriptCommand extends ContainerAwareCommand {

	protected function configure() {

		$this
			->setName( 'magnet:auto:decline-transcript' )
			->setDescription( 'Auto declines any on hold wait for transcript submission' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command gets all on hold wait for transcript submissions and auto declined them due to the cut off date/time.

<info>php %command.full_name%</info>

EOF
			);
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		$env = $this->getContainer()->get( 'kernel' )->getEnvironment();
		$output->writeln( 'Running Auto Decliner-Transcript in environment: ' . $env );

		$em = $this->getContainer()->get( 'doctrine' )->getManager();

		$placements = $em->getRepository( 'IIABMagnetBundle:Placement' )->findAll();

		$now = new \DateTime();

		foreach( $placements as $placement ) {

			if( $placement->getTranscriptDueDate() < $now ) {

				$late_period_expired =  ( $placement->getOpenEnrollment()->getLatePlacementEndingDate() < $now );

				$submissions = $em->getRepository( 'IIABMagnetBundle:Submission' )->findBy( [
					'openEnrollment' => $placement->getOpenEnrollment() ,
					'submissionStatus' => 5 //On Hold for additional Information.
				] );

				if( count( $submissions ) > 0 ) {

					$output->writeln( sprintf( '<fg=red>Found %d Submission for Enrollment: %s</fg=red>' , count( $submissions ) , $placement->getOpenEnrollment()->__toString() ) );
					$counter = 0;

					//"Inactive Due To No Transcript" Status
					$inactiveDueToNoTranscript = $em->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 14 );

					/** @var \IIAB\MagnetBundle\Entity\Submission $submission */
					foreach( $submissions as $submission ) {

						try {

							$late_submission = ( $placement->getOpenEnrollment()->getLatePlacementBeginningDate() < $submission->getCreatedAt() && $placement->getOpenEnrollment()->getLatePlacementEndingDate() > $submission->getCreatedAt() );

							if( !$late_submission || $late_period_expired ){
								$submission->setSubmissionStatus( $inactiveDueToNoTranscript );
								$counter++;
							}
						} catch( \Exception $ex ) {
							throw $ex;
						}
					}

					try {
						//Need to ensure the database is updated before we try and email out.
						$em->flush();

					} catch( \Exception $ex ) {
						throw $ex;
					}

					$output->writeln( sprintf( '<fg=green>Updated %d Submissions for Enrollment: %s</fg=green>' , $counter , $placement->getOpenEnrollment()->__toString() ) );
					$counter = null;

				} else {
					$output->writeln( sprintf( '<fg=green>No Submission found for Enrollment: %s</fg=green>' , $placement->getOpenEnrollment()->__toString() ) );
				}

				$inactiveDueToNoTranscript = null;
				$submissions = null;
			} else {
				$output->writeln( sprintf( '<fg=red>Current Transcript Due Date is %s. Now is %s. Nothing ran for Enrollment: %s</fg=red>' , $placement->getTranscriptDueDate()->format('Y-m-d') , $now->format('Y-m-d') , $placement->getOpenEnrollment()->__toString() ) );
			}
		}

		$em = null;
		$placements = null;

		$output->writeln( 'Finished Auto Decliner-Transcript in environment: ' . $env );
	}
}