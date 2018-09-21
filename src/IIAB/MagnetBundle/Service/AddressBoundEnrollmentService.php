<?php
namespace IIAB\MagnetBundle\Service;

use IIAB\MagnetBundle\Entity\AddressBoundEnrollment;

class AddressBoundEnrollmentService{

    /** @var EntityManager */
    private $magnet_manager;
    private $shared_manager;

    private $grade_progression_order;

    function __construct( $doctrine ) {

        $this->shared_manager = $doctrine->getManager('shared');
        $this->magnet_manager = $doctrine->getManager();

        foreach( MYPICK_CONFIG['grade_progression_order'] as $grade ){
            $grade_label = MYPICK_CONFIG['grade_labels'][$grade];
            if( in_array($grade, MYPICK_CONFIG['lottery']['grade_processing_order']) ){
                $this->grade_progression_order[ $grade ]= $grade_label;
            }
        }
    }

    public function initializeEnrollment( $school, $user = null, $grades_to_include = null ){

        $found_enrollments = $this->magnet_manager
            ->getRepository('IIABMagnetBundle:AddressBoundEnrollment')
            ->findBy([
                'school' => $school
            ],
            ['updateDateTime' => 'ASC']
        );

        $last_enrollments = [];
        foreach( $found_enrollments as $enrollment ){
            $last_enrollments[ $enrollment->getGrade() ] = $enrollment->getCount();
        }

        $starting_grade = array_search( $school->getStartGrade(), $this->grade_progression_order );
        $starting_grade = ( $starting_grade !== false ) ? $starting_grade : array_values( $this->grade_progression_order )[0];
        $ending_grade = array_search( $school->getEndGrade(), $this->grade_progression_order );
        $ending_grade = ( $ending_grade !== false ) ? $ending_grade : end( $this->grade_progression_order );

        $now = new \DateTime();
        $enrollment_records = [];
        for( $grade_index = $starting_grade; $grade_index <= $ending_grade; $grade_index ++ ){
            if( isset( $this->grade_progression_order[ $grade_index ] )
                && ( $grades_to_include == null
                    || in_array($grade_index, $grades_to_include)
                )
            ){
                $grade = $this->grade_progression_order[ $grade_index ];
                $enrollment = new AddressBoundEnrollment();
                $enrollment
                    ->setSchool( $school )
                    ->setGrade( $grade )
                    ->setUser( $user )
                    ->setUpdateDateTime( $now )
                    ->setCount(
                        ( isset($last_enrollments[$grade]) )
                            ? $last_enrollments[$grade]
                            : 0
                    );
                $enrollment_records[$grade] = $enrollment;
            }
        }
        return $enrollment_records;
    }

    public function getColumnLabels(){
        return MYPICK_CONFIG['grade_labels'];
    }
}