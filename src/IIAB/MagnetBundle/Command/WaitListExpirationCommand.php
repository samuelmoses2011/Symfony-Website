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

class WaitListExpirationCommand extends ContainerAwareCommand {

	protected function configure() {

		$this
			->setName( 'magnet:auto:wait-list-expire' )
			->setDescription( 'Auto expires the wait list' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command gets all wait list items and remove them from the database and updates the entity if it is in Wait List.

<info>php %command.full_name%</info>

EOF
			);
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		$env = $this->getContainer()->get( 'kernel' )->getEnvironment();
		$output->writeln( 'Running Auto Decliner in environment: ' . $env );

		$em = $this->getContainer()->get( 'doctrine' )->getManager();

		$placements = $em->getRepository( 'IIABMagnetBundle:Placement' )->findAll();
		$now = new \DateTime();

		//"Offered and Declined" Status
		$deniedDueToSpace = $em->getRepository('IIABMagnetBundle:SubmissionStatus')->find( 2 );
		$needToEmail = array();

		foreach( $placements as $placement ) {

			if( $placement->getWaitListExpireTime() != null && $placement->getWaitListExpireTime() < $now ) {

				$waitListed = $em->getRepository('IIABMagnetBundle:WaitList')->createQueryBuilder('w')
					->where('w.openEnrollment = :enrollment' )
					->setParameter( 'enrollment' , $placement->getOpenEnrollment() )
					->getQuery()
					->getResult();

				if( count( $waitListed ) > 0 ) {
					$counter = 0;

					/** @var \IIAB\MagnetBundle\Entity\WaitList $waitList */
					foreach( $waitListed as $waitList ) {

						// Wait Listed Submission only.
						if( $waitList->getSubmission()->getId() == 9 ) {
							//Only set Wait Listed Submission to Denied Due to Space.
							$waitList->getSubmission()->setSubmissionStatus( $deniedDueToSpace );
						}

						//Data Clean up to removed old Wait Listed
						$waitList->getSubmission()->removeWaitList( $waitList );
						$waitList->setSubmission( null );
						$em->remove( $waitList );

						$counter++;
					}

					try {
						//Need to ensure the database is updated before we try and email out.
						$em->flush();

					} catch( \Exception $ex ) {
						throw $ex;
					}

					$output->writeln( '<fg=green>Updated</fg=green>: ' . $counter . ' Wait Listed Submissions' );
					$counter = null;

				} else {
					$output->writeln( '<fg=green>Not Wait List found.</fg=green>' );
				}
				$waitListed = null;
			}
		}

		//Email all the offers that got updated.
		foreach( $needToEmail as $offered ) {
			$this->getContainer()->get( 'magnet.email' )->sendAutoDeclinedEmail( $offered );
		}

		$em = null;
		$needToEmail = null;
		$offeredAndDeclined = null;
		$placements = null;
		$now = null;

		$output->writeln( 'Finished Auto Decliner in environment: ' . $env );
	}


}