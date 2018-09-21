<?php
namespace IIAB\MagnetBundle\Controller;

use IIAB\MagnetBundle\Form\Type\ReportSelectionType;
use Doctrine\ORM\EntityRepository;
use IIAB\MagnetBundle\Service\GeneratePDFService;
use IIAB\MagnetBundle\Entity\AfterPlacementPopulation;
use IIAB\MagnetBundle\Entity\MagnetSchool;
use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionData;
use IIAB\MagnetBundle\Entity\Process;
use IIAB\MagnetBundle\Service\CheckAddressService;
use IIAB\MagnetBundle\Service\StudentProfileService;
use IIAB\MagnetBundle\Service\EligibilityRequirementsService;
use IIAB\MagnetBundle\Controller\Report\ApplicantOutcomeReport;
use IIAB\MagnetBundle\Controller\Report\ApplicantOutcomeByProgramReport;
use PHPExcel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

require_once( __DIR__ . '/../Library/mpdf/mpdf.php' );

class ReportController extends Controller {

	/**
	 * @Template("@IIABMagnet/Report/applicantReport.html.twig")
	 *
	 * Generates a unique Applicant Report for a specific Submissions.
     * @Route("/admin/submission/{id}/print-applicant/{choice}/print", name="applicant_report")
     * @Route("/admin/submission/{id}/print-applicant/{choice}/print/{focus_id}", name="applicant_report")
     *
	 * @param int $id
	 * @param int $choice
     * @param int $focus_id
	 *
	 * @return array
	 */
	public function generateApplicantReportAction( $id , $choice, $focus_id = 0 ) {

		$submission = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Submission' )->find( $id );
		if( $submission == null ) {
			die( 'Submission not found. Please try the button again.' );
		}

		$choice_hash = [
		    1 => 'First',
            2 => 'Second',
            3 => 'Third'
        ];

		//Submission is found, lets let see which school has been selected.
		$school = null;
		$focus = '';
		if( $submission->getFirstChoice()->getId() == $choice ) {
			$school = $submission->getFirstChoice();

			if(  isset( $choice_hash[ $focus_id ] ) ) {
                $focus = $submission->{'getFirstChoice' . $choice_hash[$focus_id] . 'ChoiceFocus'}();
            }

		} else if( $submission->getSecondChoice()->getId() == $choice ) {
			$school = $submission->getSecondChoice();

            if( isset( $choice_hash[ $focus_id ] ) ) {
                $focus = $submission->{'getSecondChoice' . $choice_hash[$focus_id] . 'ChoiceFocus'}();
            }

		} else if( $submission->getThirdChoice()->getId() == $choice ) {
			$school = $submission->getThirdChoice();

            if( isset( $choice_hash[ $focus_id ] ) ) {
                $focus = $submission->{'getThirdChoice' . $choice_hash[$focus_id] . 'ChoiceFocus'}();
            }
		}

		if( $school == null ) {
			die( 'School not found. Please try the button again.' );
		}

		$context = $this->getContext( $submission , $school, $focus );

		$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 15 , 15 , 10 , 10 );
		$template = $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:applicantReport.html.twig' );
		$htmlContent = $template->render( $context );

		$title = $submission->getOpenEnrollment() . ' Magnet Applicant Data';

		$mpdf->mirrorMargins = false;
		$mpdf->SetTitle( $title );
		$mpdf->SetDisplayMode( 'fullpage' , 'two' );
		$mpdf->WriteHTML( $htmlContent );

		$htmlContent = null;
		$s = $mpdf->Output( '' , 'S' );
		$mpdf = null;
		$name = "{$title}.pdf";

		$response = new Response();
		$response->headers->set( 'Content-Type' , 'application/pdf' );
		$response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '"' );
		$response->setContent( $s );
		$s = null;
		return $response;
	}

	/**
	 * Generates a unique Writing Sample Report for a specific Submissions.
     * @Route("/admin/submission/{id}/writing-sample/print", name="print_writing_sample")
     *
	 * @param int $id
	 *
	 * @return array
	 */
	public function generateWritingSamplePDFAction( $id ) {

		$submission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->find( $id );
		$generatePDF = new GeneratePDFService( $this->container );

        $return_pdf = $generatePDF->writingSample(
            $submission
        );

        return $return_pdf;
	}

	/**
	 * Generates a unique Writing Sample Instruction Sheet specific Submissions.
     * @Route("/writing-sample/{uniqueURL}/printout", name="writing_sample_printout")
    *
	 * @param int $uniqueURL
	 *
	 * @return array
	 */
	public function generateWritingSamplePrintoutPDFAction( $uniqueURL ) {

		$url_parts = explode( '.', $uniqueURL );

		if( empty( $url_parts[1] ) ){
			return $this->redirect( $this->generateUrl( 'submission_notfound' ) );
		}

		$submission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->findOneBy( array(
				'id' => $url_parts[0],
				'url' => $url_parts[1],
		) );

		//URL did not match any Submissions, lets redirect them to not-found offered page.
		if( $submission == null ) {
			return $this->redirect( $this->generateUrl( 'submission_notfound' ) );
		}

		$generatePDF = new GeneratePDFService( $this->container );

        $return_pdf = $generatePDF->writingSamplePrintout(
            $submission
        );

        return $return_pdf;
	}

	/**
	 * Generates Student Profile PDF.
     * @Route("/admin/submission/{id}/student-profile/print", name="print_student_profile")
     *
	 * @param int $id
	 *
	 * @return array
	 */
	public function generateStudentProfilePDFAction( $id ) {

		$submission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->find( $id );
		$generatePDF = new GeneratePDFService( $this->container );

        $return_pdf = $generatePDF->studentProfilePrintForm(
            $submission
        );

        return $return_pdf;
	}

	/**
	 * Generates a unique Applicant Awarded PDF.
	 *
	 * @param $id
	 *
	 * @return Response
	 */
	public function generateAwardPDFAction( $id ) {

		$offered = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Offered' )->findOneBySubmission( $id );

		if( $offered == null ) {
			die( 'Offer not found. Please try the button again.' );
		}

		return $this->get('magnet.pdf')->awardedPDF( $offered );
	}

	/**
	 * Gets the MagnetSchools for the specific OpenEnrollment
	 * @Route("/admin/ajax/get-magnet-schools/{openEnrollment}", name="ajax_magnet_schools")
	 *
	 * @param int $openEnrollment
	 * @param bool $json
	 *
	 * @return Response
	 */
	public function getMagnetSchoolsAction( $openEnrollment = 0 , $json = true ) {

		if( empty( $openEnrollment ) ) {
			if( $json ) {
				return new JsonResponse( [ ] , 200 );
			} else {
				return [];
			}
		}

		$user = $this->getUser();

		$schools = $user->getSchools();

		$query = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
			->where( 'school.openEnrollment = :openEnrollment' )
			->addOrderBy( 'school.name' , 'ASC' )
			->addOrderBy( 'school.grade' , 'ASC' )
			->setParameter( 'openEnrollment' , $openEnrollment );

		if( !empty( $schools ) && count( $schools ) == 1 ) {
			$query->andWhere( 'school.name LIKE :schools' )->setParameter( 'schools' , $schools );
		} else if( !empty( $schools ) && count( $schools ) > 1 ) {
			foreach( $schools as $key => $school ) {
				$query->orWhere( "school.name LIKE :school{$key}" )->setParameter( "school{$key}" , $school );
			}
		}

		$results = $query->getQuery()->getResult();

		$jsonArray = [];
		$jsonArray[] = [ 'id' => '' , 'text' => 'Choose an option' ];
		/** @var \IIAB\MagnetBundle\Entity\MagnetSchool $school */
		foreach( $results as $school ) {
			$jsonArray[] = [ 'id' => $school->getId() , 'text' => $school->__toString() ];
		}

		if( $json ) {
			return new JsonResponse( $jsonArray , 200 );
		} else {
			return $jsonArray;
		}
	}

	/**
	 * @Route( "/admin/report/submission-group-applicant-report/", name="admin_report_submission_group", options={"i18n"=false} )
	 * @Template("@IIABMagnet/Admin/Report/applicantGroupReport.html.twig")
	 *
	 * @return array
	 */
	public function generateGroupOfApplicantReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'attr' => array( 'style' => 'margin-bottom: 20px' , 'class' => 'update-magnetschool' ) ,
				'placeholder' => 'Choose an Enrollment Period' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'magnetschool' , 'entity' , array(
				'class' => 'IIABMagnetBundle:MagnetSchool' ,
				'label' => 'Program' ,
				'required' => true ,
				'validation_groups' => false ,
				'placeholder' => 'Choose an option' ,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Program Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();


		$form->handleRequest( $request );
		if( $form->isValid() ) {

			set_time_limit( 0 );

			$data = $form->getData();
			$magnetSchool = $data['magnetschool'];
			$openEnrollment = $data['openenrollment'];
			$template = $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:reportData.html.twig' );

			$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 15 , 15 , 10 , 10 );

			$submissions = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->where( 's.openEnrollment = :enrollment' )
				->andWhere( 's.firstChoice = :program OR s.secondChoice = :program OR s.thirdChoice = :program' )
				->andWhere( 's.submissionStatus = :status' )
				->orderBy( 's.lastName' , 'ASC' )
				->addOrderBy( 's.firstName' , 'ASC' )
				->setParameters( array(
					'enrollment' => $openEnrollment ,
					'program' => $magnetSchool ,
					'status' => 1
				) )
				->getQuery()
				->getResult();

			$total = count( $submissions );
			$totalCount = $total;

			$submissionsRanking = $this->getGradeRankingOrder( $submissions , $magnetSchool );

			$mpdf->WriteHTML( '<html><style type="text/css">table {font-size: 10pt;}ol li {margin-bottom: 10px;}ol li ol li {margin-top: 10px;}</style><body style="font-family: serif; font-size: 10pt;">' );

			foreach( $submissions as $submission ) {

				$context = $this->getContext( $submission , $magnetSchool );
				$context = array_merge( $context , array(
					'gradeRanking' => isset( $submissionsRanking[$submission->getId()] ) ? $submissionsRanking[$submission->getId()] : '' ,
					'gradeRankingTotal' => $totalCount
				) );

				$mpdf->WriteHTML( $template->render( $context ) );
				$total--;
				if( $total > 0 ) {
					$mpdf->WriteHTML( '<pagebreak></pagebreak>' );
				}
			}

			$mpdf->WriteHTML( '</body></html>' );
			$template = null;

			$title = $magnetSchool . ' - Magnet Applicant Data';
			$name = "{$title}.pdf";

			$mpdf->mirrorMargins = false;
			$mpdf->SetTitle( $title );
			$mpdf->SetDisplayMode( 'fullpage' , 'two' );

			$pdfContent = null;
			$s = $mpdf->Output( '' , 'S' ); //$mpdf->Output( $filePath , 'f' );
			$mpdf = null;

			$response = new Response();
			$response->headers->set( 'Content-Type' , 'application/pdf' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '"' );
			$response->setContent( $s );
			$s = null;

			return $response;
		}

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool );
	}

	/**
	 * @Route( "/admin/report/submission-validate-sibling-report/", name="admin_report_validate_sibling", options={"i18n"=false} )
	 * @Template("@IIABMagnet/Admin/Report/invalidSiblingReport.html.twig")
	 *
	 * @return array
	 */
	public function generateInvalidSiblingReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'placeholder' => 'Any Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Invalid Sibling Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );
		if( $form->isValid() ) {

			$data = $form->getData();
			$openEnrollment = $data['openenrollment'];
			$invalidSiblings = $this->get( 'magnet.validate.sibling' )->getInvalidSiblings( $openEnrollment );

			$objPHPExcel = new PHPExcel();
			$objPHPExcel->getProperties()
				->setCreator( 'Image In A Box, LLC' )
				->setCompany( 'Image In A Box, LLC' )
				->setLastModifiedBy( 'Justin Givens - jgivens@imageinabox.com' )
				->setTitle( 'Invalid Sibling Reports - Tuscaloosa Public Schools Magnet Program' );
			$objPHPExcel->setActiveSheetIndex( 0 );

			$index = 1;
			$activateSheet = $objPHPExcel->getActiveSheet();
			$activateSheet->setCellValue( "A{$index}" , "Submission" );
			$activateSheet->setCellValue( "B{$index}" , "Name" );
			$activateSheet->setCellValue( "C{$index}" , "School Choice" );
			$activateSheet->setCellValue( "D{$index}" , "Field" );
			$activateSheet->setCellValue( "E{$index}" , "Sibling #" );
			$index++;

			if( $openEnrollment != null ) {
				$title = $openEnrollment . ' - Invalid Sibling Report';
			} else {
				$title = 'All Enrollment Periods - Invalid Sibling Report';
			}

			if( count( $invalidSiblings ) > 0 ) {

				foreach( $invalidSiblings as $key => $submissions ) {

					foreach( $submissions as $submission ) {
						$value = '';
						$choice = '';
						if( $key == 'First Choice Sibling ID' ) {
							$value = $submission->getFirstSiblingValue();
							$choice = $submission->getFirstChoice()->__toString();
						} else if( $key == 'Second Choice Sibling ID' ) {
							$value = $submission->getSecondSiblingValue();
							$choice = $submission->getSecondChoice()->__toString();
						} else if( $key == 'Third Choice Sibling ID' ) {
							$value = $submission->getThirdSiblingValue();
							$choice = $submission->getThirdChoice()->__toString();
						}
						$activateSheet->setCellValue( "A{$index}" , $submission->__toString() );
						$activateSheet->setCellValue( "B{$index}" , $submission->getName() );
						$activateSheet->setCellValue( "C{$index}" , $choice );
						$activateSheet->setCellValue( "D{$index}" , $key );
						$activateSheet->setCellValue( "E{$index}" , $value );
						$index++;
					}
				}
			} else {
				$activateSheet->setCellValue( "A{$index}" , 'No invalid Siblings found.' );
			}

			$objWriter = new \PHPExcel_Writer_CSV( $objPHPExcel );

			$objPHPExcel = null;

			$name = "{$title}.csv";

			ob_start();
			$objWriter->save( 'php://output' );
			$s = ob_get_contents();
			ob_end_clean();
			$objWriter = null;

			$response = new Response();
			$response->headers->set( 'Content-Type' , 'application/pdf' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '"' );
			$response->setContent( $s );
			$s = null;

			return $response;
		}

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool );
	}

	/**
	 * @Route( "/admin/report/submission-invalid-bounds-current-students/", name="admin_report_invalid_bounds_current_students", options={"i18n"=false} )
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function generateInvalidHCSStudentReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'placeholder' => 'Any Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate MPS Submission Out of Bounds' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );
		if( $form->isValid() ) {

			$data = $form->getData();
			$openEnrollment = $data['openenrollment'];
			$submissions = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
				'nonHSVStudent' => 0 ,
				'openEnrollment' => $openEnrollment ,
			) , array( 'createdAt' => 'ASC' ) );

			$objPHPExcel = new PHPExcel();
			$objPHPExcel->getProperties()
				->setCreator( 'Image In A Box, LLC' )
				->setCompany( 'Image In A Box, LLC' )
				->setLastModifiedBy( 'Justin Givens - jgivens@imageinabox.com' )
				->setTitle( 'MPS Submission Out of Bounds Reports - Tuscaloosa Public Schools Magnet Program' );
			$objPHPExcel->setActiveSheetIndex( 0 );

			$index = 1;
			$activateSheet = $objPHPExcel->getActiveSheet();
			$activateSheet->setCellValue( "A{$index}" , "Submission" );
			$activateSheet->setCellValue( "B{$index}" , "State ID" );
			$activateSheet->setCellValue( "C{$index}" , "First Name" );
			$activateSheet->setCellValue( "D{$index}" , "Last Name" );
			$activateSheet->setCellValue( "E{$index}" , "Address" );
			$activateSheet->setCellValue( "F{$index}" , "City" );
			$activateSheet->setCellValue( "G{$index}" , "State" );
			$activateSheet->setCellValue( "H{$index}" , "Zip" );
			$activateSheet->setCellValue( "I{$index}" , "First Choice" );
			$activateSheet->setCellValue( "J{$index}" , "Second Choice" );
			$activateSheet->setCellValue( "K{$index}" , "Third Choice" );
			$activateSheet->setCellValue( "L{$index}" , "Current Grade" );
			$activateSheet->setCellValue( "M{$index}" , "Next Grade" );
			$activateSheet->setCellValue( "N{$index}" , "Parent Email" );
			$index++;

			set_time_limit( 0 );

			if( $openEnrollment != null ) {
				$title = $openEnrollment . ' - MPS Submission Out of Bounds Report';
			} else {
				$title = 'All Enrollment Periods - MPS Submission Out of Bounds Report';
			}

			if( count( $submissions ) > 0 ) {

				$checkAddress = new CheckAddressService( $this->get( 'doctrine.orm.default_entity_manager' ) );

				foreach( $submissions as $submission ) {

					$student = array(
						'student_status' => 'new' ,
						'address' => $submission->getAddress() ,
						'zip' => $submission->getZip() ,
					);
					$checkResponse = $checkAddress->checkAddress( $student );

					if( $checkResponse == false ) {

						$first = $submission->getFirstChoice();
						$second = $submission->getSecondChoice();
						$third = $submission->getThirdChoice();

						$activateSheet->setCellValue( "A{$index}" , $submission->__toString() );
						$activateSheet->setCellValue( "B{$index}" , $submission->getStateID() );
						$activateSheet->setCellValue( "C{$index}" , $submission->getFirstName() );
						$activateSheet->setCellValue( "D{$index}" , $submission->getLastName() );
						$activateSheet->setCellValue( "E{$index}" , $submission->getAddress() );
						$activateSheet->setCellValue( "F{$index}" , $submission->getCity() );
						$activateSheet->setCellValue( "G{$index}" , $submission->getState() );
						$activateSheet->setCellValue( "H{$index}" , $submission->getZip() );
						$activateSheet->setCellValue( "I{$index}" , !empty( $first ) ? $first->__toString() : '' );
						$activateSheet->setCellValue( "J{$index}" , !empty( $second ) ? $second->__toString() : '' );
						$activateSheet->setCellValue( "K{$index}" , !empty( $third ) ? $third->__toString() : '' );
						$activateSheet->setCellValue( "L{$index}" , $submission->getCurrentGradeString() );
						$activateSheet->setCellValue( "M{$index}" , $submission->getNextGradeString() );
						$activateSheet->setCellValue( "N{$index}" , $submission->getParentEmail() );
						$index++;
					}
				}
			} else {
				$activateSheet->setCellValue( "A{$index}" , 'No submissions found.' );
			}

			$objWriter = new \PHPExcel_Writer_CSV( $objPHPExcel );

			$objPHPExcel = null;

			$name = "{$title}.csv";

			$objWriter->save( $name );
			$objWriter = null;

			$content = file_get_contents( $name );

			$response = new Response();
			$response->headers->set( 'Content-Type' , 'text/csv' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '"' );
			$response->setContent( $content );
			$content = null;
			unlink( $name );

			set_time_limit( 60 );

			return $response;
		}

		$title = 'MPS Current Students Out Of Bounds';
		$subtitle = 'Group of Out Of Bounds Submissions';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle );
	}

	/**
	 * @Route( "/admin/report/submission-grade-average-by-program/", name="admin_report_grade_average_by_program", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function gradeAverageByProgramReport() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$uniqueMagnetSchools = array();
		$uniqueMagnetSchoolsAllowed = [];
		$uniqueMagnetSchoolsResults = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
			->groupBy( 'm.name' )
			->addGroupBy( 'm.openEnrollment' )
			->orderBy( 'm.name' , 'ASC' )
			->addOrderBy( 'm.openEnrollment' , 'ASC' )
			->getQuery()
			->getResult();

		/** @var \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool */
		foreach( $uniqueMagnetSchoolsResults as $magnetSchool ) {
			$uniqueMagnetSchools[$magnetSchool->getOpenEnrollment()->getId()][] = [
				'id' => $magnetSchool->getName(),
				'text' => $magnetSchool->getName()
			];
			$uniqueMagnetSchoolsAllowed[$magnetSchool->getName()] = $magnetSchool->getName();
		}
		ksort( $uniqueMagnetSchools );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'attr' => array( 'style' => 'margin-bottom: 25px;' ) ,
				'placeholder' => 'Choose an Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'program' , 'choice' , array(
				'label' => 'Select a Program' ,
				'required' => true ,
				'placeholder' => 'Choose a Magnet Program' ,
				'choices' => $uniqueMagnetSchoolsAllowed ,
				'attr' => [ 'data-choices' => json_encode( $uniqueMagnetSchools ) ]
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate MPS Submission Grade Average' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );
		if( $form->isValid() ) {

			$data = $form->getData();

			$submissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->leftJoin( 's.firstChoice' , 'first_choice' )
				->leftJoin( 's.secondChoice' , 'second_choice' )
				->leftJoin( 's.thirdChoice' , 'third_choice' )
				->where( 's.openEnrollment = :enrollment' )
				->andWhere( 's.submissionStatus = :status' )
				->andWhere( 'first_choice.name LIKE :program OR second_choice.name LIKE :program OR third_choice.name LIKE :program' )
				->setParameters( array(
					'enrollment' => $data['openenrollment'] ,
					'status' => 1 ,
					'program' => $data['program']
				) )
				->orderBy( 's.createdAt' , 'ASC' )
				->getQuery()
				->getResult();
			$phpExcelObject = $this->get( 'phpexcel' )->createPHPExcelObject();
			$phpExcelObject->getProperties()->setCreator( "Image In A Box" )
				->setLastModifiedBy( "Image In A Box" )
				->setTitle( "MPS Active Submissions Grade Average by Program" )
				->setSubject( "Grade Average" )
				->setDescription( "Document needs to be completed in order for the Magnet Program website to run correctly." )
				->setKeywords( "mymagnetapp" )
				->setCategory( "grade average" );
			$row = 1;

			$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:S{$row}" );
			$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , 'Active Submissions Grade Average by ' . $data['program'] );
			$phpExcelObject->getActiveSheet()->getStyle( "A{$row}" )->getAlignment()->setHorizontal( \PHPExcel_Style_Alignment::HORIZONTAL_CENTER );
			$row++;

			$activeSheet = $phpExcelObject->getActiveSheet();
			$activeSheet->setCellValue( "A{$row}" , 'State ID' );
			$activeSheet->setCellValue( "B{$row}" , 'Submission ID' );
			$activeSheet->setCellValue( "C{$row}" , 'Last Name' );
			$activeSheet->setCellValue( "D{$row}" , 'First Name' );
			$activeSheet->setCellValue( "E{$row}" , 'Address' );
			$activeSheet->setCellValue( "F{$row}" , 'Phone Number' );
			$activeSheet->setCellValue( "G{$row}" , 'Email' );
			$activeSheet->setCellValue( "H{$row}" , 'Race' );
			$activeSheet->setCellValue( "I{$row}" , 'Next Grade' );
			$activeSheet->setCellValue( "J{$row}" , 'First Choice' );
			$activeSheet->setCellValue( "K{$row}" , 'First Choice Grade Average' );
			$activeSheet->setCellValue( "L{$row}" , 'First Choice Committee Score' );
			$activeSheet->setCellValue( "M{$row}" , 'Second Choice' );
			$activeSheet->setCellValue( "N{$row}" , 'Second Choice Grade Average' );
			$activeSheet->setCellValue( "O{$row}" , 'Second Choice Committee Score' );
			$activeSheet->setCellValue( "P{$row}" , 'Third Choice' );
			$activeSheet->setCellValue( "Q{$row}" , 'Third Choice Grade Average' );
			$activeSheet->setCellValue( "R{$row}" , 'Third Choice Committee Score' );
			$activeSheet->setCellValue( "S{$row}" , 'Student Status' );
			//$activeSheet->setCellValue( "T{$row}" , '' );

			$activeSheet->getStyle( "I{$row}" )->getAlignment()->setHorizontal( \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT );

			$row++;

			$eligibilityService = new EligibilityRequirementsService(
				$this->container->get( 'doctrine.orm.default_entity_manager' ) );

			$counter = 1;
			if( count( $submissions ) > 0 ) {

				foreach( $submissions as $submission ) {

					$firstChoiceEligibilityGrade = '';
					if( $submission->getFirstChoice() != null ) {
						list( $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $eligibilityCheck ) = $eligibilityService->doesStudentPassRequirements( array( 'submissionID' => $submission->getId() ) , $submission->getFirstChoice() );
						if( $eligibilityCheck != null ) {
							foreach( $eligibilityCheck as $key => $check ) {
								if( $check == 'GPA CHECK' ) {
									$firstChoiceEligibilityGrade = $eligibilityGrade[$key];
								}
							}
						}
					}

					$secondChoiceEligibilityGrade = '';
					if( $submission->getSecondChoice() != null ) {
						list( $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $eligibilityCheck ) = $eligibilityService->doesStudentPassRequirements( array( 'submissionID' => $submission->getId() ) , $submission->getSecondChoice() );
						if( $eligibilityCheck != null ) {
							foreach( $eligibilityCheck as $key => $check ) {
								if( $check == 'GPA CHECK' ) {
									$secondChoiceEligibilityGrade = $eligibilityGrade[$key];
								}
							}
						}
					}

					$thirdChoiceEligibilityGrade = '';
					if( $submission->getThirdChoice() != null ) {
						list( $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $eligibilityCheck ) = $eligibilityService->doesStudentPassRequirements( array( 'submissionID' => $submission->getId() ) , $submission->getThirdChoice() );
						if( $eligibilityCheck != null ) {
							foreach( $eligibilityCheck as $key => $check ) {
								if( $check == 'GPA CHECK' ) {
									$thirdChoiceEligibilityGrade = $eligibilityGrade[$key];
								}
							}
						}
					}
					$eligibilityGrade = null;
					$passedEligibility = null;
					$eligibilityCourseTitle = null;
					$eligibilityCheck = null;

					$activeSheet->setCellValue( "A{$row}" , $submission->getStateID() );
					$activeSheet->setCellValue( "B{$row}" , $submission->__toString() );
					$activeSheet->setCellValue( "C{$row}" , $submission->getLastName() );
					$activeSheet->setCellValue( "D{$row}" , $submission->getFirstName() );
					$activeSheet->setCellValue( "E{$row}" , $submission->getAddress() . ', ' . $submission->getCity() . ', ' . $submission->getState() . ' ' . $submission->getZip() );
					$activeSheet->setCellValue( "F{$row}" , $submission->getPhoneNumber( true ) );
					$activeSheet->setCellValue( "G{$row}" , $submission->getParentEmail() );
					$activeSheet->setCellValue( "H{$row}" , $submission->getRaceFormatted() );
					$activeSheet->setCellValue( "I{$row}" , $submission->getNextGradeString() );
					$activeSheet->setCellValue( "J{$row}" , $submission->getFirstChoice() != null ? $submission->getFirstChoice()->__toString() : '' );
					$activeSheet->setCellValue( "K{$row}" , $firstChoiceEligibilityGrade );
					$activeSheet->setCellValue( "L{$row}" , $submission->getCommitteeReviewScoreFirstChoice() );
					$activeSheet->setCellValue( "M{$row}" , $submission->getSecondChoice() != null ? $submission->getSecondChoice()->__toString() : '' );
					$activeSheet->setCellValue( "N{$row}" , $secondChoiceEligibilityGrade );
					$activeSheet->setCellValue( "O{$row}" , $submission->getCommitteeReviewScoreSecondChoice() );
					$activeSheet->setCellValue( "P{$row}" , $submission->getThirdChoice() != null ? $submission->getThirdChoice()->__toString() : '' );
					$activeSheet->setCellValue( "Q{$row}" , $thirdChoiceEligibilityGrade );
					$activeSheet->setCellValue( "R{$row}" , $submission->getCommitteeReviewScoreThirdChoice() );
					$activeSheet->setCellValue( "S{$row}" , $submission->getNonHSVStudentString() );

					$activeSheet->getStyle( "I{$row}" )->getAlignment()->setHorizontal( \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT );

					$row++;

				}

			} else {
				$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:S{$row}" );
				$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , 'No submission found for this program.' );
			}

			$writer = $this->get( 'phpexcel' )->createWriter( $phpExcelObject , 'Excel2007' );
			// create the response
			$response = $this->get( 'phpexcel' )->createStreamedResponse( $writer );
			// adding headers
			$response->headers->set( 'Content-Type' , 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename=grade-average-by-program.xlsx' );
			$response->headers->set( 'Pragma' , 'public' );
			$response->headers->set( 'Cache-Control' , 'maxage=1' );
			return $response;
		}

		$title = 'MPS Active Submissions Grade Average';
		$subtitle = 'Grade Average Report by Program';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle );
	}

	/**
	 * @Route( "/admin/report/submission-committee-review-score-by-program/", name="admin_report_committee_review_score_by_program", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function committeeReviewScoreByProgramReport() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$uniqueMagnetSchools = array();
		$uniqueMagnetSchoolsAllowed = [];
		$uniqueMagnetSchoolsResults = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
			->where( 'm.grade > 5 AND m.grade < 13' )
			->groupBy( 'm.name' )
			->addGroupBy( 'm.openEnrollment' )
			->orderBy( 'm.name' , 'ASC' )
			->addOrderBy( 'm.openEnrollment' , 'ASC' )
			->getQuery()
			->getResult();

		/** @var \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool */
		foreach( $uniqueMagnetSchoolsResults as $magnetSchool ) {
			$uniqueMagnetSchools[$magnetSchool->getOpenEnrollment()->getId()][] = [
				'id' => $magnetSchool->getName(),
				'text' => $magnetSchool->getName()
			];
			$uniqueMagnetSchoolsAllowed[$magnetSchool->getName()] = $magnetSchool->getName();
		}
		ksort( $uniqueMagnetSchools );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'attr' => array( 'style' => 'margin-bottom: 25px;' ) ,
				'placeholder' => 'Choose an Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'program' , 'choice' , array(
				'label' => 'Select a Program' ,
				'required' => true ,
				'placeholder' => 'Choose a Program' ,
				'choices' => $uniqueMagnetSchoolsAllowed ,
				'attr' => [ 'data-choices' => json_encode( $uniqueMagnetSchools ) ]
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate MPS Submission Committee Review Score' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );
		if( $form->isValid() ) {

			$data = $form->getData();

			$submissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->where( 's.openEnrollment = :enrollment' )
				->andWhere( 's.submissionStatus = :status' )
				->andWhere( 's.nextGrade > 5 AND s.nextGrade < 13' )
				->setParameters( array(
					'enrollment' => $data['openenrollment'] ,
					'status' => 1 ,
				) );

			if( $data['program'] != 'All Programs' ) {
				$submissions
					->leftJoin( 's.firstChoice' , 'first_choice' )
					->leftJoin( 's.secondChoice' , 'second_choice' )
					->leftJoin( 's.thirdChoice' , 'third_choice' )
					->andWhere( 'first_choice.name LIKE :program OR second_choice.name LIKE :program OR third_choice.name LIKE :program' )
					->setParameter( 'program' , $data['program'] );
			}

			$submissions->orderBy( 's.createdAt' , 'ASC' );

			$submissions = $submissions->getQuery()->getResult();

			$phpExcelObject = $this->get( 'phpexcel' )->createPHPExcelObject();
			$phpExcelObject->getProperties()->setCreator( "Image In A Box" )
				->setLastModifiedBy( "Image In A Box" )
				->setTitle( "MPS Active Submissions Committee Review Score by Program" )
				->setSubject( "Committee Review Score" )
				->setDescription( "Document needs to be completed in order for the Magnet Program website to run correctly." )
				->setKeywords( "mymagnetapp" )
				->setCategory( "committee review score" );
			$row = 1;

			$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:P{$row}" );
			$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , 'Active Submissions Committee Review Score by ' . $data['program'] );
			$phpExcelObject->getActiveSheet()->getStyle( "A{$row}" )->getAlignment()->setHorizontal( \PHPExcel_Style_Alignment::HORIZONTAL_CENTER );
			$row++;

			$activeSheet = $phpExcelObject->getActiveSheet();
			$activeSheet->setCellValue( "A{$row}" , 'State ID' );
			$activeSheet->setCellValue( "B{$row}" , 'Submission ID' );
			$activeSheet->setCellValue( "C{$row}" , 'Last Name' );
			$activeSheet->setCellValue( "D{$row}" , 'First Name' );
			$activeSheet->setCellValue( "E{$row}" , 'Address' );
			$activeSheet->setCellValue( "F{$row}" , 'Phone Number' );
			$activeSheet->setCellValue( "G{$row}" , 'Email' );
			$activeSheet->setCellValue( "H{$row}" , 'Race' );
			$activeSheet->setCellValue( "I{$row}" , 'Next Grade' );
			$activeSheet->setCellValue( "J{$row}" , 'First Choice' );
			$activeSheet->setCellValue( "K{$row}" , 'First Choice Committee Score' );
			$activeSheet->setCellValue( "L{$row}" , 'Second Choice' );
			$activeSheet->setCellValue( "M{$row}" , 'Second Choice Committee Score' );
			$activeSheet->setCellValue( "N{$row}" , 'Third Choice' );
			$activeSheet->setCellValue( "O{$row}" , 'Third Choice Committee Score' );
			$activeSheet->setCellValue( "P{$row}" , 'Student Status' );

			$activeSheet->getStyle( "I{$row}" )->getAlignment()->setHorizontal( \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT );

			$row++;

			if( count( $submissions ) > 0 ) {

				foreach( $submissions as $submission ) {

					$activeSheet->setCellValue( "A{$row}" , $submission->getStateID() );
					$activeSheet->setCellValue( "B{$row}" , $submission->__toString() );
					$activeSheet->setCellValue( "C{$row}" , $submission->getLastName() );
					$activeSheet->setCellValue( "D{$row}" , $submission->getFirstName() );
					$activeSheet->setCellValue( "E{$row}" , $submission->getAddress() . ', ' . $submission->getCity() . ', ' . $submission->getState() . ' ' . $submission->getZip() );
					$activeSheet->setCellValue( "F{$row}" , $submission->getPhoneNumber( true ) );
					$activeSheet->setCellValue( "G{$row}" , $submission->getParentEmail() );
					$activeSheet->setCellValue( "H{$row}" , $submission->getRaceFormatted() );
					$activeSheet->setCellValue( "I{$row}" , $submission->getNextGradeString() );
					$activeSheet->setCellValue( "J{$row}" , $submission->getFirstChoice() != null ? $submission->getFirstChoice()->__toString() : '' );
					$activeSheet->setCellValue( "K{$row}" , $submission->getCommitteeReviewScoreFirstChoice() );
					$activeSheet->setCellValue( "L{$row}" , $submission->getSecondChoice() != null ? $submission->getSecondChoice()->__toString() : '' );
					$activeSheet->setCellValue( "M{$row}" , $submission->getCommitteeReviewScoreSecondChoice() );
					$activeSheet->setCellValue( "N{$row}" , $submission->getThirdChoice() != null ? $submission->getThirdChoice()->__toString() : '' );
					$activeSheet->setCellValue( "O{$row}" , $submission->getCommitteeReviewScoreThirdChoice() );
					$activeSheet->setCellValue( "P{$row}" , $submission->getNonHSVStudentString() );

					$activeSheet->getStyle( "I{$row}" )->getAlignment()->setHorizontal( \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT );

					$row++;

				}

			} else {
				$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:S{$row}" );
				$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , 'No submission found for this program.' );
			}

			$writer = $this->get( 'phpexcel' )->createWriter( $phpExcelObject , 'Excel2007' );
			// create the response
			$response = $this->get( 'phpexcel' )->createStreamedResponse( $writer );
			// adding headers
			$response->headers->set( 'Content-Type' , 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename=committee-review-score.xlsx' );
			$response->headers->set( 'Pragma' , 'public' );
			$response->headers->set( 'Cache-Control' , 'maxage=1' );
			return $response;
		}

		$title = 'MPS Active Submissions Committee Review Score';
		$subtitle = 'Committee Review Score Report by Program (Middle/High School Only)';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle );
	}

	/**
	 * @Route( "/admin/report/submission-grades-report/", name="admin_report_grades_report", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function generateSubmissionsCalculatedGradesReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$uniqueMagnetSchools = array();
		$uniqueMagnetSchoolsResults = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
			->select( 'm.name' )
			->distinct( true )
			->where( 'm.grade > 8 AND m.grade < 13' )
			->orderBy( 'm.name' , 'ASC' )
			->getQuery()
			->getArrayResult();

		foreach( $uniqueMagnetSchoolsResults as $magnetSchool ) {
			$uniqueMagnetSchools[$magnetSchool['name']] = $magnetSchool['name'];
		}

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'attr' => array( 'style' => 'margin-bottom: 20px;' ) ,
				'placeholder' => 'Any Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'program' , 'choice' , array(
				'label' => 'Select a Program' ,
				'required' => true ,
				'placeholder' => 'Choose a Magnet Program' ,
				'choices' => $uniqueMagnetSchools ,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Grade Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );
		if( $form->isValid() ) {

			$data = $form->getData();

			$activeSubmission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 1 );

			$submissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->leftJoin( 's.firstChoice' , 'first_choice' )
				->leftJoin( 's.secondChoice' , 'second_choice' )
				->leftJoin( 's.thirdChoice' , 'third_choice' )
				->where( 's.openEnrollment = :enrollment' )
				->andWhere( 's.submissionStatus = :status' )
				->andWhere( 's.nextGrade > 8 AND s.nextGrade < 13' )
				->andWhere( 'first_choice.name LIKE :program OR second_choice.name LIKE :program OR third_choice.name LIKE :program' )
				->setParameters( array(
					'enrollment' => $data['openenrollment'] ,
					'status' => $activeSubmission ,
					'program' => $data['program']
				) )
				->orderBy( 's.nextGrade' , 'ASC' )
				->getQuery()
				->getResult();

			$validTerms = array(
				'Semester 1' ,
				'Semester 2' ,
				'1st Semester Credit Recovery' ,
				'2nd Semester Credit Recovery' ,
			);
			$problemType = array(
				'1' => 'Duplicate Course IDs for a Unique Year and Unique Term' ,
				'2' => 'Does not have the required number of semesters with Unique Year and Unique Term' ,
				'3' => 'Does not have Four Main Course Types (Math, English, Social Studies, Science) for atleast one Year and Term' ,
				'4' => 'Missing sequential grade data.' ,
				'5' => 'Last Semester Scores Contains Middle School Data.' ,
				'6' => 'Previous Semester is not 2015'
			);
			$badGradeSubmission = array();
			$goodGradeSubmission = array();

			foreach( $submissions as $submission ) {

				$badSubmission = false;

				/************************************************************
				 * Problem Test 1
				 ************************************************************/
				$submissionGradesUniqueYearTermCourseType = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
					->select( 'g.academicYear' )
					->addSelect( 'g.academicTerm' )
					->addSelect( 'g.courseTypeID' )
					->addSelect( 'COUNT(g.id) as total' )
					->where( 'g.submission = :submission' )
					->andWhere( 'g.academicTerm NOT LIKE :summer' )
					->groupBy( 'g.academicYear' )
					->addGroupBy( 'g.academicTerm' )
					->addGroupBy( 'g.courseTypeID' )
					->orderBy( 'g.academicYear' , 'DESC' )
					->addOrderBy( 'g.academicTerm' , 'DESC' )
					->addOrderBy( 'g.courseTypeID' , 'ASC' )
					->setParameter( 'submission' , $submission )
					->setParameter( 'summer' , '%summer%' )
					->getQuery()
					->getResult();

				foreach( $submissionGradesUniqueYearTermCourseType as $key => $submissionGrade ) {
					if( $submissionGrade['total'] > 1 ) {
						//Break because the number of courseType is higher than 1.
						$badGradeSubmission[$submission->getId()] = array(
							'submission' => $submission ,
							'error' => $problemType[1]
						);
						$badSubmission = true;
						break;
					}
				}
				if( $badSubmission ) {
					//Continue to the next Submission;
					continue;
				}
				$submissionGradesUniqueYearTermCourseType = null;


				/************************************************************
				 * Problem Test 2
				 ************************************************************/
				$uniqueYearAndTerm = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
					->select( 'g.academicYear' )
					->addSelect( 'g.academicTerm' )
					->where( 'g.submission = :submission' )
					->andWhere( 'g.academicTerm NOT LIKE :summer' )
					->groupBy( 'g.academicYear' )
					->addGroupBy( 'g.academicTerm' )
					->orderBy( 'g.academicYear' , 'DESC' )
					->addOrderBy( 'g.academicTerm' , 'DESC' )
					->setParameter( 'summer' , '%summer%' )
					->setParameter( 'submission' , $submission );

				$numberOfSemesters = 1;
				switch( $submission->getNextGrade() ) {

					case 9:
					case 10:
						$numberOfSemesters = 1;
						$queryResponse = $uniqueYearAndTerm
							->setMaxResults( 1 )
							->getQuery()
							->getResult();

						if( count( $queryResponse ) != 1 ) {
							$badGradeSubmission[$submission->getId()] = array(
								'submission' => $submission ,
								'error' => $problemType[2]
							);
							$badSubmission = true;
						}
						break;

					case 11:

						$numberOfSemesters = 3;
						$queryResponse = $uniqueYearAndTerm
							->setMaxResults( 3 )
							->getQuery()
							->getResult();

						if( count( $queryResponse ) != 3 ) {

							$badGradeSubmission[$submission->getId()] = array(
								'submission' => $submission ,
								'error' => $problemType[2]
							);
							$badSubmission = true;
						}
						break;

					case 12:

						$numberOfSemesters = 5;
						$queryResponse = $uniqueYearAndTerm
							->setMaxResults( 5 )
							->getQuery()
							->getResult();

						if( count( $queryResponse ) != 5 ) {

							$badGradeSubmission[$submission->getId()] = array(
								'submission' => $submission ,
								'error' => $problemType[2]
							);
							$badSubmission = true;
						}
						break;
				}
				if( $badSubmission ) {
					//Continue to the next Submission;
					$uniqueYearAndTerm = null;
					continue;
				}
				$uniqueYearAndTerm = null;

				/************************************************************
				 * Problem Test 3
				 ************************************************************/
				for( $offset = 0; $offset <= $numberOfSemesters; $offset++ ) {

					$setFirstResult = 0;
					if( $offset != 0 ) {
						$setFirstResult = ( $offset * 4 );
					}

					$uniqueYearAndTerm = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
						->select( 'g.academicYear' )
						->addSelect( 'g.academicTerm' )
						->addSelect( 'g.courseTypeID' )
						->addSelect( 'COUNT(g.id) as total' )
						->where( 'g.submission = :submission' )
						->andWhere( 'g.academicTerm NOT LIKE :summer' )
						->groupBy( 'g.academicYear' )
						->addGroupBy( 'g.academicTerm' )
						->addGroupBy( 'g.courseTypeID' )
						->orderBy( 'g.academicYear' , 'DESC' )
						->addOrderBy( 'g.academicTerm' , 'DESC' )
						->addOrderBy( 'g.courseTypeID' , 'ASC' )
						->setParameter( 'summer' , '%summer%' )
						->setParameter( 'submission' , $submission )
						->setFirstResult( $setFirstResult )
						->setMaxResults( 4 )
						->getQuery()
						->getResult();

					if( count( $uniqueYearAndTerm ) != 4 ) {
						//No the right number of courseTypeIDs, break; out!

						$badGradeSubmission[$submission->getId()] = array(
							'submission' => $submission ,
							'error' => $problemType[3]
						);
						$badSubmission = true;
						break;
					}

					$currentYear = '';
					$currentTerm = '';
					$uniqueCourseIDChecker = array(
						3 => 0 ,
						4 => 0 ,
						7 => 0 ,
						9 => 0 ,
					);
					foreach( $uniqueYearAndTerm as $grades ) {
						if( $currentYear == '' ) {
							//Define the current year as it should not change!
							$currentYear = $grades['academicYear'];
						}
						if( $currentTerm == '' ) {
							$currentTerm = $grades['academicTerm'];
						}

						if( $currentYear != $grades['academicYear'] ) {
							$badSubmission = true;
							$badGradeSubmission[$submission->getId()] = array(
								'submission' => $submission ,
								'error' => $problemType[3]
							);
							break;
						}
						if( $currentTerm != $grades['academicTerm'] ) {
							$badSubmission = true;
							$badGradeSubmission[$submission->getId()] = array(
								'submission' => $submission ,
								'error' => $problemType[3]
							);
							break;
						}

						$uniqueCourseIDChecker[$grades['courseTypeID']] = $grades['total'];
					}
					if( $badSubmission ) {
						//Continue to the next Submission;
						$uniqueYearAndTerm = null;
						break;
					}

					foreach( $uniqueCourseIDChecker as $courseID => $total ) {
						if( $total != 1 ) {
							$badSubmission = true;
							$badGradeSubmission[$submission->getId()] = array(
								'submission' => $submission ,
								'error' => $problemType[3]
							);
							break;
						}
					}
					if( $badSubmission ) {
						//Continue to the next Submission;
						$uniqueYearAndTerm = null;
						break;
					}

					$uniqueCourseIDChecker = null;
					$uniqueYearAndTerm = null;
				}
				if( $badSubmission ) {
					//Continue to the next Submission;
					continue;
				}


				/************************************************************
				 * Problem Test 4
				 ************************************************************/
				$uniqueYearAndTerm = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
					->select( 'g.academicYear' )
					->where( 'g.submission = :submission' )
					->andWhere( 'g.academicTerm NOT LIKE :summer' )
					->groupBy( 'g.academicYear' )
					->orderBy( 'g.academicYear' , 'DESC' )
					->addOrderBy( 'g.academicTerm' , 'DESC' )
					->setParameter( 'summer' , '%summer%' )
					->setParameter( 'submission' , $submission )
					->getQuery()
					->getResult();
				$lastYearValue = '';
				foreach( $uniqueYearAndTerm as $grade ) {
					if( $lastYearValue == '' ) {
						$lastYearValue = $grade['academicYear'];
					}
					if( $grade['academicYear'] != $lastYearValue ) {
						if( $lastYearValue - 1 != $grade['academicYear'] ) {
							$badSubmission = true;

							$badGradeSubmission[$submission->getId()] = array(
								'submission' => $submission ,
								'error' => $problemType[4]
							);
							break;
						} else {
							$lastYearValue = $grade['academicYear'];
						}
					}
				}
				if( $badSubmission ) {
					//Continue to the next Submission;
					continue;
				}

				$uniqueYearAndTerm = null;

				/************************************************************
				 * Problem Test 5
				 ************************************************************/
				if( $submission->getNextGrade() == 9 ) {
					$uniqueYearAndTerm = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
						->select( 'g.academicTerm' )
						->where( 'g.submission = :submission' )
						->andWhere( 'g.academicTerm NOT LIKE :summer' )
						->groupBy( 'g.academicYear' )
						->addGroupBy( 'g.academicTerm' )
						->orderBy( 'g.academicYear' , 'DESC' )
						->addOrderBy( 'g.academicTerm' , 'DESC' )
						->setParameter( 'summer' , '%summer%' )
						->setParameter( 'submission' , $submission )
						->getQuery()
						->getResult();

					foreach( $uniqueYearAndTerm as $grade ) {
						if( preg_match( '/\b(middle)\b/i' , $grade['academicTerm'] ) ) {
							$badSubmission = true;

							$badGradeSubmission[$submission->getId()] = array(
								'submission' => $submission ,
								'error' => $problemType[5]
							);
							break;
						}
					}
				}
				if( $badSubmission ) {
					//Continue to the next Submission;
					continue;
				}


				/************************************************************
				 * Problem Test 6
				 ************************************************************/
				$uniqueYearAndTerm = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
					->select( 'g.academicYear' )
					->where( 'g.submission = :submission' )
					->andWhere( 'g.academicTerm NOT LIKE :summer' )
					->groupBy( 'g.academicYear' )
					->orderBy( 'g.academicYear' , 'DESC' )
					->addOrderBy( 'g.academicTerm' , 'DESC' )
					->setParameter( 'summer' , '%summer%' )
					->setParameter( 'submission' , $submission )
					->setMaxResults( 1 )
					->getQuery()
					->getResult();

				foreach( $uniqueYearAndTerm as $grade ) {
					if( $grade['academicYear'] != '2015' ) {
						$badSubmission = true;

						$badGradeSubmission[$submission->getId()] = array(
							'submission' => $submission ,
							'error' => $problemType[6]
						);
						break;
					}
				}
				if( $badSubmission ) {
					//Continue to the next Submission;
					continue;
				}

				$goodGradeSubmission[] = $submission;

			}

			$phpExcelObject = $this->get( 'phpexcel' )->createPHPExcelObject();
			$phpExcelObject->getProperties()->setCreator( "Image In A Box" )
				->setLastModifiedBy( "Image In A Box" )
				->setTitle( "MPS Active Submissions Good Grades by Program" )
				->setSubject( "Good Grades" )
				->setDescription( "Document needs to be completed in order for the Magnet Program website to run correctly." )
				->setKeywords( "mymagnetapp" )
				->setCategory( "good grades" );

			$row = 1;

			$activeSheet = $phpExcelObject->getActiveSheet();
			$activeSheet->setTitle( 'Good Grades' );
			if( count( $goodGradeSubmission ) > 0 ) {

				$eligibilityService = new EligibilityRequirementsService(
					$this->container->get( 'doctrine.orm.default_entity_manager' ) );

				$activeSheet->setCellValue( "A{$row}" , 'Submission ID' );
				$activeSheet->setCellValue( "B{$row}" , 'State ID' );
				$activeSheet->setCellValue( "C{$row}" , 'Last Name' );
				$activeSheet->setCellValue( "D{$row}" , 'First Name' );
				$activeSheet->setCellValue( "E{$row}" , 'Next Grade' );
				$activeSheet->setCellValue( "F{$row}" , 'Calculated Grade Average' );

				$column = 6;
				for( $column; $column <= 101; $column++ ) {

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'Year' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'Semester' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'CourseType' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'CourseName' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'Grade' );

				}
				$row++;

				foreach( $goodGradeSubmission as $submission ) {

					$magnetSchool = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findOneBy( array(
						'name' => $data['program'] ,
						'grade' => $submission->getNextGrade(),
						'openEnrollment' => $data['openenrollment']
					) );
					$eligibilityGrade = 'N/A';
					list( $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $eligibilityCheck ) = $eligibilityService->doesStudentPassRequirements( array( 'submissionID' => $submission->getId() ) , $magnetSchool );
					foreach( $eligibilityCheck as $key => $check ) {
						if( $check == 'GPA CHECK' ) {
							$eligibilityGrade = $eligibilityGrade[$key];
						}
					}

					$activeSheet->setCellValue( "A{$row}" , $submission->__toString() );
					$activeSheet->setCellValue( "B{$row}" , $submission->getStateID() );
					$activeSheet->setCellValue( "C{$row}" , $submission->getLastName() );
					$activeSheet->setCellValue( "D{$row}" , $submission->getFirstName() );
					$activeSheet->setCellValue( "E{$row}" , $submission->getNextGradeString() );
					$activeSheet->setCellValue( "F{$row}" , $eligibilityGrade );

					$maxNumberOfRecords = 4;
					if( $submission->getNextGrade() == 11 ) {
						$maxNumberOfRecords = 12;
					}
					if( $submission->getNextGrade() == 12 ) {
						$maxNumberOfRecords = 20;
					}

					$column = 6;
					$grades = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
						->where( 'g.academicTerm NOT LIKE :summer' )
						->andWhere( 'g.submission = :submission' )
						->orderBy( 'g.academicYear' , 'DESC' )
						->addOrderBy( 'g.academicTerm' , 'DESC' )
						->setParameter( 'summer' , '%summer%' )
						->setParameter( 'submission' , $submission )
						->setMaxResults( $maxNumberOfRecords )
						->getQuery()
						->getResult();

					foreach( $grades as $grade ) {

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getAcademicYear() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getAcademicTerm() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getCourseType() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getCourseName() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getNumericGrade() );
						$column++;
					}

					$row++;
				}

				$row++;
			} else {
				$activeSheet->setCellValue( "A{$row}" , 'No good submissions found.' );
			}

			if( count( $badGradeSubmission ) > 0 ) {
				$activeSheet = $phpExcelObject->createSheet();
				$activeSheet->setTitle( 'Bad Grades' );
				$row = 1;

				$activeSheet->setCellValue( "A{$row}" , 'Submission ID' );
				$activeSheet->setCellValue( "B{$row}" , 'State ID' );
				$activeSheet->setCellValue( "C{$row}" , 'Last Name' );
				$activeSheet->setCellValue( "D{$row}" , 'First Name' );
				$activeSheet->setCellValue( "E{$row}" , 'Next Grade' );
				$activeSheet->setCellValue( "F{$row}" , 'Error' );
				$row++;

				foreach( $badGradeSubmission as $id => $submissionArray ) {
					$activeSheet->setCellValue( "A{$row}" , $submissionArray['submission']->__toString() );
					$activeSheet->setCellValue( "B{$row}" , $submissionArray['submission']->getStateID() );
					$activeSheet->setCellValue( "C{$row}" , $submissionArray['submission']->getLastName() );
					$activeSheet->setCellValue( "D{$row}" , $submissionArray['submission']->getFirstName() );
					$activeSheet->setCellValue( "E{$row}" , $submissionArray['submission']->getNextGrade() );
					$activeSheet->setCellValue( "F{$row}" , $submissionArray['error'] );

					$row++;
				}
			}

			$writer = $this->get( 'phpexcel' )->createWriter( $phpExcelObject , 'Excel2007' );
			// create the response
			$response = $this->get( 'phpexcel' )->createStreamedResponse( $writer );
			// adding headers
			$response->headers->set( 'Content-Type' , 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename=grade-reporting-by-program.xlsx' );
			$response->headers->set( 'Pragma' , 'public' );
			$response->headers->set( 'Cache-Control' , 'maxage=1' );
			return $response;
		}

		$title = 'MPS Magnet Submissions Grade Reporting';
		$subtitle = 'Active Submissions Grade Reporting by Program (High School Only)';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle );
	}

	/**
	 * @Route( "/admin/report/after-processing-report/", name="admin_after_processing_report", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function generateAfterProcessingReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$programs = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Program' )->createQueryBuilder( 'p' )
			->orderBy( 'p.name' , 'ASC' )
			->getQuery()
			->getResult();

		$status = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->findBy( array(
			'id' => array( 2 , 3 , 6 , 7 , 8 , 9 )
		) );
		$grades = array(
			99 => 'PreK',
			0 => 'K',
			1 => '1' ,
			2 => '2' ,
			3 => '3' ,
			4 => '4' ,
			5 => '5' ,
			6 => '6' ,
			7 => '7' ,
			8 => '8' ,
			9 => '9' ,
			10 => '10' ,
			11 => '11' ,
			12 => '12' ,
		);

		$program_selector = [];
		foreach( $programs as $program ){
			if( isset( $program_selector[ $program->getOpenEnrollment()->getId() ] ) ) {
				$program_selector[$program->getOpenEnrollment()->getId()][] = $program->getId();
			} else {
				$program_selector[$program->getOpenEnrollment()->getId()] = [ $program->getId() ];
			}
		}

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'attr' => array( 'style' => 'margin-bottom: 25px;', 'data-programs' => json_encode( $program_selector ) ) ,
				'placeholder' => 'Choose an Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'program' , 'entity' , array(
				'class' => 'IIABMagnetBundle:Program' ,
				'label' => 'Select a Program' ,
				'required' => false ,
				'attr' => array( 'style' => 'margin-bottom: 25px;' ) ,
				'placeholder' => 'All Programs' ,
				'choices' => $programs ,
				'choice_attr' => function($val, $key, $index) {
					return ['class' => 'concealable'];
				}
			) )
			->add( 'grade' , 'choice' , array(
				'label' => 'Select a Specific Grade' ,
				'required' => false ,
				'attr' => array( 'style' => 'margin-bottom: 25px;' ) ,
				'placeholder' => 'All Grades' ,
				'choices' => $grades ,
			) )
			->add( 'submissionstatus' , 'entity' , array(
				'class' => 'IIABMagnetBundle:SubmissionStatus' ,
				'label' => 'Select a Status' ,
				'required' => true ,
				'choices' => $status ,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate After Processing Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );
		if( $form->isValid() ) {

			$data = $form->getData();

			if( $data['program'] != null ) {

				$program = ( $data['program']->getOpenEnrollment() == $data['openenrollment'] ) ? $data['program'] : $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Program' )->findOneBy( array(
					'name' => $data['program']->getName(),
					'openEnrollment' => $data['openenrollment']
				) );

				$magnetSchools = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findBy( array(
					'program' => $program
				) );

			} else {
				$magnetSchools = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findBy( array(
					'openEnrollment' => $data['openenrollment']
				) );
			}

			$submissionsQuery = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->where( 's.firstChoice IN (:schools) OR s.secondChoice IN (:schools) OR s.thirdChoice IN (:schools)' )
				->orderBy( 's.createdAt' , 'ASC' )
				->setParameter( 'schools' , $magnetSchools );

			if( $data['submissionstatus'] != '' ) {
				$submissionsQuery->andWhere( 's.submissionStatus = :status' )->setParameter( 'status' , $data['submissionstatus'] );

				//If the status has been set to Offered and Awarded, Offered and Accepted, or Offered and Declined, only pull those offers.
				if( in_array( $data['submissionstatus']->getId() , array( 6 , 7 , 8 ) ) ) {
					$submissionsQuery->leftJoin( 's.offered' , 'offered' )->andWhere('offered.awardedSchool IN (:schools)' );
				}

				//If the status has been set to In Waiting List
				if( $data['submissionstatus']->getId() == 9 ) {
					$submissionsQuery->leftJoin( 's.waitList' , 'waitlist' )->andWhere('waitlist.choiceSchool IN (:schools)' );
				}
			}
			if( $data['grade'] != '' ) {
				$submissionsQuery->andWhere('s.nextGrade = :grade')->setParameter( 'grade' , $data['grade'] );
			}

			$submissions = $submissionsQuery
				->getQuery()
				->getResult();

			$report_data = [];
			if( count( $submissions ) ) {

			    $report_data[] = [
			        'Submission Date',
                    'Confirmation Number',
                    'Enrollment Period',
                    'State ID',
                    'First Name',
                    'Last Name',
                    'Date of Birth',
                    'Address',
                    'City',
                    'Zip',
                    'Status',
                    'Grade',
                    'Race',
                    'Primary Phone Number',
                    'Alternate Phone Number',
                    'Email Address',
                    'Current School',
                    'Zoned Schools',
                    'First Choice',
                    'First Choice 1st SubChoice',
                    'Second Choice',
                    'Second Choice 1st SubChoice',
                    'Third Choice',
                    'Third Choice 1st SubChoice',
                    'Awarded School',
                    'Awarded School SubChoice',
                    'Wait List First School',
                    'Wait List Second School',
                    'Wait List Third School',
                    'Last Status Change Date'
                ];

				/** @var Submission $submission */
				foreach( $submissions as $submission ) {

                    //In Waiting List.
                    if( $submission->getSubmissionStatus()->getId() == 9 ) {
                        $waitListed = $this->getDoctrine()->getRepository('IIABMagnetBundle:WaitList')->findBy( array(
                            'openEnrollment' => $submission->getOpenEnrollment() ,
                            'submission' => $submission
                        ) );

                        foreach( $waitListed as $waitList ) {

                            if( $waitList->getChoiceSchool() != null ) {
                                $waiting[] = $waitList->getChoiceSchool()->__toString();
                            }
                        }
                    }

                    $lastStatusChange = $this->get( 'magnet.statusChanges' )->getLastStatusDateFormatted( $submission->getId() );

                    $report_data[] = [
                        $submission->getCreatedAtFormatted(),
                        $submission->__toString(),
                        $submission->getOpenEnrollment()->__toString(),
                        $submission->getStateID(),
                        $submission->getFirstName(),
                        $submission->getLastName(),
                        $submission->getBirthdayFormatted(),
                        $submission->getAddress(),
                        $submission->getCity(),
                        $submission->getZip(),
                        $submission->getSubmissionStatus()->__toString(),
                        $submission->getNextGradeString(),
                        $submission->getRaceFormatted(),
                        $submission->getPhoneNumber(),
                        $submission->getAlternateNumber(),
                        $submission->getParentEmail(),
                        $submission->getCurrentSchool(),
                        $submission->getZonedSchool(),
                        ( $submission->getFirstChoice() != null ) ? $submission->getFirstChoice()->__toString() : '',
                        $submission->getFirstChoiceFirstChoiceFocus(),
                        ( $submission->getSecondChoice() != null ) ? $submission->getSecondChoice()->__toString() : '',
                        $submission->getSecondChoiceFirstChoiceFocus(),
                        ( $submission->getThirdChoice() != null ) ? $submission->getThirdChoice()->__toString() : '',
                        $submission->getThirdChoiceFirstChoiceFocus(),
                        ( $submission->getOffered() != null ) ? $submission->getOffered()->getAwardedSchool()->__toString() : '',
                        ( $submission->getOffered() != null ) ? $submission->getOffered()->getAwardedFocusArea() : '',
                        ( !empty( $waiting[0] ) ) ? $waiting[0] : '',
                        ( !empty( $waiting[0] ) ) ? $waiting[1] : '',
                        ( !empty( $waiting[0] ) ) ? $waiting[2] : '',
                        ( !empty( $lastStatusChange ) ) ? $lastStatusChange : '',
                    ];
				}

			} else {
                $report_data = [ ['No submission found for this program.' ] ];
			}

            $response = new StreamedResponse();
            $response->setCallback(function() use( $report_data ) {
                $handle = fopen('php://output', 'w+');

                // Add the data queried from database
                foreach( $report_data as $row ){
                    fputcsv(
                        $handle, // The file pointer
                        $row
                    );
                }

                fclose($handle);
            });

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="after-processing-report-by-program.csv"');

			return $response;
		}

		$title = 'MPS After Processing Report';
		$subtitle = 'Report After Processing by Program';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle );
	}

	/**
	 * @Route( "/admin/report/submission-all-grades-report/", name="admin_report_all_grades_report", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function generateSubmissionsAllGradesReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$uniqueMagnetSchools = array();
		$uniqueMagnetSchoolsAllowed = [];
		$uniqueMagnetSchoolsResults = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
			->where( 'm.grade > 8 AND m.grade < 13' )
			->groupBy( 'm.name' )
			->addGroupBy( 'm.openEnrollment' )
			->orderBy( 'm.name' , 'ASC' )
			->addOrderBy( 'm.openEnrollment' , 'ASC' )
			->getQuery()
			->getResult();

		/** @var \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool */
		foreach( $uniqueMagnetSchoolsResults as $magnetSchool ) {
			$uniqueMagnetSchools[$magnetSchool->getOpenEnrollment()->getId()][] = [
				'id' => $magnetSchool->getName(),
				'text' => $magnetSchool->getName()
			];
			$uniqueMagnetSchoolsAllowed[$magnetSchool->getName()] = $magnetSchool->getName();
		}
		ksort( $uniqueMagnetSchools );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'attr' => array( 'style' => 'margin-bottom: 20px;' ) ,
				'placeholder' => 'Choose an Enrollment Period' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'program' , 'choice' , array(
				'label' => 'Select a Program' ,
				'required' => true ,
				'placeholder' => 'Choose a Magnet Program' ,
				'choices' => $uniqueMagnetSchoolsAllowed ,
				'attr' => [ 'data-choices' => json_encode( $uniqueMagnetSchools ) ]
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate All Grade Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );
		if( $form->isValid() ) {

			$data = $form->getData();

			$activeSubmission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 1 );

			$submissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->leftJoin( 's.firstChoice' , 'first_choice' )
				->leftJoin( 's.secondChoice' , 'second_choice' )
				->leftJoin( 's.thirdChoice' , 'third_choice' )
				->where( 's.openEnrollment = :enrollment' )
				->andWhere( 's.submissionStatus = :status' )
				->andWhere( 's.nextGrade > 8 AND s.nextGrade < 13' )
				->andWhere( 'first_choice.name LIKE :program OR second_choice.name LIKE :program OR third_choice.name LIKE :program' )
				->setParameters( array(
					'enrollment' => $data['openenrollment'] ,
					'status' => $activeSubmission ,
					'program' => $data['program']
				) )
				->orderBy( 's.nextGrade' , 'ASC' )
				->getQuery()
				->getResult();

			$badGradeSubmission = array();
			$goodGradeSubmission = array();

			$eligibilityService = new EligibilityRequirementsService(
				$this->container->get( 'doctrine.orm.default_entity_manager' ) );

			/** @var \IIAB\MagnetBundle\Entity\Submission $submission */
			foreach( $submissions as $submission ) {

				$magnetSchool = $this->getDoctrine()->getRepository('IIABMagnetBundle:MagnetSchool')->findOneBy( [
					'grade' => $submission->getNextGrade() ,
					'openEnrollment' => $data['openenrollment'] ,
					'name' => $data['program']
				] );

				list( $passRequirements , $passGradeArray , $passCourseTitle , $eligibilityCheck , $missingGrades ) = $eligibilityService->doesSubmissionPassRequirements( $submission , $magnetSchool );

				if( $missingGrades ) {
					$badGradeSubmission[$submission->getId()] = array(
						'submission' => $submission ,
						'error' => 'Issue with Grades'
					);
				} else {
					$goodGradeSubmission[] = $submission;
				}
			}

			$phpExcelObject = $this->get( 'phpexcel' )->createPHPExcelObject();
			$phpExcelObject->getProperties()->setCreator( "Image In A Box" )
				->setLastModifiedBy( "Image In A Box" )
				->setTitle( "MPS Active Submissions All Grades by Program" )
				->setSubject( "Good Grades" )
				->setDescription( "Document needs to be completed in order for the Magnet Program website to run correctly." )
				->setKeywords( "mymagnetapp" )
				->setCategory( "good grades" );

			$row = 1;

			$eligibilityService = new EligibilityRequirementsService(
				$this->container->get( 'doctrine.orm.default_entity_manager' ) );

			$activeSheet = $phpExcelObject->getActiveSheet();
			$activeSheet->setTitle( 'Good Grades' );
			if( count( $goodGradeSubmission ) > 0 ) {

				$activeSheet->setCellValue( "A{$row}" , 'Submission ID' );
				$activeSheet->setCellValue( "B{$row}" , 'State ID' );
				$activeSheet->setCellValue( "C{$row}" , 'Last Name' );
				$activeSheet->setCellValue( "D{$row}" , 'First Name' );
				$activeSheet->setCellValue( "E{$row}" , 'Next Grade' );
				$activeSheet->setCellValue( "F{$row}" , 'Current School' );
				$activeSheet->setCellValue( "G{$row}" , 'Committee Review Score' );
				$activeSheet->setCellValue( "H{$row}" , 'Calculated Grade Average' );

				$column = 8;
				for( $column; $column <= 103; $column++ ) {

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'Year' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'Semester' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'CourseType' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'CourseName' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'Grade' );

				}
				$row++;

				foreach( $goodGradeSubmission as $submission ) {

					$magnetSchool = $this->getDoctrine()->getRepository('IIABMagnetBundle:MagnetSchool')->findOneBy( [
						'grade' => $submission->getNextGrade() ,
						'openEnrollment' => $data['openenrollment'] ,
						'name' => $data['program']
					] );

					$eligibilityGrade = 'N/A';
					list( $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $eligibilityCheck , $missingGrades ) = $eligibilityService->doesSubmissionPassRequirements( $submission , $magnetSchool );
					foreach( $eligibilityCheck as $key => $check ) {
						if( $check == 'GPA CHECK' ) {
							$eligibilityGrade = $eligibilityGrade[$key];
						}
					}

					$committeeScore = '';
					if( $submission->getFirstChoice() != null && $data['program'] == $submission->getFirstChoice()->getName() ) {
						$committeeScore = $submission->getCommitteeReviewScoreFirstChoice();
					}
					if( $submission->getSecondChoice() != null && $data['program'] == $submission->getSecondChoice()->getName() ) {
						$committeeScore = $submission->getCommitteeReviewScoreSecondChoice();
					}
					if( $submission->getThirdChoice() != null && $data['program'] == $submission->getThirdChoice()->getName() ) {
						$committeeScore = $submission->getCommitteeReviewScoreThirdChoice();
					}

					$activeSheet->setCellValue( "A{$row}" , $submission->__toString() );
					$activeSheet->setCellValue( "B{$row}" , $submission->getStateID() );
					$activeSheet->setCellValue( "C{$row}" , $submission->getLastName() );
					$activeSheet->setCellValue( "D{$row}" , $submission->getFirstName() );
					$activeSheet->setCellValue( "E{$row}" , $submission->getNextGradeString() );
					$activeSheet->setCellValue( "F{$row}" , $submission->getCurrentSchool() );
					$activeSheet->setCellValue( "G{$row}" , $committeeScore );
					$activeSheet->setCellValue( "H{$row}" , $eligibilityGrade );

					$maxNumberOfRecords = 8;
					if( $submission->getNextGrade() == 11 ) {
						$maxNumberOfRecords = 8;
					}
					if( $submission->getNextGrade() == 12 ) {
						$maxNumberOfRecords = 16;
					}

					$column = 8;
					$grades = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
						->where( 'g.academicTerm NOT LIKE :summer' )
						->andWhere( 'g.submission = :submission' )
						->orderBy( 'g.academicYear' , 'DESC' )
						->addOrderBy( 'g.academicTerm' , 'DESC' )
						->setParameter( 'summer' , '%summer%' )
						->setParameter( 'submission' , $submission )
						->setMaxResults( $maxNumberOfRecords )
						->getQuery()
						->getResult();

					foreach( $grades as $grade ) {

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getAcademicYear() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getAcademicTerm() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getCourseType() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getCourseName() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getNumericGrade() );
						$column++;
					}

					$row++;
				}

				$row++;
			} else {
				$activeSheet->setCellValue( "A{$row}" , 'No good submissions found.' );
			}

			if( count( $badGradeSubmission ) > 0 ) {
				$activeSheet = $phpExcelObject->createSheet();
				$activeSheet->setTitle( 'Bad Grades' );
				$row = 1;
				$activeSheet->setCellValue( "A{$row}" , 'Submission ID' );
				$activeSheet->setCellValue( "B{$row}" , 'State ID' );
				$activeSheet->setCellValue( "C{$row}" , 'Last Name' );
				$activeSheet->setCellValue( "D{$row}" , 'First Name' );
				$activeSheet->setCellValue( "E{$row}" , 'Next Grade' );
				$activeSheet->setCellValue( "F{$row}" , 'Current School' );
				$activeSheet->setCellValue( "G{$row}" , 'Committee Review Score' );
				$activeSheet->setCellValue( "H{$row}" , 'Calculated Grade Average' );
				$activeSheet->setCellValue( "I{$row}" , 'Error' );

				$column = 9;
				for( $column; $column <= 104; $column++ ) {

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'Year' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'Semester' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'CourseType' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'CourseName' );
					$column++;

					$activeSheet->setCellValueByColumnAndRow( $column , $row , 'Grade' );

				}

				$row++;

				foreach( $badGradeSubmission as $id => $submissionArray ) {

					/** @var $submission Submission */
					$submission = $submissionArray['submission'];

					$magnetSchool = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findOneBy( array(
						'name' => $data['program'] ,
						'openEnrollment' => $data['openenrollment'] ,
						'grade' => $submission->getNextGrade()
					) );

					$eligibilityGrade = 'N/A';
					list( $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $eligibilityCheck , $missingGrades ) = $eligibilityService->doesStudentPassRequirements( array( 'submissionID' => $submission->getId() ) , $magnetSchool );
					foreach( $eligibilityCheck as $key => $check ) {
						if( $check == 'GPA CHECK' ) {
							$eligibilityGrade = $eligibilityGrade[$key];
						}
					}

					$committeeScore = '';
					if( $submission->getFirstChoice() != null && $data['program'] == $submission->getFirstChoice()->getName() ) {
						$committeeScore = $submission->getCommitteeReviewScoreFirstChoice();
					}
					if( $submission->getSecondChoice() != null && $data['program'] == $submission->getSecondChoice()->getName() ) {
						$committeeScore = $submission->getCommitteeReviewScoreSecondChoice();
					}
					if( $submission->getThirdChoice() != null && $data['program'] == $submission->getThirdChoice()->getName() ) {
						$committeeScore = $submission->getCommitteeReviewScoreThirdChoice();
					}

					$activeSheet->setCellValue( "A{$row}" , $submission->__toString() );
					$activeSheet->setCellValue( "B{$row}" , $submission->getStateID() );
					$activeSheet->setCellValue( "C{$row}" , $submission->getLastName() );
					$activeSheet->setCellValue( "D{$row}" , $submission->getFirstName() );
					$activeSheet->setCellValue( "E{$row}" , $submission->getNextGradeString() );
					$activeSheet->setCellValue( "F{$row}" , $submission->getCurrentSchool() );
					$activeSheet->setCellValue( "G{$row}" , $committeeScore );
					$activeSheet->setCellValue( "H{$row}" , $eligibilityGrade );
					$activeSheet->setCellValue( "I{$row}" , $submissionArray['error'] );

					$grades = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
						->where( 'g.academicTerm NOT LIKE :summer' )
						->andWhere( 'g.submission = :submission' )
						->orderBy( 'g.academicYear' , 'DESC' )
						->addOrderBy( 'g.academicTerm' , 'DESC' )
						->setParameter( 'summer' , '%summer%' )
						->setParameter( 'submission' , $submission )
						->getQuery()
						->getResult();

					$column = 9;
					foreach( $grades as $grade ) {

						$activeSheet->setCellValueByColumnAndRow( $column , 1 , 'Year' );
						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getAcademicYear() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , 1 , 'Semester' );
						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getAcademicTerm() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , 1 , 'CourseType' );
						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getCourseType() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , 1 , 'CourseName' );
						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getCourseName() );
						$column++;

						$activeSheet->setCellValueByColumnAndRow( $column , 1 , 'Grade' );
						$activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getNumericGrade() );
						$column++;
					}

					$grades = null;

					$row++;
				}
			}

			$writer = $this->get( 'phpexcel' )->createWriter( $phpExcelObject , 'Excel2007' );
			// create the response
			$response = $this->get( 'phpexcel' )->createStreamedResponse( $writer );
			// adding headers
			$response->headers->set( 'Content-Type' , 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename=all-grade-reporting-by-program.xlsx' );
			$response->headers->set( 'Pragma' , 'public' );
			$response->headers->set( 'Cache-Control' , 'maxage=1' );
			return $response;
		}

		$title = 'MPS Magnet Submissions All Grade Reporting';
		$subtitle = 'Active Submissions All Grade Reporting by Program (High School Only)';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle );
	}


	/**
	 * @Route("/admin/report/court-order-report/", name="admin_report_court_order", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 */
	public function courtOrderReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$title = 'Court Order Report';
		$subtitle = '';

		$form = $this->createFormBuilder()
			->add( 'openEnrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'attr' => array( 'style' => 'margin-bottom: 25px;' ) ,
				'placeholder' => 'Choose an Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Court Order Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();
			/** @var \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment */
			$openEnrollment = $data['openEnrollment'];

			$reportDataArray = [ ];

			//Pushing out to master before all other features are completed.
			//Backwards compabiltilty until OpenEnrollment Entity gets updated.
			if( is_callable( array( $openEnrollment , 'getPrograms') , false ) ) {
				$programs = $openEnrollment->getPrograms();
			} else {
				$programs = $this->getDoctrine()->getRepository('IIABMagnetBundle:Program')->findAll();
			}

			/** @var \IIAB\MagnetBundle\Entity\Program $program */
			foreach( $programs as $program ) {

				$magnetSchools = $program->getMagnetSchools();

				/** @var \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool */
				foreach( $magnetSchools as $magnetSchool ) {

					$currentPopulation = $this->getDoctrine()->getRepository('IIABMagnetBundle:CurrentPopulation')->findOneBy([ 'magnetSchool' => $magnetSchool ]);
					if( $currentPopulation->getMaxCapacity() > 0 ) {
						$schoolName = $magnetSchool->getName();
						if (!isset($reportDataArray[$schoolName])) {
							$reportDataArray[$schoolName] = [
								'Applicants' => [
									'Black' => 0,
									'White' => 0,
									'Other' => 0,
								],
								'Offered' => [
									'Black' => 0,
									'White' => 0,
									'Other' => 0,
								],
								'Ineligibility' => [
									'Black' => 0,
									'White' => 0,
									'Other' => 0,
								],
								'Committee' => [
									'Black' => 0,
									'White' => 0,
									'Other' => 0,
								],
								'Space' => [
									'Black' => 0,
									'White' => 0,
									'Other' => 0,
								],
								'Withdrew' => [
									'Black' => '',
									'White' => '',
									'Other' => '',
								],
								'Enrolled' => [
									'Black' => 0,
									'White' => 0,
									'Other' => 0,
								],
								'October' => [
									'Black' => '',
									'White' => '',
									'Other' => '',
								]
							];
						}
					}
				}
				$magnetSchools = null;
			}
			$programs = null;
			ksort( $reportDataArray );

			$submissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->findBy( [ 'openEnrollment' => $openEnrollment ] );

			/** @var \IIAB\MagnetBundle\Entity\Submission $submission */
			foreach( $submissions as $submission ) {

				$race = $submission->getRaceFormatted();

				$firstChoice = $submission->getFirstChoice();
				$secondChoice = $submission->getSecondChoice();
				$thirdChoice = $submission->getThirdChoice();

				$offered = $submission->getOffered();

				if( !empty( $firstChoice ) ) {
					$reportDataArray[$firstChoice->getName()]['Applicants'][$race]++;

					if( !empty( $offered ) && $offered->getAwardedSchool() == $firstChoice ) {

						$reportDataArray[$firstChoice->getName()]['Offered'][$race]++;

						if( $offered->getAccepted() ) {
							$reportDataArray[$firstChoice->getName()]['Enrolled'][$race]++;
						}
					}
				}

				if( !empty( $secondChoice ) ) {
					$reportDataArray[$secondChoice->getName()]['Applicants'][$race]++;

					if( !empty( $offered ) && $offered->getAwardedSchool() == $secondChoice ) {

						$reportDataArray[$secondChoice->getName()]['Offered'][$race]++;

						if( $offered->getAccepted() ) {
							$reportDataArray[$secondChoice->getName()]['Enrolled'][$race]++;
						}
					}
				}

				if( !empty( $thirdChoice ) ) {
					$reportDataArray[$thirdChoice->getName()]['Applicants'][$race]++;

					if( !empty( $offered ) && $offered->getAwardedSchool() == $thirdChoice ) {

						$reportDataArray[$thirdChoice->getName()]['Offered'][$race]++;

						if( $offered->getAccepted() ) {
							$reportDataArray[$thirdChoice->getName()]['Enrolled'][$race]++;
						}
					}
				}

				switch( $submission->getSubmissionStatus()->getId() ) {

					//denied due to space
					case 2:
						if( !empty( $firstChoice ) ) {
							$reportDataArray[$firstChoice->getName()]['Space'][$race]++;
						}
						if( !empty( $secondChoice ) ) {
							$reportDataArray[$secondChoice->getName()]['Space'][$race]++;
						}
						if( !empty( $thirdChoice ) ) {
							$reportDataArray[$thirdChoice->getName()]['Space'][$race]++;
						}
						break;

					//denied
					case 3:
						if( !empty( $firstChoice ) ) {
							$score = $submission->getCommitteeReviewScoreFirstChoice();
							if( empty( $score ) || $score < 2 ) {
								$reportDataArray[$firstChoice->getName()]['Committee'][$race]++;
							}
						}
						if( !empty( $secondChoice ) ) {
							$score = $submission->getCommitteeReviewScoreSecondChoice();
							if( empty( $score ) || $score < 2 ) {
								$reportDataArray[$secondChoice->getName()]['Committee'][$race]++;
							}
						}
						if( !empty( $thirdChoice ) ) {
							$score = $submission->getCommitteeReviewScoreThirdChoice();
							if( empty( $score ) || $score < 2 ) {
								$reportDataArray[$thirdChoice->getName()]['Committee'][$race]++;
							}
						}
						break;

					//inactive due ineligibility
					case 12:
						if( !empty( $firstChoice ) ) {
							$reportDataArray[$firstChoice->getName()]['Ineligibility'][$race]++;
						}
						if( !empty( $secondChoice ) ) {
							$reportDataArray[$secondChoice->getName()]['Ineligibility'][$race]++;
						}
						if( !empty( $thirdChoice ) ) {
							$reportDataArray[$thirdChoice->getName()]['Ineligibility'][$race]++;
						}
						break;
				}
			}

			$generationDate = date( 'm/d/Y g:i:s a' );

			$phpExcelObject = $this->get( 'phpexcel' )->createPHPExcelObject();
			$phpExcelObject->getProperties()->setCreator( "Image In A Box" )
				->setLastModifiedBy( "Image In A Box" )
				->setTitle( "MPS Court Order Report" )
				->setSubject( "Court Order " )
				->setDescription( "Generated on " . $generationDate )
				->setKeywords( "mymagnetapp" )
				->setCategory( "court order" );

			$row = 1;

			$activeSheet = $phpExcelObject->getActiveSheet();
			$activeSheet->setTitle( 'Court Order ' );

			$activeSheet->setCellValue( "A{$row}" , 'Report Owner: Director of Magnet Programs' );
			$activeSheet->setCellValue( "C{$row}" , 'Data Source: Magnet Application Website/ iNow' );
			$activeSheet->setCellValue( "D{$row}" , 'Consent Order Reference: II.F.1' );
			//$activeSheet->setCellValue( "F{$row}" , 'Revision Date:  5/5/2015' );
			$activeSheet->setCellValue( "F{$row}" , 'Revision Date: ' . $generationDate );
			$activeSheet->setCellValue( "U{$row}" , 'Data Generated Date/Time: ' . $generationDate );
			$activeSheet->mergeCells( "U{$row}:Y{$row}" );
			$row++;

			$activeSheet->setCellValue( "A{$row}" , 'Name of Magnet Program/School' );
			$activeSheet->mergeCells( "A{$row}:A" . ( $row + 1 ) );
			$activeSheet->setCellValue( "B{$row}" , 'Number of Applicants' );
			$activeSheet->mergeCells( "B{$row}:D{$row}" );
			$activeSheet->setCellValue( "E{$row}" , 'Number of Students Offered' );
			$activeSheet->mergeCells( "E{$row}:G{$row}" );
			$activeSheet->setCellValue( "H{$row}" , 'Number of Students Denied Due to Ineligibility' );
			$activeSheet->mergeCells( "H{$row}:J{$row}" );
			$activeSheet->setCellValue( "K{$row}" , 'Number of Students Denied Due to Committee Review' );
			$activeSheet->mergeCells( "K{$row}:M{$row}" );
			$activeSheet->setCellValue( "N{$row}" , 'Number of Students Denied Due to Space' );
			$activeSheet->mergeCells( "N{$row}:P{$row}" );
			$activeSheet->setCellValue( "Q{$row}" , 'Total Number of Students Withdrew/Transferred (include reasons)' );
			$activeSheet->mergeCells( "Q{$row}:S{$row}" );
			$activeSheet->setCellValue( "T{$row}" , 'Offered and Accepted Applications' );
			$activeSheet->mergeCells( "T{$row}:V{$row}" );
			$activeSheet->setCellValue( "W{$row}" , 'Total School Enrollment October 1' );
			$activeSheet->mergeCells( "W{$row}:Y{$row}" );
			$row++;

			$activeSheet->setCellValue( "B{$row}" , 'Black' );
			$activeSheet->setCellValue( "C{$row}" , 'White' );
			$activeSheet->setCellValue( "D{$row}" , 'Other' );

			$activeSheet->setCellValue( "E{$row}" , 'Black' );
			$activeSheet->setCellValue( "F{$row}" , 'White' );
			$activeSheet->setCellValue( "G{$row}" , 'Other' );

			$activeSheet->setCellValue( "H{$row}" , 'Black' );
			$activeSheet->setCellValue( "I{$row}" , 'White' );
			$activeSheet->setCellValue( "J{$row}" , 'Other' );

			$activeSheet->setCellValue( "K{$row}" , 'Black' );
			$activeSheet->setCellValue( "L{$row}" , 'White' );
			$activeSheet->setCellValue( "M{$row}" , 'Other' );

			$activeSheet->setCellValue( "N{$row}" , 'Black' );
			$activeSheet->setCellValue( "O{$row}" , 'White' );
			$activeSheet->setCellValue( "P{$row}" , 'Other' );

			$activeSheet->setCellValue( "Q{$row}" , 'Black' );
			$activeSheet->setCellValue( "R{$row}" , 'White' );
			$activeSheet->setCellValue( "S{$row}" , 'Other' );

			$activeSheet->setCellValue( "T{$row}" , 'Black' );
			$activeSheet->setCellValue( "U{$row}" , 'White' );
			$activeSheet->setCellValue( "V{$row}" , 'Other' );

			$activeSheet->setCellValue( "W{$row}" , 'Black' );
			$activeSheet->setCellValue( "X{$row}" , 'White' );
			$activeSheet->setCellValue( "Y{$row}" , 'Other' );
			$row++;

			foreach( $reportDataArray as $program => $data ) {

				$activeSheet->setCellValue( "A{$row}" , $program );

				$activeSheet->setCellValue( "B{$row}" , $data['Applicants']['Black'] );
				$activeSheet->setCellValue( "C{$row}" , $data['Applicants']['White'] );
				$activeSheet->setCellValue( "D{$row}" , $data['Applicants']['Other'] );

				$activeSheet->setCellValue( "E{$row}" , $data['Offered']['Black'] );
				$activeSheet->setCellValue( "F{$row}" , $data['Offered']['White'] );
				$activeSheet->setCellValue( "G{$row}" , $data['Offered']['Other'] );

				$activeSheet->setCellValue( "H{$row}" , $data['Ineligibility']['Black'] );
				$activeSheet->setCellValue( "I{$row}" , $data['Ineligibility']['White'] );
				$activeSheet->setCellValue( "J{$row}" , $data['Ineligibility']['Other'] );

				$activeSheet->setCellValue( "K{$row}" , $data['Committee']['Black'] );
				$activeSheet->setCellValue( "L{$row}" , $data['Committee']['White'] );
				$activeSheet->setCellValue( "M{$row}" , $data['Committee']['Other'] );

				$activeSheet->setCellValue( "N{$row}" , $data['Space']['Black'] );
				$activeSheet->setCellValue( "O{$row}" , $data['Space']['White'] );
				$activeSheet->setCellValue( "P{$row}" , $data['Space']['Other'] );

				$activeSheet->setCellValue( "Q{$row}" , $data['Withdrew']['Black'] );
				$activeSheet->setCellValue( "R{$row}" , $data['Withdrew']['White'] );
				$activeSheet->setCellValue( "S{$row}" , $data['Withdrew']['Other'] );

				$activeSheet->setCellValue( "T{$row}" , $data['Enrolled']['Black'] );
				$activeSheet->setCellValue( "U{$row}" , $data['Enrolled']['White'] );
				$activeSheet->setCellValue( "V{$row}" , $data['Enrolled']['Other'] );

				$activeSheet->setCellValue( "W{$row}" , $data['October']['Black'] );
				$activeSheet->setCellValue( "X{$row}" , $data['October']['White'] );
				$activeSheet->setCellValue( "Y{$row}" , $data['October']['Other'] );

				$row++;
			}

			$combinedArray = null;

			$writer = $this->get( 'phpexcel' )->createWriter( $phpExcelObject , 'Excel2007' );
			// create the response
			$response = $this->get( 'phpexcel' )->createStreamedResponse( $writer );
			// adding headers
			$response->headers->set( 'Content-Type' , 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename=court-order-report.xlsx' );
			$response->headers->set( 'Pragma' , 'public' );
			$response->headers->set( 'Cache-Control' , 'maxage=1' );
			return $response;
		}


		return [ 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle ];
	}

	/**
	 * @Template("@IIABMagnet/Report/reportData.html.twig")
	 *
	 * @param $submission
	 * @param $submissionGrades
	 * @param $school
	 * @param $race
	 * @param $passedEligibility
	 * @param $eligibilityGrade
	 * @param $eligibilityCourseTitle
	 * @param $missingGrades
     * @param $focus
	 *
	 * @return array
	 */
	public function generatePDFContentAction( $submission , $submissionGrades , $school , $race , $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $missingGrades, $focus = '' ) {

		return array(
			'submission' => $submission ,
			'grades' => $submissionGrades ,
			'choice' => $school ,
			'race' => $race ,
			'passedEligibility' => $passedEligibility ,
			'eligibilityGrade' => $eligibilityGrade ,
			'eligibilityCourseTitle' => $eligibilityCourseTitle ,
			'missingGrades' => $missingGrades ,
            'focus' => $focus
		);
	}

	/**
	 * @Route( "/admin/exit-application/" )
	 */
	public function exitApplicationAction() {
		return $this->redirect( $this->generateUrl( 'admin_submission_exitWithSaving' ) );
	}

	/**
	 * @Route( "/admin/report/check-after-population", options={"i18n"=false})
	 *
	 * @return array
	 */
	public function checkAfterPopulationNumbersAgainstOfferedAndAcceptedAction() {

		$orm = $this->getDoctrine()->getManager();

		$lastOpenEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneBy( [] , ['endingDate' => 'DESC'] );

		$programs = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Program' )->createQueryBuilder( 'p' )
			->where( 'p.openEnrollment = :openEnrollment')
			->setParameter('openEnrollment', $lastOpenEnrollment )
			->orderBy( 'p.name' , 'ASC' )
			->groupBy( 'p.name')
			->getQuery()
			->getResult();

		$programChecks = [];

		foreach( $programs as $program ) {

			$programChecks[$program->getId()] = [];

			/** @var \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool */
			foreach( $program->getMagnetSchools() as $magnetSchool ) {
				if( !isset( $programChecks[$program->getId()]['sums'] ) ) {
					$programChecks[$program->getId()]['sums'] = [
						'white' => 0 ,
						'black' => 0 ,
						'other' => 0 ,
					];
				}
				if( !isset( $programChecks[$program->getId()]['after'] ) ) {
					$programChecks[$program->getId()]['after'] = [
						'white' => 0 ,
						'black' => 0 ,
						'other' => 0 ,
					];
				}
				if( !isset( $programChecks[$program->getId()]['actual'] ) ) {
					$programChecks[$program->getId()]['actual'] = [
						'white' => 0 ,
						'black' => 0 ,
						'other' => 0 ,
					];
				}
				if( !isset( $programChecks[$program->getId()][$magnetSchool->getId()] ) ) {
					$programChecks[$program->getId()][$magnetSchool->getId()] = [
						'white' => 0 ,
						'black' => 0 ,
						'other' => 0 ,
					];
				}


				$currentPopulation = $orm->getRepository('IIABMagnetBundle:CurrentPopulation')->findOneBy( ['magnetSchool' => $magnetSchool ] );

				$afterPopulation = $orm->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findBy( [ 'magnetSchool' => $magnetSchool ] , [ 'lastUpdatedDateTime' => 'DESC' ] , 1 );
				if( $afterPopulation != null ) {
					/** @var \IIAB\MagnetBundle\Entity\AfterPlacementPopulation $afterPopulation */
					$afterPopulation = $afterPopulation[0];
				}

				$programChecks[$program->getId()][$magnetSchool->getId()]['white'] = $currentPopulation->getCPWhite();
				$programChecks[$program->getId()]['sums']['white'] += $currentPopulation->getCPWhite();
				if( $afterPopulation != null ) {
					$programChecks[$program->getId()]['after']['white'] += $afterPopulation->getCPWhite();
				} else {
					$programChecks[$program->getId()]['after']['white'] += $currentPopulation->getCPWhite();
				}

				$programChecks[$program->getId()][$magnetSchool->getId()]['black'] = $currentPopulation->getCPBlack();
				$programChecks[$program->getId()]['sums']['black'] += $currentPopulation->getCPBlack();
				if( $afterPopulation != null ) {
					$programChecks[$program->getId()]['after']['black'] += $afterPopulation->getCPBlack();
				} else {
					$programChecks[$program->getId()]['after']['black'] += $currentPopulation->getCPBlack();
				}

				$programChecks[$program->getId()][$magnetSchool->getId()]['other'] = $currentPopulation->getCPSumOther();
				$programChecks[$program->getId()]['sums']['other'] += $currentPopulation->getCPSumOther();
				if( $afterPopulation != null ) {
					$programChecks[$program->getId()]['after']['other'] += $afterPopulation->getCPSumOther();
				} else {
					$programChecks[$program->getId()]['after']['other'] += $currentPopulation->getCPSumOther();
				}

				$offers = $orm->getRepository('IIABMagnetBundle:Offered')->findBy( [ 'awardedSchool' => $magnetSchool , 'accepted' => 1 ] );

				foreach( $offers as $offer ) {
					switch( $offer->getSubmission()->getRaceFormatted() ) {
						case 'White':
							$programChecks[$program->getId()]['actual']['white']++;
							$programChecks[$program->getId()][$magnetSchool->getId()]['white']++;
							break;

						case 'Black':
							$programChecks[$program->getId()]['actual']['black']++;
							$programChecks[$program->getId()][$magnetSchool->getId()]['black']++;
							break;

						default:
							$programChecks[$program->getId()]['actual']['other']++;
							$programChecks[$program->getId()][$magnetSchool->getId()]['other']++;
							break;
					}
				}

				if( $afterPopulation != null ) {
					if( $afterPopulation->getCPWhite() != $programChecks[$program->getId()][$magnetSchool->getId()]['white'] ) {
						var_dump( "Magnet ID: {$magnetSchool->getId()} -- After ID: {$afterPopulation->getId()} -- Correct White to: " . $programChecks[$program->getId()][$magnetSchool->getId()]['white'] );
					}

					if( $afterPopulation->getCPBlack() != $programChecks[$program->getId()][$magnetSchool->getId()]['black'] ) {
						var_dump( "Magnet ID: {$magnetSchool->getId()} -- Correct Black to: " . $programChecks[$program->getId()][$magnetSchool->getId()]['black'] );
					}

					if( $afterPopulation->getCPSumOther() != $programChecks[$program->getId()][$magnetSchool->getId()]['other'] ) {
						var_dump( "Magnet ID: {$magnetSchool->getId()} -- Correct Other to: " . $programChecks[$program->getId()][$magnetSchool->getId()]['other'] );
					}
				}

				unset( $programChecks[$program->getId()][$magnetSchool->getId()] );
			}
		}


		var_dump( $programChecks );

		die('stopping because this is just error checking.');
	}

	/**
	 * Gets the Context array of information to be used in the Report.
	 *
	 * @param Submission   $submission
	 * @param MagnetSchool $magnetSchool
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getContext( Submission $submission , MagnetSchool $magnetSchool, $focus = '' ) {

		$eligibilityService = new EligibilityRequirementsService(
				$this->container->get( 'doctrine.orm.default_entity_manager' ) );

		list( $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $eligibilityCheck , $missingGrade ) = $eligibilityService->doesStudentPassRequirements( array( 'submissionID' => $submission->getId() ) , $magnetSchool );

		$submissionGrades = array();

		$academicYearsAndTerms = $eligibilityService->getAcademicYearsAndTerms( $submission , $magnetSchool );

		foreach( $academicYearsAndTerms as $academicYearTerm ) {
			$grades = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->findBy( array(
				'submission' => $submission ,
				'academicYear' => $academicYearTerm['academicYear'] ,
				'academicTerm' => $academicYearTerm['academicTerm'] ,
			) , array(
				'courseTypeID' => 'ASC'
			) );
			foreach( $grades as $grade ) {
				$submissionGrades[] = $grade;
			}
		}

		$context = array(
			'submission' => $submission ,
			'grades' => $submissionGrades ,
			'choice' => $magnetSchool ,
			'race' => $submission->getRaceFormatted() ,
			'passedEligibility' => $passedEligibility ,
			'eligibilityGrade' => '' ,
			'eligibilityCourseTitle' => '' ,
			'missingGrades' => $missingGrade ,
            'focus' => $focus
		);

		if( !empty( $eligibilityCheck ) ) {
			foreach( $eligibilityCheck as $key => $check ) {
				if( $check == 'GPA CHECK' ) {
					$context['eligibilityGrade'] = $eligibilityGrade[$key];
				}
				if( $check == 'COURSE TITLE CHECK' ) {
					$context['eligibilityCourseTitle'] = $eligibilityGrade[$key];
				}
			}
		}

		$eligibilityService = null;
		$submission = null;
		$submissionGrades = null;
		$magnetSchool = null;
		$passedEligibility = null;
		$eligibilityGrade = null;
		$eligibilityCourseTitle = null;

		return $context;
	}

	/**
	 * @param array $submissions
	 *
	 * @return array
	 */
	private function getGradeRankingOrder( $submissions = array() , MagnetSchool $magnetSchool ) {

		$gradeRanking = array();
		$finalGradeRanking = array();
		$eligibilityService = new EligibilityRequirementsService(
				$this->container->get( 'doctrine.orm.default_entity_manager' ) );

		foreach( $submissions as $submission ) {

			list( $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $eligibilityCheck ) = $eligibilityService->doesStudentPassRequirements( array( 'submissionID' => $submission->getId() ) , $magnetSchool );
			if( !empty($eligibilityCheck ) ){
				foreach( $eligibilityCheck as $key => $check ) {
					if( $check == 'GPA CHECK' ) {
						$gradeRanking[$submission->getId()] = $eligibilityGrade[$key];
					}
				}
			}

			$passedEligibility = null;
			$eligibilityGrade = null;
			$eligibilityCourseTitle = null;
			$eligibilityCheck = null;
		}

		arsort( $gradeRanking , SORT_NUMERIC );

		$ranking = 1;
		foreach( $gradeRanking as $ID => $rank ) {
			$finalGradeRanking[$ID] = $ranking;
			$ranking++;
		}

		$ranking = null;
		$gradeRanking = null;
		$eligibilityService = null;
		$submissions = null;
		$magnetSchool = null;

		return $finalGradeRanking;
	}


	/**
	 * @Route("/admin/report/racial-composition-report/", name="admin_report_racial_composition", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 */
	public function racialCompositionReport() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$title = 'Racial Composition Report';
		$subtitle = '';

		$form = $this->createFormBuilder()
			->add( 'openEnrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'attr' => array( 'style' => 'margin-bottom: 25px;' ) ,
				'placeholder' => 'Choose an Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Racial Composition Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();
			$openEnrollment = $data['openEnrollment'];

			$startingPopulations = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:CurrentPopulation' )->findBy( [ 'openEnrollment' => $openEnrollment ] );

			$acceptedOffers = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Offered' )->findBy( [ 'openEnrollment' => $openEnrollment , 'accepted' => 1 ] );

			$withdrawals = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Withdrawals' )->findBy( [ 'openEnrollment' => $openEnrollment ] );

			$endingPopulations = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findBy( [ 'openEnrollment' => $openEnrollment ] );

			$report_array = [];

			$now = new \DateTime(null, new \DateTimeZone('America/Chicago') );

			foreach( $endingPopulations as $ending_population ){

				$program_id = $ending_population->getMagnetSchool()->getProgram()->getId();
				$school_id = $ending_population->getMagnetSchool()->getId();

				if( empty( $report_array[$program_id][$school_id]['ending_population'] ) || $ending_population->getLastUpdatedDateTime() >  $report_array[$program_id][$school_id]['ending_population']->getLastUpdatedDateTime() ){
					$report_array[$program_id][$school_id]['ending_population'] = $ending_population;
				}
			}

			foreach( $startingPopulations as $starting_population ){

				$program_id = $starting_population->getMagnetSchool()->getProgram()->getId();
				$school_id = $starting_population->getMagnetSchool()->getId();

				$report_array[$program_id][$school_id]['starting_population'] = $starting_population;

				if( empty( $report_array[$program_id][$school_id]['ending_population'] ) ){
					$report_array[$program_id][$school_id]['ending_population'] = $starting_population;
				}

				$report_array[$program_id][$school_id]['accepted_offers'] = [];

				$report_array[$program_id][$school_id]['awarded_counts'] = [
					'black' => 0,
					'white' => 0,
					'other' => 0,
					'total' => 0
				];
				$report_array[$program_id][$school_id]['withdrawal_counts'] = [
					'black' => 0,
					'white' => 0,
					'other' => 0,
					'total' => 0
				];
			}

			foreach( $acceptedOffers as $accepted_offer ){

				$program_id = $accepted_offer->getAwardedSchool()->getProgram()->getId();
				$school_id = $accepted_offer->getAwardedSchool()->getId();

				$report_array[$program_id][$school_id]['accepted_offers'][] = $accepted_offer;

				switch( $accepted_offer->getSubmission()->getRaceFormatted() ) {

					case 'Black':
						$report_array[$program_id][$school_id]['awarded_counts']['black']++;
						break;

					case 'White':
						$report_array[$program_id][$school_id]['awarded_counts']['white']++;
						break;

					case 'Other':
						$report_array[$program_id][$school_id]['awarded_counts']['other']++;
						break;
				}
				$report_array[$program_id][$school_id]['awarded_counts']['total']++;
			}

			foreach( $withdrawals as $withdrawal ){

				$program_id = $withdrawal->getMagnetSchool()->getProgram()->getId();
				$school_id = $withdrawal->getMagnetSchool()->getId();

				$report_array[$program_id][$school_id]['withdrawals'][] = $withdrawal;

				$report_array[$program_id][$school_id]['withdrawal_counts']['black'] += $withdrawal->getCPBlack();
				$report_array[$program_id][$school_id]['withdrawal_counts']['white'] += $withdrawal->getCPWhite();
				$report_array[$program_id][$school_id]['withdrawal_counts']['other'] += $withdrawal->getCPOther();
				$report_array[$program_id][$school_id]['withdrawal_counts']['total'] += ( $withdrawal->getCPBlack() + $withdrawal->getCPWhite() + $withdrawal->getCPOther() );
			}

            $response = new StreamedResponse();

            $response->setCallback(function() use( $report_array, $openEnrollment ) {
                $handle = fopen('php://output', 'w+');

                $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
                $generationDate = $now->format('m/d/Y g:i:s a');
                fputcsv($handle, [ 'Note: Racial Composition report pulled from MPW on ' . $generationDate ]);

                fputcsv($handle, [
                    'Program',
                    '',
                    'Black',
                    'White',
                    'Other',
                    'Total',
                ]);

                $program_rows = [
                    'starting_population' => 'Starting Population',
                    'awarded_counts' => 'Offered and Accepted',
                    'withdrawal_counts' => 'Withdrawals',
                    'ending_population' => 'Ending Population'
                ];

                foreach( $report_array as $program_array ) {
                    $program_name = reset($program_array)['starting_population']->getMagnetSchool()->getProgram()->getName();
                    fputcsv($handle, [$program_name] );

                    foreach ($program_rows as $row_key => $row_name) {
                        $black = 0;
                        $white = 0;
                        $other = 0;
                        $total = 0;
                        foreach ($program_array as $school) {
                            if (is_array($school[$row_key])) {
                                $black += $school[$row_key]['black'];
                                $white += $school[$row_key]['white'];
                                $other += $school[$row_key]['other'];
                                $total += $school[$row_key]['total'];
                            } else {
                                $black += $school[$row_key]->getCPBlack();
                                $white += $school[$row_key]->getCPWhite();
                                $other += $school[$row_key]->getCPOther();
                                $total += $school[$row_key]->getCPSum();
                            }
                        }

                        fputcsv($handle, [
                            '',
                            $row_name,
                            $black,
                            $white,
                            $other,
                            $total
                        ]);
                    }

                    foreach ($program_array as $school) {
                        $black += $school['ending_population']->getCPBlack();
                        $white += $school['ending_population']->getCPWhite();
                        $other += $school['ending_population']->getCPOther();
                        $total += $school['ending_population']->getCPSum();
                    }

                    $black = number_format( ( ( $black / $total ) * 100 ) , 2 ) . '%';
                    $white = number_format( ( ( $white / $total ) * 100 ) , 2 ) . '%';
                    $other = number_format( ( ( $other / $total ) * 100 ) , 2 ) . '%';

                    fputcsv($handle,[
                        '','',
                        $black,
                        $white,
                        $other,
                    ]);
                    fputcsv($handle, [''] );
                }

               fclose( $handle );
            });

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="racial-composition-report.csv"');

			return $response;
		}

		return [ 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle ];
	}

	/**
	 * @Route("/admin/report/lottery-list-report/", name="admin_report_lottery_list_report", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 */
	public function lotteryListReport()
	{
		$request = $this->get('request_stack')->getCurrentRequest();
		$admin_pool = $this->get('sonata.admin.pool');

		$title = 'Lottery List Report';
		$subtitle = '';
		$downloadFiles = [];

		$session = $request->getSession();

		$openEnrollment_id = $request->get('form')['openEnrollment'];
		$openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-lottery-list-openEnrollment', 0 ) : $openEnrollment_id;
		$openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : null;

		$form = $this->createFormBuilder()
			->add('openEnrollment', 'entity', array(
				'class' => 'IIABMagnetBundle:OpenEnrollment',
				'label' => 'Enrollment',
				'data' => $openEnrollment,
				'required' => true,
				'attr' => array('style' => 'margin-bottom: 25px;'),
				'placeholder' => 'Choose an Enrollment Periods',
				'query_builder' => function (EntityRepository $er) {

					$query = $er->createQueryBuilder('enrollment')
						->orderBy('enrollment.year', 'ASC');

					return $query;
				},
			))
			->getForm();

		if ( !$openEnrollment_id ) {
			$form->add('load_lists', 'submit', array('label' => 'Find Lottery Lists', 'attr' => array('class' => 'btn btn-primary', 'style' => 'margin-top:20px;')));
		} else {

			$placement = $this->getDoctrine()->getRepository('IIABMagnetBundle:Placement')->findOneBy(
				[
					'openEnrollment' => $openEnrollment,
					'completed' => 1
				],
				['round' => 'DESC']
			);

			$round = ( empty($placement) ) ? null : $placement->getRound();
			//$choices = ( empty($round) ) ? ['lottery-list' => 'Standard Lottery'] : ['wait-list' => 'Wait-List', 'late-period-list' => 'Late-Lottery'];
            $choices = ( empty($round) ) ? ['Standard Lottery' => 'lottery-list'] : ['Wait-List' => 'wait-list'];

			$form->add('lotteryType', 'choice', array(
				'choices' => $choices,
				'label' => 'Preview List Type',
				'required' => false,
				'validation_groups' => false,
				'placeholder' => 'Choose list to generate',
			))
			->add('generate_report', 'submit', array('label' => 'Generate Preview List', 'attr' => array('class' => 'btn btn-primary', 'style' => 'margin-top:20px;')));

			$downloadDirectory = '/reports/lottery-list/';
			$previewDIR = $this->container->get('kernel')->getRootDir() . '/../web' . $downloadDirectory . 'preview/' ;

			if (file_exists($previewDIR)) {
				$previewFiles = array_diff(scandir($previewDIR, SCANDIR_SORT_ASCENDING), array('..', '.', '.DS_Store'));
				if ($previewFiles) {
					$downloadFiles[] = [
						'header' => 'Lottery List Previews',
						'files' => $previewFiles,
						'directory' => $downloadDirectory . 'preview/',
					];
				}
			}


			$completedDIR = $this->container->get('kernel')->getRootDir() . '/../web' . $downloadDirectory . $openEnrollment_id . '/';
			if (file_exists($completedDIR)) {
				$completedFiles = array_diff(scandir($completedDIR, SCANDIR_SORT_ASCENDING), array('..', '.', '.DS_Store'));
				if ($completedFiles) {

					usort($completedFiles, function ($a, $b) {
						return (substr($a, -21) > substr($b, -21)) ? -1 : 1;
					});

					$downloadFiles[] = [
						'header' => 'Completed Lottery Lists',
						'files' => $completedFiles,
						'directory' => $downloadDirectory . $openEnrollment_id . '/',
					];
				}
			}
		}

		$form->handleRequest($request);

		if ($form->isValid()) {

			$data = $form->getData();

			$openEnrollment = $data['openEnrollment'];

			$session->set( 'admin-lottery-list-openEnrollment', $openEnrollment->getId() );

			if ($form->get('generate_report')->isClicked() && isset($data['lotteryType']) && $data['lotteryType']) {

				$process = new Process();
				$process->setEvent('download');
				$process->setType($data['lotteryType']);
				$process->setOpenEnrollment($openEnrollment);

				$this->getDoctrine()->getManager()->persist($process);
				$this->getDoctrine()->getManager()->flush();

				return $this->redirect( $this->generateUrl( 'admin_report_lottery_list_report' ) );
			}
		}

		return [
			'form' => $form->createView(),
			'admin_pool' => $admin_pool,
			'title' => $title,
			'subtitle' => $subtitle,
			'downloadFiles' => $downloadFiles
		];
	}

    /**
     * @Route("/admin/report/missing-grades-all/", name="admin_report_missing_grades_report_all", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function missingGradesReportAll() {

        $request = $this->get('request_stack')->getCurrentRequest();
        $admin_pool = $this->get('sonata.admin.pool');

        $title = 'Missing Grade Report';
        $subtitle = '';
        $downloadFiles = [];

        $session = $request->getSession();

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-grades-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : null;

        $form = $this->createFormBuilder()
            ->add('openEnrollment', 'entity', array(
                'class' => 'IIABMagnetBundle:OpenEnrollment',
                'label' => 'Enrollment',
                'data' => $openEnrollment,
                'required' => true,
                'attr' => array('style' => 'margin-bottom: 25px;'),
                'placeholder' => 'Choose an Enrollment Periods',
                'query_builder' => function (EntityRepository $er) {

                    $query = $er->createQueryBuilder('enrollment')
                        ->orderBy('enrollment.year', 'ASC');

                    return $query;
                },
            ))
            ->getForm();

        if ( !$openEnrollment_id ) {
            $form->add('load_lists', 'submit', array('label' => 'Find Missing Grades', 'attr' => array('class' => 'btn btn-primary', 'style' => 'margin-top:20px;')));
        } else {
            //**************************************************************************

            $em = $this->getDoctrine()->getManager();
            $connection = $em->getConnection();
            $statement = $connection->prepare("select
                    s.id,
                    s.stateID,
                    s.submissionStatus,
                    s.next_grade,
                    s.current_grade,
                    s.currentSchool,
                    s.lastName,
                    s.firstName,
                    s.next_grade,

                    ms.name as firstChoice,

                    ms01.numericGrade as y16_s1_math,
                    ss01.numericGrade as y16_s1_science,
                    ts01.numericGrade as y16_s1_social,
                    es01.numericGrade as y16_s1_english,

                    mw01.numericGrade as y16_w1_math,
                    sw01.numericGrade as y16_w1_science,
                    rw01.numericGrade as y16_w1_reading,
                    tw01.numericGrade as y16_w1_social,
                    ew01.numericGrade as y16_w1_english,

                    mw02.numericGrade as y16_w2_math,
                    sw02.numericGrade as y16_w2_science,
                    rw02.numericGrade as y16_w2_reading,
                    tw02.numericGrade as y16_w2_social,
                    ew02.numericGrade as y16_w2_english,

                    ms11.numericGrade as y15_s1_math,
                    ss11.numericGrade as y15_s1_science,
                    rs11.numericGrade as y15_s1_reading,
                    ts11.numericGrade as y15_s1_social,
                    es11.numericGrade as y15_s1_english,

                    ms12.numericGrade as y15_s2_math,
                    ss12.numericGrade as y15_s2_science,
                    rs12.numericGrade as y15_s2_reading,
                    ts12.numericGrade as y15_s2_social,
                    es12.numericGrade as y15_s2_english,

                    mw11.numericGrade as y15_w1_math,
                    sw11.numericGrade as y15_w1_science,
                    rw11.numericGrade as y15_w1_reading,
                    tw11.numericGrade as y15_w1_social,
                    ew11.numericGrade as y15_w1_english,

                    mw12.numericGrade as y15_w2_math,
                    sw12.numericGrade as y15_w2_science,
                    rw12.numericGrade as y15_w2_reading,
                    tw12.numericGrade as y15_w2_social,
                    ew12.numericGrade as y15_w2_english,

                    mw13.numericGrade as y15_w3_math,
                    sw13.numericGrade as y15_w3_science,
                    rw13.numericGrade as y15_w3_reading,
                    tw13.numericGrade as y15_w3_social,
                    ew13.numericGrade as y15_w3_english,

                    mw14.numericGrade as y15_w4_math,
                    sw14.numericGrade as y15_w4_science,
                    rw14.numericGrade as y15_w4_reading,
                    tw14.numericGrade as y15_w4_social,
                    ew14.numericGrade as y15_w4_english
                from submission as s

                left join ( SELECT
                        submission_id,
                        count( id ) as id_count
                    FROM submissiongrade
                    GROUP BY submission_id
                ) as sgc
                    on sgc.submission_id = s.id

                left join magnetschool as ms
                    on ms.id = s.firstChoice

                left join submissiondata as gpa
                    on s.id = gpa.submission_id and gpa.meta_key = 'calculated_gpa'

                left join submissiongrade as ms01
                    on s.id = ms01.submission_id and ms01.academicYear = 0 and ms01.academicTerm = 'semester 1' and ms01.courseType='math'
                left join submissiongrade as ss01
                    on s.id = ss01.submission_id and ss01.academicYear = 0 and ss01.academicTerm = 'semester 1' and ss01.courseType='science'
                left join submissiongrade as rs01
                    on s.id = rs01.submission_id and rs01.academicYear = 0 and rs01.academicTerm = 'semester 1' and rs01.courseType='reading'
                left join submissiongrade as ts01
                    on s.id = ts01.submission_id and ts01.academicYear = 0 and ts01.academicTerm = 'semester 1' and ts01.courseType='social'
                left join submissiongrade as es01
                    on s.id = es01.submission_id and es01.academicYear = 0 and es01.academicTerm = 'semester 1' and es01.courseType='english'

                left join submissiongrade as mw01
                    on s.id = mw01.submission_id and mw01.academicYear = 0 and mw01.academicTerm = '1st 9 weeks' and mw01.courseType='math'
                left join submissiongrade as sw01
                    on s.id = sw01.submission_id and sw01.academicYear = 0 and sw01.academicTerm = '1st 9 weeks' and sw01.courseType='science'
                left join submissiongrade as rw01
                    on s.id = rw01.submission_id and rw01.academicYear = 0 and rw01.academicTerm = '1st 9 weeks' and rw01.courseType='reading'
                left join submissiongrade as tw01
                    on s.id = tw01.submission_id and tw01.academicYear = 0 and tw01.academicTerm = '1st 9 weeks' and tw01.courseType='social'
                left join submissiongrade as ew01
                    on s.id = ew01.submission_id and ew01.academicYear = 0 and ew01.academicTerm = '1st 9 weeks' and ew01.courseType='english'

                left join submissiongrade as mw02
                    on s.id = mw02.submission_id and mw02.academicYear = 0 and mw02.academicTerm = '2nd 9 weeks' and mw02.courseType='math'
                left join submissiongrade as sw02
                    on s.id = sw02.submission_id and sw02.academicYear = 0 and sw02.academicTerm = '2nd 9 weeks' and sw02.courseType='science'
                left join submissiongrade as rw02
                    on s.id = rw02.submission_id and rw02.academicYear = 0 and rw02.academicTerm = '2nd 9 weeks' and rw02.courseType='reading'
                left join submissiongrade as tw02
                    on s.id = tw02.submission_id and tw02.academicYear = 0 and tw02.academicTerm = '2nd 9 weeks' and tw02.courseType='social'
                left join submissiongrade as ew02
                    on s.id = ew02.submission_id and ew02.academicYear = 0 and ew02.academicTerm = '2nd 9 weeks' and ew02.courseType='english'

                left join submissiongrade as ms11
                    on s.id = ms11.submission_id and ms11.academicYear = -1 and ms11.academicTerm = 'semester 1' and ms11.courseType='math'
                left join submissiongrade as ss11
                    on s.id = ss11.submission_id and ss11.academicYear = -1 and ss11.academicTerm = 'semester 1' and ss11.courseType='science'
                left join submissiongrade as rs11
                    on s.id = rs11.submission_id and rs11.academicYear = -1 and rs11.academicTerm = 'semester 1' and rs11.courseType='reading'
                left join submissiongrade as ts11
                    on s.id = ts11.submission_id and ts11.academicYear = -1 and ts11.academicTerm = 'semester 1' and ts11.courseType='social'
                left join submissiongrade as es11
                    on s.id = es11.submission_id and es11.academicYear = -1 and es11.academicTerm = 'semester 1' and es11.courseType='english'

                left join submissiongrade as ms12
                    on s.id = ms12.submission_id and ms12.academicYear = -1 and ms12.academicTerm = 'semester 2' and ms12.courseType='math'
                left join submissiongrade as ss12
                    on s.id = ss12.submission_id and ss12.academicYear = -1 and ss12.academicTerm = 'semester 2' and ss12.courseType='science'
                left join submissiongrade as rs12
                    on s.id = rs12.submission_id and rs12.academicYear = -1 and rs12.academicTerm = 'semester 2' and rs12.courseType='reading'
                left join submissiongrade as ts12
                    on s.id = ts12.submission_id and ts12.academicYear = -1 and ts12.academicTerm = 'semester 2' and ts12.courseType='social'
                left join submissiongrade as es12
                    on s.id = es12.submission_id and es12.academicYear = -1 and es12.academicTerm = 'semester 2' and es12.courseType='english'

                left join submissiongrade as mw11
                    on s.id = mw11.submission_id and mw11.academicYear = -1 and mw11.academicTerm = '1st 9 weeks' and mw11.courseType='math'
                left join submissiongrade as sw11
                    on s.id = sw11.submission_id and sw11.academicYear = -1 and sw11.academicTerm = '1st 9 weeks' and sw11.courseType='science'
                left join submissiongrade as rw11
                    on s.id = rw11.submission_id and rw11.academicYear = -1 and rw11.academicTerm = '1st 9 weeks' and rw11.courseType='reading'
                left join submissiongrade as tw11
                    on s.id = tw11.submission_id and tw11.academicYear = -1 and tw11.academicTerm = '1st 9 weeks' and tw11.courseType='social'
                left join submissiongrade as ew11
                    on s.id = ew11.submission_id and ew11.academicYear = -1 and ew11.academicTerm = '1st 9 weeks' and ew11.courseType='english'

                left join submissiongrade as mw12
                    on s.id = mw12.submission_id and mw12.academicYear = -1 and mw12.academicTerm = '2nd 9 weeks' and mw12.courseType='math'
                left join submissiongrade as sw12
                    on s.id = sw12.submission_id and sw12.academicYear = -1 and sw12.academicTerm = '2nd 9 weeks' and sw12.courseType='science'
                left join submissiongrade as rw12
                    on s.id = rw12.submission_id and rw12.academicYear = -1 and rw12.academicTerm = '2nd 9 weeks' and rw12.courseType='reading'
                left join submissiongrade as tw12
                    on s.id = tw12.submission_id and tw12.academicYear = -1 and tw12.academicTerm = '2nd 9 weeks' and tw12.courseType='social'
                left join submissiongrade as ew12
                    on s.id = ew12.submission_id and ew12.academicYear = -1 and ew12.academicTerm = '2nd 9 weeks' and ew12.courseType='english'

                left join submissiongrade as mw13
                    on s.id = mw13.submission_id and mw13.academicYear = -1 and mw13.academicTerm = '3rd 9 weeks' and mw13.courseType='math'
                left join submissiongrade as sw13
                    on s.id = sw13.submission_id and sw13.academicYear = -1 and sw13.academicTerm = '3rd 9 weeks' and sw13.courseType='science'
                left join submissiongrade as rw13
                    on s.id = rw13.submission_id and rw13.academicYear = -1 and rw13.academicTerm = '3rd 9 weeks' and rw13.courseType='reading'
                left join submissiongrade as tw13
                    on s.id = tw13.submission_id and tw13.academicYear = -1 and tw13.academicTerm = '3rd 9 weeks' and tw13.courseType='social'
                left join submissiongrade as ew13
                    on s.id = ew13.submission_id and ew13.academicYear = -1 and ew13.academicTerm = '3rd 9 weeks' and ew13.courseType='english'

                left join submissiongrade as mw14
                    on s.id = mw14.submission_id and mw14.academicYear = -1 and mw14.academicTerm = '4th 9 weeks' and mw14.courseType='math'
                left join submissiongrade as sw14
                    on s.id = sw14.submission_id and sw14.academicYear = -1 and sw14.academicTerm = '4th 9 weeks' and sw14.courseType='science'
                left join submissiongrade as rw14
                    on s.id = rw14.submission_id and rw14.academicYear = -1 and rw14.academicTerm = '4th 9 weeks' and rw14.courseType='reading'
                left join submissiongrade as tw14
                    on s.id = tw14.submission_id and tw14.academicYear = -1 and tw14.academicTerm = '4th 9 weeks' and tw14.courseType='social'
                left join submissiongrade as ew14
                    on s.id = ew14.submission_id and ew14.academicYear = -1 and ew14.academicTerm = '4th 9 weeks' and ew14.courseType='english'

                where gpa.meta_value IS NULL

                and s.openEnrollment = :oe_id

                and s.submissionStatus IN (1,5)

                order by s.current_grade ASC;
            ");
            $statement->bindValue('oe_id', $openEnrollment_id);
            $statement->execute();
            $results = $statement->fetchAll();

            $response = new StreamedResponse();
            $response->setCallback(function() use( $results, $openEnrollment ) {
                $handle = fopen('php://output', 'w+');

                $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
                $generationDate = $now->format( 'm/d/Y g:i:s a' );
                fputcsv($handle, ['Note: All Schools Submission Missing Grades report pulled from MPW on ' . $generationDate] );

                    fputcsv($handle, [
                    'Submission ID',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'First Choice School',
                    'Number of Missing Grades',
                ]);

                $count_titles = [
                    1 => '1st',
                    2 => '2nd',
                    3 => '3rd',
                    4 => '4th'
                ];

                $terms = [];
                foreach( $results as $row_data ){

                    $grade_level = $row_data[ 'current_grade' ];

                    switch( $grade_level ){
                        case 1:
                            $terms = [
                                16 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science', 'reading' ],
                                    'semesters' => [],
                                    '9_weeks' => [1,2]
                                ]
                            ];
                            break;

                        case 2:
                        case 3:
                        case 4:
                        case 5:

                            $terms = [
                                16 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science', 'reading' ],
                                    'semesters' => [],
                                    '9_weeks' => [1,2]
                                ],
                                15 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science', 'reading' ],
                                    'semesters' => [],
                                    '9_weeks' => [1,2,3,4]
                                ]
                            ];
                            break;
                        case 6:

                            $terms = [
                                16 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science', 'reading' ],
                                    'semesters' => [],
                                    '9_weeks' => [1,2]
                                ],
                                15 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science'],
                                    'semesters' => [],
                                    '9_weeks' => [1,2,3,4]
                                ]
                            ];
                            break;

                        case 7:

                            $terms = [
                                16 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science' ],
                                    'semesters' => [],
                                    '9_weeks' => [1,2]
                                ],
                                15 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science' ],
                                    'semesters' => [],
                                    '9_weeks' => [1,2,3,4]
                                ]
                            ];
                            break;

                        case 8:

                            $terms = [
                                16 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science' ],
                                    'semesters' => [],
                                    '9_weeks' => [1,2]
                                ],
                                15 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science' ],
                                    'semesters' => [],
                                    '9_weeks' => [1,2,3,4]
                                ]
                            ];
                            break;

                        case 9:

                            $terms = [
                                16 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science' ],
                                    'semesters' => [1],
                                    '9_weeks' => []
                                ],
                                15 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science' ],
                                    'semesters' => [],
                                    '9_weeks' => [1,2,3,4]
                                ]
                            ];
                            break;

                        case 10:
                        case 11:
                        case 12:
                            $terms = [
                                16 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science' ],
                                    'semesters' => [1],
                                    '9_weeks' => []
                                ],
                                15 => [
                                    'subjects' => [ 'math', 'social', 'english', 'science' ],
                                    'semesters' => [1,2],
                                    '9_weeks' => []
                                ]
                            ];
                            break;
                    }

                    $missing_count = 0;
                    $missing_grades = [];
                    $submissionStatus = [];
                    foreach( $terms as $year => $grades ){

                        $offset = ( $year == 15 ) ? -1 : 0;
                        $academic_year = $openEnrollment->getOffsetYear( $offset );

                        foreach( $grades['subjects'] as $subject ){

                            foreach( $grades['semesters'] as $semester ){

                                if( !$row_data[ 'y'.$year.'_s'.$semester.'_'.$subject ] ){
                                    $missing_count++;
                                    $missing_grades[] = implode( ' / ', [ $academic_year, $count_titles[ $semester ] .' Semester', ucfirst( $subject ) ] );
                                }

                            }

                            foreach( $grades['9_weeks'] as $week ){

                                if( !$row_data[ 'y'.$year.'_w'.$week.'_'.$subject ] ){
                                    $missing_count++;
                                    $missing_grades[] = implode( ' / ', [ $academic_year, $count_titles[ $week ] .' 9 Weeks', ucfirst( $subject ) ] );
                                }

                            }
                        }
                    }

                    if( $missing_count > 0 ){

                        if( empty( $submissionStatus[ $row_data['submissionStatus'] ] ) ){
                            $submissionStatus[ $row_data['submissionStatus'] ] = $this->getDoctrine()->getRepository('IIABMagnetBundle:SubmissionStatus')->find( $row_data['submissionStatus'] );
                        }

                        $row = array_merge( [
                            $row_data['id'],
                            $submissionStatus[ $row_data['submissionStatus'] ],
                            $row_data['stateID'],
                            $row_data['lastName'],
                            $row_data['firstName'],
                            $row_data['next_grade'],
                            $row_data['currentSchool'],
                            $row_data['firstChoice'],
                            $missing_count
                        ], $missing_grades );
                        fputcsv( $handle, $row );
                    }
                }

                fclose($handle);
            });

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="Missing_Grades.csv"');

            return $response;

            //**************************************************************************
        }

        $form->handleRequest($request);

        if ($form->isValid()) {

            $data = $form->getData();

            $openEnrollment = $data['openEnrollment'];

            $session->set( 'admin-missing-grades-openEnrollment', $openEnrollment->getId() );

            if ($form->get('generate_report')->isClicked() && isset($data['lotteryType']) && $data['lotteryType']) {

                $process = new Process();
                $process->setEvent('download');
                $process->setType($data['lotteryType']);
                $process->setOpenEnrollment($openEnrollment);

                $this->getDoctrine()->getManager()->persist($process);
                $this->getDoctrine()->getManager()->flush();

                return $this->redirect( $this->generateUrl( 'admin_report_missing_grades_report' ) );
            }
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @Route("/admin/report/duplicate-courses-all/", name="admin_report_duplicate_courses_report_all", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function duplicateCoursesReportAll() {

        $request = $this->get('request_stack')->getCurrentRequest();
        $admin_pool = $this->get('sonata.admin.pool');

        $title = 'Duplicate Courses Report';
        $subtitle = '';
        $downloadFiles = [];

        $session = $request->getSession();

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-duplicate-courses-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : null;

        $form = $this->createFormBuilder()
            ->add('openEnrollment', 'entity', array(
                'class' => 'IIABMagnetBundle:OpenEnrollment',
                'label' => 'Enrollment',
                'data' => $openEnrollment,
                'required' => true,
                'attr' => array('style' => 'margin-bottom: 25px;'),
                'placeholder' => 'Choose an Enrollment Periods',
                'query_builder' => function (EntityRepository $er) {

                    $query = $er->createQueryBuilder('enrollment')
                        ->orderBy('enrollment.year', 'ASC');

                    return $query;
                },
            ))
            ->getForm();

        if ( !$openEnrollment_id ) {
            $form->add('load_lists', 'submit', array('label' => 'Find Duplicate Courses', 'attr' => array('class' => 'btn btn-primary', 'style' => 'margin-top:20px;')));
        } else {
            //**************************************************************************

            $current_year = explode( '-', $openEnrollment->getYear() );

            $academicYears = [];
            if( count( $current_year ) == 2 ){
                foreach( [0,-1] as $index => $offset ){
                    $academic_year = ($current_year[0] + ( $offset - 1) ) .'-'. ($current_year[1] + ( $offset - 1) );
                    $academicYears[ $index ] = $academic_year;
                }
            }

            $em = $this->getDoctrine()->getManager();
            $connection = $em->getConnection();
            $statement = $connection->prepare("SELECT
                    g.submission_id,
                    s.current_grade,
                    g.academicYear,
                    g.academicTerm,
                    g.courseType,
                    count( g.courseType ) as `count`,
                    group_concat( g.courseName separator ' -- ') as course_names
                FROM   submissiongrade as g
                  LEFT JOIN submission as s
                    ON s.id = g.submission_id
                GROUP  BY submission_id, academicYear, academicTerm, courseType
                HAVING COUNT(g.id) > 1;
            ");
            $statement->execute();
            $results = $statement->fetchAll();

            $report_data = [];
            foreach( $results as $result ){

                $grade_level = $result[ 'current_grade' ];

                switch( $grade_level ) {
                    case 1:
                        $terms = [
                            16 => [
                                'subjects' => ['math', 'social', 'english', 'science', 'reading'],
                                'semesters' => [],
                                '9_weeks' => [1, 2]
                            ]
                        ];
                        break;

                    case 2:
                    case 3:
                    case 4:
                    case 5:

                        $terms = [
                            16 => [
                                'subjects' => ['math', 'social', 'english', 'science', 'reading'],
                                'semesters' => [],
                                '9_weeks' => [1, 2]
                            ],
                            15 => [
                                'subjects' => ['math', 'social', 'english', 'science', 'reading'],
                                'semesters' => [],
                                '9_weeks' => [1, 2, 3, 4]
                            ]
                        ];
                        break;
                    case 6:

                        $terms = [
                            16 => [
                                'subjects' => ['math', 'social', 'english', 'science', 'reading'],
                                'semesters' => [],
                                '9_weeks' => [1, 2]
                            ],
                            15 => [
                                'subjects' => ['math', 'social', 'english', 'science'],
                                'semesters' => [],
                                '9_weeks' => [1, 2, 3, 4]
                            ]
                        ];
                        break;

                    case 7:

                        $terms = [
                            16 => [
                                'subjects' => ['math', 'social', 'english', 'science'],
                                'semesters' => [],
                                '9_weeks' => [1, 2]
                            ],
                            15 => [
                                'subjects' => ['math', 'social', 'english', 'science'],
                                'semesters' => [],
                                '9_weeks' => [1, 2, 3, 4]
                            ]
                        ];
                        break;

                    case 8:

                        $terms = [
                            16 => [
                                'subjects' => ['math', 'social', 'english', 'science'],
                                'semesters' => [],
                                '9_weeks' => [1, 2]
                            ],
                            15 => [
                                'subjects' => ['math', 'social', 'english', 'science'],
                                'semesters' => [],
                                '9_weeks' => [1, 2, 3, 4]
                            ]
                        ];
                        break;

                    case 9:

                        $terms = [
                            16 => [
                                'subjects' => ['math', 'social', 'english', 'science'],
                                'semesters' => [1],
                                '9_weeks' => []
                            ],
                            15 => [
                                'subjects' => ['math', 'social', 'english', 'science'],
                                'semesters' => [],
                                '9_weeks' => [1, 2, 3, 4]
                            ]
                        ];
                        break;

                    case 10:
                    case 11:
                    case 12:
                        $terms = [
                            16 => [
                                'subjects' => ['math', 'social', 'english', 'science'],
                                'semesters' => [1],
                                '9_weeks' => []
                            ],
                            15 => [
                                'subjects' => ['math', 'social', 'english', 'science'],
                                'semesters' => [1, 2],
                                '9_weeks' => []
                            ]
                        ];
                        break;
                }
                $course_names = explode( ' -- ', $result['course_names'] );

                $year = ( $result['academicYear'] == 0 ) ? 15 : 16;
                $term_type = ( strpos($result['academicTerm'], 'semester') !== false) ? 'semesters' : '9_weeks';
                $term_number = ( $term_type == 'semesters' ) ? substr( $result['academicTerm'], -1 ) : substr( $result['academicTerm'], 0, 1 );
                $term_number = intval( $term_number );

                foreach( $course_names as $index => $course ){
                    if( !in_array( $term_number, $terms[ $year ][ $term_type ]  ) ){
                        unset( $course_names[ $index ] );
                    }
                }
                $course_names = array_values( $course_names );

                if( $course_names ) {
                    $report_data[] = [
                        'Submission' => $result['submission_id'],
                        'Year' => $openEnrollment->getOffsetYear($result['academicYear']),
                        'Term' => $result['academicTerm'],
                        'Course Type' => $result['courseType'],
                        'Count' => $result['count'],
                        'Course Name 1' => (isset($course_names[0])) ? $course_names[0] : '',
                        'Course Name 2' => (isset($course_names[1])) ? $course_names[1] : '',
                        'Course Name 3' => (isset($course_names[2])) ? $course_names[2] : '',
                    ];
                }
            }

            $response = new StreamedResponse();

            $response->setCallback(function() use( $report_data ) {
                $handle = fopen('php://output', 'w+');

                if( $report_data ){
	                // Add the header of the CSV file
	                fputcsv($handle, array_keys( $report_data[0] ) );
	                // Add the data queried from database
	                foreach( $report_data as $row ){
	                    fputcsv(
	                        $handle, // The file pointer
	                        $row
	                    );
	                }
	            }

                fclose($handle);
            });

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="Duplicate_Courses.csv"');

            return $response;

            //**************************************************************************
        }

        $form->handleRequest($request);

        if ($form->isValid()) {

            $data = $form->getData();

            $openEnrollment = $data['openEnrollment'];

            $session->set( 'admin-duplicate-courses-openEnrollment', $openEnrollment->getId() );

            if ($form->get('generate_report')->isClicked() && isset($data['lotteryType']) && $data['lotteryType']) {

                $process = new Process();
                $process->setEvent('download');
                $process->setType($data['lotteryType']);
                $process->setOpenEnrollment($openEnrollment);

                $this->getDoctrine()->getManager()->persist($process);
                $this->getDoctrine()->getManager()->flush();

                return $this->redirect( $this->generateUrl( 'admin_report_duplicate_courses_report' ) );
            }
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @Route("/admin/report/missing-grades/", name="admin_report_missing_grades_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function missingGradesReport(){

        $title = 'Missing Grade Report';
        $subtitle = '';
        $downloadFiles = [];

        $request = $this->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-grades-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

        $user = $this->getUser();

        $schools = $user->getSchools();

        if( empty( $schools ) ){

            $unique_schools = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
                ->select( 'm.name' )
                ->distinct( true )
                ->orderBy( 'm.name' , 'ASC' )
                ->getQuery()
                ->getArrayResult();

            foreach( $unique_schools as $row ){
                $schools[] = $row['name'];
            }
        }

        $school_choices = [];
        foreach( $schools as $school ){
            $school_choices[ trim( $school ) ] = trim( $school );
        }

        $form = $this->createFormBuilder()
            ->add( 'openenrollment' , 'entity' , array(
                'class' => 'IIABMagnetBundle:OpenEnrollment' ,
                'label' => 'Enrollment' ,
                'required' => true ,
                'attr' => array( 'style' => 'margin-bottom: 20px' , 'class' => 'update-magnetschool' ) ,
                'placeholder' => 'Choose an Enrollment Period' ,
                'query_builder' => function ( EntityRepository $er ) {

                    $query = $er->createQueryBuilder( 'enrollment' )
                        ->orderBy( 'enrollment.year' , 'ASC' );

                    return $query;
                } ,
                'data' => $openEnrollment
            ) )
            ->add( 'magnetschool' , 'choice' , array(
                'label' => 'School' ,
                'required' => true ,
                'placeholder' => 'Choose an option' ,
                //'choices' => array_merge( ['all' => 'All Schools'], $school_choices ),
                'choices' => $school_choices,
            ) )
            ->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Program Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {

            $gpa_service = $this->get( 'magnet.calculategpa' );

            $data = $form->getData();

            $magnet_school_name = $data['magnetschool'];

            $query = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
                ->where( 'school.openEnrollment = :openEnrollment' )
                ->addOrderBy( 'school.name' , 'ASC' )
                ->addOrderBy( 'school.grade' , 'ASC' )
                ->setParameter( 'openEnrollment' , $openEnrollment );

            if( $magnet_school_name && $magnet_school_name != 'all' ) {
                $query->andWhere( 'school.name LIKE :school_name' )->setParameter( 'school_name' , '%'.$magnet_school_name.'%' );
            }

            $results = $query->getQuery()->getResult();

            $submission_status = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->findBy([
                'id' => [ 1,5,9 ]
            ]);

            $first_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'firstChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $second_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'secondChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $third_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'thirdChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $submissions = array_merge( $first_choice_submissions, $second_choice_submissions, $third_choice_submissions );

            $max_missing_grades = 0;
            $report_data = [];

            foreach( $submissions as $submission ) {
                $gpa_service->calculateGPA($submission);
            }
            $this->getDoctrine()->getManager()->flush();

            foreach( $submissions as $submission ) {

                $submission_data = $submission->getAdditionalData(true);

                foreach ($submission_data as $index => $datum) {
                    if ($datum->getMetaKey() == 'missing_grade') {
                        if (empty($report_data[$submission->getId()])) {
                            $report_data[$submission->getId()] = [
                                'submission' => $submission,
                                'missing_grades' => []
                            ];
                        }
                        $report_data[$submission->getId()] ['missing_grades'][] = $datum->getMetaValue();
                    }
                }
                $max_missing_grades = ( count( $report_data[$submission->getId()] ['missing_grades'] ) > $max_missing_grades) ? count( $report_data[$submission->getId()] ['missing_grades'] ) : $max_missing_grades;
            }

            usort($report_data, function( $a, $b ){

                if( $a['submission']->getCurrentGrade() == $b['submission']->getCurrentGrade() ){

                    if( $a['submission']->getLastName() == $b['submission']->getLastName() ){

                        if( $a['submission']->getFirstName() == $b['submission']->getFirstName() ){
                            return 0;
                        }

                        return ( $a['submission']->getFirstName() < $b['submission']->getFirstName() ) ? -1 : 1;
                    }
                    return ( $a['submission']->getLastName() < $b['submission']->getLastName() ) ? -1 : 1;
                }

                return ( $a['submission']->getCurrentGrade() < $b['submission']->getCurrentGrade() ) ? -1 : 1;
            });

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );

            $response = new StreamedResponse();
            $response->setCallback(function() use( $report_data , $generationDate, $magnet_school_name) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Submission Missing Grades report pulled from MPW on ' . $generationDate] );

                $row = [
                    'Submission ID',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'Submission Choice of '. $short_school_name,
                    'First Choice School',
                    'Number of Missing Grades'
                ];

                fputcsv($handle, $row );

                foreach( $report_data as $row_data ) {

                    $choice_order = '';
                    if( !empty( $row_data['submission']->getFirstChoice() ) && stripos( $row_data['submission']->getFirstChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'First';
                    } else if( !empty( $row_data['submission']->getSecondChoice() ) && stripos( $row_data['submission']->getSecondChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Second';
                    } else if( !empty( $row_data['submission']->getThirdChoice() ) && stripos( $row_data['submission']->getThirdChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Third';
                    }

                    $row = [
                        $row_data['submission']->getId(),
                        $row_data['submission']->getSubmissionStatus()->__toString(),
                        $row_data['submission']->getStateId(),
                        strtoupper( $row_data['submission']->getLastName() ),
                        strtoupper( $row_data['submission']->getFirstName() ),
                        $row_data['submission']->getNextGrade(),
                        strtoupper( $row_data['submission']->getCurrentSchool() ),
                        $choice_order,
                        strtoupper( $row_data['submission']->getFirstChoice()->getName() ),
                        count( $row_data['missing_grades'] )
                    ];

                    foreach( $row_data['missing_grades'] as $grade){

                        $missing_grade_data = explode( ' / ', $grade );
                        $missing_grade_data[2] = ucwords( $missing_grade_data[2] );

                        $row[] = implode( ' / ', $missing_grade_data );
                    }
                    fputcsv($handle, $row);
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'. $short_school_name .'_Submission_Missing_Grades.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }


    /**
     * @Route("/admin/report/duplicate-courses/", name="admin_report_duplicate_courses_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function duplicateCoursesReport(){

        $title = 'Duplicate Grade Report';
        $subtitle = '';
        $downloadFiles = [];

        $request = $this->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-duplicate-grades-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

        $user = $this->getUser();

        $schools = $user->getSchools();

        if( empty( $schools ) ){

            $unique_schools = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
                ->select( 'm.name' )
                ->distinct( true )
                ->orderBy( 'm.name' , 'ASC' )
                ->getQuery()
                ->getArrayResult();

            foreach( $unique_schools as $row ){
                $schools[] = $row['name'];
            }
        }

        $school_choices = [];
        foreach( $schools as $school ){
            $school_choices[ trim( $school ) ] = trim( $school );
        }

        $form = $this->createFormBuilder()
            ->add( 'openenrollment' , 'entity' , array(
                'class' => 'IIABMagnetBundle:OpenEnrollment' ,
                'label' => 'Enrollment' ,
                'required' => true ,
                'attr' => array( 'style' => 'margin-bottom: 20px' , 'class' => 'update-magnetschool' ) ,
                'placeholder' => 'Choose an Enrollment Period' ,
                'query_builder' => function ( EntityRepository $er ) {

                    $query = $er->createQueryBuilder( 'enrollment' )
                        ->orderBy( 'enrollment.year' , 'ASC' );

                    return $query;
                } ,
                'data' => $openEnrollment
            ) )
            ->add( 'magnetschool' , 'choice' , array(
                'label' => 'School' ,
                'required' => true ,
                'placeholder' => 'Choose an option' ,
                //'choices' => array_merge( ['all' => 'All Schools'], $school_choices ),
                'choices' => $school_choices,
            ) )
            ->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Program Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {

            $gpa_service = $this->get( 'magnet.calculategpa' );

            $data = $form->getData();

            $magnet_school_name = $data['magnetschool'];

            $query = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
                ->where( 'school.openEnrollment = :openEnrollment' )
                ->addOrderBy( 'school.name' , 'ASC' )
                ->addOrderBy( 'school.grade' , 'ASC' )
                ->setParameter( 'openEnrollment' , $openEnrollment );

            if( $magnet_school_name && $magnet_school_name != 'all' ) {
                $query->andWhere( 'school.name LIKE :school_name' )->setParameter( 'school_name' , '%'.$magnet_school_name.'%' );
            }

            $results = $query->getQuery()->getResult();

            $submission_status = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->findBy([
                'id' => [ 1,5,9 ]
            ]);

            $first_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'firstChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $second_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'secondChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $third_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'thirdChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $submissions = array_merge( $first_choice_submissions, $second_choice_submissions, $third_choice_submissions );

            $max_duplicate_grades = 0;
            $report_data = [];

            foreach( $submissions as $submission ) {
                $gpa_service->calculateGPA($submission);
            }
            $this->getDoctrine()->getManager()->flush();

            foreach( $submissions as $submission ) {

                $submission_data = $submission->getAdditionalData();

                foreach ($submission_data as $index => $datum) {
                    if ($datum->getMetaKey() == 'duplicate_grade') {
                        if (empty($report_data[$submission->getId()])) {
                            $report_data[$submission->getId()] = [
                                'submission' => $submission,
                                'duplicate_grades' => []
                            ];
                        }
                        $report_data[$submission->getId()] ['duplicate_grades'][] = $datum->getMetaValue();
                    }
                }
                $max_duplicate_grades = ( count( $report_data[$submission->getId()] ['duplicate_grades'] ) > $max_duplicate_grades) ? count( $report_data[$submission->getId()] ['duplicate_grades'] ) : $max_duplicate_grades;
            }

            usort($report_data, function( $a, $b ){

                if( $a['submission']->getCurrentGrade() == $b['submission']->getCurrentGrade() ){

                    if( $a['submission']->getLastName() == $b['submission']->getLastName() ){

                        if( $a['submission']->getFirstName() == $b['submission']->getFirstName() ){
                            return 0;
                        }

                        return ( $a['submission']->getFirstName() < $b['submission']->getFirstName() ) ? -1 : 1;
                    }
                    return ( $a['submission']->getLastName() < $b['submission']->getLastName() ) ? -1 : 1;
                }

                return ( $a['submission']->getCurrentGrade() < $b['submission']->getCurrentGrade() ) ? -1 : 1;
            });

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );

            $response = new StreamedResponse();
            $response->setCallback(function() use( $report_data , $generationDate, $magnet_school_name) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note:'. $short_school_name .' Submission Duplicate Grades report pulled from MPW on ' . $generationDate] );

                $row = [
                    'Submission ID',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'Submission Choice of '. $short_school_name,
                    'First Choice School',
                    'Number of Duplicate Grades'
                ];

                fputcsv($handle, $row );

                foreach( $report_data as $row_data ) {

                    $choice_order = '';
                    if( !empty( $row_data['submission']->getFirstChoice() ) && stripos( $row_data['submission']->getFirstChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'First';
                    } else if( !empty( $row_data['submission']->getSecondChoice() ) && stripos( $row_data['submission']->getSecondChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Second';
                    } else if( !empty( $row_data['submission']->getThirdChoice() ) && stripos( $row_data['submission']->getThirdChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Third';
                    }

                    $row = [
                        $row_data['submission']->getId(),
                        $row_data['submission']->getSubmissionStatus()->getStatus(),
                        $row_data['submission']->getStateId(),
                        strtoupper( $row_data['submission']->getLastName() ),
                        strtoupper( $row_data['submission']->getFirstName() ),
                        $row_data['submission']->getNextGrade(),
                        strtoupper( $row_data['submission']->getCurrentSchool() ),
                        $choice_order,
                        strtoupper( $row_data['submission']->getFirstChoice()->getName() ),
                        count( $row_data['duplicate_grades'] )
                    ];

                    foreach( $row_data['duplicate_grades'] as $grade){

                        $duplicate_grade_data = explode( ' / ', $grade );
                        $duplicate_grade_data[2] = ucwords( $duplicate_grade_data[2] );

                        $row[] = implode( ' / ', $duplicate_grade_data );
                    }
                    fputcsv($handle, $row);
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'. $short_school_name .'_Submission_Duplicate_Grades.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @Route("/admin/report/audition-scores/", name="admin_report_audition_scores_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function auditionScoreReport(){

        $title = 'Audition Score Report';
        $subtitle = '';
        $downloadFiles = [];

        $request = $this->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-audition-scores-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

        $user = $this->getUser();

        $schools = $user->getSchools();

        if( empty( $schools ) ){

            $unique_schools = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
                ->select( 'm.name' )
                ->distinct( true )
                ->orderBy( 'm.name' , 'ASC' )
                ->getQuery()
                ->getArrayResult();

            foreach( $unique_schools as $row ){
                $schools[] = $row['name'];
            }
        }

        $school_choices = [];
        foreach( $schools as $school ){
            $school_choices[ trim( $school ) ] = trim( $school );
        }

        $form = $this->createFormBuilder()
            ->add( 'openenrollment' , 'entity' , array(
                'class' => 'IIABMagnetBundle:OpenEnrollment' ,
                'label' => 'Enrollment' ,
                'required' => true ,
                'attr' => array( 'style' => 'margin-bottom: 20px' , 'class' => 'update-magnetschool' ) ,
                'placeholder' => 'Choose an Enrollment Period' ,
                'query_builder' => function ( EntityRepository $er ) {

                    $query = $er->createQueryBuilder( 'enrollment' )
                        ->orderBy( 'enrollment.year' , 'ASC' );

                    return $query;
                } ,
                'data' => $openEnrollment
            ) )
            ->add( 'magnetschool' , 'choice' , array(
                'label' => 'School' ,
                'required' => true ,
                'placeholder' => 'Choose an option' ,
                //'choices' => array_merge( ['all' => 'All Schools'], $school_choices ),
                'choices' => $school_choices,
            ) )
            ->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Program Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {

            $data = $form->getData();

            $magnet_school_name = $data['magnetschool'];

            $query = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
                ->where( 'school.openEnrollment = :openEnrollment' )
                ->addOrderBy( 'school.name' , 'ASC' )
                ->addOrderBy( 'school.grade' , 'ASC' )
                ->setParameter( 'openEnrollment' , $openEnrollment );

            if( $magnet_school_name && $magnet_school_name != 'all' ) {
                $query->andWhere( 'school.name LIKE :school_name' )->setParameter( 'school_name' , '%'.$magnet_school_name.'%' );
            }

            $results = $query->getQuery()->getResult();

            $submission_status = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->findBy([
                'id' => [ 1,5,9 ]
            ]);

            $first_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'firstChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $second_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'secondChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $third_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'thirdChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $submissions = array_merge( $first_choice_submissions, $second_choice_submissions, $third_choice_submissions );

            $report_data = [];
            foreach( $submissions as $submission ) {

                $auditions =[
                    'first' => [
                        'required' => false,
                        'school_name' => '',
                        'scores' => [
                            'first_choice_audition_1' => 'n/a',
                            'first_choice_audition_2' => 'n/a',
                            'audition_total' => 'n/a'
                        ]
                    ],
                    'second' => [
                        'required' => false,
                        'school_name' => '',
                        'scores' => [
                            'second_choice_audition_1' => 'n/a',
                            'second_choice_audition_2' => 'n/a',
                            'audition_total' => 'n/a'
                        ]
                    ],
                    'third' => [
                        'required' => false,
                        'school_name' => '',
                        'scores' => [
                            'third_choice_audition_1' => 'n/a',
                            'third_choice_audition_2' => 'n/a',
                            'audition_total' => 'n/a'
                        ]
                    ],
                ];

                foreach( $auditions as $choice => $choice_data ){

                    if( !empty( $submission->{'get'.ucwords($choice).'Choice'}() ) ){

                        $auditions[$choice]['school_name'] = $submission->{'get'.ucwords($choice).'Choice'}()->getName();

                        foreach( $auditions[$choice]['scores'] as $key => $value ){
                            $auditions[$choice]['scores'][$key] = '';
                        }

                        $audition_results = [
                        	0 =>'Not Ready',
                            1 =>'Ready',
                            2 =>'Exceptional'
                        ];
                        $meta_data = $submission->getAdditionalData(true);
                        foreach( $meta_data as $datum ){
                            if( in_array( $datum->getMetaKey(), array_keys( $choice_data['scores'] ) ) ){

                            	$value = ( $datum->getMetaKey() == 'audition_total' )
                            		? $datum->getMetaValue()
                            		: $audition_results[ $datum->getMetaValue() ];

                                $auditions[$choice]['scores'][ $datum->getMetaKey() ] = $value;



                            }
                        }
                    }
                }
                $report_data[$submission->getId()] = [
                    'submission' => $submission,
                    'auditions' => $auditions
                ];
            }

            usort($report_data, function( $a, $b ){

                if( $a['submission']->getCurrentGrade() == $b['submission']->getCurrentGrade() ){

                    if( $a['submission']->getLastName() == $b['submission']->getLastName() ){

                        if( $a['submission']->getFirstName() == $b['submission']->getFirstName() ){
                            return 0;
                        }

                        return ( $a['submission']->getFirstName() < $b['submission']->getFirstName() ) ? -1 : 1;
                    }
                    return ( $a['submission']->getLastName() < $b['submission']->getLastName() ) ? -1 : 1;
                }

                return ( $a['submission']->getCurrentGrade() < $b['submission']->getCurrentGrade() ) ? -1 : 1;
            });

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );

            $response = new StreamedResponse();
            $response->setCallback(function() use( $report_data , $generationDate, $magnet_school_name ) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Submission Audition Scores report pulled from MPW on ' . $generationDate] );

                $row = [
                    'Submission ID',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Address',
                    'City',
                    'State',
                    'Phone Number',
                    'Email',
                    'Race',
                    'Next Grade',
                    'Current School',
                    'Submission Choice of '. $short_school_name,
                    '1st Audition',
                    '2nd Audition',
                    'Total Audition',
                ];

                fputcsv($handle, $row );

                foreach( $report_data as $row_data ) {

                    $choice_order = '';
                    $scores = [
                        '',
                        '',
                        ''
                    ];
                    if( !empty( $row_data['submission']->getFirstChoice() ) && stripos( $row_data['submission']->getFirstChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'First';

                        $scores = [
                            $row_data['auditions']['first']['scores']['first_choice_audition_1'],
                            $row_data['auditions']['first']['scores']['first_choice_audition_2'],
                            $row_data['auditions']['first']['scores']['audition_total'],
                        ];

                    } else if( !empty( $row_data['submission']->getSecondChoice() ) && stripos( $row_data['submission']->getSecondChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Second';

                        $scores = [
                            $row_data['auditions']['second']['scores']['second_choice_audition_1'],
                            $row_data['auditions']['second']['scores']['second_choice_audition_2'],
                            $row_data['auditions']['second']['scores']['audition_total'],
                        ];

                    } else if( !empty( $row_data['submission']->getThirdChoice() ) && stripos( $row_data['submission']->getThirdChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Third';

                        $scores = [
                            $row_data['auditions']['third']['scores']['third_choice_audition_1'],
                            $row_data['auditions']['third']['scores']['third_choice_audition_2'],
                            $row_data['auditions']['third']['scores']['audition_total']
                        ];
                    }

                    $row = [
                        $row_data['submission']->getId(),
                        $row_data['submission']->getSubmissionStatus()->getStatus(),
                        $row_data['submission']->getStateId(),
                        strtoupper($row_data['submission']->getLastName()),
                        strtoupper($row_data['submission']->getFirstName()),
                        $row_data['submission']->getAddress(),
                        $row_data['submission']->getCity(),
                        $row_data['submission']->getState(),
                        $row_data['submission']->getPhoneNumber(),
                        $row_data['submission']->getParentEmail(),
                        $row_data['submission']->getRace(),
                        $row_data['submission']->getNextGrade(),
                        $row_data['submission']->getCurrentSchool(),
                        $choice_order,
                        $scores[0],
                        $scores[1],
                        $scores[2]
                    ];
                    fputcsv($handle, $row);
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'. $short_school_name .'_Submission_Audition_Scores.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @Route("/admin/report/gpa-calculations/", name="admin_report_gpa_calculations_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function gpaCalculationsReport(){

        $title = 'GPA Calculations Report';
        $subtitle = '';
        $downloadFiles = [];

        $request = $this->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-gpa-calculations-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

        $user = $this->getUser();

        $schools = $user->getSchools();

        if( empty( $schools ) ){

            $unique_schools = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
                ->select( 'm.name' )
                ->distinct( true )
                ->orderBy( 'm.name' , 'ASC' )
                ->getQuery()
                ->getArrayResult();

            foreach( $unique_schools as $row ){
                $schools[] = $row['name'];
            }
        }

        $school_choices = [];
        foreach( $schools as $school ){
            $school_choices[ trim( $school ) ] = trim( $school );
        }

        $form = $this->createFormBuilder()
            ->add( 'openenrollment' , 'entity' , array(
                'class' => 'IIABMagnetBundle:OpenEnrollment' ,
                'label' => 'Enrollment' ,
                'required' => true ,
                'attr' => array( 'style' => 'margin-bottom: 20px' , 'class' => 'update-magnetschool' ) ,
                'placeholder' => 'Choose an Enrollment Period' ,
                'query_builder' => function ( EntityRepository $er ) {

                    $query = $er->createQueryBuilder( 'enrollment' )
                        ->orderBy( 'enrollment.year' , 'ASC' );

                    return $query;
                } ,
                'data' => $openEnrollment
            ) )
            ->add( 'magnetschool' , 'choice' , array(
                'label' => 'School' ,
                'required' => true ,
                'placeholder' => 'Choose an option' ,
                //'choices' => array_merge( ['all' => 'All Schools'], $school_choices ),
                'choices' => $school_choices,
            ) )
            ->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Program Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {

            $gpa_service = $this->get( 'magnet.calculategpa' );

            $data = $form->getData();

            $magnet_school_name = $data['magnetschool'];

            $query = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
                ->where( 'school.openEnrollment = :openEnrollment' )
                ->addOrderBy( 'school.name' , 'ASC' )
                ->addOrderBy( 'school.grade' , 'ASC' )
                ->setParameter( 'openEnrollment' , $openEnrollment );

            if( $magnet_school_name && $magnet_school_name != 'all' ) {
                $query->andWhere( 'school.name LIKE :school_name' )->setParameter( 'school_name' , '%'.$magnet_school_name.'%' );
            }

            $results = $query->getQuery()->getResult();

            $submission_status = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->findBy([
                'id' => [ 1,5,9 ]
            ]);

            $first_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'firstChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $second_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'secondChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $third_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'thirdChoice' => $results,
                'submissionStatus' => $submission_status,
            ]  );

            $submissions = array_merge( $first_choice_submissions, $second_choice_submissions, $third_choice_submissions );

            $max_grades_used = 0;
            $report_data = [];

            foreach( $submissions as $submission ) {
                $gpa_service->calculateGPA($submission);
            }
            $this->getDoctrine()->getManager()->flush();

            foreach( $submissions as $submission ) {

                $submission_grades = $submission->getGrades();

                if (empty($report_data[$submission->getId()])) {
                    $report_data[$submission->getId()] = [
                        'submission' => $submission,
                        'used_grades' => []
                    ];
                }

                foreach ($submission_grades as $index => $grade) {
                    if ( !empty( $grade->getUseInCalculations() ) ) {
                        $report_data[$submission->getId()] ['used_grades'][] = $grade;
                    }
                }
                $max_grades_used = ( count( $report_data[$submission->getId()] ['used_grades'] ) > $max_grades_used) ? count( $report_data[$submission->getId()] ['used_grades'] ) : $max_grades_used;
            }

            usort($report_data, function( $a, $b ){

                if( $a['submission']->getCurrentGrade() == $b['submission']->getCurrentGrade() ){

                    if( $a['submission']->getLastName() == $b['submission']->getLastName() ){

                        if( $a['submission']->getFirstName() == $b['submission']->getFirstName() ){
                            return 0;
                        }

                        return ( $a['submission']->getFirstName() < $b['submission']->getFirstName() ) ? -1 : 1;
                    }
                    return ( $a['submission']->getLastName() < $b['submission']->getLastName() ) ? -1 : 1;
                }

                return ( $a['submission']->getCurrentGrade() < $b['submission']->getCurrentGrade() ) ? -1 : 1;
            });

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );


            $response = new StreamedResponse();
            $response->setCallback(function() use( $report_data , $generationDate, $max_grades_used, $openEnrollment, $magnet_school_name) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Submission GPA Calculations report pulled from MPW on ' . $generationDate] );

                $row = [
                    'Submission ID',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'Submission Choice of '. $short_school_name,
                    'First Choice School',
                    'GPA'
                ];

                for( $column_count = 1; $column_count <= $max_grades_used; $column_count++  ){
                    $row[] = 'Grade Used '. $column_count;
                    $row[] = 'Numeric Grade '. $column_count;
                }

                fputcsv($handle, $row );

                foreach( $report_data as $row_data ) {

                    $choice_order = '';
                    if( !empty( $row_data['submission']->getFirstChoice() ) && stripos( $row_data['submission']->getFirstChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'First';
                    } else if( !empty($row_data['submission']->getSecondChoice()) &&  stripos( $row_data['submission']->getSecondChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Second';
                    } else if( !empty($row_data['submission']->getThirdChoice()) &&stripos(  $row_data['submission']->getThirdChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Third';
                    }

                    $row = [
                        $row_data['submission']->getId(),
                        $row_data['submission']->getSubmissionStatus()->getStatus(),
                        $row_data['submission']->getStateId(),
                        strtoupper($row_data['submission']->getLastName()),
                        strtoupper($row_data['submission']->getFirstName()),
                        $row_data['submission']->getNextGrade(),
                        strtoupper($row_data['submission']->getCurrentSchool()),
                        $choice_order,
                        strtoupper( $row_data['submission']->getFirstChoice()->getName() ),
                        $row_data['submission']->getGPA()
                    ];

                    foreach( $row_data['used_grades'] as $grade){

                        $row[] = implode( ' / ', [
                            $openEnrollment->getOffsetYear( $grade->getAcademicYear() ),
                            $grade->getAcademicTerm(),
                            ucwords( $grade->getCourseType() )
                        ]);
                        $row[] = $grade->getNumericGrade();
                    }
                    fputcsv($handle, $row);
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'. $short_school_name .'_Submission_GPA_Calculations.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @Route("/admin/report/missing-eligibility/", name="admin_report_missing_eligibility_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function missingEligibilityReport(){

        $title = 'Missing Eligibility Report';
        $subtitle = '';
        $downloadFiles = [];

        $request = $this->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

        $user = $this->getUser();

        $schools = $user->getSchools();

        if( empty( $schools ) ){

            $unique_schools = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
                ->select( 'm.name' )
                ->distinct( true )
                ->orderBy( 'm.name' , 'ASC' )
                ->getQuery()
                ->getArrayResult();

            foreach( $unique_schools as $row ){
                $schools[] = $row['name'];
            }
        }

        $school_choices = [];
        foreach( $schools as $school ){
            $school_choices[ trim( $school ) ] = trim( $school );
        }

        $form = $this->createForm( ReportSelectionType::class, null, [
		 	'user' => $user,
		 	'entity_manager' => $this->getDoctrine()->getManager(),
		 	'open_enrollment' => $openEnrollment,
		]);

        $form->handleRequest($request);

        if ($form->isValid()) {

            $data = $form->getData();

            $eligibility_service = new EligibilityRequirementsService(
				$this->container->get( 'doctrine.orm.default_entity_manager' ) );

            $magnet_school_name = $data['magnetschool'];

            $query = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
                ->where('school.openEnrollment = :openEnrollment')
                ->addOrderBy('school.name', 'ASC')
                ->addOrderBy('school.grade', 'ASC')
                ->setParameter('openEnrollment', $openEnrollment);

            if ($magnet_school_name && $magnet_school_name != 'all') {
                $query->andWhere('school.name LIKE :school_name')->setParameter('school_name', '%' . $magnet_school_name . '%');
            }

            $results = $query->getQuery()->getResult();

            $submission_status = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->findBy([
                'id' => [1, 5, 9]
            ]);

            $first_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'firstChoice' => $results,
                'submissionStatus' => $submission_status,
            ]);

            $second_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'secondChoice' => $results,
                'submissionStatus' => $submission_status,
            ]);

            $third_choice_submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')->findBy([
                'thirdChoice' => $results,
                'submissionStatus' => $submission_status,
            ]);

            $submissions = array_merge($first_choice_submissions, $second_choice_submissions, $third_choice_submissions);

            $fields_for_schools = [ 'all' =>[] ];
            foreach ($results as $magnet_school) {
            	$use_fields = [];
            	$eligibility = $eligibility_service->getEligibilitySubmissionDataKeysForSchool( $magnet_school );
				foreach( $eligibility as $key ){
					$key = str_replace('combined_', '', $key);
					if( $key == 'student_profile' ){

						$use_profile = false;
						$school_requirements = $magnet_school->getEligibility();
						foreach( $school_requirements as $requirement ){
							if( $requirement->getCriteriaType() == 'student_profile'
								&& $requirement->getPassingThreshold() > 0
							){
								$use_profile = true;
							}
						}

						$program_requirements = $magnet_school->getProgram()->getEligibility();
						foreach( $program_requirements as $requirement ){
							if( $requirement->getCriteriaType() == 'student_profile'
								&& $requirement->getPassingThreshold() > 0
							){
								$use_profile = true;
							}
						}

						if( $use_profile ){
							$use_fields[] = 'student_profile_percentage';
						}
					} else if( !in_array($key, ['grades', 'student_profile', 'recommendations', 'standardized_testing', 'conduct_eligible', 'learner_screening_device']) ){
                		$use_fields[] = $key;
                	}
                }

                $fields_for_schools[ $magnet_school->getId() ] = $use_fields;

                $fields_for_schools['all'] = array_unique( array_merge( $fields_for_schools['all'], $fields_for_schools[ $magnet_school->getId() ] ) );
            }

            $default_values = [];

            foreach( $fields_for_schools['all'] as $field ){
                $default_values[ $field ] = 'missing';
            }

            $max_grades_used = 0;
            $report_data = [];
            foreach( $submissions as $submission ) {

                $submission_data = $submission->getAdditionalData(true);

                $check_fields = [];

                if( !empty( $submission->getFirstChoice() ) && isset( $fields_for_schools[ $submission->getFirstChoice()->getId() ] ) ){
                    $check_fields = $fields_for_schools[ $submission->getFirstChoice()->getId() ];
                }
                if( !empty( $submission->getSecondChoice() ) && isset( $fields_for_schools[ $submission->getSecondChoice()->getId() ] ) ){
                    $check_fields = array_merge( $check_fields,  $fields_for_schools[ $submission->getSecondChoice()->getId() ] );
                }
                if( !empty( $submission->getThirdChoice() ) && isset( $fields_for_schools[ $submission->getThirdChoice()->getId() ] ) ){
                    $check_fields = array_merge( $fields_for_schools[ $submission->getThirdChoice()->getId() ] );
                }

				$submission_defaults = $default_values;
                foreach( array_keys( $submission_defaults ) as $default ){
                    if( !in_array($default, $check_fields ) ){
                        $submission_defaults[ $default ] = 'n/a';
                    }
                }

                $report_data[ $submission->getId() ] = array_merge( ['submission' => $submission ], $submission_defaults );

                foreach ($submission_data as $index => $datum) {

                    if ( in_array( $datum->getMetaKey(), array_keys( $submission_defaults ) ) ) {

                    	if( $datum->getMetaValue() != null ){
                        	$report_data[$submission->getId()] [ $datum->getMetaKey() ] = '';
                    	}
                    }
                }
            }

            foreach( $report_data as $key => $row ){
                $keep_row = false;
                foreach(  $fields_for_schools['all'] as $field ){
                    if( $row[$field] && $row[$field] != 'n/a' ){
                        $keep_row = true;
                    }
                }
                if( !$keep_row ){
                    unset( $report_data[$key] );
                }
            }

            usort($report_data, function( $a, $b ){

                if( $a['submission']->getCurrentGrade() == $b['submission']->getCurrentGrade() ){

                    if( $a['submission']->getLastName() == $b['submission']->getLastName() ){

                        if( $a['submission']->getFirstName() == $b['submission']->getFirstName() ){
                            return 0;
                        }

                        return ( $a['submission']->getFirstName() < $b['submission']->getFirstName() ) ? -1 : 1;
                    }
                    return ( $a['submission']->getLastName() < $b['submission']->getLastName() ) ? -1 : 1;
                }

                return ( $a['submission']->getCurrentGrade() < $b['submission']->getCurrentGrade() ) ? -1 : 1;
            });

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );

            $response = new StreamedResponse();
            $response->setCallback(function() use( $report_data , $generationDate, $magnet_school_name,  $fields_for_schools ) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Missing Eligibility report pulled from MPW on ' . $generationDate] );

                $row = [
                    'Submission ID',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'Submission Choice of '. $short_school_name,
                    'First Choice School'
                ];

                foreach(  $fields_for_schools['all'] as $field ){
                    $row[] =  str_replace( 'Focus', 'SubChoice', ucwords( str_replace( '_', ' ', str_replace( '_gpa', '_GPA', $field ) ) ) );
                }

                fputcsv($handle, $row );

                foreach( $report_data as $row_data ) {

                    $choice_order = '';
                    if( !empty( $row_data['submission']->getFirstChoice() ) && stripos( $row_data['submission']->getFirstChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'First';
                    } else if( !empty( $row_data['submission']->getSecondChoice() ) && stripos( $row_data['submission']->getSecondChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Second';
                    } else if( !empty( $row_data['submission']->getThirdChoice() ) && stripos( $row_data['submission']->getThirdChoice()->getName(), $magnet_school_name ) !== false ){
                        $choice_order = 'Third';
                    }

                    $row = [
                        $row_data['submission']->getId(),
                        $row_data['submission']->getSubmissionStatus()->getStatus(),
                        $row_data['submission']->getStateId(),
                        strtoupper($row_data['submission']->getLastName()),
                        strtoupper($row_data['submission']->getFirstName()),
                        $row_data['submission']->getNextGrade(),
                        strtoupper($row_data['submission']->getCurrentSchool()),
                        $choice_order,
                        strtoupper( $row_data['submission']->getFirstChoice()->getName() ),
                    ];

                    foreach(  $fields_for_schools['all'] as $key ){
                        $row[] = $row_data[$key];
                    }
                    fputcsv($handle, $row);
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Missing_Eligibility.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @Route("/admin/report/missing-recommendation/", name="admin_report_missing_recommendation_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function missingRecommendationReport(){
    	$title = 'Missing Recommendations Report';
        $subtitle = '';
        $downloadFiles = [];

        $request = $this->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

        $user = $this->getUser();

        $form = $this->createForm( ReportSelectionType::class, null, [
		 	'user' => $user,
		 	'entity_manager' => $this->getDoctrine()->getManager(),
		 	'open_enrollment' => $openEnrollment,
		]);

        $form->handleRequest($request);

        if ( $form->isValid() ) {

            $data = $form->getData();

            if( $data['magnetschool'] != 'all' ){
	            $magnet_school = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
	                ->where('school.openEnrollment = :openEnrollment')
	                ->addOrderBy('school.name', 'ASC')
	                ->addOrderBy('school.grade', 'ASC')
	                ->setParameter('openEnrollment', $openEnrollment)
	                ->andWhere('school.name LIKE :school_name')
	                ->setParameter('school_name', '%' . $data['magnetschool'] . '%')
	            	->getQuery()
	            	->getResult();
	        }

	        $missing = [];
	        if( isset( MYPICK_CONFIG['eligibility_fields']['recommendations'] ) ) {

	        	foreach( array_keys( MYPICK_CONFIG['eligibility_fields']['recommendations']['info_field'] ) as $key ){

	        		$title = ucwords( str_replace('recommendation_', '', $key ) );
	        		$missing[ $title ] = $submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')
		            	->findAllMissingRecommendationsBy([
		            		'openEnrollment' => $openEnrollment,
		            		'magnetSchool' => ( isset( $magnet_school ) ) ? $magnet_school : null,
		            		'user' => ( empty( $magnet_school ) ) ? $user : null,
		            		'recommendation_key' => $key,
		            	]);
	            }
		    }

		    $submissions = [];
            foreach( $missing as $type => $missing_submissions ){

            	foreach( $missing_submissions as $submission ){
            		if( empty( $submissions[ $submission->getId() ] ) ){
            			$submissions[ $submission->getId() ] = [
            				'submission' => $submission,
            				'missing' => [],
            			];
            		}
            		$submissions[ $submission->getId() ]['missing'][] = $type;
            	}

            }

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );
            $magnet_school_name = $data['magnetschool'];

            $response = new StreamedResponse();
            $response->setCallback(function() use( $submissions , $generationDate, $magnet_school_name) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Missing Recommendations report pulled from MPW on ' . $generationDate] );

                $report_second_choice = false;
                $report_third_choice = false;
                foreach( $submissions as $submission_row ){
                	$submission = $submission_row['submission'];
                	if( $submission->getSecondChoice() != null ){
                		$report_second_choice = true;
                	}

                	if( $submission->getThirdChoice() != null ){
                		$report_third_choice = true;
                	}
                }

                $row = [
                    'Submission ID',
                    'Created At',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'First Choice School',
                ];
                if( $report_second_choice ){
                	$row[] = 'Second Choice School';
                }
                if( $report_third_choice ){
                	$row[] = 'Third Choice School';
                }
                $row[] = 'Missing Recommendations';

                fputcsv($handle, $row );

                foreach( $submissions as $submission_row ) {
                	$submission = $submission_row['submission'];
                    $row = [
                        $submission->getId(),
                        $submission->getCreatedAtFormatted(),
                        $submission->getSubmissionStatus()->getStatus(),
                        $submission->getStateID(),
                        $submission->getLastName(),
                        $submission->getFirstName(),
                        $submission->getNextGrade(),
                        $submission->getCurrentSchool(),
                        $submission->getFirstChoice(),
                    ];
                    if( $report_second_choice ){
                		$row[] = $submission->getSecondChoice();
                	}
                	if( $report_third_choice ){
                		$row[] = $submission->getThirdChoice();
                	}

                	foreach( $submission_row['missing'] as $missing ){
                		$row[] = $missing;
                	}
                    fputcsv($handle, $row);
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Missing_Recommendations.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }


    /**
     * @Route("/admin/report/missing-learner-screening-device/", name="admin_report_missing_learner_screening_device_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function missingLearnerScreeningDeviceReport(){
    	//var_dump( MYPICK_CONFIG['eligibility_fields']['learner_screening_device']); die;
	    $title = 'Missing Learner Screening Device Report';
	    $subtitle = '';
	    $downloadFiles = [];

	    $request = $this->get('request_stack')->getCurrentRequest();
	    $session = $request->getSession();

	    $admin_pool = $this->get( 'sonata.admin.pool' );

	    $openEnrollment_id = $request->get('form')['openEnrollment'];
	    $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
	    $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

	    $user = $this->getUser();

	    $form = $this->createForm( ReportSelectionType::class, null, [
		 	'user' => $user,
		 	'entity_manager' => $this->getDoctrine()->getManager(),
		 	'open_enrollment' => $openEnrollment,
		]);

	    $form->handleRequest($request);

	    if ( $form->isValid() ) {

		    $data = $form->getData();

		    if( $data['magnetschool'] != 'all' ){
			    $magnet_school = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
			                          ->where('school.openEnrollment = :openEnrollment')
			                          ->addOrderBy('school.name', 'ASC')
			                          ->addOrderBy('school.grade', 'ASC')
			                          ->setParameter('openEnrollment', $openEnrollment)
			                          ->andWhere('school.name LIKE :school_name')
			                          ->setParameter('school_name', '%' . $data['magnetschool'] . '%')
			                          ->getQuery()
			                          ->getResult();
		    }

		    $submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')
		                        ->findAllMissingLearnerScreeningDevice([
			                        'openEnrollment' => $openEnrollment,
			                        'magnetSchool' => ( isset( $magnet_school ) ) ? $magnet_school : null,
			                        'user' => ( empty( $magnet_school ) ) ? $user : null
		                        ]);

		    $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
		    $generationDate = $now->format( 'm/d/Y g:i:s a' );
		    $magnet_school_name = $data['magnetschool'];

		    $response = new StreamedResponse();
		    $response->setCallback(function() use( $submissions , $generationDate, $magnet_school_name) {
			    $handle = fopen('php://output', 'w+');

			    $short_school_name = $this->shortenSchoolName( $magnet_school_name );

			    // Add the header of the CSV file
			    fputcsv($handle, ['Note: '. $short_school_name .' Missing Recommendations report pulled from MPW on ' . $generationDate] );

			    $report_second_choice = false;
			    $report_third_choice = false;
			    foreach( $submissions as $submission ){
				    if( $submission->getSecondChoice() != null ){
					    $report_second_choice = true;
				    }

				    if( $submission->getThirdChoice() != null ){
					    $report_third_choice = true;
				    }
			    }


			    $row = [
				    'Submission ID',
				    'Created At',
				    'Submission Status',
				    'State ID',
				    'Last Name',
				    'First Name',
				    'Next Grade',
				    'Current School',
				    'First Choice School',
			    ];
			    if( $report_second_choice ){
				    $row[] = 'Second Choice School';
			    }
			    if( $report_third_choice ){
				    $row[] = 'Third Choice School';
			    }

			    fputcsv($handle, $row );

			    foreach( $submissions as $submission ) {

				    $row = [
					    $submission->getId(),
					    $submission->getCreatedAtFormatted(),
					    $submission->getSubmissionStatus()->getStatus(),
					    $submission->getStateID(),
					    $submission->getLastName(),
					    $submission->getFirstName(),
					    $submission->getNextGrade(),
					    $submission->getCurrentSchool(),
					    $submission->getFirstChoice(),
				    ];
				    if( $report_second_choice ){
					    $row[] = $submission->getSecondChoice();
				    }
				    if( $report_third_choice ){
					    $row[] = $submission->getThirdChoice();
				    }
				    fputcsv($handle, $row);
			    }
			    fclose($handle);
		    });

		    $short_school_name = $this->shortenSchoolName( $magnet_school_name );
		    $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
		    $short_school_name = str_replace( ' ', '_', $short_school_name );

		    $response->setStatusCode(200);
		    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
		    $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Missing_Learner_Screening_Devices.csv"');

		    return $response;
	    }

	    return [
		    'form' => $form->createView(),
		    'admin_pool' => $admin_pool,
		    'title' => $title,
		    'subtitle' => $subtitle,
		    'downloadFiles' => $downloadFiles
	    ];
    }

    /**
     * @Route("/admin/report/missing-audition/", name="admin_report_missing_audition_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function missingAuditionReport(Request $request){
    	$title = 'Missing Auditions Report';
        $subtitle = '';
        $downloadFiles = [];

        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

    	$user = $this->getUser();
    	$form = $this->createForm( ReportSelectionType::class, null, [
		 	'user' => $user,
		 	'entity_manager' => $this->getDoctrine()->getManager(),
		 	'open_enrollment' => $openEnrollment,
		]);
		$form->handleRequest( $request );

    	if ( $form->isValid() ) {

            $data = $form->getData();

            if( $data['magnetschool'] != 'all' ){
	            $magnet_school = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
	                ->where('school.openEnrollment = :openEnrollment')
	                ->addOrderBy('school.name', 'ASC')
	                ->addOrderBy('school.grade', 'ASC')
	                ->setParameter('openEnrollment', $openEnrollment)
	                ->andWhere('school.name LIKE :school_name')
	                ->setParameter('school_name', '%' . $data['magnetschool'] . '%')
	            	->getQuery()
	            	->getResult();
	        }

            $submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')
            	->findAllMissingAuditionsBy([
            		'openEnrollment' => $openEnrollment,
            		'magnetSchool' => ( isset( $magnet_school ) ) ? $magnet_school : null,
            		'user' => ( empty( $magnet_school ) ) ? $user : null
            	]);

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );
            $magnet_school_name = $data['magnetschool'];

            $response = new StreamedResponse();
            $response->setCallback(function() use( $submissions , $generationDate, $magnet_school_name) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Missing Recommendations report pulled from MPW on ' . $generationDate] );

                $report_second_choice = false;
                $report_third_choice = false;
                foreach( $submissions as $submission ){
                	if( $submission->getSecondChoice() != null ){
                		$report_second_choice = true;
                	}

                	if( $submission->getThirdChoice() != null ){
                		$report_third_choice = true;
                	}
                }


                $row = [
                    'Submission ID',
                    'Created At',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'First Choice School',
                ];
                if( $report_second_choice ){
                	$row[] = 'Second Choice School';
                }
                if( $report_third_choice ){
                	$row[] = 'Third Choice School';
                }

                fputcsv($handle, $row );

                foreach( $submissions as $submission ) {

                    $row = [
                        $submission->getId(),
                        $submission->getCreatedAtFormatted(),
                        $submission->getSubmissionStatus()->getStatus(),
                        $submission->getStateID(),
                        $submission->getLastName(),
                        $submission->getFirstName(),
                        $submission->getNextGrade(),
                        $submission->getCurrentSchool(),
                        $submission->getFirstChoice(),
                    ];
                    if( $report_second_choice ){
                		$row[] = $submission->getSecondChoice();
                	}
                	if( $report_third_choice ){
                		$row[] = $submission->getThirdChoice();
                	}
                    fputcsv($handle, $row);
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Missing_Auditions.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

	/**
     * @Route("/admin/report/missing-testing/", name="admin_report_missing_testing_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function missingTestingReport(Request $request){
    	$title = 'Missing Standardized Tests Report';
        $subtitle = '';
        $downloadFiles = [];

        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

    	$user = $this->getUser();
    	$form = $this->createForm( ReportSelectionType::class, null, [
		 	'user' => $user,
		 	'entity_manager' => $this->getDoctrine()->getManager(),
		 	'open_enrollment' => $openEnrollment,
		]);
		$form->handleRequest( $request );

    	if ( $form->isValid() ) {

            $data = $form->getData();

            if( $data['magnetschool'] != 'all' ){
	            $magnet_school = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
	                ->where('school.openEnrollment = :openEnrollment')
	                ->addOrderBy('school.name', 'ASC')
	                ->addOrderBy('school.grade', 'ASC')
	                ->setParameter('openEnrollment', $openEnrollment)
	                ->andWhere('school.name LIKE :school_name')
	                ->setParameter('school_name', '%' . $data['magnetschool'] . '%')
	            	->getQuery()
	            	->getResult();
	        }

            $missing = [];
	        if( isset( MYPICK_CONFIG['eligibility_fields']['standardized_testing'] ) ) {

	        	foreach( array_keys( MYPICK_CONFIG['eligibility_fields']['standardized_testing']['info_field'] ) as $key ){

	        		$title = ucwords( str_replace('_test', '', $key ) );
	        		$missing[ $title ] = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')
		            	->findAllMissingTestingBy([
		            		'openEnrollment' => $openEnrollment,
		            		'magnetSchool' => ( isset( $magnet_school ) ) ? $magnet_school : null,
		            		'user' => ( empty( $magnet_school ) ) ? $user : null,
		            		'test' => $key,
		            	]);
	            }
		    }

		    $submissions = [];
            foreach( $missing as $type => $missing_submissions ){

            	foreach( $missing_submissions as $submission ){
            		if( empty( $submissions[ $submission->getId() ] ) ){
            			$submissions[ $submission->getId() ] = [
            				'submission' => $submission,
            				'missing' => [],
            			];
            		}
            		$submissions[ $submission->getId() ]['missing'][] = $type;
            	}

            }

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );
            $magnet_school_name = $data['magnetschool'];

            $response = new StreamedResponse();
            $response->setCallback(function() use( $submissions , $generationDate, $magnet_school_name) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Missing Standardized Tests Report pulled from MPW on ' . $generationDate] );

                $report_second_choice = false;
                $report_third_choice = false;
                foreach( $submissions as $submission_row ){
                	$submission = $submission_row['submission'];
                	if( $submission->getSecondChoice() != null ){
                		$report_second_choice = true;
                	}

                	if( $submission->getThirdChoice() != null ){
                		$report_third_choice = true;
                	}
                }

                $row = [
                    'Submission ID',
                    'Created At',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'First Choice School',
                ];
                if( $report_second_choice ){
                	$row[] = 'Second Choice School';
                }
                if( $report_third_choice ){
                	$row[] = 'Third Choice School';
                }
                $row[] = 'Missing Standardized Test';

                fputcsv($handle, $row );

                foreach( $submissions as $submission_row ) {
                	$submission = $submission_row['submission'];
                    $row = [
                        $submission->getId(),
                        $submission->getCreatedAtFormatted(),
                        $submission->getSubmissionStatus()->getStatus(),
                        $submission->getStateID(),
                        $submission->getLastName(),
                        $submission->getFirstName(),
                        $submission->getNextGrade(),
                        $submission->getCurrentSchool(),
                        $submission->getFirstChoice(),
                    ];
                    if( $report_second_choice ){
                		$row[] = $submission->getSecondChoice();
                	}
                	if( $report_third_choice ){
                		$row[] = $submission->getThirdChoice();
                	}

                	foreach( $submission_row['missing'] as $missing ){
                		$row[] = $missing;
                	}
                    fputcsv($handle, $row);
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Missing_Standardized_Tests.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

	/**
     * @Route("/admin/report/missing-writing/", name="admin_report_missing_writing_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function missingWritingReport(Request $request){
    	$title = 'Missing Writing Sample Report';
        $subtitle = '';
        $downloadFiles = [];

        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

    	$user = $this->getUser();
    	$form = $this->createForm( ReportSelectionType::class, null, [
		 	'user' => $user,
		 	'entity_manager' => $this->getDoctrine()->getManager(),
		 	'open_enrollment' => $openEnrollment,
		]);
		$form->handleRequest( $request );

    	if ( $form->isValid() ) {

            $data = $form->getData();

            if( $data['magnetschool'] != 'all' ){
	            $magnet_school = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
	                ->where('school.openEnrollment = :openEnrollment')
	                ->addOrderBy('school.name', 'ASC')
	                ->addOrderBy('school.grade', 'ASC')
	                ->setParameter('openEnrollment', $openEnrollment)
	                ->andWhere('school.name LIKE :school_name')
	                ->setParameter('school_name', '%' . $data['magnetschool'] . '%')
	            	->getQuery()
	            	->getResult();
	        }

            $submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')
            	->findAllMissingWritingSamplesBy([
            		'openEnrollment' => $openEnrollment,
            		'magnetSchool' => ( isset( $magnet_school ) ) ? $magnet_school : null,
            		'user' => ( empty( $magnet_school ) ) ? $user : null,
            	]);

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );
            $magnet_school_name = $data['magnetschool'];

            $response = new StreamedResponse();
            $response->setCallback(function() use( $submissions , $generationDate, $magnet_school_name) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Missing Writing Samples Report pulled from MPW on ' . $generationDate] );

                $report_second_choice = false;
                $report_third_choice = false;
                foreach( $submissions as $submission_row ){
                	$submission = $submission_row;
                	if( $submission->getSecondChoice() != null ){
                		$report_second_choice = true;
                	}

                	if( $submission->getThirdChoice() != null ){
                		$report_third_choice = true;
                	}
                }

                $row = [
                    'Submission ID',
                    'Created At',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'First Choice School',
                ];
                if( $report_second_choice ){
                	$row[] = 'Second Choice School';
                }
                if( $report_third_choice ){
                	$row[] = 'Third Choice School';
                }

                fputcsv($handle, $row );

                foreach( $submissions as $submission_row ) {
                	$submission = $submission_row;
                    $row = [
                        $submission->getId(),
                        $submission->getCreatedAtFormatted(),
                        $submission->getSubmissionStatus()->getStatus(),
                        $submission->getStateID(),
                        $submission->getLastName(),
                        $submission->getFirstName(),
                        $submission->getNextGrade(),
                        $submission->getCurrentSchool(),
                        $submission->getFirstChoice(),
                    ];
                    if( $report_second_choice ){
                		$row[] = $submission->getSecondChoice();
                	}
                	if( $report_third_choice ){
                		$row[] = $submission->getThirdChoice();
                	}
                    fputcsv($handle, $row);
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Missing_Writing_Sample.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @Route("/admin/report/lottery-requirements/", name="admin_lottery_requirements_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function lotteryRequirementsReport(Request $request){
    	$title = 'Lottery Requirements Report';
        $subtitle = '';
        $downloadFiles = [];

        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

    	$user = $this->getUser();
    	$form = $this->createForm( ReportSelectionType::class, null, [
		 	'user' => $user,
		 	'entity_manager' => $this->getDoctrine()->getManager(),
		 	'open_enrollment' => $openEnrollment,
		]);
		$form->handleRequest( $request );

    	if ( $form->isValid() ) {

            $data = $form->getData();

            if( $data['magnetschool'] != 'all' ){
	            $magnet_school = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
	                ->where('school.openEnrollment = :openEnrollment')
	                ->addOrderBy('school.name', 'ASC')
	                ->addOrderBy('school.grade', 'ASC')
	                ->setParameter('openEnrollment', $openEnrollment)
	                ->andWhere('school.name LIKE :school_name')
	                ->setParameter('school_name', '%' . $data['magnetschool'] . '%')
	            	->getQuery()
	            	->getResult();
	        } else {
	        	$magnet_school = $this->getDoctrine()->getManager()
	        		->getRepository('IIABMagnetBundle:MagnetSchool')
	        		->findBy([ 'openEnrollment' => $openEnrollment ]);
	        }

	        $submission_status = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->findBy([
                'id' => [ 1,5,9 ]
            ]);

	        $all_submissions =[];
	        $submissions = [

            	'first' => $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')
	            	->findBy([
	            		'openEnrollment' => $openEnrollment,
	            		'firstChoice' => ( isset( $magnet_school ) ) ? $magnet_school : null,
	            		'submissionStatus' => $submission_status,
	            	], [
	            		'nextGrade' => 'DESC',
	            		'lotteryNumber' => 'ASC'
	            	]
	            ),
	            'second' => $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')
	            	->findBy([
	            		'openEnrollment' => $openEnrollment,
	            		'secondChoice' => ( isset( $magnet_school ) ) ? $magnet_school : null,
	            		'submissionStatus' => $submission_status,
	            	], [
	            		'nextGrade' => 'DESC',
	            		'lotteryNumber' => 'ASC'
	            	]
	            ),
	            'third' => $this->getDoctrine()->getRepository('IIABMagnetBundle:Submission')
	            	->findBy([
	            		'openEnrollment' => $openEnrollment,
	            		'thirdChoice' => ( isset( $magnet_school ) ) ? $magnet_school : null,
	            		'submissionStatus' => $submission_status,
	            	], [
	            		'nextGrade' => 'DESC',
	            		'lotteryNumber' => 'ASC'
	            	]
	            )
	        ];

	        foreach( $submissions as $choice => $choice_list ){

		        $grouped_by_school = [];
	            foreach( $choice_list as $submission ){
	            	$all_submissions[] = $submission;

	                $magnetSchool = $submission->{'get'. ucfirst($choice) .'Choice'}();

	                if( !empty( $magnetSchool ) ){

	                	if( !isset( $grouped_by_school[ $magnetSchool->getId() ] ) ){
	                		$grouped_by_school[ $magnetSchool->getId() ] = [];
	                	}

	                	$grouped_by_school[ $magnetSchool->getId() ][] = $submission;
	                }
	            }
	            krsort($grouped_by_school);

	            foreach( $grouped_by_school as $magnetSchoolID => $group ){

                	$magnetSchool = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:MagnetSchool')->find($magnetSchoolID);

                	$slotting_method = $magnetSchool->getProgram()->getAdditionalData('slotting_method');
                	$slotting_method = ( count($slotting_method) ) ? $slotting_method[0]->getMetaValue() : 'zoned';

                	if( $slotting_method == 'zoned' ){
                		$slotter = new \IIAB\MagnetBundle\Service\Lottery\ZonedLottery( $this->container );
                		$group = $slotter->sortSubmissions( $group );
                	} else if( $slotting_method == 'scored' ){
                		$slotter = new \IIAB\MagnetBundle\Service\Lottery\ScoredLottery( $this->container );
                		$group = $slotter->sortSubmissions( $group );
                	}
            	}

	            $submissions[ $choice ] = $grouped_by_school;
	        }

	        $all_submissions = array_values( $all_submissions );

	        $all_meta_keys = [];
	        foreach( MYPICK_CONFIG['eligibility_fields'] as $maybe_key => $settings ){
	      	    if( $settings['info_field'] !== false ){
	        		foreach( $settings['info_field'] as $key => $value ){
	        			if( strpos($key, 'recommendation_') !== false ){
	        				$all_meta_keys[] = $key .'_overall_recommendation';
	        			} else {
	        				$all_meta_keys[] = $key;
	        			}
	        		}
	        	} else {
	        		$all_meta_keys[] = $maybe_key;
	        	}
	        }

	        $all_submission_data = $this->getDoctrine()->getManager()
	        	->getRepository('IIABMagnetBundle:SubmissionData')
	        	->findBy([
	        		'submission' => $all_submissions,
	        		'metaKey' => $all_meta_keys
	        	]
	        );
	        unset( $all_submissions );
	        unset( $all_meta_keys );

	        $submission_data_hash = [];
	        foreach( $all_submission_data as $datum ){
	        	$submission_data_hash[ $datum->getSubmission()->getId() ]
	        		[ $datum->getMetaKey() ]
	        		= $datum->getMetaValue();
	        }
	        unset( $all_submission_data );

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );
            $magnet_school_name = $data['magnetschool'];

            $response = new StreamedResponse();
            $response->setCallback(function() use( $submissions , $generationDate, $magnet_school_name, $submission_data_hash) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $magnet_school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Lottery Requirements Report pulled from MPW on ' . $generationDate] );

                $row = [
                    'Submission ID',
                    'Lottery Number',
                    'Created At',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'First Choice School',
                    'Second Choice School'
                ];

                foreach( MYPICK_CONFIG['eligibility_fields'] as $maybe_key => $settings ){
                	if( !$settings['display_only']){
			      	    if( $settings['info_field'] !== false ){
			      	    	foreach( $settings['info_field'] as $key => $value ){
			      	    		if( strpos($key, 'recommendation_') !== false ){
			        				$row[] = $key .'_overall_recommendation';
			        			} else {
			        				$row[] = $key;
			        			}
			      	    	}
			      	    } else {
			      	    	$row[] = $settings['label'];
			      	    }
			      	}
		      	}

                fputcsv($handle, $row );

                foreach( $submissions as $submission_row ) {
                	foreach( $submission_row as $subs ){
                		foreach( $subs as $submission){

							$row = [
		                        $submission->getId(),
		                        $submission->getLotteryNumber(),
		                        $submission->getCreatedAtFormatted(),
		                        $submission->getSubmissionStatus()->getStatus(),
		                        $submission->getStateID(),
		                        $submission->getLastName(),
		                        $submission->getFirstName(),
		                        $submission->getNextGrade(),
		                        $submission->getCurrentSchool(),
		                        $submission->getFirstChoice(),
		                        $submission->getSecondChoice(),
		                    ];

		                    foreach( MYPICK_CONFIG['eligibility_fields'] as $maybe_key => $settings ){
		                    	if( !$settings['display_only'] ){
	                    			if( $settings['info_field'] !== false ){
						      	    	foreach( $settings['info_field'] as $key => $value ){

						      	    	 	if( !$submission->doesRequire( $maybe_key ) ){
			                     				$row[] = '';
			                     			} else {
							      	    		if( strpos($key, 'recommendation_') !== false ){
							        				$key = $key .'_overall_recommendation';
							        			}

							      	    		$row[] = ( $submission->doesRequire( $maybe_key ) )
							      	    			? ( isset( $submission_data_hash[$submission->getId()][$key] ) )
							      	    				? ( is_array($settings['choices']) && isset($settings['choices'][$submission_data_hash[$submission->getId()][$key] ]) )
							      	    					? $settings['choices'][ $submission_data_hash[$submission->getId()][$key] ]
							      	    					: $submission_data_hash[$submission->getId()][$key]
							      	    				: ''
							      	    			: '';
						      	    		}
						      	    	}
						      	    } else {
						      	    	if( !$submission->doesRequire( $maybe_key ) ){
		                    				$row[] = '';
		                    			} else {
					      	    			$row[] = ( $submission->doesRequire( $maybe_key ) )
						      	    			? ( isset( $submission_data_hash[$submission->getId()][$maybe_key] ) )
						      	    				? (is_array($settings['choices']) && isset($settings['choices'][$submission_data_hash[$submission->getId()][$maybe_key] ]) )
						      	    					? $settings['choices'][ $submission_data_hash[$submission->getId()][$maybe_key] ]
						      	    					: $submission_data_hash[$submission->getId()][$maybe_key]
						      	    				: ''
						      	    			: '';
					      	    		}
						      	    }
						      	}
					      	}
					      	fputcsv($handle, $row);
				      	}
			      	}
                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $magnet_school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Lottery_Requirements.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @Route("/admin/report/applicant-outcome/", name="admin_applicant_outcome_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function applicantOutcomeReport(Request $request){
    	$title = 'Applicant Outcome Summary Report';
        $subtitle = '';
        $downloadFiles = [];

        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

        $user = $this->getUser();
        $form = $this->createForm( ReportSelectionType::class, null, [
            'user' => $user,
            'entity_manager' => $this->getDoctrine()->getManager(),
            'open_enrollment' => $openEnrollment,
        ]);
        $form->handleRequest( $request );

        if ( $form->isValid() ) {

            $data = $form->getData();
            $report_builder = new ApplicantOutcomeReport( $this->getDoctrine() );
            $report = $report_builder->buildReport( $data, $openEnrollment );

            $now = new \DateTime();
            $generationDate = $now->format( 'm/d/Y g:i:s a' );
            $magnet_school_name = $this->shortenSchoolName( $data['magnetschool'] );

            return $report_builder->streamResponse( $report, $magnet_school_name, $generationDate );
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
	}

	/**
     * @Route("/admin/report/applicant-outcome-by-program/", name="admin_applicant_outcome_by_program_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function applicantOutcomeByProgramReport(Request $request){
    	$title = 'Applicant Outcome by Program';
        $subtitle = '';
        $downloadFiles = [];

        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

        $user = $this->getUser();
        $form = $this->createForm( ReportSelectionType::class, null, [
            'user' => $user,
            'entity_manager' => $this->getDoctrine()->getManager(),
            'open_enrollment' => $openEnrollment,
        ]);
        $form->handleRequest( $request );

        if ( $form->isValid() ) {

            $data = $form->getData();
            $report_builder = new ApplicantOutcomeByProgramReport( $this->getDoctrine() );
            $report = $report_builder->buildReport( $data, $openEnrollment );

            $now = new \DateTime();
            $generationDate = $now->format( 'm/d/Y g:i:s a' );
            $magnet_school_name = $this->shortenSchoolName( $data['magnetschool'] );

            return $report_builder->streamResponse( $report, $magnet_school_name, $generationDate );
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
	}

    /**
     * @Route("/admin/report/current-school-status/", name="admin_current_school_status_report", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     */
    public function currentSchoolStatusReport(Request $request){
    	$title = 'Submission Status By Current School Report';
        $subtitle = '';
        $downloadFiles = [];

        $session = $request->getSession();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $openEnrollment_id = $request->get('form')['openEnrollment'];
        $openEnrollment_id = ( empty( $openEnrollment_id ) ) ? $session->get( 'admin-missing-eligibility-openEnrollment', 0 ) : $openEnrollment_id;
        $openEnrollment = ( $openEnrollment_id ) ? $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $openEnrollment_id ) : $this->getDefaultOpenEnrollment();

    	$user = $this->getUser();

    	$all_label = ( empty( $schools ) ) ? 'District (all programs)' : 'Mangaged Programs (all programs you manage)';

        $unique_schools = $this->getDoctrine()
        	->getRepository( 'IIABMagnetBundle:AddressBoundSchool' )
        	->findAll();

        $schools = ['other' => 'Non-District Schools'];
        foreach( $unique_schools as $row ){
            $schools[ $row->getId() ] = $row->getName();
        }

        $form = $this->createFormBuilder()

            ->add( 'openEnrollment' , 'entity' , array(
                'class' => 'IIABMagnetBundle:OpenEnrollment' ,
                'label' => 'Enrollment' ,
                'required' => true ,
                'attr' => array( 'style' => 'margin-bottom: 20px' , 'class' => 'update-magnetschool' ) ,
                'placeholder' => 'Choose an Enrollment Period' ,
                'query_builder' => function ( $er ) {

                    $query = $er->createQueryBuilder( 'enrollment' )
                        ->orderBy( 'enrollment.year' , 'ASC' );

                    return $query;
                } ,
                'data' => $openEnrollment,
            ) )

            ->add( 'school' , 'choice' , array(
                    'label' => 'School' ,
                    'required' => true ,
                    'placeholder' => 'Choose an option' ,
                    'choices' => array_flip( $schools ),
            ) )

            ->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Program Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) );

        $form = $form->getForm();
		$form->handleRequest( $request );

    	if ( $form->isValid() ) {

            $data = $form->getData();

            if( $data['school'] != 'other' ){
            	$school = $this->getDoctrine()->getManager()
            		->getRepository('IIABMagnetBundle:AddressBoundSchool')
            		->find( $data['school'] );

            	$submissions = $this->getDoctrine()
	        		->getRepository('IIABMagnetBundle:Submission')
	            	->findBy([
	            		'openEnrollment' => $data['openEnrollment'],
            			'currentSchool' => $school->getAlias(),
            		], [
            			'lastName' => 'ASC',
            			'firstName' => 'ASC',
            		]);

	        } else {

	        	$schools = $this->getDoctrine()->getManager()
            		->getRepository('IIABMagnetBundle:AddressBoundSchool')
            		->findAll();

            	$school_names = [];
            	foreach( $schools as $school ){
            		$school_names[] = $school->getAlias();
            	}


            	$all_submissions = $this->getDoctrine()
	        		->getRepository('IIABMagnetBundle:Submission')
	            	->findBy([
	            		'openEnrollment' => $data['openEnrollment'],
            		], [
            			'lastName' => 'ASC',
            			'firstName' => 'ASC',
            		]);

            	foreach( $all_submissions as $submission ){
            		if( !in_array( $submission->getCurrentSchool(), $school_names ) ){
            			$submissions[] = $submission;
            		}
            	}
	        }

	        $all_status = $this->getDoctrine()
	        		->getRepository('IIABMagnetBundle:submissionStatus')
	            	->findAll();

	        $status_names = [];
	        $status_counts = [];
	        foreach( $all_status as $status ){
	        	$status_names[ $status->getId() ] = $status->__toString();
	        	$status_counts[ $status->getId() ] = 0;
	        }


	        foreach( $submissions as $submission ){
	        	$status_counts[ $submission->getSubmissionStatus()->getId() ] ++;
	        }

            $now = new \DateTime(null, new \DateTimeZone('America/Chicago') );
            $generationDate = $now->format( 'm/d/Y g:i:s a' );
            $school_name = $this->getDoctrine()->getManager()
        		->getRepository('IIABMagnetBundle:AddressBoundSchool')
        		->find( $data['school'] )->getAlias();

            $response = new StreamedResponse();
            $response->setCallback(function() use(
            	$submissions ,
            	$generationDate,
            	$school_name,
            	$status_counts,
            	$status_names
            ) {
                $handle = fopen('php://output', 'w+');

                $short_school_name = $this->shortenSchoolName( $school_name );

                // Add the header of the CSV file
                fputcsv($handle, ['Note: '. $short_school_name .' Submission Status by Current School Report pulled from MPW on ' . $generationDate] );

                fputcsv( $handle, [''] );
                fputcsv( $handle, ['Submission Status', 'Count'] );
                foreach( $status_counts as $id => $count ){
					fputcsv( $handle, [ $status_names[ $id ], $status_counts[$id] ] );
                }

                fputcsv( $handle, [''] );
                $row = [
                    'Submission ID',
                    'Created At',
                    'Submission Status',
                    'State ID',
                    'Last Name',
                    'First Name',
                    'Next Grade',
                    'Current School',
                    'First Choice School',
                    'Second Choice School'
                ];

                fputcsv($handle, $row );

                foreach( $submissions as $submission ) {

					$row = [
                        $submission->getId(),
                        $submission->getCreatedAtFormatted(),
                        $submission->getSubmissionStatus()->getStatus(),
                        $submission->getStateID(),
                        $submission->getLastName(),
                        $submission->getFirstName(),
                        $submission->getNextGrade(),
                        $submission->getCurrentSchool(),
                        $submission->getFirstChoice(),
                        $submission->getSecondChoice(),
                    ];

			      	fputcsv($handle, $row);

                }
                fclose($handle);
            });

            $short_school_name = $this->shortenSchoolName( $school_name );
            $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
            $short_school_name = str_replace( ' ', '_', $short_school_name );

            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Submission_Status_by_Current_School.csv"');

            return $response;
        }

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @return openEnrollment
     */
    function getDefaultOpenEnrollment()
    {

        $now = new \DateTime();
        $query = $this->getDoctrine()->getManager()->createQuery('SELECT oe
            FROM IIABMagnetBundle:OpenEnrollment oe
            WHERE oe.beginningDate < :now
            ORDER BY oe.id DESC'
        )->setParameter('now', $now->format( 'Y-m-d H:i:s' ) );
        $openEnrollment = $query->getResult();

        return ( !empty( $openEnrollment ) ) ? $openEnrollment[0] : null;
    }

    /**
     * @param $magnet_school_name
     * @return string
     */
    function shortenSchoolName( $magnet_school_name ){
        $short_school_name = explode( ' ', $magnet_school_name );
        return ( count( $short_school_name ) > 1 ) ? implode( ' ', [$short_school_name[0], $short_school_name[1] ] ) : $short_school_name[0];
    }

}