<?php
/**
 * Created by PhpStorm.
 * User: michellegivens
 * Date: 3/10/15
 * Time: 11:35 PM
 */

namespace IIAB\MagnetBundle\Command;

use IIAB\MagnetBundle\Entity\Process;
use IIAB\MagnetBundle\Entity\Student;
use IIAB\MagnetBundle\Entity\SubmissionStatus;
use IIAB\MagnetBundle\Entity\Race;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportStudentDataCommand extends ContainerAwareCommand {

    private $states = [
        '',
        'AL',
        'AK',
        'AZ',
        'AR',
        'CA',
        'CO',
        'CT',
        'DE',
        'DC',
        'FL',
        'GA',
        'HI',
        'ID',
        'IL',
        'IN',
        'IA',
        'KS',
        'KY',
        'LA',
        'ME',
        'MD',
        'MA',
        'MI',
        'MN',
        'MS',
        'MO',
        'MT',
        'NE',
        'NV',
        'NH',
        'NJ',
        'NM',
        'NY',
        'NC',
        'ND',
        'OH',
        'OK',
        'OR',
        'PA',
        'PR',
        'RI',
        'SC',
        'SD',
        'TN',
        'TX',
        'UT',
        'VT',
        'VA',
        'WA',
        'WV',
        'WI',
        'WY',
    ];



	protected function configure() {

		$this
			->setName( 'magnet:student:import' )
			->setDescription( 'Import Student data from API' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command imports student data from the iNow database API.

<info>php %command.full_name%</info>

EOF
			);
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		ini_set('memory_limit','2048M');

		$env = $this->getContainer()->get( 'kernel' )->getEnvironment();
		var_dump( 'Running Student Import in environment: ' . $env );

        $this->import_students_from_inow();

		$this->getContainer()->get( 'doctrine' )->getManager()->flush();
		var_dump( 'Completed Student Import in envinronment: ' . $env );
	}


	public function import_students_from_inow(){

        // $local_db_races = $this->getContainer()->get( 'doctrine' )->getManager()->getRepository('IIABMagnetBundle:Race')->findAll();

	    // Get all ethnicity/race types
        // TODO use the Races table
        //$import_to_local_races_hash = [];
	    $race_hash = array();
	    $ethnicities = $this->get_inow_api_response( 'ethnicities' );

        foreach( $ethnicities as $ethnicity ){

            $race_hash[ $ethnicity->ID ] = $ethnicity->Name;
            // $local_race  = null;
            // foreach( $local_db_races as $local ){
            //     if(
            //         strtoupper( $ethnicity->Name ) == strtoupper( $local->getRace() )
            //         || strtoupper( $ethnicity->Name ) == strtoupper( $local->getShortName() )
            //     ){
            //         $local_race = $local;
            //     }
            // }

            // if( is_null( $local_race ) ) {
            //         $new_race = new Race();
            //         $new_race->setRace( $ethnicity->Name );
            //         $new_race->setShortName( '' );
            //         $new_race->setReportAsOther( true );
            //         $new_race->setReportAsNoAnswer( false );

            //         $this->getContainer()->get( 'doctrine' )->getManager()->persist( $new_race );
            //         $this->getContainer()->get( 'doctrine' )->getManager()->flush();
            //         $local_race = $new_race;
            //     }
            // $import_to_local_races_hash[ $ethnicity->Id ] = $local_race;
        }

        // Get all student ethnicities
        $student_race_hash = array();
        $ethnicities = $this->get_inow_api_response( 'persons/ethnicities' );
        foreach( $ethnicities as $ethnicity ){
            if( $ethnicity->IsPrimary ) {
                //$student_race_hash[$ethnicity->PersonId] = $import_to_local_races_hash[ $ethnicity->EthnicityId ];
                $student_race_hash[$ethnicity->PersonId] = $race_hash[ $ethnicity->EthnicityId ];
            }
        }

        // Get all student addresses
        $student_address_hash = array();
        $addresses = $this->get_inow_api_response( 'persons/addresses' );
        foreach( $addresses as $address ){

            if( $address->IsPhysical ) {
                $student_address_hash[ $address->PersonId ] = [
                    'address' => implode( ' ', [ $address->AddressLine1, $address->AddressLine2 ] ),
                    'city' => $address->City,
                    'state' => ( isset( $this->states[ $address->StateId ] ) ) ? $this->states[ $address->StateId ] : 'AL',
                    'zip' => $address->PostalCode
                ];
            }
        }

        // Get all student email addresses
        $student_email_hash = array();
        $emails = $this->get_inow_api_response( 'persons/emailaddresses' );
        foreach( $emails as $email ){

            if( $email->IsPrimary ) {
                $student_email_hash[ $email->PersonId ] = $email->EmailAddress;
            }
        }

        // Get all schools
        $schools_hash = array();
        $schools = $this->get_inow_api_response( 'schools' );
        foreach( $schools as $school ){
            $schools_hash[ $school->Id ] = $school->Name;
        }

        // Get all Grade Levels
        $grade_level_hash = array();
        $grade_levels = $this->get_inow_api_response( 'gradelevels' );
        foreach( $grade_levels as $grade_level ){
            $grade_level_hash[ $grade_level->Id ] = intval( $grade_level->Name );
        }

        // Get all Genders
        $gender_hash = array();
        $genders = $this->get_inow_api_response( 'genders' );
        foreach( $genders as $gender ){
            $gender_hash[ $gender->Id ] = $gender->Name;
        }

        // Get all Academic Sessions
        $sessions_hash = [
            'current' => [],
            'late' => []
        ];
	    $acad_sessions = $this->get_inow_api_response( 'acadsessions' );


        foreach($acad_sessions as $acad_session){

            if( $acad_session->AcadYear == date('Y') ){
                $sessions_hash['current'][ $acad_session->Id ] = $acad_session;
            } else if( $acad_session->AcadYear == date('Y') - 1  ){
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

        foreach ($sessions_hash as $sessions) {

            foreach( $enrollment_statuses as $status ) {

                foreach ($sessions as $session_id => $acad_session) {

                    $students = $this->get_inow_api_response($session_id . '/students?status=' . $status);

                    if (is_object($students)) {
                        $students = array();
                    };

                    $find_students = [];
                    foreach ($students as $index => $student) {

                        if (!is_object($student)) {
                            unset($students[$index]);
                        } else {
                            $find_students[] = $student->StateIdNumber;
                        }
                    }

                    $found_students = $this->getContainer()->get('doctrine')->getRepository('IIABMagnetBundle:Student')->findBy(['stateID' => $find_students]);
                    $found_students_hash = [];

                    foreach ($found_students as $found_student) {
                        $found_students_hash[$found_student->getStateID()] = $found_student;
                    }
                    unset($found_students);

                    foreach ($students as $student) {

                        if (!isset($student_objects[$student->StateIdNumber])) {

                            if (empty($found_students_hash[$student->StateIdNumber])) {
                                $student_object = new Student();
                                $student_object->setStateID($student->StateIdNumber);
                                $new++;
                            } else {
                                $student_object = $found_students_hash[$student->StateIdNumber];
                            }

                            $birthday = new \DateTime(str_replace('T', ' ', $student->DateOfBirth));
                            $race = isset($student_race_hash[$student->Id]) ? $student_race_hash[$student->Id] : '';
                            $address = (isset($student_address_hash[$student->Id])) ? $student_address_hash[$student->Id]['address'] : '';
                            $city = (isset($student_address_hash[$student->Id])) ? $student_address_hash[$student->Id]['city'] : '';
                            $state = (isset($student_address_hash[$student->Id])) ? $student_address_hash[$student->Id]['state'] : '';
                            $zip = (isset($student_address_hash[$student->Id])) ? $student_address_hash[$student->Id]['zip'] : '';
                            $school = (isset($schools_hash[$acad_session->SchoolId])) ? $schools_hash[$acad_session->SchoolId] : '';
                            $grade_level = (isset($grade_level_hash[$student->GradeLevelId])) ? $grade_level_hash[$student->GradeLevelId] : '';
                            $gender = isset($gender_hash[$student->GenderId]) ? $gender_hash[$student->GenderId] : '';
                            $email = (isset($student_email_hash[$student->Id])) ? $student_email_hash[$student->Id] : '';

                            $student_object->setFirstName($student->FirstName);
                            $student_object->setLastName($student->LastName);
                            $student_object->setRace($race);
                            $student_object->setBirthday($birthday);
                            $student_object->setAddress($address);
                            $student_object->setCity($city);
                            $student_object->setState($state);
                            $student_object->setZip($zip);
                            $student_object->setCurrentSchool($school);
                            $student_object->setGrade($grade_level);
                            $student_object->setNonHSVStudent(false);
                            $student_object->setIsHispanic($student->IsHispanic);
                            $student_object->setGender($gender);
                            $student_object->setEmail($email);

                            $this->getContainer()->get('doctrine')->getManager()->persist($student_object);
                            $objects_entry = [
                                'student' => $student_object,
                                'sessions' => [],
                            ];
                            $objects_entry['sessions'][] = $acad_session;
                            $student_objects[$student->StateIdNumber] = $objects_entry;
                        } else {
                            $is_duplicate = false;
                            foreach ($student_objects[$student->StateIdNumber]['sessions'] as $previous_session) {
                                if ($acad_session->StartDate >= $previous_session->StartDate) {
                                    $is_duplicate = true;
                                }
                            }

                            if ($is_duplicate) {
                                $student_objects[$student->StateIdNumber]['sessions'][] = $acad_session;
                                if (!in_array($student->StateIdNumber, $duplicates)) {

                                    $duplicates[] = $student->StateIdNumber;
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->getContainer()->get( 'doctrine' )->getManager()->flush();
    }

    /**
     * @param $endpoint
     */
    public function get_inow_api_response($endpoint){

	    $url = 'https://inow.tusc.k12.al.us/API/' . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:13.0) Gecko/20100101 Firefox/13.0.1');	// mPDF 5.7.4
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'ApplicationKey: leanfrog B/1F8Y/ToQlRufi/0DgoaKLOBcrd3PpT+wFJL6Sdwy2Z8vZP6GamF7KDmU2nb+Cn/ayElMuxwrWreWae06oNhrCE29gnEizIdFuS3bICs3eFOe7bnRsVyPbPE+4CmOc9QzI5pTbUv9aH/7TrSVVSYcL5WaLzeEwnl2+hlj9c2dw=',
        ));
        curl_setopt ( $ch , CURLOPT_RETURNTRANSFER , 1 );
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); //timeout after 30 seconds
        curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
        curl_setopt( $ch, CURLOPT_USERPWD, "LeanFrog_API:qs4p2CNu4H4N9g7ETKzF");
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