<?php
/**
 * Created by PhpStorm.
 * User: michellegivens
 * Date: 3/10/15
 * Time: 11:35 PM
 */

namespace IIAB\MagnetBundle\Command;

use IIAB\MagnetBundle\Entity\SubmissionGrade;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class applyGradesCommand extends ContainerAwareCommand {

    public $output = null;
    public $students = [];
    public $problem_students = [];

	protected function configure() {

		$this
			->setName( 'magnet:student:grades:apply' )
			->setDescription( 'move student grades to submissions' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command imports student grades from the iNow dump file.

<info>php %command.full_name%</info>

EOF
			);
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		ini_set('memory_limit','2048M');

		$this->output = $output;

		$env = $this->getContainer()->get( 'kernel' )->getEnvironment();
		$output->writeln( 'Start Migration: ' . $env );

        $em = $this->getContainer()->get( 'doctrine' )->getManager();

        $submissions = $em->getRepository( 'IIABMagnetBundle:Submission' )->findAll();

        $record_count = 0;
        foreach( $submissions as $submission ){

            if( !empty( $submission->getStateId() ) ){

                $sub_grades = $submission->getGrades();

                $grades = $em->getRepository( 'IIABMagnetBundle:StudentGrade' )->findBy([
                    'stateID' => $submission->getStateId()
                ]);

                foreach( $grades as $grade ){
                    $needs_grade = true;
                    foreach( $sub_grades as $sub_grade ){
                        if( $sub_grade->getAcademicYear() == $grade->getAcademicYear()
                            && $sub_grade->getAcademicTerm() == $grade->getAcademicTerm()
                            && $sub_grade->getCourseType() == $grade->getCourseType()
                            && strtolower( $sub_grade->getCourseName() ) == strtolower( $grade->getCourseName() )
                        ){
                            $needs_grade = false;
                        }
                    }

                    if( $needs_grade ) {
                        $submissionGrade = new SubmissionGrade();
                        $submissionGrade->setSubmission($submission);
                        $submissionGrade->setAcademicYear($grade->getAcademicYear());
                        $submissionGrade->setAcademicTerm($grade->getAcademicTerm());
                        $submissionGrade->setCourseTypeID($grade->getCourseTypeID());
                        $submissionGrade->setCourseType($grade->getCourseType());
                        $submissionGrade->setCourseName($grade->getCourseName());
                        $submissionGrade->setSectionNumber($grade->getSectionNumber());
                        $submissionGrade->setNumericGrade($grade->getNumericGrade());
                        $em->persist($submissionGrade);
                        $record_count ++;
                    }
                }
            }
            if( $record_count >= 1000 ){
                $em->flush();
                $record_count = 0;
                var_dump( $submission->getId() );
            }
        }
        $em->flush();

		$this->getContainer()->get( 'doctrine' )->getManager()->flush();
		$output->writeln( 'Completed: ' . $env );
	}
}