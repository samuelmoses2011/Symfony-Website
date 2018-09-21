<?php
/**
 * Created by PhpStorm.
 * User: michellegivens
 * Date: 12/26/14
 * Time: 2:48 PM
 */

namespace IIAB\MagnetBundle\Service;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Constraints\DateTime;
use IIAB\MagnetBundle\Entity\CurrentPopulation;

class GetEligibleSchoolService {

	/** @var array */
	private $student;

	/** @var EntityManager */
	private $emLookup;

    private $translator;

	public function __construct( EntityManager $emLookup, $translator ) {

		$this->emLookup = $emLookup;

        $this->translator = $translator;
	}

    /**
     * @param array $schools
     * @return array
     */
	public function getFoci( array $schools ){

	    $return_foci = [];
	    foreach( $schools as $school_id => $school ){
            $return_foci[ $school_id ] = [];
	        $school =  $this->getEmLookup()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $school_id );

	        $foci = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:ProgramSchoolData' )->findBy([
	            'program' => $school->getProgram(),
                'metaKey' => 'focus'
            ]);

	        foreach( $foci as $focus_data ){
	            $return_foci[ $school_id ][] = $focus_data->getMetaValue();
            }
            if( empty( $return_foci[ $school_id ] ) ){
	            unset( $return_foci[ $school_id ] );
            }
        }
        return $return_foci;
    }

    /**
     * @param array $schools
     * @return array
     */
    public function getFocusExtras( array $schools ){

        $return_extras = [];
        foreach( $schools as $school_id => $school ){
            $return_extras[ $school_id ] = [];
            $school =  $this->getEmLookup()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $school_id );

            $foci = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:ProgramSchoolData' )->findBy([
                'program' => $school->getProgram(),
                'metaKey' => 'focus'
            ]);

            foreach( $foci as $focus_data ){

                if( $focus_data->getExtraData1() ){
                    $return_extras[ $school_id ][$focus_data->getMetaValue()]['extra_1'] = $this->translator->trans( $focus_data->getExtraData1() );
                }
                if( $focus_data->getExtraData2() ){
                    $return_extras[ $school_id ][$focus_data->getMetaValue()]['extra_2'] = $this->translator->trans( $focus_data->getExtraData2() );
                }
                if( $focus_data->getExtraData3() ){
                    $return_extras[ $school_id ][$focus_data->getMetaValue()]['extra_3'] = $this->translator->trans( $focus_data->getExtraData3() );
                }

            }
            if( empty( $return_extras[ $school_id ] ) ){
                unset( $return_extras[ $school_id ] );
            }
        }
        return $return_extras;
    }

    /**
     * @param array $schools
     * @return array
     */
    public function getExclusions( array $schools ){

        $return_exclusions = [];
        foreach( $schools as $school_id => $school ){
            $return_exclusions[ $school_id ] = [];
            $school =  $this->getEmLookup()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $school_id );

            $exclusions = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:ProgramSchoolData' )->findBy([
                'program' => $school->getProgram(),
                'metaKey' => 'exclude'
            ]);

            foreach( $exclusions as $exclusion_data ){

                $program = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:Program' )->find( $exclusion_data->getMetaValue() );

                foreach( $program->getMagnetSchools() as $excludedSchool ){
                    $return_exclusions[ $school_id ][] = $excludedSchool->getId();
                }
            }
            if( empty( $return_exclusions[ $school_id ] ) ){
                unset( $return_exclusions[ $school_id ] );
            }
        }

        return $return_exclusions;
    }

    /**
     * @param array $schools
     * @return array
     */
    public function getFocusLabels( array $schools ){

        $return_labels = [];
        foreach( $schools as $school_id => $school ){
            $return_exclusions[ $school_id ] = [];
            $school =  $this->getEmLookup()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $school_id );

            $label = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:ProgramSchoolData' )->findOneBy([
                'program' => $school->getProgram(),
                'metaKey' => 'focus_description'
            ]);

            if( empty( $label ) ) {
                $return_labels[ $school->getId() ] =  $this->translator->trans( 'focus' );
            } else {
                $return_labels[ $school->getId() ] = $this->translator->trans( $label->getMetaValue() );
            }
        }

        return $return_labels;
    }

    /**
     * @param array $schools
     * @return array
     */
    public function getFocusExperiences( array $schools ){

        $return_experiences = [];
        foreach( $schools as $school_id => $school ){
            $return_experiences[ $school_id ] = [];
            $school =  $this->getEmLookup()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find( $school_id );

            $experiences = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:ProgramSchoolData' )->findBy([
                'program' => $school->getProgram(),
                'metaKey' => 'focus_experience'
            ]);

            foreach( $experiences as $experience ){
                $return_experiences[ $school_id ][] = $experience->getMetaValue();
            }
            if( empty( $return_experiences[ $school_id ] ) ){
                unset( $return_experiences[ $school_id ] );
            }
        }
        return $return_experiences;
    }

	/**
	 * Gets all available schools to be used in the Drop Menus
	 * @param array $student
	 *
	 * @return array|bool
	 */
	public function getEligibleSchools( array $student ) {

		$this->student = $student;

		$schools = array();

		$eligibleSchools = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:MagnetSchool' )
            ->getSchoolsByGrade(
                $this->student['next_grade'] ,
                $this->student['openEnrollment']
        );

		if( empty( $eligibleSchools ) || $eligibleSchools == null || count( $eligibleSchools ) == 0 ) {

			return false;
		}

		$openEnrollment = $this->student['openEnrollment'];
		$openEnrollment = ( $openEnrollment != null) ? $openEnrollment : $this->getEmLookup()->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findLatePlacementByDate( new \DateTime );

		//Check if it is during the Late Placement Period
		$now = new \DateTime();

		$isLatePlacement = false;
		if( isset( $openEnrollment ) && $openEnrollment ){

			$openEnrollment = ( is_array( $openEnrollment ) ) ? $openEnrollment[0] : $openEnrollment;
			$isLatePlacement = ( $openEnrollment->getLatePlacementBeginningDate() < $now && $openEnrollment->getLatePlacementEndingDate() > $now );
		}

		if( $isLatePlacement ) {

			$specialEnrollments = $this->getEmLookup()->getRepository('IIABMagnetBundle:SpecialEnrollment')->createQueryBuilder('se')
				->where('se.openEnrollment = :enrollment')
				->setParameter('enrollment', $openEnrollment)
				->andWhere('se.beginningDate <= :date')
				->andWhere('se.endingDate >= :date')
				->setParameter('date', new \DateTime())
				->select('se.id')
				->getQuery()
				->getResult();

			$allowed_schools = [];
			foreach ($specialEnrollments as $specialEnrollment_id) {
				$specialEnrollment = $this->getEmLookup()->getRepository('IIABMagnetBundle:SpecialEnrollment')->findOneBy(['id' => $specialEnrollment_id['id']]);

				foreach ($specialEnrollment->getSchools() as $school) {
					if (!isset($allowed_schools[$school->getId()])) {
						$allowed_schools[$school->getId()] = $school;
					}
				}
			}
		}

		foreach( $eligibleSchools as $magnetSchool ) {

			if( $isLatePlacement ){

				if( isset( $allowed_schools[ $magnetSchool->getId() ] ) ){

					$population =  $this->getEmLookup()->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( ['magnetSchool' => $magnetSchool], ['lastUpdatedDateTime' => 'DESC'] );
					if( is_null( $population) ){
						$population =  $this->getEmLookup()->getRepository( 'IIABMagnetBundle:CurrentPopulation' )->findOneBy( ['magnetSchool' => $magnetSchool] );
						if( is_null( $population ) ){
							$population = new CurrentPopulation();
							$population->setOpenEnrollment( $openEnrollment );
							$population->setMagnetSchool( $magnetSchool );
							$this->emLookup->persist( $population );
							$this->emLookup->flush();
						}
					}
					//Get the total Number of slots available.
					$totalSlots = $population->getMaxCapacity() - $population->getCPSum();

					if( $totalSlots > 0 ) {
						$schools[$magnetSchool->getId()] = $magnetSchool->__toString();
					}
				}
			} else {
				$schools[$magnetSchool->getId()] = $magnetSchool->__toString();
			}
		}

		/*
		if( isset( $this->student['student_status'] ) && $this->student['student_status'] == 'new' ) {

			 * Disabling Eligibility for now!
			 *
			 * //check to see if student passes eligibleSchool's requirements with doesStudentPassRequirements () from the EligibilityRequirementService
			 * list( $passRequirements , $passGrade , $passCourseTitle , $eligibilityCheck ) = $eligibilityRequirementsServices->doesStudentPassRequirements( $this->student , $magnetSchool );
			 *
			 * if( !$passEligibility ) {
			 * 	//if true, add school to the list of eligible schools
			 * 	unset( $schools[ $magnetSchool->getId() ] );
			 * }

		}*/

		return ($schools) ? $schools : false;
	}

	/**
	 * @return EntityManager
	 */
	public function getEmLookup() {

		return $this->emLookup;
	}

	/**
	 * @param EntityManager $emLookup
	 */
	public function setEmLookup( $emLookup ) {

		$this->emLookup = $emLookup;
	}

}