<?php
namespace IIAB\MagnetBundle\Command;

use IIAB\MagnetBundle\Entity\StudentGrade;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportGradeDataCommand extends ContainerAwareCommand {

    // public $output = null;
    // public $students = [];
    public $year = null;

    protected function configure() {

        $this
            ->setName( 'magnet:student:grades' )
            ->setDescription( 'Import Grade data from iNow dump file' )
            ->setHelp( <<<EOF
The <info>%command.name%</info> command imports student grades from the iNow dump file.

<info>php %command.full_name%</info>

EOF
            );
    }

    protected function execute( InputInterface $input , OutputInterface $output ) {

        var_dump( date('H:i') );

        ini_set('memory_limit','5120M');

        $this->output = $output;

        $env = $this->getContainer()->get( 'kernel' )->getEnvironment();

        $em = $this->getContainer()->get( 'doctrine' )->getManager();
        $em->getConnection()->getConfiguration()->setSQLLogger(null);

        $clear_outcomes = $em->createQuery('DELETE IIABMagnetBundle:StudentGrade');
        $clear_outcomes->execute();

        // Get all Academic Sessions
        $sessions_hash = [
            'current' => [],
            'late' => []
        ];
        $acad_sessions = $this->get_inow_api_response( 'acadsessions' );

        $grading_periods_hash = [];
        $graded_items_hash = [];
        $graded_items_terms = [];
        foreach($acad_sessions as $acad_session){

            $year_offset = intval( $acad_session->AcadYear ) - $this->getYear();

            if( $year_offset == 0 ){
                $sessions_hash['current'][ $acad_session->Id ] = $acad_session;
            } else if( $year_offset == -1 ){
                $sessions_hash['last'][ $acad_session->Id ] = $acad_session;
            }
        }

        uasort( $sessions_hash['current'], function ($a, $b)
        {
            if ($a->StartDate == $b->StartDate) {
                return 0;
            }
            return ($a->StartDate < $b->StartDate) ? 1 : -1;
        });

        uasort( $sessions_hash['last'], function ($a, $b)
        {
            if ($a->StartDate == $b->StartDate) {
                return 0;
            }
            return ($a->StartDate < $b->StartDate) ? 1 : -1;
        });

        $new = 0;
        $student_objects = array();
        $duplicates = [];

        $enrollment_statuses = [
            'Enrolled',
            'Registered'
        ];

        $find_students = [];
        foreach ($sessions_hash as $sessions) {

            foreach( $enrollment_statuses as $status ) {

                foreach ($sessions as $session_id => $acad_session) {

                    $grades = $this->get_inow_api_response($session_id . '/students/grades');

                    $grading_periods = $this->get_inow_api_response( $acad_session->Id .'/gradingPeriods' );

                    foreach( $grading_periods as $grading_period ){
                        if( is_object( $grading_period ) ){
                            $grading_periods_hash[ $grading_period->Id ] = $grading_period->Code;
                        }
                    }

                    $graded_items = $this->get_inow_api_response( $acad_session->Id .'/gradedItems' );

                    foreach( $graded_items as $graded_item ){
                        if( is_object( $graded_item ) ){

                            $item_name = strtoupper( trim( $graded_item->Name ) );
                            $term = '';

                            switch( $item_name ){
                                case 'AVG 1':
                                case 'AVG 2':
                                case 'AVG 3':
                                case 'AVG 4':
                                    $term = $grading_periods_hash[ $graded_item->GradingPeriodId ];
                                    break;
                                case 'TRM 1':
                                    $term = 'Semester 1';
                                    break;
                                case 'TRM 2':
                                    $term = 'Semester 2';
                                    break;
                                case 'FINAL':
                                case 'TSCRPT':
                                case 'TRANS':
                                case 'EXM 1':
                                case 'EXM 2':
                                case 'EXM 3':
                                case 'EXM 4':
                                case 'GRD 1':
                                case 'GRD 2':
                                case 'GRD 3':
                                case 'GRD 4':
                                case 'BTEST1':
                                case 'BTEST2':
                                case 'BTEST3':
                                case 'BTEST4':
                                    $term = '';
                                    break;
                                default:
                                    var_dump(
                                        $graded_item->GradingPeriodId.' '.
                                        $graded_item->Id .' unknown term '.
                                        $graded_item->Name
                                    );
                                    $term = '';
                                    break;
                            }


                            $graded_items_terms[ $graded_item->Id ] = $term;
                            $graded_items_hash[ $graded_item->Id ] = $graded_item->GradingPeriodId;
                        }
                    }

                    $students = $this->get_inow_api_response($session_id . '/students?status=' . $status);
                    if (is_object($students)) {
                        $students = array();
                    };

                    foreach ($students as $index => $student) {

                        if ( is_object($student) && $student->StateIdNumber ) {


                            if( empty( $find_students[ $student->Id ] ) ){
                                $find_students[ $student->Id ] = $student ;
                            }
                        }
                    }
                }
            }
        }

        $all_grades = [];
        $sections = [];

        $commits = 0;
        $flush_counter = 0;
        foreach( $sessions_hash as $sessions ){
            foreach( $sessions as $session_id => $acad_session ){

                $grades = $this->get_inow_api_response($session_id . '/students/grades');

                $year_offset = intval( $acad_session->AcadYear ) - $this->getYear();

                foreach( $grades as $grade ){

                    if( is_object( $grade ) ){

                        if( empty( $sections[ $grade->SectionId ] ) ){

                            $section = $this->get_inow_api_response($session_id . '/sections/'. $grade->SectionId );

                            $course_type = false;
                            if( is_object( $section ) ){

                                echo $section->CourseTypeId.' ';

                                switch( $section->CourseTypeId ){
                                    case 1:
                                        $course_type = '?business?' . $section->Name;
                                        break;
                                    case 2:
                                        $course_type = 'elective';
                                        break;
                                    case 3:
                                        if( strpos( strtolower( $section->ShortName ), 'read') !== false ){
                                            $course_type = 'reading';
                                        } else {
                                            $course_type = 'english';
                                        }
                                        break;
                                    case 4:
                                        $course_type = 'art';
                                        break;
                                    case 5:
                                        $course_type = 'foreign language';
                                        break;
                                    case 6:
                                        $course_type = 'health';
                                        break;
                                    case 7:
                                        $course_type = 'math';
                                        break;
                                    case 8:
                                        $course_type = '??' . $section->Name;
                                        break;
                                    case 9:
                                        $course_type = 'physical education';
                                        break;
                                    case 10:
                                        $course_type = 'science';
                                        break;
                                    case 11:
                                        $course_type = 'social studies';
                                        break;
                                    default:
                                        $course_type = '??' . $section->Name;
                                        break;
                                }

                                $sections[ $grade->SectionId ] = [
                                    'course_id' => $section->CourseTypeId,
                                    'course_type' => $course_type,
                                    'course_title' => $section->ShortName
                                ];
                            }

                        } else {
                            $course_type = $sections[ $grade->SectionId ]['course_type'];
                        }

                        if( empty( $graded_items_hash[ $grade->GradedItemId ] ) ){
                            $graded_item = $this->get_inow_api_response( $session_id . '/gradedItems/'. $grade->GradedItemId );

                            $graded_items_hash[ $grade->GradedItemId ] = $graded_item->GradingPeriodId;

                        }

                        if( $course_type ){

                            if( isset( $find_students[ $grade->StudentId ] ) ){

                                $commit_grade = true;

                                $state_id = $find_students[ $grade->StudentId ]->StateIdNumber;
                                if( empty( $state_id )
                                    || is_array( $state_id )
                                    || is_object( $state_id )
                                ){
                                    $commit_grade = false;
                                }

                                $academic_term = $grading_periods_hash[ $graded_items_hash[ $grade->GradedItemId ] ];
                                if( empty( $academic_term )
                                    || is_array( $academic_term )
                                    || is_object( $academic_term )
                                ){
                                    $commit_grade = false;
                                }

                                $course_id = $sections[ $grade->SectionId ]['course_id'];
                                if( empty( $course_id )
                                    || is_array( $course_id )
                                    || is_object( $course_id )
                                ){
                                    $commit_grade = false;
                                }

                                $course_title = $sections[ $grade->SectionId ]['course_title'];
                                if( empty( $course_title )
                                    || is_array( $course_title )
                                    || is_object( $course_title )
                                ){
                                    $commit_grade = false;
                                }

                                $numeric_grade = $grade->NumericGrade;
                                if( !is_numeric( $numeric_grade )
                                    || is_array( $numeric_grade )
                                    || is_object( $numeric_grade )
                                ){
                                    $commit_grade = false;
                                }

                                if( $commit_grade ){
                                    $student_grade_object = new StudentGrade();
                                    $student_grade_object->setStateID( $state_id );
                                    $student_grade_object->setAcademicYear( $year_offset );

                                    $student_grade_object->setAcademicTerm( $academic_term );
                                    $student_grade_object->setCourseTypeId( $course_id );
                                    $student_grade_object->setCourseType( $course_type );
                                    $student_grade_object->setCourseName( $course_title );
                                    $student_grade_object->SetSectionNumber( $grade->SectionId );
                                    $student_grade_object->setNumericGrade( $grade->NumericGrade );

                                    $em->persist( $student_grade_object );
                                    $flush_counter++;
                                }

                                if( $flush_counter >= 5000 ){

                                    $commits ++;
                                    var_dump( $commits . date(' H:i ') . memory_get_usage() );
                                    $em->flush();
                                    $em->clear('IIAB\MagnetBundle\Entity\StudentGrade');
                                    $em->clear();
                                    $flush_counter = 0;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function getYear(){

        if( !empty( $this->year ) ){
            return $this->year;
        }

        $em = $this->getContainer()->get( 'doctrine' )->getManager();
        $openEnrollment = $em->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findByDate( new \DateTime() );

        if( !count( $openEnrollment ) ) {
            $openEnrollment = $em->getRepository('IIABMagnetBundle:OpenEnrollment')->findLatePlacementByDate(new \DateTime());
        }
        $openEnrollment = ( isset( $openEnrollment[0] ) ) ? $openEnrollment[0] : null;

        $this->year = ( empty($openEnrollment ) ) ?
            intval( date('Y') ) :
            intval( explode('-', $openEnrollment->getYear())[1] ) -1 ;
        return $this->year;
    }

    /**
     * @param $endpoint
     */
    public function get_inow_api_response($endpoint){

        //$url = 'https://inow.mps.k12.al.us/Api/' . $endpoint;
        //$url = 'https://inow.hsv-k12.org/Api/' . $endpoint;
        $url = 'https://inow.tusc.k12.al.us/API/' . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:13.0) Gecko/20100101 Firefox/13.0.1');  // mPDF 5.7.4
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'ApplicationKey: leanfrog B/1F8Y/ToQlRufi/0DgoaKLOBcrd3PpT+wFJL6Sdwy2Z8vZP6GamF7KDmU2nb+Cn/ayElMuxwrWreWae06oNhrCE29gnEizIdFuS3bICs3eFOe7bnRsVyPbPE+4CmOc9QzI5pTbUv9aH/7TrSVVSYcL5WaLzeEwnl2+hlj9c2dw=',
        ));
        curl_setopt ( $ch , CURLOPT_RETURNTRANSFER , 1 );
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); //timeout after 30 seconds
        curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
        //curl_setopt( $ch, CURLOPT_USERPWD, "LeanFrog_API:9ySjKbFy"); // HCS
        //curl_setopt( $ch, CURLOPT_USERPWD, "LeanFrog_API:ggxnBv9w"); // MPS
        curl_setopt( $ch, CURLOPT_USERPWD, "LeanFrog_API:qs4p2CNu4H4N9g7ETKzF"); //TCS
        $data = curl_exec($ch);
        curl_close($ch);

        if( !$data ) {
            //var_dump( $url );
            return [];
        }
        $decoded_data = json_decode($data);

        if( json_last_error() != JSON_ERROR_NONE ){

            var_dump( 'JSON error: ' . json_last_error() );
            return false;
        }
        return $decoded_data;
    }
}