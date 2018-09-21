<?php
/**
 * Company: Image In A Box
 * Date: 1/20/15
 * Time: 11:19 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Command;


use IIAB\MagnetBundle\Service\EligibilityRequirementsService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckEligibilityCommand extends ContainerAwareCommand {

	/**
	 * @inheritdoc
	 */
	protected function configure() {

		$this
			->setName( 'magnet:check:eligibility' )
			->setDescription( 'Checks a submission to see if it passes eligibility.' )
			->addArgument( 'submission' , null , InputArgument::REQUIRED , 'Submission ID you want to test.' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command checks the eligibility of a specific submission.

<info>php %command.full_name% ###</info>

<info>php %command.full_name% --submission=###</info>

EOF
			);
	}

	/**
	 * @inheritdoc
	 */
	protected function execute( InputInterface $input , OutputInterface $output ) {

		$submissionID = $input->getArgument( 'submission' );

		$submission = $this->getContainer()->get('doctrine')->getManager()->getRepository('IIABMagnetBundle:Submission')->find( $submissionID );

		if( $submission == null ) {
			$output->writeln( '<fg=red>Submission not found. Please try another submission.</fg=red>' );
			return;
		}

		$student = array(
			'submissionID' => $submission->getId()
		);

		$eligibilityRequirementsServices = new EligibilityRequirementsService(
			$this->getContainer()->get('doctrine.orm.default_entity_manager') );

		if( $submission->getFirstChoice() != null ) {
			list( $response , $grade , $courseTitle , $eligibilityCheck ) = $eligibilityRequirementsServices->doesStudentPassRequirements( $student , $submission->getFirstChoice() );
			if( $response ) {
				$output->writeln( '<fg=green>Student passes Eligibility for ' . $submission->getFirstChoice() . '</fg=green>' );
			} else {
				$output->writeln( '<fg=red>Student does not pass Eligibility for ' . $submission->getFirstChoice() . '</fg=red>' );
			}
		} else {
			$output->writeln( 'First Choice is empty.' );
		}
		if( $submission->getSecondChoice() != null ) {
			list( $response , $grade , $courseTitle , $eligibilityCheck ) = $eligibilityRequirementsServices->doesStudentPassRequirements( $student , $submission->getSecondChoice() );
			if( $response ) {
				$output->writeln( '<fg=green>Student passes Eligibility for ' . $submission->getSecondChoice() . '</fg=green>' );
			} else {
				$output->writeln( '<fg=red>Student does not pass Eligibility for ' . $submission->getSecondChoice() . '</fg=red>' );
			}
		} else {
			$output->writeln( 'Second Choice is empty.' );
		}
		if( $submission->getThirdChoice() != null ) {
			list( $response , $grade , $courseTitle , $eligibilityCheck ) = $eligibilityRequirementsServices->doesStudentPassRequirements( $student , $submission->getThirdChoice() );
			if( $response ) {
				$output->writeln( '<fg=green>Student passes Eligibility for ' . $submission->getThirdChoice() . '</fg=green>' );
			} else {
				$output->writeln( '<fg=red>Student does not pass Eligibility for ' . $submission->getThirdChoice() . '</fg=red>' );
			}
		} else {
			$output->writeln( 'Third Choice is empty.' );
		}
	}
}