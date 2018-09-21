<?php
/**
 * Company: Image In A Box
 * Date: 1/13/15
 * Time: 5:45 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ResendEmailCommand extends ContainerAwareCommand {

	protected function configure() {

		$this
			->setName( 'magnet:send:email' )
			->setDescription( 'Resends a specific email for a submisison' )
			->addArgument( 'submission' , InputArgument::REQUIRED , 'The submission you want to send about.' )
			->addArgument( 'template' , InputArgument::REQUIRED , 'The email you want to send. Confirmation only works.' )
			->addOption( 'nextSchoolYear' , '' , InputOption::VALUE_OPTIONAL , 'Only for Denied Emails')
			->addOption( 'nextYear' , '' , InputOption::VALUE_OPTIONAL , 'Only for Denied Emails');

	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		$submissionIds = explode( ',', $input->getArgument( 'submission' ) );
		$templateName = $input->getArgument( 'template' );

		if( empty( $templateName ) || empty( $submissionIds ) ) {
			$output->writeln( 'Missing Required fields. Requires Submission ID and template name' );
			return;
		}

		$mailer = $this->getContainer()->get( 'magnet.email' );
		foreach(  $submissionIds as $submissionId) {
			$submission = $this->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:Submission')->find( $submissionId );

			if ($submission != null) {
				$email = $submission->getParentEmail();
				if (!empty($email)) {

					if ($templateName == 'confirmation') {

						$mailer->sendConfirmationEmail( $submission );
					}

					if ($templateName == 'awarded') {

						$output->writeln('Sending ' . $templateName . ' for submission ID: ' . $submission);

						$offered = $this->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:Offered')->findOneBy(array('submission' => $submission));
						if ($offered != null) {
							$response = $this->getContainer()->get('magnet.email')->sendAwardedEmail($offered);

							$output->writeln('Sent Response: ' . $response);
						} else {
							$output->writeln('No awarded Offered Found for: ' . $submission);
						}
					}

					if ($templateName == 'accepted') {

						$output->writeln('Sending ' . $templateName . ' for submission ID: ' . $submission);

						$offered = $this->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:Offered')->findOneBy(array('submission' => $submission));
						if ($offered != null) {
							$response = $this->getContainer()->get('magnet.email')->sendAcceptedEmail($offered);

							$output->writeln('Sent Response: ' . $response);
						} else {
							$output->writeln('No awarded Offered Found for: ' . $submission);
						}
					}

					if ($templateName == 'declined') {

						$output->writeln('Sending ' . $templateName . ' for submission ID: ' . $submission);

						$offered = $this->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:Offered')->findOneBy(array('submission' => $submission));
						if ($offered != null) {
							$response = $this->getContainer()->get('magnet.email')->sendDeclinedEmail($offered);

							$output->writeln('Sent Response: ' . $response);
						} else {
							$output->writeln('No awarded Offered Found for: ' . $submission);
						}
					}

					if ($templateName == 'waitlist') {

						$output->writeln('Sending ' . $templateName . ' for submission ID: ' . $submission);

						$response = $this->getContainer()->get('magnet.email')->sendWaitListEmail($submission);
						$output->writeln('Sent Response: ' . $response);

					}

					if ($templateName == 'declined-waitlist') {

						$output->writeln('Sending ' . $templateName . ' for submission ID: ' . $submission);

						$response = $this->getContainer()->get('magnet.email')->sendDeclinedWaitListEmail($submission);
						$output->writeln('Sent Response: ' . $response);

					}

					if ($templateName == 'denied') {

						$output->writeln('Sending ' . $templateName . ' for submission ID: ' . $submission);

						$placement = $this->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:Placement')->findOneBy(array(
							'openEnrollment' => $submission->getOpenEnrollment()
						));

						$nextSchoolYear = $placement->getNextSchoolYear();
						$nextYear = $placement->getNextYear();

						$response = $this->getContainer()->get('magnet.email')->sendDeniedEmail($submission, $nextSchoolYear, $nextYear);
						$output->writeln('Sent Response: ' . $response);

					}

					if ($templateName == 'next-step') {

						$output->writeln('Sending ' . $templateName . ' for submission ID: ' . $submission);

						$response = $this->getContainer()->get('magnet.email')->sendNextStepEmail($submission);
						$output->writeln('Sent Response: ' . $response);

					}
				} else {
					$output->writeln('Submissing does not have an email address on file. Please fix');
				}
			} else {
				$output->writeln('Submission: ' . $submissionId . ' not found.');
			}
		}
	}
}