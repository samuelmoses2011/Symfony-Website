<?php
/**
 * Company: Image In A Box
 * Date: 11/4/15
 * Time: 8:08 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Command;

use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionGrade;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DoubleCheckGradesCommand extends ContainerAwareCommand {

	/** @var \Symfony\Component\Console\Output\OutputInterface */
	var $output;

	protected function configure() {

		$this
			->setName( 'magnet:check:grades' )
			->setDescription( 'Checks an grades to ensure there are not bad values.' )
			->addArgument( 'enrollment' , InputArgument::REQUIRED , 'Enrollment to check against' )
			->addArgument( 'submission' , InputArgument::OPTIONAL , 'Submission ID' );
			//->addOption( 'submission' , null , InputOption::VALUE_OPTIONAL , 'Submission ID' );
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		$enrollment = $input->getArgument( 'enrollment' );
		
		$openEnrollment = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->find( $enrollment );
		if( empty( $openEnrollment ) ) {
			$response = 'No enrollment found';
		}

		$this->output = $output;
		
		$districtYears = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'grades' )
			->leftJoin( 'grades.submission' , 'submission' )
			->select( 'grades.academicYear' )
			->distinct( true )
			->where( 'submission.openEnrollment = :enrollment' )
			->setParameter( 'enrollment' , $openEnrollment )
			->getQuery()
			->getResult();

		$uniqueBadYears = [ ];
		foreach( $districtYears as $year ) {
			if( !checkdate( 1 , 1 , $year['academicYear'] ) ) {
				$uniqueBadYears[] = $year['academicYear'];
			}
		}
		$uniqueBadYears = array_unique( $uniqueBadYears );

		$districtTerms = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'grades' )
			->leftJoin( 'grades.submission' , 'submission' )
			->select( 'grades.academicTerm' )
			->distinct( true )
			->where( 'submission.openEnrollment = :enrollment' )
			->setParameter( 'enrollment' , $openEnrollment )
			->getQuery()
			->getResult();

		$uniqueBadTerms = [ ];
		$badTerms = [
			'3' , '4' , '7' , '9' ,

		];
		foreach( $districtTerms as $term ) {
			if( in_array( $term['academicTerm'] , $badTerms ) ) {
				$uniqueBadTerms[] = $term['academicTerm'];
			}
		}
		$uniqueBadTerms = array_unique( $uniqueBadTerms );

		$submissionsWithBadAcademicYears = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 'submission' )
			->select('submission')
			->leftJoin( 'submission.grades' , 'grades' )
			->where( 'grades.academicYear IN (:years)' )
			->setParameter( 'years' , $uniqueBadYears )
			->andWhere( 'submission.openEnrollment = :enrollment' )
			->setParameter( 'enrollment' , $openEnrollment )
			->getQuery()
			->getResult();

		$output->writeln( sprintf( 'Found %d submission(s) with bad Academic Years' , count( $submissionsWithBadAcademicYears ) ) );

		if( count( $submissionsWithBadAcademicYears ) > 0 ) {

			/** @var \IIAB\MagnetBundle\Entity\Submission $submission */
			foreach( $submissionsWithBadAcademicYears as $submission ) {

				$this->fixGrades( $submission );

			}

		}

		$submissionsWithBadAcademicTerms = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 'submission' )
			->select('submission')
			->leftJoin( 'submission.grades' , 'grades' )
			->where( 'grades.academicTerm IN (:terms)' )
			->setParameter( 'terms' , $uniqueBadTerms )
			->andWhere( 'submission.openEnrollment = :enrollment' )
			->setParameter( 'enrollment' , $openEnrollment )
			->getQuery()
			->getResult();

		$output->writeln( sprintf( 'Found %d submission(s) with bad Academic Terms' , count( $submissionsWithBadAcademicTerms ) ) );

		if( count( $submissionsWithBadAcademicTerms ) > 0 ) {

			/** @var \IIAB\MagnetBundle\Entity\Submission $submission */
			foreach( $submissionsWithBadAcademicYears as $submission ) {

				$this->fixGrades( $submission );

			}
		}

		$submissionsWithFirstSemesterTerm = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 'submission' )
			->select('submission')
			->leftJoin( 'submission.grades' , 'grades' )
			->where( 'grades.academicTerm = :term' )
			->setParameter( 'term' , '1st Semester' )
			->andWhere( 'submission.openEnrollment = :enrollment' )
			->setParameter( 'enrollment' , $openEnrollment )
			->getQuery()
			->getResult();

		$output->writeln( sprintf( 'Found %d submission(s) with bad 1st Semester Term' , count( $submissionsWithFirstSemesterTerm ) ) );

		if( count( $submissionsWithFirstSemesterTerm )> 0 ) {

			/** @var \IIAB\MagnetBundle\Entity\Submission $submission */
			foreach( $submissionsWithFirstSemesterTerm as $submission ) {

				$updated = false;

				/** @var \IIAB\MagnetBundle\Entity\SubmissionGrade $grade */
				foreach( $submission->getGrades() as $grade ) {

					if( $grade->getAcademicTerm() == '1st Semester' ) {
						$grade->setAcademicTerm( 'Semester 1' );
						$this->getContainer()->get('doctrine')->getManager()->persist( $grade );
						$updated = true;
					}

				}
				if( $updated ) {
					$this->output->writeln( sprintf( '<fg=green>Submission ID: %d grades were updated.</fg=green>' , $submission->getId() ) );
					$this->getContainer()->get('doctrine')->getManager()->flush();
				}
			}
		}

		$submission_id = $input->getArgument( 'submission' );
		if( $submission_id ) {
			$submission = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Submission' )->findOneBy(
				[
					'openEnrollment' => $openEnrollment,
					'id' => $submission_id
				]
			);

			$this->fixGrades( $submission );
		}
	}

	/**
	 * Checks and looks for the bad grades.
	 *
	 * @param Submission $submission
	 */
	private function fixGrades( Submission $submission ) {
		if( $submission->getStateID() == '' ) {
			$this->output->writeln( sprintf( '<fg=red>Submission ID: %d does not have a State ID. So grades were entered in wrong.</fg=red>' , $submission->getId() ) );
			return;
		}

		$iNowGrades = $this->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:StudentGrade')->findGradesByStateID( $submission->getStateID() );

		if( count( $iNowGrades ) == 0 ) {
			$this->output->writeln( sprintf( '<fg=red>Submission ID: %d does not have any grades in iNow</fg=red>' , $submission->getId() ) );
			return;
		}

		/** @var \IIAB\MagnetBundle\Entity\SubmissionGrade $grade */
		foreach( $submission->getGrades() as $grade ) {

			$submission->removeGrade( $grade );

			$this->getContainer()->get('doctrine')->getManager()->remove( $grade );
		}

		$this->output->writeln( sprintf( '<fg=green>Submission ID: %d removed old grades.</fg=green>' , $submission->getId() ) );

		/** @var \IIAB\MagnetBundle\Entity\StudentGrade $grade */
		foreach( $iNowGrades as $grade ) {

			$submissionGrade = new SubmissionGrade();
			$submissionGrade->setAcademicYear( $grade->getAcademicYear() );
			$submissionGrade->setAcademicTerm( $grade->getAcademicTerm() );
			$submissionGrade->setCourseTypeID( $grade->getCourseTypeID() );
			$submissionGrade->setCourseType( $grade->getCourseType() );
			$submissionGrade->setCourseName( $grade->getCourseName() );
			$submissionGrade->setSectionNumber( $grade->getSectionNumber() );
			$submissionGrade->setNumericGrade( $grade->getNumericGrade() );

			$submission->addGrade( $submissionGrade );

			$this->getContainer()->get('doctrine')->getManager()->persist( $submissionGrade );

		}

		$this->getContainer()->get('doctrine')->getManager()->flush();
		$this->output->writeln( sprintf( '<fg=green>Submission ID: %d grades were updated.</fg=green>' , $submission->getId() ) );

	}
}