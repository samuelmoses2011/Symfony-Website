<?php
/**
 * Created by PhpStorm.
 * User: michellegivens
 * Date: 3/10/15
 * Time: 11:35 PM
 */

namespace IIAB\MagnetBundle\Command;

use IIAB\MagnetBundle\Entity\SubmissionData;
use IIAB\MagnetBundle\Service\StudentProfileService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class calculateGPACommand extends ContainerAwareCommand {

    public $output = null;
    public $students = [];
    public $problem_students = [];

	protected function configure() {

		$this
			->setName( 'magnet:student:grades:gpa' )
			->setDescription( 'Calculate GPA for all submissions' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command calculates the GPA for all submissions.

<info>php %command.full_name%</info>

EOF
			);
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		ini_set('memory_limit','2048M');

		$this->output = $output;

		$env = $this->getContainer()->get( 'kernel' )->getEnvironment();
		$output->writeln( 'Start Calculations: ' . $env );

        $em = $this->getContainer()->get( 'doctrine' )->getManager();

        $submissions = $em->getRepository( 'IIABMagnetBundle:Submission' )->findAll();
        $gpa_service = $this->getContainer()->get( 'magnet.calculategpa' );

        $count = 0;
        foreach( $submissions as $submission ){

            if( $submission->doesRequire('calculated_gpa') ){
                $subData = $submission->getAdditionalDataByKey('calculated_gpa');

                $gpa = $gpa_service->calculateGPA($submission);

                if( $gpa != null ){

                    if( empty( $subData ) ) {
                        $subData = new SubmissionData();
                        $subData->setMetaKey('calculated_gpa');
                        $subData->setSubmission($submission);
                    }

                    $subData->setMetaValue($gpa);
                    $submission->addAdditionalDatum($subData);

                    $em->persist($subData);
                    $em->persist($submission);
                    $count++;
                }
            }

            if( $submission->doesRequire('student_profile') ){

                $subData = $submission->getAdditionalDataByKey('student_profile_score');
                $percentData = $submission->getAdditionalDataByKey('student_profile_percentage');

                $studentProfileService = new StudentProfileService( $submission, $em );
                $scores = $studentProfileService->getProfileScores();

                if( !empty( $scores ) ){

                    if( empty( $subData ) ){
                        $subData = new SubmissionData();
                        $subData->setMetaKey('student_profile_score');
                        $subData->setSubmission( $submission );
                    }
                    $subData->setMetaValue( ( !empty( $scores ) ) ? $scores['total'] : null );
                    $submission->addAdditionalDatum($subData);

                    $em->persist($subData);

                    if( empty( $percentData ) ){
                        $percentData = new SubmissionData();
                        $percentData->setMetaKey('student_profile_percentage');
                        $percentData->setSubmission( $submission );
                    }
                    $percentData->setMetaValue( ( !empty( $scores ) ) ? $scores['percentage'] : null );
                    $submission->addAdditionalDatum($percentData);

                    $em->persist($percentData);
                    $em->persist($submission);
                }
            }
        }
        $em->flush();
        $output->writeln( 'Completed: ' . $env );
	}
}
