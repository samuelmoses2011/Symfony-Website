<?php
/**
 * Company: Image In A Box
 * Date: 3/12/15
 * Time: 9:12 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetZonedSchoolCommand extends ContainerAwareCommand {

	protected function configure() {


		$this
			->setName( 'magnet:set:zoned-school' )
			->setDescription( 'Sets the Zoned School fo all Submissions within a given Open Enrollment' )
			->addOption( 'enrollment' , null , InputOption::VALUE_OPTIONAL , 'Set Zoned Schools for a specific Open Enrollment.' , '' , 0 )
			->setHelp( <<<EOF
The <info>%command.name%</info> --enrollment=1

<info>php %command.full_name%</info>
<info>php %command.full_name%</info> --enrollment=1

EOF
			);
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		$enrollment = $input->getOption( 'enrollment' );
		$em = $this->getContainer()->get( 'doctrine' );

		if( $enrollment == 0 ) {
			$output->writeln( 'Running "Set Zoned Schools for <fg=green>all</fg=green> Enrollments".' );
			$openEnrollments = $em->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findAll();

		} else {
			$output->writeln( 'Running "Set Zoned Schools for <fg=green>' . $enrollment . '</fg=green> Enrollment".' );
			$openEnrollments = $em->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->find( $enrollment );
			if( $openEnrollments != null ) {
				$openEnrollments = array( $openEnrollments );
			} else {
				$openEnrollments = array();
			}
		}

		foreach( $openEnrollments as $openEnrollment ) {

			$output->writeln( "\r\n\tBeginning update on <fg=yellow>" . $openEnrollment . "</fg=yellow>." );

			$submissionsThatNeedUpdating = $em->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->where( 's.zonedSchool IS NULL' )
				->andWhere( 's.openEnrollment = :enrollment' )
				->andWhere( 's.submissionStatus = 1' )
				->setParameter( 'enrollment' , $openEnrollment )
				->getQuery()
				->getResult();

			$output->writeln("\t\tFound <fg=red>" . count( $submissionsThatNeedUpdating ) . "</fg=red> submissions missing zoning data" );

			$updated = 0;

			foreach( $submissionsThatNeedUpdating as $submission ) {

				$studentArray = array(
					'address' => $submission->getAddress(),
					'zip' => $submission->getZip(),
					'student_status' => 'new'
				);

				$addressBound = $this->getContainer()->get('magnet.check.address')->checkAddress( $studentArray );

				if( $addressBound != false ) {
					switch( $submission->getNextGrade() ) {
						case 6:
						case 7:
						case 8:
							$schoolName = $addressBound->getMSBND();
							break;

						case 9:
						case 10:
						case 11:
						case 12:
							$schoolName = $addressBound->getHSBND();
							break;

						default:
							$schoolName = $addressBound->getESBND();
							break;
					}
					$submission->setZonedSchool( $schoolName );
					$em->getManager()->persist( $submission );
					$updated++;
				}
			}
			$em->getManager()->flush();

			$output->writeln( "\tFinished update on <fg=yellow>{$openEnrollment}</fg=yellow>. Updated a total of <fg=green>{$updated}</fg=green> submissions.\r\n" );
		}
	}
}