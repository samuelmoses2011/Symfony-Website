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
use IIAB\MagnetBundle\Service\PopulationService;

class AutoDeclineOfferedCommand extends ContainerAwareCommand {

	protected function configure() {

		$this
			->setName( 'magnet:auto:decline' )
			->setDescription( 'Auto declines any offered submission' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command gets all offered submissions and auto declined them due to the the last date/time to accept.

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
		$offeredAndDeclined = $em->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 8 );
		$needToEmail = array();

		foreach( $placements as $placement ) {

			$offered = $em->getRepository( 'IIABMagnetBundle:Offered' )->createQueryBuilder( 'o' )
				->where( 'o.onlineEndTime < :now' )
				->andWhere( 'o.openEnrollment = :enrollment' )
				->andWhere( 'o.accepted = 0 AND o.declined = 0' )
				->setParameter( 'now' , $now )
				->setParameter( 'enrollment' , $placement->getOpenEnrollment() )
				->getQuery()
				->getResult();

			if( count( $offered ) > 0 ) {

				$output->writeln( '<fg=red>Found Offered </fg=red>: ' . count( $offered ) );
				$counter = 0;

				$population_service = new PopulationService( $this->getContainer()->get('doctrine') );

				/** @var \IIAB\MagnetBundle\Entity\Offered $offer */
				foreach( $offered as $offer ) {

					$population_service->decline([
	                    'submission'=>$offer->getSubmission(),
	                    'school' => $offer->getAwardedSchool(),
	                ]);

					if( $offer->getSubmission()->getSubmissionStatus()->getId() != 6 ) {
						throw new \Exception( 'Offer has no been accepted/declined but Submission Status is not Offered. Submission ID: ' . $offer->getSubmission()->getId() );
					}

					try {

						$afterPopulation = $em->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
							'magnetSchool' => $offer->getAwardedSchool() ,
							'openEnrollment' => $offer->getOpenEnrollment() ,
						) , array( 'lastUpdatedDateTime' => 'DESC' ) );

						if( $afterPopulation != null ) {

							$offer->setAccepted( 0 );
							$offer->setDeclined( 1 );
							$offer->setChangedDateTime( new \DateTime() );
							$offer->setAcceptedBy( 'auto' );
							$offer->getSubmission()->setSubmissionStatus( $offeredAndDeclined );

							$race = strtoupper( $offer->getSubmission()->getRaceFormatted() );
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
						} else {
							//throw new \Exception('Offer does not have an After Placement Data. Something is wrong');
						}




					} catch( \Exception $ex ) {
						throw $ex;
					}

					$counter++;
					$needToEmail[] = $offer;
				}

				try {
					//Need to ensure the database is updated before we try and email out.
					$em->flush();

				} catch( \Exception $ex ) {
					throw $ex;
				}

				$output->writeln( '<fg=green>Updated</fg=green>: ' . $counter . ' Submissions' );
				$counter = null;

			} else {
				$output->writeln( sprintf( '<fg=green>Not Offers found for Enrollment %s.</fg=green>' , $placement->getOpenEnrollment()->__toString() ) );
			}
			$offered = null;
		}

		$population_service->persist_and_flush();
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