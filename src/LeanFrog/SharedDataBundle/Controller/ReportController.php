<?php
namespace LeanFrog\SharedDataBundle\Controller;

use Doctrine\ORM\EntityRepository;
use PHPExcel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use LeanFrog\SharedDataBundle\Service\SharedPopulationService;

class ReportController extends Controller {

    /**
     * @Route( "/admin/report/population-changes-v1/", name="admin_report_population_changes_v1" )
     * @Template("@lfSharedData/Admin/Report/report.html.twig")
     *
     * @return array
     */
    public function populationChangesReportActionV1() {

        $request = $this->get('request_stack')->getCurrentRequest();
        $admin_pool = $this->get('sonata.admin.pool');

        $session = $request->getSession();
        $title = 'Population Changes Report';
        $subtitle = '';
        $downloadFiles = [];

        $academic_year = $request->get('form')['academicYear'];
        $academic_year = ( empty( $academic_year ) ) ? $session->get( 'admin-population-changes-academicYear', 0 ) : $academic_year;
        $academic_year = ( $academic_year )
            ? $this->getDoctrine()
                ->getManager('shared')
                ->getRepository('lfSharedDataBundle:AcademicYear')
                ->find( $academic_year )
            : null;

        $form = $this->createFormBuilder()
            ->add('academicYear', 'entity', array(
                'class' => 'lfSharedDataBundle:AcademicYear',
                'label' => 'Academic Year',
                'data' => $academic_year,
                'required' => true,
                'attr' => array('style' => 'margin-bottom: 25px;'),
                'placeholder' => 'Choose an Academic Year',
                'query_builder' => function ($er) {

                    $query = $er->createQueryBuilder('year')
                        ->orderBy('year.startDate', 'DESC');

                    return $query;
                },
            ))
            // ->add( 'parentSchool', 'entity', [
            //     'class' => 'lfSharedDataBundle:ProgramSchool',
            //     'label' => 'Program / School',
            //     'data' => $parent_school,
            //     'required' => true,
            //     'attr' => array('style' => 'margin-bottom: 25px;'),
            //     'placeholder' => 'Choose an Program / School',
            //     'query_builder' => function ($er) {

            //         $query = $er->createQueryBuilder('school')
            //             ->where( 'school.parent IS NULL' )
            //             ->orderBy('school.name', 'ASC');

            //         return $query;
            //     },
            // ])
            ->add( 'generate_report' , 'submit' , array(
                'label' => 'Generate Population Changes Report' ,
                'attr' => array(
                    'class' => 'btn btn-primary' ,
                    'style' => 'margin-top:20px;'
                )
            ))
            ->getForm();

        $form->handleRequest( $request );
        if( $form->isValid() ) {

            set_time_limit( 0 );

            $data = $form->getData();

            $template = $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:reportData.html.twig' );

            $parent_schools = $this->getDoctrine()
                ->getManager('shared')
                ->getRepository('lfSharedDataBundle:ProgramSchool')
                ->findBy([
                    'parent' => null,
                ], ['name' => 'ASC']);

            $schools = $this->getDoctrine()
                ->getManager('shared')
                ->getRepository('lfSharedDataBundle:ProgramSchool')
                ->findBy([
                    'parent' => $parent_schools,
                ], ['gradeLevel' => 'ASC']);

            $population_service = new SharedPopulationService( $this->getDoctrine() );

            $history = [];
            foreach( $schools as $school ){
                $history[ $school->getId() ] = $population_service->getPopulationHistoryReport( $school );
            }

            echo '<pre>';
            foreach( $history as $school ){
                var_dump( $school ); die;
            }

            $phpExcelObject = $this->get( 'phpexcel' )
                ->createPHPExcelObject();

            $phpExcelObject->getProperties()
                ->setCreator( "Shared Population DB" )
                ->setLastModifiedBy( "Shared Population DB" )
                ->setTitle( "Population Changes Report for ". $parent_school->__toString() )
                ->setSubject( "Population Changes" )
                ->setDescription( "Population Changes" )
                ->setKeywords( "population" )
                ->setCategory( "population" );

            $row = 1;

            $activeSheet = $phpExcelObject->getActiveSheet();
            $activeSheet->setTitle( 'Population Changes' );

            $activeSheet->setCellValue( "A1" , 'Transfer To School/Grade' );
            $activeSheet->setCellValue( "B1" , 'From School (those Projected Next Year School)' );

            $row = 2;
            foreach( $schools as $school ){
                $activeSheet->setCellValue( "A{$row}" , $school->__toString() );
                $activeSheet->setCellValue( "B{$row}", $history[ $school->getId() ]['final'] );
                $activeSheet->setCellValue( "C{$row}", $school->getId() );
                $row ++;
            }






            // if( count( $goodGradeSubmission ) > 0 ) {

            //     $eligibilityService = new EligibilityRequirementsService(
            //         $this->container->get( 'doctrine.orm.default_entity_manager' ) );

            //     $activeSheet->setCellValue( "A{$row}" , 'Submission ID' );
            //     $activeSheet->setCellValue( "B{$row}" , 'State ID' );
            //     $activeSheet->setCellValue( "C{$row}" , 'Last Name' );
            //     $activeSheet->setCellValue( "D{$row}" , 'First Name' );
            //     $activeSheet->setCellValue( "E{$row}" , 'Next Grade' );
            //     $activeSheet->setCellValue( "F{$row}" , 'Calculated Grade Average' );

            //     $column = 6;
            //     for( $column; $column <= 101; $column++ ) {

            //         $activeSheet->setCellValueByColumnAndRow( $column , $row , 'Year' );
            //         $column++;

            //         $activeSheet->setCellValueByColumnAndRow( $column , $row , 'Semester' );
            //         $column++;

            //         $activeSheet->setCellValueByColumnAndRow( $column , $row , 'CourseType' );
            //         $column++;

            //         $activeSheet->setCellValueByColumnAndRow( $column , $row , 'CourseName' );
            //         $column++;

            //         $activeSheet->setCellValueByColumnAndRow( $column , $row , 'Grade' );

            //     }
            //     $row++;

            //     foreach( $goodGradeSubmission as $submission ) {

            //         $magnetSchool = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findOneBy( array(
            //             'name' => $data['program'] ,
            //             'grade' => $submission->getNextGrade(),
            //             'openEnrollment' => $data['openenrollment']
            //         ) );
            //         $eligibilityGrade = 'N/A';
            //         list( $passedEligibility , $eligibilityGrade , $eligibilityCourseTitle , $eligibilityCheck ) = $eligibilityService->doesStudentPassRequirements( array( 'submissionID' => $submission->getId() ) , $magnetSchool );
            //         foreach( $eligibilityCheck as $key => $check ) {
            //             if( $check == 'GPA CHECK' ) {
            //                 $eligibilityGrade = $eligibilityGrade[$key];
            //             }
            //         }

            //         $activeSheet->setCellValue( "A{$row}" , $submission->__toString() );
            //         $activeSheet->setCellValue( "B{$row}" , $submission->getStateID() );
            //         $activeSheet->setCellValue( "C{$row}" , $submission->getLastName() );
            //         $activeSheet->setCellValue( "D{$row}" , $submission->getFirstName() );
            //         $activeSheet->setCellValue( "E{$row}" , $submission->getNextGradeString() );
            //         $activeSheet->setCellValue( "F{$row}" , $eligibilityGrade );

            //         $maxNumberOfRecords = 4;
            //         if( $submission->getNextGrade() == 11 ) {
            //             $maxNumberOfRecords = 12;
            //         }
            //         if( $submission->getNextGrade() == 12 ) {
            //             $maxNumberOfRecords = 20;
            //         }

            //         $column = 6;
            //         $grades = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionGrade' )->createQueryBuilder( 'g' )
            //             ->where( 'g.academicTerm NOT LIKE :summer' )
            //             ->andWhere( 'g.submission = :submission' )
            //             ->orderBy( 'g.academicYear' , 'DESC' )
            //             ->addOrderBy( 'g.academicTerm' , 'DESC' )
            //             ->setParameter( 'summer' , '%summer%' )
            //             ->setParameter( 'submission' , $submission )
            //             ->setMaxResults( $maxNumberOfRecords )
            //             ->getQuery()
            //             ->getResult();

            //         foreach( $grades as $grade ) {

            //             $activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getAcademicYear() );
            //             $column++;

            //             $activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getAcademicTerm() );
            //             $column++;

            //             $activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getCourseType() );
            //             $column++;

            //             $activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getCourseName() );
            //             $column++;

            //             $activeSheet->setCellValueByColumnAndRow( $column , $row , $grade->getNumericGrade() );
            //             $column++;
            //         }

            //         $row++;
            //     }

            //     $row++;
            // } else {
            //     $activeSheet->setCellValue( "A{$row}" , 'No good submissions found.' );
            // }

            // if( count( $badGradeSubmission ) > 0 ) {
            //     $activeSheet = $phpExcelObject->createSheet();
            //     $activeSheet->setTitle( 'Bad Grades' );
            //     $row = 1;

            //     $activeSheet->setCellValue( "A{$row}" , 'Submission ID' );
            //     $activeSheet->setCellValue( "B{$row}" , 'State ID' );
            //     $activeSheet->setCellValue( "C{$row}" , 'Last Name' );
            //     $activeSheet->setCellValue( "D{$row}" , 'First Name' );
            //     $activeSheet->setCellValue( "E{$row}" , 'Next Grade' );
            //     $activeSheet->setCellValue( "F{$row}" , 'Error' );
            //     $row++;

            //     foreach( $badGradeSubmission as $id => $submissionArray ) {
            //         $activeSheet->setCellValue( "A{$row}" , $submissionArray['submission']->__toString() );
            //         $activeSheet->setCellValue( "B{$row}" , $submissionArray['submission']->getStateID() );
            //         $activeSheet->setCellValue( "C{$row}" , $submissionArray['submission']->getLastName() );
            //         $activeSheet->setCellValue( "D{$row}" , $submissionArray['submission']->getFirstName() );
            //         $activeSheet->setCellValue( "E{$row}" , $submissionArray['submission']->getNextGrade() );
            //         $activeSheet->setCellValue( "F{$row}" , $submissionArray['error'] );

            //         $row++;
            //     }
            // }

            $writer = $this->get( 'phpexcel' )
                ->createWriter( $phpExcelObject , 'Excel2007' );
            // create the response
            $response = $this->get( 'phpexcel' )
                ->createStreamedResponse( $writer );
            // adding headers
            $response->headers->set( 'Content-Type' , 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8' );
            $response->headers->set( 'Content-Disposition' , 'attachment;filename=population-changes-for-'. str_replace(' ', '-', $this->shortenSchoolName( $parent_school->__toString() ) ) .'.xlsx' );
            $response->headers->set( 'Pragma' , 'public' );
            $response->headers->set( 'Cache-Control' , 'maxage=1' );
            return $response;

            die;

            //$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 15 , 15 , 10 , 10 );




            var_dump( $school->getParent() );

            echo '<pre>';
            $history = $population_service->getPopulationHistoryReport( $school );
            var_dump( $history ); die;

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

        return [
            'form' => $form->createView(),
            'admin_pool' => $admin_pool,
            'title' => $title,
            'subtitle' => $subtitle,
            'downloadFiles' => $downloadFiles
        ];
    }

    /**
     * @Route( "/admin/report/special-population-changes/", name="admin_report_mpw_population_changes" )
     * @Template("@lfSharedData/Admin/Report/report.html.twig")
     *
     * @return array
     */
    public function mpwPopulationChangesReportAction() {

        $request = $this->get('request_stack')->getCurrentRequest();
        $admin_pool = $this->get('sonata.admin.pool');

        $session = $request->getSession();
        $title = 'Population Changes Report';
        $subtitle = '';
        $downloadFiles = [];

        $open_enrollment = $request->get('form')['openEnrollment'];
        $open_enrollment = ( empty( $open_enrollment ) ) ? $session->get( 'admin-population-changes-openEnrollment', 0 ) : $open_enrollment;
        $open_enrollment = ( $open_enrollment )
            ? $this->getDoctrine()
                ->getManager()
                ->getRepository('IIABMagnetBundle:OpenEnrollment')
                ->find( $open_enrollment )
            : null;

        $form = $this->createFormBuilder()
            ->add('openEnrollment', 'entity', array(
                'class' => 'IIABMagnetBundle:OpenEnrollment',
                'label' => 'Enrollment Period',
                'data' => $open_enrollment,
                'required' => true,
                'attr' => array('style' => 'margin-bottom: 25px;'),
                'placeholder' => 'Choose an Enrollment Period',
                'query_builder' => function ($er) {

                    $query = $er->createQueryBuilder('oe')
                        ->orderBy('oe.year', 'DESC');

                    return $query;
                },
            ))

            ->add( 'generate_report' , 'submit' , array(
                'label' => 'Generate Population Changes Report' ,
                'attr' => array(
                    'class' => 'btn btn-primary' ,
                    'style' => 'margin-top:20px;'
                )
            ))
            ->getForm();

        $form->handleRequest( $request );
        if( $form->isValid() ) {

            set_time_limit( 0 );

            $data = $form->getData();

            $template = $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:reportData.html.twig' );

            $programs = $this->getDoctrine()
                ->getManager()
                ->getRepository('IIABMagnetBundle:Program')
                ->findBy([
                    'openEnrollment' => $open_enrollment
                ], ['name' => 'ASC']);

            $magnet_schools = $this->getDoctrine()
                ->getManager()
                ->getRepository('IIABMagnetBundle:MagnetSchool')
                ->findBy([
                    'program' => $programs
                ], [
                    'name' => 'ASC',
                    'grade' => 'ASC'
                ]);

            $accepted_offers = $this->getDoctrine()
                ->getManager()
                ->getRepository('IIABMagnetBundle:Offered')
                ->findBy([
                    'openEnrollment' => $open_enrollment,
                    'accepted' => 1,
                ], [
                    'offeredDateTime' => 'ASC'
                ]);

            $address_bound_schools = $this->getDoctrine()
                ->getManager()
                ->getRepository('IIABMagnetBundle:AddressBoundSchool')
                ->findAll();

            $zoned_schools = $this->get( 'magnet.population' )->getTrackingColumnLabels()['HomeZone'];

            $zoned_shcool_hash = [];
            foreach( $address_bound_schools as $address_bound_school ){
                $zoned_shcool_hash[ $address_bound_school->getAlias() ] = $address_bound_school->getName();
            }

            $report_data = [];
            foreach( $programs as $program ){
                $report_data[ $program->getId() ] = [
                    'program' => $program,
                    'magnet_schools' => [],
                ];
            }

            foreach( $magnet_schools as $magnet_school ){
                $report_data
                    [ $magnet_school->getProgram()->getId() ]
                    ['magnet_schools']
                    [ $magnet_school->getId() ] = [
                        'magnet_school' => $magnet_school,
                        'zoned_schools' => array_fill_keys( $zoned_schools, 0 ),
                        'changes' => 0,
                        'total_population' => $this->get( 'magnet.population' )->getCurrentTotalPopulation( $magnet_school )['Race'],
                    ];
            }

            foreach( $accepted_offers as $offer ){

                $zoned_school = $offer->getSubmission()->getZonedSchool();

                $zoned_school = ( !empty( $zoned_school ) ) ? $zoned_school : 'OTHER SCHOOL';

                $zoned_school = ( isset( $zoned_shcool_hash[ $zoned_school ] ) ) ? $zoned_shcool_hash[ $zoned_school ] : 'OTHER SCHOOL';

                $report_data
                    [$offer->getAwardedSchool()->getProgram()->getId()]
                    ['magnet_schools']
                    [$offer->getAwardedSchool()->getId()]
                    ['changes'] ++;

                $report_data
                    [$offer->getAwardedSchool()->getProgram()->getId()]
                    ['magnet_schools']
                    [$offer->getAwardedSchool()->getId()]
                    ['zoned_schools']
                    [$zoned_school] ++;
            }


            $phpExcelObject = $this->get( 'phpexcel' )
                ->createPHPExcelObject();

            $phpExcelObject->getProperties()
                ->setCreator( "Shared Population DB" )
                ->setLastModifiedBy( "Shared Population DB" )
                ->setTitle( "Population Changes Report for ". $open_enrollment->__toString() )
                ->setSubject( "Population Changes" )
                ->setDescription( "Population Changes" )
                ->setKeywords( "population" )
                ->setCategory( "population" );

            $row = 1;

            $activeSheet = $phpExcelObject->getActiveSheet();
            $activeSheet->setTitle( 'Population Changes' );

            $activeSheet->setCellValue( "A1" , 'Transfer To School/Grade' );
            $activeSheet->setCellValue( "B1" , 'Total' );
            $activeSheet->setCellValue( "C1" , 'From School (those Projected Next Year School)' );
            $column = 'C';
            foreach( $zoned_schools as $zoned_school ){
                $activeSheet->setCellValue( "{$column}2" , $zoned_school );
                $column ++;
            }

            $row = 3;
            foreach( $report_data as $data_row ){
                $activeSheet->setCellValue( "A{$row}" , $data_row['program']->__toString() );

                foreach( $data_row['magnet_schools'] as $magnet_school ){
                    $row++;
                    $activeSheet->setCellValue( "A{$row}" , $magnet_school['magnet_school']->__toString() );
                    $activeSheet->setCellValue( "B{$row}" , $magnet_school['changes'] );
                    $column = 'C';
                    foreach( $magnet_school['zoned_schools'] as $zoned ){
                        $activeSheet->setCellValue( "{$column}{$row}" , $zoned );
                        $column ++;
                    }
                }
                $row ++;
            }

            $activeSheet = $phpExcelObject->createSheet();
            $activeSheet->setTitle( 'Data' );

            $column_titles = [
                'Submission',
                'State Id',
                'Last Name',
                'First Name',
                'Race',
                'Current Grade',
                'Current School',
                'Address',
                'ZIP Code',
                'Next Grade',
                'Zoned School',
                'Choice School',
                'Awarded School',
            ];

            $column = 'A';
            foreach( $column_titles as $title ){
                $activeSheet->setCellValue( "{$column}1" , $title );
                $column ++;
            }

            $row = 2;
            foreach( $accepted_offers as $offer ){
                $submission = $offer->getSubmission();
                $activeSheet->setCellValue( "A{$row}" , $submission->__toString() );
                $activeSheet->setCellValue( "B{$row}" , $submission->getStateID() );
                $activeSheet->setCellValue( "C{$row}" , $submission->getLastName() );
                $activeSheet->setCellValue( "D{$row}" , $submission->getFirstName() );
                $activeSheet->setCellValue( "E{$row}" , $submission->getRaceFormatted() );
                $activeSheet->setCellValue( "F{$row}" , $submission->getCurrentGrade() );
                $activeSheet->setCellValue( "G{$row}" , $submission->getCurrentSchool() );
                $activeSheet->setCellValue( "H{$row}" , $submission->getAddress() );
                $activeSheet->setCellValue( "I{$row}" , $submission->getZip() );
                $activeSheet->setCellValue( "J{$row}" , $submission->getNextGrade() );
                $activeSheet->setCellValue( "K{$row}" , $submission->getZonedSchool() );
                $activeSheet->setCellValue( "L{$row}" , $submission->getChoiceZone() );
                $activeSheet->setCellValue( "M{$row}" , $offer->getAwardedSchool()->getName() );
                $row ++;
            }

            $writer = $this->get( 'phpexcel' )
                ->createWriter( $phpExcelObject , 'Excel2007' );
            // create the response
            $response = $this->get( 'phpexcel' )
                ->createStreamedResponse( $writer );
            // adding headers
            $response->headers->set( 'Content-Type' , 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8' );
            $response->headers->set( 'Content-Disposition' , 'attachment;filename=population-changes-for-'. str_replace(' ', '-', $open_enrollment->__toString() ) .'.xlsx' );
            $response->headers->set( 'Pragma' , 'public' );
            $response->headers->set( 'Cache-Control' , 'maxage=1' );
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
     * @param $school_name
     * @return string
     */
    function shortenSchoolName( $school_name ){
        $short_school_name = explode( ' ', $school_name );
        return ( count( $short_school_name ) > 1 ) ? implode( ' ', [$short_school_name[0], $short_school_name[1] ] ) : $short_school_name[0];
    }

}