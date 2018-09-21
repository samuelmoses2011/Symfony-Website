<?php

namespace IIAB\MagnetBundle\Controller;

use IIAB\MagnetBundle\Entity\ADMData;
use IIAB\MagnetBundle\Entity\Correspondence;
use IIAB\MagnetBundle\Entity\CurrentPopulation;
use IIAB\MagnetBundle\Entity\Eligibility;
use IIAB\MagnetBundle\Entity\LotteryOutcomePopulation;
use IIAB\MagnetBundle\Entity\MagnetSchool;
use IIAB\MagnetBundle\Entity\MagnetSchoolSetting;
use IIAB\MagnetBundle\Entity\OpenEnrollment;
use IIAB\MagnetBundle\Entity\Placement;
use IIAB\MagnetBundle\Entity\PlacementMessage;
use IIAB\MagnetBundle\Entity\Process;
use IIAB\MagnetBundle\Entity\Program;
use IIAB\MagnetBundle\Entity\ProgramSchoolData;
use IIAB\MagnetBundle\Entity\WaitListProcessing;
use IIAB\MagnetBundle\Entity\Population;
use IIAB\MagnetBundle\Entity\Capacity;
use IIAB\MagnetBundle\Entity\AddressBoundEnrollment;
use IIAB\MagnetBundle\Form\Type\DatesType;
use IIAB\MagnetBundle\Form\Type\ADMDataType;
use IIAB\MagnetBundle\Form\Type\CurrentPopulationType;
use IIAB\MagnetBundle\Form\Type\CurrentEnrollmentType;
use IIAB\MagnetBundle\Form\Type\EligibilitySettingType;
use IIAB\MagnetBundle\Form\Type\NextStepType;
use IIAB\MagnetBundle\Form\Type\PlacementEligibilityType;
use IIAB\MagnetBundle\Form\Type\PlacementType;
use IIAB\MagnetBundle\Form\Type\WaitListIndividualProcessingType;
use IIAB\MagnetBundle\Form\Type\WaitListProcessingType;
use IIAB\MagnetBundle\Service\CorrespondenceVariablesService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\DateTime;
use IIAB\MagnetBundle\Service\EligibilityRequirementsService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ivory\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

/**
 * Class ProgramProcessingController
 * @package IIAB\MagnetBundle\Controller
 * @Route("/admin/processing/", options={"i18n"=false})
 */
class ProgramProcessingController extends Controller {

	/**
	 * Current Status/Dashboard View
	 * @Route("dashboard/", name="iiab_magnet_program_processing_dashboard")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/dashboard.html.twig")
	 */
	public function indexAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$request = $this->get('request_stack')->getCurrentRequest();

		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

		// Determine Processing Status
 		$offersMade = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:Offered')->findBy([
 			'openEnrollment' => $openEnrollment
 		]);
 		$offersMade = !empty( $offersMade );


		$user = $this->getUser();

		$schools = $user->getSchools();

		// Initialize Filter Choices

		$all_label = ( empty( $schools ) ) ? 'District (all programs)' : 'Mangaged Programs (all programs you manage)';
		$filter_choices = [
			'all' => $all_label,
			// 'By Program' => [], //added later if needed
			// 'By Grade' => [], //added later if needed
			// 'By School/Grade' => [],	//added later
		];

		// Get an array of MagnetSchools for the User
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
		$magnetSchools = $query->getQuery()->getResult();

		// Get an array of Programs for the User's Magnet Schools
		if( empty( $schools ) ){
			$programs = $this->getDoctrine()->getManager()->getRepository('IIABMagnetBundle:Program')->findBy([
				'openEnrollment' => $openEnrollment
			]);
			$gradeLevels = [
				99=> 'PreK',
				0 => 'K',
				1 => 1,
				2 => 2,
				3 => 3,
				4 => 4,
				5 => 5,
				6 => 6,
				7 => 7,
				8 => 8,
				9 => 9,
				10=> 10,
				11=> 11,
				12=> 12,
			];

		} else {
			$programs = [];
			$gradeLevels = [];
			foreach( $magnetSchools as $magnetSchool ){
				if( empty( $programs[ $magnetSchool->getProgram()->getId() ] ) ){
					$programs[ $magnetSchool->getProgram()->getId() ] = $magnetSchool->getProgram();
				}
				if( empty( $gradeLevels[ $magnetSchool->getGrade() ] ) ){
					$gradeLevels[ $magnetSchool->getGrade() ] = $magnetSchool->getGradeString();
				}
			}
		}

		// Add Programs to Filter Choices
		if( count( $programs ) == 1 ){
			$program = array_pop( $programs );
			$filter_choices[ $program->__toString() ] = 'all';
		} else{
			$filter_choices['By Program'] = [];
			$filter_choices['By Grade (all schools)'] = [];

			foreach( $programs as $program ){
				//$filter_choices[ 'By Program' ][ 'program-'.$program->getId() ] = $program->__toString();
                $filter_choices[ 'By Program' ][ $program->__toString() ] = 'program-'.$program->getId();
			}

			foreach( $gradeLevels as $grade => $gradeLevel ){
				$filter_choices[ 'By Grade (all schools)' ][ 'Grade '. $gradeLevel ] = 'grade-'.$grade;
			}
		}

		// Add Schools to Filter Choices
		$filter_choices['By School/Grade'] = [];
		$school_keys = [];
		foreach( $magnetSchools as $magnetSchool ){
			$school_keys[] = $magnetSchool->getId();

			$filter_choices['By School/Grade'][$magnetSchool->__toString()] = 'school-'. $magnetSchool->getId();
		}

		$form = $this->createFormBuilder()

			->add( 'program_filter' , 'choice' , array(
				'label' => 'Display Data for Program(s)' ,
				'required' => true ,
				'choices' => $filter_choices,
				'data' => 'all',
				'attr' => [ 'data-choices' => json_encode( $filter_choices ) ]
			) )
			->add( 'submit_filter' , 'submit' , array(
				'label' => 'Update Dashboard' ,
				'attr' => array(
					'class' => 'btn btn-primary' ,
					'style' => 'margin-top:20px;'
				)
			) )
			->getForm();

		$filter_type = 'all';
		$filter_value = 'all';
		$form->handleRequest( $request );
		if( $form->isValid() ) {
			$data = $form->getData();

			$filter = explode('-', $data['program_filter'] );

			$filter_type = $filter[0];
			$filter_value = ( isset( $filter[1]) ) ? $filter[1] : 'all';
		}

		$grade_list = $gradeLevels;
		switch( $filter_type ){
			case 'all':
				$school_list = $magnetSchools;
				break;
			case 'program':
				$school_list = [];
				$grade_list = [];
				foreach( $magnetSchools as $magnetSchool ){
					if( $magnetSchool->getProgram()->getId() == $filter_value ){
						$school_list[] = $magnetSchool;
						if( empty( $grade_list[ $magnetSchool->getGrade() ] ) ){
							$grade_list[ $magnetSchool->getGrade() ] = $magnetSchool->getGradeString();
						}
					}
				}
				break;
			case 'grade':
				$school_list = [];
				$grade_list = [];
				foreach( $magnetSchools as $magnetSchool ){
					if( $magnetSchool->getGrade() == $filter_value ){
						$school_list[] = $magnetSchool;
						if( empty( $grade_list[ $magnetSchool->getGrade() ] ) ){
							$grade_list[ $magnetSchool->getGrade() ] = $magnetSchool->getGradeString();
						}
					}
				}
				break;
			case 'school':
				$school_list = [];
				$grade_list = [];
				foreach( $magnetSchools as $magnetSchool ){
					if( $magnetSchool->getId() == $filter_value ){
						$school_list[] = $magnetSchool;
						if( empty( $grade_list[ $magnetSchool->getGrade() ] ) ){
							$grade_list[ $magnetSchool->getGrade() ] = $magnetSchool->getGradeString();
						}
					}

				}
				break;
		}

		if( !empty( $school_list ) ){
            $orSchools = [];
            $inSchools = [];
            foreach( $school_list as $key => $school ) {
                $inSchools[] = $school->getId();
                $orSchools[] = 's.firstChoice = '. $school->getId();
                $orSchools[] = 's.secondChoice = '. $school->getId();
                $orSchools[] = 's.thirdChoice = '. $school->getId();
            }

            $submissionStatusCounts = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder('s')
            ->leftJoin('s.submissionStatus','ss')
            ->leftJoin('s.offered', 'o')
            ->select( 'COUNT( s.id ) AS total' )
            ->addSelect( 'ss.id')
            ->addSelect( 'ss.status')
            ->addSelect( 'CASE WHEN (o.awardedSchool IS NULL or o.awardedSchool in ('.implode(',', $inSchools).') ) THEN ss.status ELSE CONCAT(ss.status,\' other school\') as status')
            ->groupBy( 'ss.id' )
            ->addGroupBy( 'o.awardedSchool')
            ->orderBy( 'ss.id', 'ASC');

        } else {

            $submissionStatusCounts = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder('s')
                ->join('s.submissionStatus','ss')
                ->select( 'COUNT( s.id ) AS total' )
                ->addSelect( 'ss.id')
                ->addSelect( 'ss.status' )
                ->groupBy( 'ss.id' )
                ->orderBy( 's.submissionStatus', 'ASC');
        }

        $submissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder('s')
        	->orderBy( 's.submissionStatus','ASC');

     	if( empty( $school_list ) ){
     		$submissionStatusCounts->where( 's.openEnrollment = :enrollment' )->setParameter( 'enrollment' , $openEnrollment );
     		$submissions->where( 's.openEnrollment = :enrollment' )->setParameter( 'enrollment' , $openEnrollment );
     	} else {
			$submissionStatusCounts->andWhere( implode( ' OR ', $orSchools ) );
			$submissions->andWhere( implode( ' OR ', $orSchools ) );
		}
        $submissionStatusCounts = $submissionStatusCounts->getQuery()->getResult();

        $regrouped = [];
        foreach( $submissionStatusCounts as $statusCount ){

            if( isset( $regrouped[ $statusCount['status'] ] ) ){
                $regrouped[ $statusCount['status'] ]['total'] += $statusCount['total'];
            } else {
                $regrouped[ $statusCount['status'] ] = $statusCount;
            }
        }
        $submissionStatusCounts = $regrouped;

        $submissions = $submissions->getQuery()->getResult();

        $status_chart =	[
            'div_id' => 'chart_status',
            'title' => 'Submission Status',
            'columns' => [
                [ 'string', 'Status' ],
                [ 'number', 'Count' ]
            ],
            'rows' => []
        ];

        $rows = [];
        $submissionStatusAvailable = [];
        foreach( $submissionStatusCounts as $status_count ){
        	if( !strpos($status_count['status'], 'other school') ){
                $submissionStatusAvailable[ $status_count['id'] ] = ucwords( $status_count['status'] );
            }
        	$status_chart['rows'][] = [
        		ucwords( $status_count['status'] .' ('.$status_count['total'].')' ), (int) $status_count['total']
        	];
        }

        $status_check = [
        	$this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 6 ),
        	$this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 7 )
        ];

        $population_service = $this->get('magnet.population');

        $population_tracking_methods = $population_service->getTrackingColumnLabels();

        $race_options = array_fill_keys ( array_keys( $population_tracking_methods) , 0 );

        $current_populations = [];
        foreach( $school_list as $school ){
            $current_populations[] = $population_service->getCurrentPopulation( $school );
        }

        $race_counts = [];
        foreach( $current_populations as $populations ){

            foreach( $populations['Race'] as $tracking_value => $population ){
                $race_counts[$tracking_value] = ( isset( $race_counts[$tracking_value] ) )
                    ? $race_counts[$tracking_value] + $population->getCount()
                    : $population->getCount()
                ;
            }
        }
        foreach( $race_counts as $key => $count ){
            if( is_int( $key ) ){
                $new_key = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Race' )->find( $key );

                if ( $new_key != null ){
                    $race_counts[ $new_key->getShortName() ] = $count;
                    unset( $race_counts[$key] );
                }
            }
        }
        $race_chart_counts = [];
        foreach( $race_counts as $key => $count ){
            $race_chart_counts[] = [
                $key .' ('. $count .')',
                $count
            ];
        }

		$race_chart =	[
            'div_id' => 'chart_race',
            'title' => 'Projected Population',
            'columns' => [
                [ 'string', 'Status' ],
                [ 'number', 'Count' ]
            ],
            'rows' => $race_chart_counts
        ];

		return array(
			'form' => $form->createView(),
		    'drawCharts' => [
		        $status_chart,
                $race_chart
            ],
            'submissions' => $submissions,
            'submissionStatusAvailable' => $submissionStatusAvailable,
            'schoolKeys' => $school_keys,
            'gradeLevels' => $grade_list,
            'magnetSchools' => $school_list,
            'populationTotal' => array_sum( $race_counts ) ,
			'admin_pool' => $admin_pool ,
			'openEnrollment' => $openEnrollment ,
			'allSchools' => empty( $schools ),
        );
	}

    /**
     * Population View
     * @Route("enrollment/", name="iiab_magnet_program_processing_enrollment")
     * @Template("@IIABMagnet/Admin/ProgramProcessing/enrollment.html.twig")
     */
    public function enrollmentAction() {

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $request = $this->get('request_stack')->getCurrentRequest();

        $openEnrollment = $this->getActiveOpenEnrollment();
        if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
            return $openEnrollment;
        }

        $user = $this->getUser();

        $population_service = $this->get('magnet.population');
        $enrollment_service = $this->get('magnet.enrollment');

        $form = $this->createFormBuilder();

        $magnetSchools = $this->getDoctrine()
            ->getRepository( 'IIABMagnetBundle:MagnetSchool' )
            ->findBy([
                'openEnrollment' => $openEnrollment,
                'active' => true
            ]);

        $needed_grades = [];
        foreach( $magnetSchools as $magnet ){
            if( !in_array( $magnet->getGrade(), $needed_grades )
                && $population_service->doesSchoolUseTracker( $magnet, 'zoned')
            ){
                $needed_grades[] = $magnet->getGrade();
            }
        }

        $address_bound_schools = $this->getDoctrine()
            ->getRepository( 'IIABMagnetBundle:AddressBoundSchool' )
            ->findBy(['active'=>1],['endGrade'=>'ASC','name'=>'ASC']);

        $school_hash = [];
        foreach( $address_bound_schools as $school ){
            $school_hash[ $school->getId() ] = $school;
        }

        $enrollment_list= [];

        $max_columns = 0;
        $slotting_methods = [];
        foreach( $address_bound_schools as $school ) {
            $adjustment = $enrollment_service->initializeEnrollment( $school, $user, $needed_grades );

            if( $adjustment ){

                $enrollment_list[$school->getId()] = [
                    'adjustment' => $adjustment,
                ];

                if( $adjustment ){
                    $max_columns = ( count($adjustment) > $max_columns )
                        ? count($adjustment)
                        : $max_columns;
                }

                $field_name = 'ab'.$school->getId();

                $form->add( $field_name, CurrentEnrollmentType::class, [
                    'enrollment_columns' => $adjustment,
                ] );
            }
        }

        $column_labels = $enrollment_service->getColumnLabels();

        $form->add( 'saveEnrollment' , 'submit' , array(
            'label' => 'Save Changes' ,
            'attr' => array( 'class' => 'btn btn-primary' )
        ) );

        $session = $request->getSession();
        $updated = $session->get( 'updated' );
        if( $updated == 1 ) {
            $session->remove( 'updated' );
        }

        $form = $form->getForm();

        $form->handleRequest( $request );

        if( $form->isValid() ) {

            $data = $form->getData();

            $now = new \DateTime();
            foreach( $data as $key => $key_data ){

                if ( preg_match ('/^ab\d/', $key ) ){
                    $key_exploded = explode( 'ab', $key );

                    $addressBoundSchool = $school_hash[ $key_exploded[1] ];

                    foreach( $key_data as $school_grade => $count ){

                        $count = ( is_int( $count ) ) ? $count : 0;
                        $grade = explode( '_', $school_grade )[1];

                        $enrollment = new AddressBoundEnrollment();
                        $enrollment
                            ->setSchool( $addressBoundSchool )
                            ->setUser( $user )
                            ->setGrade( $grade )
                            ->setCount( ( !empty( $count ) ) ? $count : 0 )
                            ->setUpdateDateTime( $now );
                        $this->getDoctrine()->getManager()->persist( $enrollment );
                    }
                }

                $this->getDoctrine()->getManager()->flush();

                $session->set( 'updated' , 1 );
            }
            return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_enrollment' ) );
        }

        return array(
            'admin_pool' => $admin_pool ,
            'openEnrollment' => $openEnrollment ,
            'form' => $form->createView() ,
            'max_columns' => $max_columns,
            'column_labels' => $column_labels,
            'enrollment_list' => $enrollment_list,
            'school_hash' => $school_hash,
        );

    }

	/**
	 * Create New view
	 *
	 * @Route("create-new/", name="iiab_magnet_program_processing_new")
	 * @Route("create-new/new-openenrollment/", name="iiab_magnet_program_processing_new_open")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/create.view.html.twig")
	 */
	public function createNewAction(  ) {
		$admin_pool = $this->get( 'sonata.admin.pool' );

		$request = $this->get('request_stack')->getCurrentRequest();

		$today = new \DateTime();

		// Create new Open Enrollment and set default values
		$openEnrollment = new OpenEnrollment();
		$openEnrollment->setYear( ( $today->format( 'Y' ) +1 ) .'-'. ( $today->format( 'Y' ) +2 ) );
		$openEnrollment->setBeginningDate( $today );
		$openEnrollment->setEndingDate( $today );
		$openEnrollment->setConfirmationStyle( ( $today->format( 'y' ) +1 ) . ( $today->format( 'y' ) +2 ) );
		$openEnrollment->setActive( false );

		$this->getDoctrine()->getManager()->persist( $openEnrollment );

		// Create new Placement and set default values
		$placement = new Placement();
		$placement->setPreKDateCutOff( new \DateTime( ( $today->format( 'Y' ) -5 ) .'-09-01' ) );
		$placement->setKindergartenDateCutOff( new \DateTime( ( $today->format( 'Y' ) -6 ) .'-09-01' ) );
		$placement->setFirstGradeDateCutOff( new \DateTime( ( $today->format( 'Y' ) -7 ) .'-09-01' ) );
		$placement->setCompleted( false );
		$placement->setOpenEnrollment( $openEnrollment );
		$placement->setEligibility( false );

		$this->getDoctrine()->getManager()->persist( $placement );

		// Find the programs from the last Open Enrollment to serve as a selection
		$lastEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneBy( array() , array(
			'endingDate' => 'DESC'
		) );
		$programs = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Program' )->findBy( array( 'openEnrollment' => $lastEnrollment ) , array( 'name' => 'ASC' ) );

		$selectedSchools = [];
		foreach( $programs as $program ) {

			$magnetSchools = $program->getMagnetSchools();

			/** @var \IIAB\MagnetBundle\Entity\MagnetSchool MagnetSchool */
			foreach ($magnetSchools as $magnetSchool) {

				$selectedSchools[] = $magnetSchool;
			}
		}
		$placement->setselectedSchools( $selectedSchools );

		$form = $this->createForm( PlacementType::class, $placement );

		$form->handleRequest( $request );

		if( $form->isValid() ) {
			$openEnrollment->setActive( 1 );
			$activeOpenEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneByActive( 1 );
			if( !empty( $activeOpenEnrollment ) ){
				$activeOpenEnrollment->setActive( 0 );
			}

			$copiedPrograms = [];
			foreach( $placement->getselectedSchools() as $oldSchool){

				$oldProgram = $oldSchool->getProgram();

				if( isset( $copiedPrograms[ $oldProgram->getId() ] ) ){
					$newProgram = $copiedPrograms[ $oldSchool->getProgram()->getId() ];
				} else {
					$newProgram = new Program();
					$newProgram->setOpenEnrollment( $openEnrollment );
					$newProgram->setName( $oldProgram->getName() );
					$this->getDoctrine()->getManager()->persist( $newProgram );

					$copiedPrograms[ $oldProgram->getId() ] = $newProgram;

                    foreach( $oldProgram->getEligibility() as $oldEligibility ){
                        $newEligibility = clone $oldEligibility;
                        //$newEligibility->setId(null);
                        $newEligibility->setProgram( $newProgram );
                        $this->getDoctrine()->getManager()->persist( $newEligibility );
                    }

                    foreach( $oldProgram->getAdditionalData() as $oldData ){
                        $newData = clone $oldData;
                        //$newData->setId(null);
                        $newData->setProgram( $newProgram );
                        $this->getDoctrine()->getManager()->persist( $newData );
                    }
				}

				$newMagnetSchool = new MagnetSchool();
				$newMagnetSchool->setProgram( $newProgram );
				$newMagnetSchool->setName( $oldSchool->getName() );
				$newMagnetSchool->setGrade( $oldSchool->getGrade() );
				$newMagnetSchool->setAddress( $oldSchool->getAddress() );
				$newMagnetSchool->setOpenEnrollment( $openEnrollment );
				$newMagnetSchool->setActive( true );
				$this->getDoctrine()->getManager()->persist( $newMagnetSchool );

				foreach( $oldSchool->getEligibility() as $oldEligibility ){
                    $newEligibility = clone $oldEligibility;
                    //$newEligibility->setId(null);
                    $newEligibility->setMagnetSchool( $newMagnetSchool );
                    $this->getDoctrine()->getManager()->persist( $newEligibility );
                }

                foreach( $oldSchool->getAdditionalData() as $oldData ){
				    $newData = clone $oldData;
				    //$newData->setId(null);
				    $newData->setMagnetSchool( $newMagnetSchool );
                    $this->getDoctrine()->getManager()->persist( $newData );
                }
			}

			$this->getDoctrine()->getManager()->flush();
			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_dates' ) );
		}

		return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'programs' => $programs );
	}

    /**
     * Edit Date Settings view
     *
     * @Route("dates/", name="iiab_magnet_program_processing_dates")
     * @Template("@IIABMagnet/Admin/ProgramProcessing/dates.view.html.twig")
     */
	public function dateSettingsAction(){

        ini_set('memory_limit' , '512M' );

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $request = $this->get('request_stack')->getCurrentRequest();

        //Are we creating a new OpenEnrollment???
        $openEnrollment = $this->getActiveOpenEnrollment();
        if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
            return $openEnrollment;
        }

        $programs = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Program' )->findBy( array( 'openEnrollment' => $openEnrollment ) , array( 'name' => 'ASC' ) );

        /** @var \IIAB\MagnetBundle\Entity\Placement $placement */
        $placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
            'openEnrollment' => $openEnrollment ,
        ), ['round' => 'DESC'] );


        if( $placement == null ) {
            $placement = new Placement();
            $placement->setOpenEnrollment( $openEnrollment );
            $this->getDoctrine()->getManager()->persist( $placement );
        }

        $form = $this->createForm( DatesType::class, $placement );

        $form->handleRequest( $request );

        if( $form->isValid() ) {

            $formData = $form->getData();

            $this->getDoctrine()->getManager()->flush();
        }

        return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'programs' => $programs );
    }

    /**
     * Edit OpenEnrollment Date Settings view
     *
     * @Route("application-period/", name="iiab_magnet_program_processing_application_settings")
     * @Template("@IIABMagnet/Admin/ProgramProcessing/applicationSettings.view.html.twig")
     */
    public function applicationSettingsAction(){

        ini_set('memory_limit' , '512M' );

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $request = $this->get('request_stack')->getCurrentRequest();
        //Are we creating a new OpenEnrollment???
        $openEnrollment = $this->getActiveOpenEnrollment();
        if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
            return $openEnrollment;
        }

        $programs = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Program' )->findBy( array( 'openEnrollment' => $openEnrollment ) , array( 'name' => 'ASC' ) );

        /** @var \IIAB\MagnetBundle\Entity\Placement $placement */
        $placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
            'openEnrollment' => $openEnrollment ,
        ), ['round' => 'DESC'] );


        if( $placement == null ) {
            $placement = new Placement();
            $placement->setOpenEnrollment( $openEnrollment );
            $this->getDoctrine()->getManager()->persist( $placement );
        }

        $form = $this->createForm( DatesType::class, $placement );

        $form->handleRequest( $request );

        if( $form->isValid() ) {

            $formData = $form->getData();

            $this->getDoctrine()->getManager()->flush();
        }

        return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'programs' => $programs );
    }


    /**
     * Edit Eligibility Settings view
     *
     * @Route("eligibility/", name="iiab_magnet_program_processing_eligibility")
     * @Template("@IIABMagnet/Admin/ProgramProcessing/eligibility.view.html.twig")
     */
    public function eligibilitySettingsAction() {

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $request = $this->get('request_stack')->getCurrentRequest();

        //Are we creating a new OpenEnrollment???
        $openEnrollment = $this->getActiveOpenEnrollment();
        if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
            return $openEnrollment;
        }

        $programs = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Program' )->findBy( array( 'openEnrollment' => $openEnrollment ) , array( 'name' => 'ASC' ) );

        /** @var \IIAB\MagnetBundle\Entity\Placement $placement */
        $placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
            'openEnrollment' => $openEnrollment ,
        ), ['round' => 'DESC'] );


        if( $placement == null ) {
            $placement = new Placement();
            $placement->setOpenEnrollment( $openEnrollment );
            $this->getDoctrine()->getManager()->persist( $placement );
        }

        $eligibilitySettings = [];
        foreach( $programs as $program ) {

            $eligibilitySettings[] = $program;
        }

        $placement->setEligibilitySettings( $eligibilitySettings );
        $placement->entityManager = $this->getDoctrine()->getManager();

        $eligibility_requirements_service = new EligibilityRequirementsService( $this->getDoctrine()->getManager() );
        $eligibility_fields = $eligibility_requirements_service->getEligibilityFieldIDs();

        $form = $this->createForm( PlacementEligibilityType::class, $placement, [ 'eligibility_requirements_service' => $eligibility_requirements_service ] );

        $form->handleRequest($request);

        if( $form->isValid() ) {

            $formData = $form->getData();

            $submitted_data = $request->request->all();

            foreach( $eligibility_fields as $key => $field ) {

                foreach ($formData->getEligibilitySettings() as $index => $program) {

                    foreach( $submitted_data['placement_eligibility']['eligibility_settings'] as $index => $submitted ){
                        if( isset( $submitted_data['placement_eligibility']['eligibility_settings'][$index][ 'program_'. $program->getId() .'_'. $key ] ) ) {

                            $eligibility = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Eligibility' )->findOneBy( array(
                                'program' => $program,
                                'criteriaType' => $key
                            ));

                            if( empty( $eligibility ) ){
                                $eligibility = new Eligibility();
                                $eligibility->setProgram( $program );
                                $eligibility->setCriteriaType( 'audition_score' );
                            }

                            $eligibility->setPassingThreshold( $submitted_data['placement_eligibility']['eligibility_settings'][$index][ 'program_'. $program->getId() .'_'. $key ] );
                            $this->getDoctrine()->getManager()->persist( $eligibility );
                        }
                    }

                    foreach( $program->getMagnetSchools() as $magnetSchool ){

                        foreach( $submitted_data['placement_eligibility']['eligibility_settings'] as $index => $submitted ){
                            if( isset( $submitted_data['placement_eligibility']['eligibility_settings'][$index][ 'school_'. $magnetSchool->getId() .'_'. $key ] ) ) {
                                $eligibility = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Eligibility' )->findOneBy( array(
                                    'magnetSchool' => $magnetSchool,
                                    'criteriaType' => $key
                                ));

                                if( !empty( $eligibility ) ) {

                                    $eligibility->setPassingThreshold($submitted_data['placement_eligibility']['eligibility_settings'][$index]['school_' . $magnetSchool->getId() . '_' . $key]);
                                    $this->getDoctrine()->getManager()->persist($eligibility);
                                }
                            }
                        }
                    }
                }
            }
            $this->getDoctrine()->getManager()->flush();
        }


        return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'programs' => $programs, 'eligibility_fields' => $eligibility_fields );

    }

	/**
	 * Edit Settings view
	 *
	 * @Route("edit-settings/", name="iiab_magnet_program_processing_edit")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/edit.view.html.twig")
	 */
	public function editSettingsAction() {

		ini_set('memory_limit' , '512M' );

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$request = $this->get('request_stack')->getCurrentRequest();

		//Are we creating a new OpenEnrollment???
		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

		$programs = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Program' )->findBy( array( 'openEnrollment' => $openEnrollment ) , array( 'name' => 'ASC' ) );

		/** @var \IIAB\MagnetBundle\Entity\Placement $placement */
		$placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
			'openEnrollment' => $openEnrollment ,
		), ['round' => 'DESC'] );


		if( $placement == null ) {
			$placement = new Placement();
			$placement->setOpenEnrollment( $openEnrollment );
			$this->getDoctrine()->getManager()->persist( $placement );
		}

		$eligibilitySettings = [];
		$gpaSettings = [];
		$committeeSettings = [];
        $nextStepSettings = [];

		foreach( $programs as $program ) {

		    $eligibilitySettings[] = $program;

			$magnetSchools = $program->getMagnetSchools();

			/** @var \IIAB\MagnetBundle\Entity\MagnetSchool MagnetSchool */
			foreach( $magnetSchools as $magnetSchool ) {

				$gpaSetting = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Eligibility' )->findOneBy( [ 'magnetSchool' => $magnetSchool ] );

				if( $gpaSetting == null ) {
					$gpaSetting = new Eligibility();
					$gpaSetting->setMagnetSchool( $magnetSchool );
					$this->getDoctrine()->getManager()->persist( $gpaSetting );
				}

                $nextStepSetting = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( [ 'openEnrollment' => $openEnrollment , 'magnetSchool' => $magnetSchool ] );
                if( $nextStepSetting == null ) {
                    $nextStepSetting = new PlacementMessage();
                    $nextStepSetting->setOpenEnrollment( $openEnrollment );
                    $nextStepSetting->setMagnetSchool( $magnetSchool );
                    $this->getDoctrine()->getManager()->persist( $nextStepSetting );
                }

				$gpaSettings[] = $gpaSetting;
                $nextStepSettings[] = $nextStepSetting;
			}
		}

		$placement->setCommitteeSettings( $committeeSettings );
		$placement->setEligibilitySettings( $eligibilitySettings );
        $placement->setNextStep( $nextStepSettings );
        $placement->setGpaSettings( $gpaSettings );

		$form = $this->createForm( PlacementType::class, $placement );

		$form->handleRequest( $request );

		$errors = $form->getErrors();

		if( $form->isValid() ) {

            $formData = $form->getData();

            foreach( $formData->getEligibilitySettings() as $index => $program ) {

                $check_for_fields = [
                    'calculated_gpa',
                    'conduct_eligible',
                    'orientation',
                    'assessment_test_eligible',
                    'course_eligibility_met',
                ];

                foreach ($check_for_fields as $key) {
                    $foundData = $program->getAdditionalData($key);
                    $foundData = (isset($foundData[0])) ? $foundData[0] : null;

                    if ($foundData == null) {
                        if (isset($_POST['placement']['eligibility_settings'][$index])) {
                            $subData = new ProgramSchoolData();
                            $subData->setMetaKey($key);
                            $subData->setMetaValue($_POST['placement']['eligibility_settings'][$index][$key]);
                            $subData->setProgram($program);
                            $this->getDoctrine()->getManager()->persist($subData);
                        }
                    } else {
                        if (isset($_POST['placement']['eligibility_settings'][$index])) {
                            $foundData->setMetaValue($_POST['placement']['eligibility_settings'][$index][$key]);
                            $this->getDoctrine()->getManager()->persist($foundData);
                        } else {
                            $program->removeAdditionalDatum($foundData);
                        }
                    }
                }
            }

			if( $request->get( '_route' ) == 'iiab_magnet_program_processing_new_open' ) {
				$openEnrollment->setActive( 1 );
				$activeOpenEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneByActive( 1 );
				$activeOpenEnrollment->setActive( 0 );
			}

			$this->getDoctrine()->getManager()->flush();
			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_edit' ) );
		}

		return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'programs' => $programs );
	}

    /**
     * Header/Footer Letters/Emails.
     *
     * @Route("header-footer/", name="iiab_magnet_program_processing_header_footer")
     * @Template("@IIABMagnet/Admin/ProgramProcessing/headerFooterTemplate.html.twig")
     */
    public function headerFooterAction() {

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $request = $this->get('request_stack')->getCurrentRequest();

        $openEnrollment = $this->getActiveOpenEnrollment();
        if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
            return $openEnrollment;
        }

        $placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
            'openEnrollment' => $openEnrollment ,
        ), ['round' => 'DESC'] );


        if( $placement == null ) {
            $placement = new Placement();
            $placement->setOpenEnrollment( $openEnrollment );
            $placement->setAwardedMailedDate( new \DateTime( '+1 day' ) );
            $placement->setAddedDateTime( new \DateTime() );
        }

        $letterHeaderCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'header',
            'type' => 'letter'
        ) );

        if($letterHeaderCorrespondence == null) {
            $letterHeaderCorrespondence = new Correspondence();
            $letterHeaderCorrespondence->setName('header');
            $letterHeaderCorrespondence->setType('letter');
            $letterHeaderCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Report/header.html.twig'));
            $letterHeaderCorrespondence->setActive(1);
            $letterHeaderCorrespondence->setLastUpdateDateTime(new \DateTime());
        }

        $emailHeaderCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'header',
            'type' => 'email'
        ) );

        if($emailHeaderCorrespondence == null) {
            $emailHeaderCorrespondence = new Correspondence();
            $emailHeaderCorrespondence->setName('header');
            $emailHeaderCorrespondence->setType('email');
            $emailHeaderCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Email/header.email.twig'));
            $emailHeaderCorrespondence->setActive(1);
            $emailHeaderCorrespondence->setLastUpdateDateTime(new \DateTime());
        }

        $letterFooterCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'footer',
            'type' => 'letter'
        ) );

        if($letterFooterCorrespondence == null) {
            $letterFooterCorrespondence = new Correspondence();
            $letterFooterCorrespondence->setName('footer');
            $letterFooterCorrespondence->setType('letter');
            $letterFooterCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Report/footer.html.twig'));
            $letterFooterCorrespondence->setActive(1);
            $letterFooterCorrespondence->setLastUpdateDateTime(new \DateTime());
        }

        $emailFooterCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'footer',
            'type' => 'email'
        ) );

        if($emailFooterCorrespondence == null) {
            $emailFooterCorrespondence = new Correspondence();
            $emailFooterCorrespondence->setName('footer');
            $emailFooterCorrespondence->setType('email');
            $emailFooterCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Report/footer.html.twig'));
            $emailFooterCorrespondence->setActive(1);
            $emailFooterCorrespondence->setLastUpdateDateTime(new \DateTime());
        }
        $dynamicVariables = CorrespondenceVariablesService::getDynamicVariables();

        $form = $this->createFormBuilder(null, [ 'attr' => [ 'data-dynamic' => json_encode( $dynamicVariables ) ] ] )

            ->add( 'letterHeaderTemplate', CKEditorType::class, array(
                'data' => $letterHeaderCorrespondence->getTemplate(),
            ))

            ->add( 'emailHeaderTemplate', CKEditorType::class, array(
                'data' => $emailHeaderCorrespondence->getTemplate(),
            ))

            ->add( 'letterFooterTemplate', CKEditorType::class, array(
                'data' => $letterFooterCorrespondence->getTemplate(),
            ))

            ->add( 'emailFooterTemplate', CKEditorType::class, array(
                'data' => $emailFooterCorrespondence->getTemplate(),
            ))

            ->add( 'saveChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' )
            ) )

            ->add( 'saveChanges2' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' )
            ) );

        $form = $form->getForm();

        $form->handleRequest( $request );

        if( $form->isValid()) {

            $data = $form->getData();

            $letterHeaderCorrespondence->setTemplate($data['letterHeaderTemplate']);
            $this->getDoctrine()->getManager()->persist($letterHeaderCorrespondence);

            $emailHeaderCorrespondence->setTemplate($data['emailHeaderTemplate']);
            $this->getDoctrine()->getManager()->persist($emailHeaderCorrespondence);

            $letterFooterCorrespondence->setTemplate($data['letterFooterTemplate']);
            $this->getDoctrine()->getManager()->persist($letterFooterCorrespondence);

            $emailFooterCorrespondence->setTemplate($data['emailFooterTemplate']);
            $this->getDoctrine()->getManager()->persist($emailFooterCorrespondence);


            $this->getDoctrine()->getManager()->flush();
       }

        return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'files' => [], 'page' => 'header-footer' );
    }


	/**
	 * Next Steps Letters/Emails.
	 *
	 * @Route("next-step/", name="iiab_magnet_program_processing_next_step")
     * @Template("@IIABMagnet/Admin/ProgramProcessing/lettersEmail.html.twig")
	 */
	public function nextStepAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

        $request = $this->get('request_stack')->getCurrentRequest();

        $openEnrollment = $this->getActiveOpenEnrollment();
        if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
            return $openEnrollment;
        }

		$placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
			'openEnrollment' => $openEnrollment ,
		), ['round' => 'DESC'] );


        if( $placement == null ) {
            $placement = new Placement();
            $placement->setOpenEnrollment( $openEnrollment );
            $placement->setAwardedMailedDate( new \DateTime( '+1 day' ) );
            $placement->setAddedDateTime( new \DateTime() );
        }

        $emailCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'nextStep',
            'type' => 'email'
        ) );

        if($emailCorrespondence == null) {
            $emailCorrespondence = new Correspondence();
            $emailCorrespondence->setName('nextStep');
            $emailCorrespondence->setType('email');
            $emailCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Email/nextStep.email.twig'));
            $emailCorrespondence->setActive(1);
            $emailCorrespondence->setLastUpdateDateTime(new \DateTime());
        }

        $emailBlock = CorrespondenceVariablesService::divideEmailBlocks($emailCorrespondence->getTemplate());

        $letterCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'nextStep',
            'type' => 'letter'
        ) );

        if( $letterCorrespondence == null ) {
            $letterCorrespondence = new Correspondence();
            $letterCorrespondence->setName('nextStep');
            $letterCorrespondence->setType( 'letter' );
            $letterCorrespondence->setTemplate( file_get_contents($this->container->get( 'kernel' )->getRootDir() .'/../src/IIAB/MagnetBundle/Resources/views/Report/nextStepLetter.html.twig') );
            $letterCorrespondence->setActive(1);
            $letterCorrespondence->setLastUpdateDateTime( new \DateTime() );
        }

        $dynamicVariables = CorrespondenceVariablesService::getDynamicVariables();

        $form = $this->createFormBuilder(null, [ 'attr' => [ 'data-dynamic' => json_encode( $dynamicVariables ) ] ] )
            ->add( 'mailDateSetting' , 'date' , array(
                'label' => 'Mail Date Setting' ,
                'data' => $placement->getNextStepMailedDate() ,
            ) )

            ->add( 'letterTemplate', CKEditorType::class, array(
                'data' => $letterCorrespondence->getTemplate(),
            ))

            ->add( 'emailSubject', CKEditorType::class, array(
                'data' => $emailBlock['subject'],
                'attr' => array('class' => 'plain-text single-line')
            ))

            ->add( 'emailBodyHtml', CKEditorType::class, array(
                'data' => $emailBlock['body_html'],
            ))

            ->add( 'sendEmailsNow' , 'submit' , array(
                'label' => 'Send Next Step Emails Now' ,
                'attr' => array( 'class' => 'btn btn-primary' ) ,
            ) )

            ->add( 'saveEmailChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' ) ,
            ) )

            ->add( 'generateLettersNow' , 'submit' , array(
                'label' => 'Generate Send Next Step Letters Now' ,
                'attr' => array( 'class' => 'btn btn-primary' ) ,
            ) )

            ->add( 'saveChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' )
            ) );

        $form = $form->getForm();

        $form->handleRequest( $request );

        $rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/next-step/' . $openEnrollment->getId() . '/';
        if( !file_exists( $rootDIR ) ) {
            mkdir( $rootDIR , 0755 , true );
        }

        $lastGeneratedFiles = array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) );
        rsort( $lastGeneratedFiles );
        $lastGeneratedFiles = array_slice( $lastGeneratedFiles , 0 , 5 );

        if( $form->isValid()) {

            $data = $form->getData();

            if ($form->get('sendEmailsNow')->isClicked()) {
                $process = new Process();
                $process->setEvent('email');
                $process->setType('next-step');
                $process->setOpenEnrollment($openEnrollment);

                $this->getDoctrine()->getManager()->persist($process);
                $this->getDoctrine()->getManager()->flush();

            }

            if ($form->get('sendEmailsNow')->isClicked() || $form->get('saveEmailChanges')->isClicked()) {
                $emailTemplate = CorrespondenceVariablesService::combineEmailBlocks(['subject' => $data['emailSubject'], 'body_html' => $data['emailBodyHtml']]);
                $emailCorrespondence->setTemplate($emailTemplate);
                $this->getDoctrine()->getManager()->persist($emailCorrespondence);
                $this->getDoctrine()->getManager()->flush();
            }

            if ($form->get('generateLettersNow')->isClicked()) {

                $process = new Process();
                $process->setEvent('pdf');
                $process->setType('next-step');
                $process->setOpenEnrollment($openEnrollment);

                $this->getDoctrine()->getManager()->persist($process);
                $this->getDoctrine()->getManager()->persist($placement);
                $this->getDoctrine()->getManager()->flush();
            }

            if ($form->get('generateLettersNow')->isClicked() || $form->get('saveChanges')->isClicked()) {

                $letterCorrespondence->setTemplate($data['letterTemplate']);
                $this->getDoctrine()->getManager()->persist($letterCorrespondence);

                $placement->setNextStepMailedDate($data['mailDateSetting']);
                $this->getDoctrine()->getManager()->persist($placement);

                $this->getDoctrine()->getManager()->flush();
            }

            return $this->redirect($this->generateUrl('iiab_magnet_program_processing_next_step'));
        }

        return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'files' => $lastGeneratedFiles , 'page' => 'next-step' );
	}

	/**
	 * Process Submission
	 *
	 * @Route("process-submissions/", name="iiab_magnet_program_processing_process")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/process.html.twig")
	 */
	public function processAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$request = $this->get('request_stack')->getCurrentRequest();

		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

		$placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
			'openEnrollment' => $openEnrollment ,
		), ['round' => 'DESC'] );

		if( $placement == null ) {
			$placement = new Placement();
			$placement->setOpenEnrollment( $openEnrollment );

			$this->getDoctrine()->getManager()->persist( $placement );
			$this->getDoctrine()->getManager()->flush();
		}

		$form = $this->createFormBuilder();

		$alreadyAcceptedDeclinedOffered = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Offered' )->createQueryBuilder( 'o' )
			->where( 'o.openEnrollment = :enrollment' )
			->andWhere( 'o.accepted != 0 OR o.declined != 0' )
			->setParameter( 'enrollment' , $placement->getOpenEnrollment() )
			->getQuery()
			->getResult();

		//Settings Tab

		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'onlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => $placement->getOnlineEndTime()
			) );
		} else {
			$form->add( 'onlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => $placement->getOnlineEndTime() ,
				'disabled' => true ,
                'attr' => ['readonly'=>'readonly'],
			) );
		}
		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'offlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => $placement->getOfflineEndTime()
			) );
		} else {
			$form->add( 'offlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => $placement->getOfflineEndTime() ,
				'attr' => ['readonly'=>'readonly'],
				'disabled' => true ,
			) );
		}

		$form->add( 'registrationNewStartDate' , 'date' , array(
			'label' => 'Beginning Registration Date (New Student)' ,
			'data' => $placement->getRegistrationNewStartDate() ,
			'format' => \IntlDateFormatter::LONG
		) );

		$form->add( 'registrationCurrentStartDate' , 'date' , array(
			'label' => 'Beginning Registration Date (Current Student)' ,
			'data' => $placement->getRegistrationCurrentStartDate() ,
			'format' => \IntlDateFormatter::LONG
		) );

		$form->add( 'nextSchoolYear' , 'text' , array(
			'label' => 'Next School Year' ,
			'help' => ' (ex: ' . date( 'Y' , strtotime( '+1 year' ) ) . '-' . date( 'Y' , strtotime( '+2 year' ) ) . ')' ,
			'data' => $placement->getNextSchoolYear() ,
		) );

		$form->add( 'nextYear' , 'text' , array(
			'label' => 'Next Year' ,
			'help' => '(ex: ' . date( 'Y' , strtotime( '+1 year' ) ) . ')' ,
			'data' => $placement->getNextYear() ,
		) );

		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'processNow' , 'submit' , array(
				'label' => 'Process submissions now' ,
				'attr' => array( 'class' => 'btn btn-danger' )
			) );
		}
		$form->add( 'saveSettings' , 'submit' , array(
			'label' => 'Save Settings' ,
			'attr' => array( 'class' => 'btn btn-primary' )
		) );


		//Enrollment Tab
        $user = $this->getUser();
        $schools = [
            'magnetSchool' => $this->getDoctrine()
                ->getRepository( 'IIABMagnetBundle:MagnetSchool' )
                ->findByUser( $user, $openEnrollment ),
        ];

        $school_hash = [];
        foreach( $schools as $school_type => $type_schools ){
            foreach( $type_schools as $school ){
                $school_hash[$school_type][ $school->getId() ] = $school;
            }
        }

        $programs = [];
        foreach( $school_hash['magnetSchool'] as $magnetSchool ){
            if( !isset( $programs[$magnetSchool->getProgram()->getId()] ) ){
                $programs[$magnetSchool->getProgram()->getId()] = $magnetSchool->getProgram();
            }
        }

        $capacities = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Capacity' )
            ->findAll();
        $capacity_hash = [];
        foreach( $capacities as $capacity ){
            $focus = $capacity->getFocusArea();
            $focus = ( !empty($focus) )? $focus : 0;
            $capacity_hash[ $capacity->getSchool()->getId() ][$focus] = $capacity;
        }

        $population_list = [
            'magnetSchool' => [],
        ];

        $population_service = $this->get('magnet.population');

        $max_columns = 0;
        $slotting_methods = [];
        foreach( array_keys( $population_list ) as $list_type){

            foreach( $school_hash[$list_type] as $school ) {

                $history = $population_service->getPopulationHistory( $school );

                $adjustment = $population_service->initializePopulation( $school );

                $population_list[$list_type][$school->getId()] = [
                    'history' => $history,
                    'adjustment' => $adjustment,
                ];

                if( $adjustment ){

                    foreach( $adjustment as $slotting_method => $columns ){
                        $slotting_methods[] = $slotting_method;
                        $max_columns = ( count($columns) > $max_columns )
                            ? count($columns)
                            : $max_columns;
                    }

                    $field_name = ( $list_type == 'magnetSchool' )
                        ? 'p'.$school->getProgram()->getId().'m'.$school->getId()
                        : 'ab'.$school->getId();

                    $form->add( $field_name, CurrentPopulationType::class, [
                        'columns' => $adjustment,
                        //'population_counts' => $adjustment,
                        'capacity' => ( isset( $capacity_hash[$school->getId() ][0] ) ) ? $capacity_hash[$school->getId()][0]->getMax() : 0,
                        'school_type' => $list_type,
                    ] );
                }
            }
        }

        $column_labels = $population_service->getTrackingColumnLabels();

        $form->add( 'savePopulation' , 'submit' , array(
            'label' => 'Save Changes' ,
            'attr' => array( 'class' => 'btn btn-primary' )
        ) );

		$session = $request->getSession();
		$updated = $session->get( 'updated' );
		if( $updated == 1 ) {
			$session->remove( 'updated' );
		}

		$form = $form->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();

			if( count( $alreadyAcceptedDeclinedOffered ) == 0 && $form->get( 'processNow' )->isClicked() ) {

				$process = new Process();
				$process->setEvent( 'lottery' );
				$process->setType( 'process' );
				$process->setOpenEnrollment( $openEnrollment );

				$this->getDoctrine()->getManager()->persist( $process );
				$this->getDoctrine()->getManager()->flush();

				$session->set( 'updated' , 1 );

			} else {

				$openEnrollment->setHRCBlack( 0 );
				$openEnrollment->setHRCWhite( 0 );
				$openEnrollment->setHRCOther( 0 );
				$openEnrollment->setMaxPercentSwing( 0 );

				$placement->setNextSchoolYear( $data['nextSchoolYear'] );
				$placement->setNextYear( $data['nextYear'] );
				if( isset( $data['onlineEndTime'] ) ) {
					$placement->setOnlineEndTime( $data['onlineEndTime'] );
				}
				if( isset( $data['offlineEndTime'] ) ) {
					$placement->setOfflineEndTime( $data['offlineEndTime'] );
				}
				$placement->setRegistrationNewStartDate( $data['registrationNewStartDate'] );
				$placement->setRegistrationCurrentStartDate( $data['registrationCurrentStartDate'] );

				$this->getDoctrine()->getManager()->persist( $openEnrollment );
				$this->getDoctrine()->getManager()->persist( $placement );

                $now = new \DateTime();
                foreach( $data as $key => $key_data ){
                    if( preg_match ('/^p\d+m\d+$/', $key ) ){
                        $key_exploded = explode( 'm', $key );

                        $magnetSchool = $school_hash['magnetSchool'][ $key_exploded[1] ];

                        $capacity = ( isset($capacity_hash[ $key_exploded[1] ][ 0 ]) )
                            ? $capacity_hash[ $key_exploded[1] ][ 0 ]
                            : new Capacity();

                        $capacity
                            ->setSchool( $magnetSchool )
                            ->setMax( $key_data['maxCapacity'] );
                        $this->getDoctrine()->getManager()->persist( $capacity );

                        foreach( $key_data as $tracking_pair => $count ){

                            $tracking_exploded = explode( '_', $tracking_pair );

                            if( $tracking_pair != 'maxCapacity' ){
                                $population_service->create([
                                    'type' => 'starting',
                                    'date_time' => $now,
                                    'school' => $magnetSchool,
                                    'tracking_column' => $tracking_exploded[0],
                                    'tracking_value' => $tracking_exploded[1],
                                    'count' => ( !empty( $count ) ) ? $count : 0,
                                ]);
                            }
                        }
                    }

                    $population_service->persist_and_flush();

                    $this->getDoctrine()->getManager()->flush();

                    $session->set( 'updated' , 1 );
                }
			}
			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process' ) );
		}

		$grades = array(
			99 => 'PreK' ,
			0 => 'PreK' ,
			1 => 'K' ,
			2 => 1 ,
			3 => 2 ,
			4 => 3 ,
			5 => 4 ,
			6 => 5 ,
			7 => 6 ,
			8 => 7 ,
			9 => 8 ,
			10 => 9 ,
			11 => 10 ,
			12 => 11 ,
		);

		return array(
            'admin_pool' => $admin_pool ,
            'openEnrollment' => $openEnrollment ,
            'form' => $form->createView() ,
            'updated' => $updated ,
            'programs' => $programs ,
            'grades' => $grades,
            'max_columns' => $max_columns,
            'column_labels' => $column_labels,
            'population_list' => $population_list,
            'school_hash' => $school_hash,
        );
	}

	/**
	 * Process Submission
	 *
	 * @Route("view-reports/", name="iiab_magnet_program_processing_view_reports")

	 * @Template("@IIABMagnet/Admin/ProgramProcessing/viewReports.html.twig")
	 */
	public function viewReportsAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );
		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

		return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment );
	}

	/**
	 * Awarded Letters/Emails
	 *
	 * @Route("awarded/", name="iiab_magnet_program_processing_awarded")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/lettersEmail.html.twig")
	 */
	public function awardsAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$request = $this->get('request_stack')->getCurrentRequest();

		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

		$placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
			'openEnrollment' => $openEnrollment ,
		), ['round' => 'DESC'] );

		if( $placement == null ) {
			$placement = new Placement();
			$placement->setOpenEnrollment( $openEnrollment );
			$placement->setAwardedMailedDate( new \DateTime( '+1 day' ) );
			$placement->setAddedDateTime( new \DateTime() );
		}

        $emailCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'awarded',
            'type' => 'email'
        ) );

        if($emailCorrespondence == null) {
            $emailCorrespondence = new Correspondence();
            $emailCorrespondence->setName('awarded');
            $emailCorrespondence->setType('email');
            $emailCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Email/awarded.email.twig'));
            $emailCorrespondence->setActive(1);
            $emailCorrespondence->setLastUpdateDateTime(new \DateTime());
        }

        $emailBlock = CorrespondenceVariablesService::divideEmailBlocks($emailCorrespondence->getTemplate());

        $letterCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'awarded',
            'type' => 'letter'
        ) );

        if( $letterCorrespondence == null ) {
            $letterCorrespondence = new Correspondence();
            $letterCorrespondence->setName('awarded');
            $letterCorrespondence->setType( 'letter' );
            $letterCorrespondence->setTemplate( file_get_contents($this->container->get( 'kernel' )->getRootDir() .'/../src/IIAB/MagnetBundle/Resources/views/Report/awardedLetter.html.twig') );
            $letterCorrespondence->setActive(1);
            $letterCorrespondence->setLastUpdateDateTime( new \DateTime() );
        }

        $dynamicVariables = CorrespondenceVariablesService::getDynamicVariables();

		$form = $this->createFormBuilder(null, [ 'attr' => [ 'data-dynamic' => json_encode( $dynamicVariables ) ] ] )
			->add( 'mailDateSetting' , 'date' , array(
				'label' => 'Mail Date Setting' ,
				'data' => $placement->getAwardedMailedDate() ,
			) )

            ->add( 'letterTemplate', CKEditorType::class, array(
                'data' => $letterCorrespondence->getTemplate(),
            ))

            ->add( 'emailSubject', CKEditorType::class, array(
                'data' => $emailBlock['subject'],
                'attr' => array('class' => 'plain-text single-line')
            ))

            ->add( 'emailBodyHtml', CKEditorType::class, array(
                'data' => $emailBlock['body_html'],
            ))

            ->add( 'sendEmailsNow' , 'submit' , array(
				'label' => 'Send Awarded Emails Now' ,
				'attr' => array( 'class' => 'btn btn-primary' ) ,
			) )

            ->add( 'saveEmailChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' ) ,
            ) )

            ->add( 'generateLettersNow' , 'submit' , array(
                'label' => 'Generate Awarded Letters Now' ,
                'attr' => array( 'class' => 'btn btn-primary' ) ,
            ) )

			->add( 'saveChanges' , 'submit' , array(
				'label' => 'Save Changes' ,
				'attr' => array( 'class' => 'btn btn-info' )
			) );

		$form = $form->getForm();

		$form->handleRequest( $request );

		$rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/awarded/' . $openEnrollment->getId() . '/';
		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}

		$lastGeneratedFiles = array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) );
		rsort( $lastGeneratedFiles );
		$lastGeneratedFiles = array_slice( $lastGeneratedFiles , 0 , 5 );

		if( $form->isValid()) {

			$data = $form->getData();

            if( $form->get( 'sendEmailsNow' )->isClicked() ) {
                $process = new Process();
                $process->setEvent( 'email' );
                $process->setType( 'awarded' );
                $process->setOpenEnrollment( $openEnrollment );

                $this->getDoctrine()->getManager()->persist( $process );
                $this->getDoctrine()->getManager()->flush();

            }

            if( $form->get( 'sendEmailsNow' )->isClicked() || $form->get( 'saveEmailChanges' )->isClicked() ) {
                $emailTemplate = CorrespondenceVariablesService::combineEmailBlocks(['subject' => $data['emailSubject'], 'body_html' => $data['emailBodyHtml']]);
                $emailCorrespondence->setTemplate($emailTemplate);
                $this->getDoctrine()->getManager()->persist($emailCorrespondence);
                $this->getDoctrine()->getManager()->flush();
            }

            if( $form->get( 'generateLettersNow' )->isClicked() || $form->get( 'saveChanges' )->isClicked() ){
                $letterCorrespondence->setTemplate( $data['letterTemplate'] );
                $this->getDoctrine()->getManager()->persist( $letterCorrespondence );

                $placement->setAwardedMailedDate( $data['mailDateSetting'] );
                $this->getDoctrine()->getManager()->persist( $placement );
                $this->getDoctrine()->getManager()->flush();
            }

            if( $form->get( 'generateLettersNow' )->isClicked() ){
                $process = new Process();
                $process->setEvent( 'pdf' );
                $process->setType( 'awarded' );
                $process->setOpenEnrollment( $openEnrollment );

                $this->getDoctrine()->getManager()->persist( $process );
                $this->getDoctrine()->getManager()->flush();
            }

			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_awarded' ) );
		}

		return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'files' => $lastGeneratedFiles , 'page' => 'awarded' );
	}

    /**
     * Awarded with Waitlist Letters/Emails
     *
     * @Route("awarded-wait-list/", name="iiab_magnet_program_processing_awarded_waitlist")
     * @Template("@IIABMagnet/Admin/ProgramProcessing/lettersEmail.html.twig")
     */
    public function awardsWaitListAction() {

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $request = $this->get('request_stack')->getCurrentRequest();

        $openEnrollment = $this->getActiveOpenEnrollment();
        if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
            return $openEnrollment;
        }

        $placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
            'openEnrollment' => $openEnrollment ,
        ), ['round' => 'DESC'] );

        if( $placement == null ) {
            $placement = new Placement();
            $placement->setOpenEnrollment( $openEnrollment );
            $placement->setAwardedMailedDate( new \DateTime( '+1 day' ) );
            $placement->setAddedDateTime( new \DateTime() );
        }

        $emailCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'awardedWaitList',
            'type' => 'email'
        ) );

        if($emailCorrespondence == null) {
            $emailCorrespondence = new Correspondence();
            $emailCorrespondence->setName('awardedWaitList');
            $emailCorrespondence->setType('email');
            $emailCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Email/awardedWaitList.email.twig'));
            $emailCorrespondence->setActive(1);
            $emailCorrespondence->setLastUpdateDateTime(new \DateTime());
        }

        $emailBlock = CorrespondenceVariablesService::divideEmailBlocks($emailCorrespondence->getTemplate());

        $letterCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'awardedWaitList',
            'type' => 'letter'
        ) );

        if( $letterCorrespondence == null ) {
            $letterCorrespondence = new Correspondence();
            $letterCorrespondence->setName('awardedWaitList');
            $letterCorrespondence->setType( 'letter' );
            $letterCorrespondence->setTemplate( file_get_contents($this->container->get( 'kernel' )->getRootDir() .'/../src/IIAB/MagnetBundle/Resources/views/Report/awardedWaitListLetter.html.twig') );
            $letterCorrespondence->setActive(1);
            $letterCorrespondence->setLastUpdateDateTime( new \DateTime() );
        }

        $dynamicVariables = CorrespondenceVariablesService::getDynamicVariables();

        $form = $this->createFormBuilder(null, [ 'attr' => [ 'data-dynamic' => json_encode( $dynamicVariables ) ] ] )
            ->add( 'mailDateSetting' , 'date' , array(
                'label' => 'Mail Date Setting' ,
                'data' => $placement->getAwardedMailedDate() ,
            ) )

            ->add( 'letterTemplate', CKEditorType::class, array(
                'data' => $letterCorrespondence->getTemplate(),
            ))

            ->add( 'emailSubject', CKEditorType::class, array(
                'data' => $emailBlock['subject'],
                'attr' => array('class' => 'plain-text single-line')
            ))

            ->add( 'emailBodyHtml', CKEditorType::class, array(
                'data' => $emailBlock['body_html'],
            ))

            ->add( 'sendEmailsNow' , 'submit' , array(
                'label' => 'Send Awarded WaitListed Emails Now' ,
                'attr' => array( 'class' => 'btn btn-primary' ) ,
            ) )

            ->add( 'saveEmailChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' ) ,
            ) )

            ->add( 'generateLettersNow' , 'submit' , array(
                'label' => 'Generate Awarded WaitListed Letters Now' ,
                'attr' => array( 'class' => 'btn btn-primary' ) ,
            ) )

            ->add( 'saveChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' )
            ) );

        $form = $form->getForm();

        $form->handleRequest( $request );

        $rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/awarded-wait-list/' . $openEnrollment->getId() . '/';
        if( !file_exists( $rootDIR ) ) {
            mkdir( $rootDIR , 0755 , true );
        }

        $lastGeneratedFiles = array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) );
        rsort( $lastGeneratedFiles );
        $lastGeneratedFiles = array_slice( $lastGeneratedFiles , 0 , 5 );

        if( $form->isValid()) {

            $data = $form->getData();

            if( $form->get( 'sendEmailsNow' )->isClicked() ) {
                $process = new Process();
                $process->setEvent( 'email' );
                $process->setType( 'awarded-wait-list' );
                $process->setOpenEnrollment( $openEnrollment );

                $this->getDoctrine()->getManager()->persist( $process );
                $this->getDoctrine()->getManager()->flush();

            }

            if( $form->get( 'sendEmailsNow' )->isClicked() || $form->get( 'saveEmailChanges' )->isClicked() ) {
                $emailTemplate = CorrespondenceVariablesService::combineEmailBlocks(['subject' => $data['emailSubject'], 'body_html' => $data['emailBodyHtml']]);
                $emailCorrespondence->setTemplate($emailTemplate);
                $this->getDoctrine()->getManager()->persist($emailCorrespondence);
                $this->getDoctrine()->getManager()->flush();
            }

            if( $form->get( 'generateLettersNow' )->isClicked() ) {
                $process = new Process();
                $process->setEvent( 'pdf' );
                $process->setType( 'awarded-wait-list' );
                $process->setOpenEnrollment( $openEnrollment );

                $this->getDoctrine()->getManager()->persist( $process );
            }

            if( $form->get( 'generateLettersNow' )->isClicked() || $form->get( 'saveChanges' )->isClicked() ) {

                $letterCorrespondence->setTemplate( $data['letterTemplate'] );
                $this->getDoctrine()->getManager()->persist( $letterCorrespondence );

                $placement->setAwardedMailedDate( $data['mailDateSetting'] );
                $this->getDoctrine()->getManager()->persist( $placement );
                $this->getDoctrine()->getManager()->flush();
            }

            return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_awarded_waitlist' ) );
        }

        return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'files' => $lastGeneratedFiles , 'page' => 'awarded-wait-list' );
    }

	/**
	 * Wait List Letters/Emails
	 *
	 * @Route("wait-list/", name="iiab_magnet_program_processing_wait_list")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/lettersEmail.html.twig")
	 */
	public function waitListAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$request = $this->get('request_stack')->getCurrentRequest();

		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

		$placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
			'openEnrollment' => $openEnrollment ,
		), ['round' => 'DESC'] );


		if( $placement == null ) {
			$placement = new Placement();
			$placement->setOpenEnrollment( $openEnrollment );
			$placement->setWaitListMailedDate( new \DateTime( '+1 day' ) );
			$placement->setAddedDateTime( new \DateTime() );
		}

        $emailCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'waitList',
            'type' => 'email'
        ) );

        if($emailCorrespondence == null) {
            $emailCorrespondence = new Correspondence();
            $emailCorrespondence->setName('waitList');
            $emailCorrespondence->setType('email');
            $emailCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Email/waitingList.email.twig'));
            $emailCorrespondence->setActive(1);
            $emailCorrespondence->setLastUpdateDateTime(new \DateTime());
        }
        $emailBlock = CorrespondenceVariablesService::divideEmailBlocks($emailCorrespondence->getTemplate());

        $letterCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'waitList',
            'type' => 'letter'
        ) );

        if( $letterCorrespondence == null ) {
            $letterCorrespondence = new Correspondence();
            $letterCorrespondence->setName('waitList');
            $letterCorrespondence->setType( 'letter' );
            $letterCorrespondence->setTemplate( file_get_contents($this->container->get( 'kernel' )->getRootDir() .'/../src/IIAB/MagnetBundle/Resources/views/Report/waitListLetter.html.twig') );
            $letterCorrespondence->setActive(1);
            $letterCorrespondence->setLastUpdateDateTime( new \DateTime() );
        }

        $dynamicVariables = CorrespondenceVariablesService::getDynamicVariables();

        $form = $this->createFormBuilder(null, [ 'attr' => [ 'data-dynamic' => json_encode( $dynamicVariables ) ] ] )
			->add( 'mailDateSetting' , 'date' , array(
				'label' => 'Mail Date Setting' ,
				'data' => $placement->getWaitListMailedDate() ,
			) )
			->add( 'sendEmailsNow' , 'submit' , array(
				'label' => 'Send Wait List Emails Now' ,
				'attr' => array( 'class' => 'btn btn-primary' ) ,
			) )
			->add( 'saveChanges' , 'submit' , array(
				'label' => 'Save Changes' ,
				'attr' => array( 'class' => 'btn btn-info' )
			) )

            ->add( 'letterTemplate', CKEditorType::class, array(
                'data' => $letterCorrespondence->getTemplate(),
            ) )

            ->add( 'emailSubject', CKEditorType::class, array(
                'data' => $emailBlock['subject'],
                'attr' => array('class' => 'plain-text single-line')
            ) )

            ->add( 'emailBodyHtml', CKEditorType::class, array(
                'data' => $emailBlock['body_html'],
            ) )

            ->add( 'generateLettersNow' , 'submit' , array(
                'label' => 'Generate Wait List Letters Now' ,
                'attr' => array( 'class' => 'btn btn-primary' ) ,
            ) )

            ->add( 'saveEmailChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' ) ,
            ) );

		$form = $form->getForm();

		$form->handleRequest( $request );

		$rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/wait-list/' . $openEnrollment->getId() . '/';
		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}

		$lastGeneratedFiles = array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) );
		rsort( $lastGeneratedFiles );
		$lastGeneratedFiles = array_slice( $lastGeneratedFiles , 0 , 5 );

		if( $form->isValid() ) {

			$data = $form->getData();

            if( $form->get( 'sendEmailsNow' )->isClicked() ) {

                $process = new Process();
                $process->setEvent( 'email' );
                $process->setType( 'wait-list' );
                $process->setOpenEnrollment( $openEnrollment );

                $this->getDoctrine()->getManager()->persist( $process );
                $this->getDoctrine()->getManager()->flush();

            }

            if( $form->get( 'sendEmailsNow' )->isClicked() || $form->get( 'saveEmailChanges' )->isClicked() ) {
                $emailTemplate = CorrespondenceVariablesService::combineEmailBlocks(['subject' => $data['emailSubject'], 'body_html' => $data['emailBodyHtml']]);
                $emailCorrespondence->setTemplate($emailTemplate);
                $this->getDoctrine()->getManager()->persist($emailCorrespondence);
                $this->getDoctrine()->getManager()->flush();
            }

            if( $form->get( 'generateLettersNow' )->isClicked() ) {
                $process = new Process();
                $process->setEvent( 'pdf' );
                $process->setType( 'wait-list' );
                $process->setOpenEnrollment( $openEnrollment );
                $this->getDoctrine()->getManager()->persist( $process );
                $this->getDoctrine()->getManager()->flush();
            }

            if( $form->get( 'generateLettersNow' )->isClicked() || $form->get( 'saveChanges' )->isClicked() ) {
                $letterCorrespondence->setTemplate( $data['letterTemplate'] );
                $this->getDoctrine()->getManager()->persist( $letterCorrespondence );

                $placement->setWaitListMailedDate( $data['mailDateSetting'] );
                $this->getDoctrine()->getManager()->persist( $placement );
                $this->getDoctrine()->getManager()->flush();
            }

			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_wait_list' ) );
		}

		return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'files' => $lastGeneratedFiles , 'page' => 'wait-list' );
	}

    /**
     * Denied Due to Space Letters/Emails
     *
     * @Route("denied/", name="iiab_magnet_program_processing_denied")
     * @Template("@IIABMagnet/Admin/ProgramProcessing/lettersEmail.html.twig")
     */
    public function deniedAction() {

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $request = $this->get('request_stack')->getCurrentRequest();

        $openEnrollment = $this->getActiveOpenEnrollment();
        if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
            return $openEnrollment;
        }

        $placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
            'openEnrollment' => $openEnrollment ,
        ), ['round' => 'DESC'] );

        if( $placement == null ) {
            $placement = new Placement();
            $placement->setOpenEnrollment( $openEnrollment );
            $placement->setDeniedMailedDate( new \DateTime( '+1 day' ) );
            $placement->setAddedDateTime( new \DateTime() );
        }

        $emailCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'denied',
            'type' => 'email'
        ) );

        if($emailCorrespondence == null) {
            $emailCorrespondence = new Correspondence();
            $emailCorrespondence->setName('denied');
            $emailCorrespondence->setType('email');
            $emailCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Email/denied.email.twig'));
            $emailCorrespondence->setActive(1);
            $emailCorrespondence->setLastUpdateDateTime(new \DateTime());
        }

        $emailBlock = CorrespondenceVariablesService::divideEmailBlocks($emailCorrespondence->getTemplate());

        $letterCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'denied',
            'type' => 'letter'
        ) );

        if( $letterCorrespondence == null ) {
            $letterCorrespondence = new Correspondence();
            $letterCorrespondence->setName('denied');
            $letterCorrespondence->setType( 'letter' );
            $letterCorrespondence->setTemplate( file_get_contents($this->container->get( 'kernel' )->getRootDir() .'/../src/IIAB/MagnetBundle/Resources/views/Report/deniedLetter.html.twig') );
            $letterCorrespondence->setActive(1);
            $letterCorrespondence->setLastUpdateDateTime( new \DateTime() );
        }

        $dynamicVariables = CorrespondenceVariablesService::getDynamicVariables();

        $form = $this->createFormBuilder(null, [ 'attr' => [ 'data-dynamic' => json_encode( $dynamicVariables ) ] ] )
            ->add( 'mailDateSetting' , 'date' , array(
                'label' => 'Mail Date Setting' ,
                'data' => $placement->getDeniedMailedDate() ,
            ) )
            ->add( 'sendEmailsNow' , 'submit' , array(
                'label' => 'Send Denied Emails Now' ,
                'attr' => array( 'class' => 'btn btn-primary' ) ,
            ) )
            ->add( 'saveChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' )
            ) )
            ->add( 'letterTemplate', CKEditorType::class, array(
                'data' => $letterCorrespondence->getTemplate(),
            ))

            ->add( 'emailSubject', CKEditorType::class, array(
                'data' => $emailBlock['subject'],
                'attr' => array('class' => 'plain-text single-line')
            ))

            ->add( 'emailBodyHtml', CKEditorType::class, array(
                'data' => $emailBlock['body_html'],
            ))

            ->add( 'generateLettersNow' , 'submit' , array(
                'label' => 'Generate Denied Letters Now' ,
                'attr' => array( 'class' => 'btn btn-primary' ) ,
            ) )

            ->add( 'saveEmailChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' ) ,
            ) );

        $form = $form->getForm();

        $form->handleRequest( $request );

        $rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/denied/' . $openEnrollment->getId() . '/';
        if( !file_exists( $rootDIR ) ) {
            mkdir( $rootDIR , 0755 , true );
        }

        $lastGeneratedFiles = array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) );
        rsort( $lastGeneratedFiles );
        $lastGeneratedFiles = array_slice( $lastGeneratedFiles , 0 , 5 );

        if( $form->isValid() ) {

            $data = $form->getData();

            if( $form->get( 'sendEmailsNow' )->isClicked() ) {

                $process = new Process();
                $process->setEvent( 'email' );
                $process->setType( 'denied' );
                $process->setOpenEnrollment( $openEnrollment );

                $this->getDoctrine()->getManager()->persist( $process );
                $this->getDoctrine()->getManager()->flush();

            }

            if( $form->get( 'sendEmailsNow' )->isClicked() || $form->get( 'saveEmailChanges' )->isClicked() ) {
                $emailTemplate = CorrespondenceVariablesService::combineEmailBlocks(['subject' => $data['emailSubject'], 'body_html' => $data['emailBodyHtml']]);
                $emailCorrespondence->setTemplate($emailTemplate);
                $this->getDoctrine()->getManager()->persist($emailCorrespondence);
                $this->getDoctrine()->getManager()->flush();
            }

            if( $form->get( 'generateLettersNow' )->isClicked() ) {
                $process = new Process();
                $process->setEvent( 'pdf' );
                $process->setType( 'denied' );
                $process->setOpenEnrollment( $openEnrollment );
                $this->getDoctrine()->getManager()->persist( $process );
                $this->getDoctrine()->getManager()->flush();
            }

            if( $form->get( 'generateLettersNow' )->isClicked() || $form->get( 'saveChanges' )->isClicked() ) {
                $letterCorrespondence->setTemplate( $data['letterTemplate'] );
                $this->getDoctrine()->getManager()->persist( $letterCorrespondence );

                $placement->setDeniedMailedDate( $data['mailDateSetting'] );
                $this->getDoctrine()->getManager()->persist( $placement );
                $this->getDoctrine()->getManager()->flush();
            }

            return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_denied' ) );
        }

        return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'files' => $lastGeneratedFiles , 'page' => 'denied' );
    }

	/**
	 * Denied Due to No Transcripts Letters/Emails
	 *
	 * @Route("denied-no-transcripts/", name="iiab_magnet_program_processing_denied_no_transcripts")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/lettersEmail.html.twig")
	 */
	public function deniedNoTranscriptsAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$request = $this->get('request_stack')->getCurrentRequest();

		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

		$placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
			'openEnrollment' => $openEnrollment ,
		), ['round' => 'DESC'] );

		if( $placement == null ) {
			$placement = new Placement();
			$placement->setOpenEnrollment( $openEnrollment );
			$placement->setDeniedMailedDate( new \DateTime( '+1 day' ) );
			$placement->setAddedDateTime( new \DateTime() );
		}

        $emailCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'deniedNoTranscripts',
            'type' => 'email'
        ) );

        if($emailCorrespondence == null) {
            $emailCorrespondence = new Correspondence();
            $emailCorrespondence->setName('deniedNoTranscripts');
            $emailCorrespondence->setType('email');
            $emailCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Email/deniedNoTranscripts.email.twig'));
            $emailCorrespondence->setActive(1);
            $emailCorrespondence->setLastUpdateDateTime(new \DateTime());
        }

        $emailBlock = CorrespondenceVariablesService::divideEmailBlocks($emailCorrespondence->getTemplate());

        $letterCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'deniedNoTranscripts',
            'type' => 'letter'
        ) );

        if( $letterCorrespondence == null ) {
            $letterCorrespondence = new Correspondence();
            $letterCorrespondence->setName('deniedNoTranscripts');
            $letterCorrespondence->setType( 'letter' );
            $letterCorrespondence->setTemplate( file_get_contents($this->container->get( 'kernel' )->getRootDir() .'/../src/IIAB/MagnetBundle/Resources/views/Report/deniedNoTranscriptsLetter.html.twig') );
            $letterCorrespondence->setActive(1);
            $letterCorrespondence->setLastUpdateDateTime( new \DateTime() );
        }

        $dynamicVariables = CorrespondenceVariablesService::getDynamicVariables();

        $form = $this->createFormBuilder(null, [ 'attr' => [ 'data-dynamic' => json_encode( $dynamicVariables ) ] ] )
			->add( 'mailDateSetting' , 'date' , array(
				'label' => 'Mail Date Setting' ,
				'data' => $placement->getTranscriptDueDate() ,
			) )
			->add( 'sendEmailsNow' , 'submit' , array(
				'label' => 'Send Denied Due to No Transcripts Emails Now' ,
				'attr' => array( 'class' => 'btn btn-primary' ) ,
			) )
			->add( 'saveChanges' , 'submit' , array(
				'label' => 'Save Changes' ,
				'attr' => array( 'class' => 'btn btn-info' )
			) )
            ->add( 'letterTemplate', CKEditorType::class, array(
                'data' => $letterCorrespondence->getTemplate(),
            ))

            ->add( 'emailSubject', CKEditorType::class, array(
                'data' => $emailBlock['subject'],
                'attr' => array('class' => 'plain-text single-line')
            ))

            ->add( 'emailBodyHtml', CKEditorType::class, array(
                'data' => $emailBlock['body_html'],
            ))

            ->add( 'generateLettersNow' , 'submit' , array(
                'label' => 'Generate Denied Due to No Transcripts Letters Now' ,
                'attr' => array( 'class' => 'btn btn-primary' ) ,
            ) )

            ->add( 'saveEmailChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' ) ,
            ) );

		$form = $form->getForm();

		$form->handleRequest( $request );

		$rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/denied-no-transcripts/' . $openEnrollment->getId() . '/';
		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}

		$lastGeneratedFiles = array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) );
		rsort( $lastGeneratedFiles );
		$lastGeneratedFiles = array_slice( $lastGeneratedFiles , 0 , 5 );

		if( $form->isValid() ) {

			$data = $form->getData();

            if( $form->get( 'sendEmailsNow' )->isClicked() ) {

                $process = new Process();
                $process->setEvent( 'email' );
                $process->setType( 'denied-no-transcripts' );
                $process->setOpenEnrollment( $openEnrollment );

                $this->getDoctrine()->getManager()->persist( $process );
                $this->getDoctrine()->getManager()->flush();

            }

            if( $form->get( 'sendEmailsNow' )->isClicked() || $form->get( 'saveEmailChanges' )->isClicked() ) {
                $emailTemplate = CorrespondenceVariablesService::combineEmailBlocks(['subject' => $data['emailSubject'], 'body_html' => $data['emailBodyHtml']]);
                $emailCorrespondence->setTemplate($emailTemplate);
                $this->getDoctrine()->getManager()->persist($emailCorrespondence);
                $this->getDoctrine()->getManager()->flush();
            }

            if( $form->get( 'generateLettersNow' )->isClicked() ) {
                $process = new Process();
                $process->setEvent( 'pdf' );
                $process->setType( 'denied-no-transcripts' );
                $process->setOpenEnrollment( $openEnrollment );
                $this->getDoctrine()->getManager()->persist( $process );
                $this->getDoctrine()->getManager()->flush();
            }

            if( $form->get( 'generateLettersNow' )->isClicked() || $form->get( 'saveChanges' )->isClicked() ) {
                $letterCorrespondence->setTemplate( $data['letterTemplate'] );
                $this->getDoctrine()->getManager()->persist( $letterCorrespondence );

                $placement->setDeniedMailedDate( $data['mailDateSetting'] );
                $this->getDoctrine()->getManager()->persist( $placement );
                $this->getDoctrine()->getManager()->flush();
            }

			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_denied_no_transcripts' ) );
		}

		return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'files' => $lastGeneratedFiles , 'page' => 'denied-no-transcripts' );
	}

	/**
	 * Process Wait List
	 *
	 * @Route("process-wait-list/", name="iiab_magnet_program_processing_process_wait_list")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/waitList.html.twig")
	 */
	public function processWaitListAction() {

		ini_set( 'memory_limit' , '512M' );

		$admin_pool = $this->get( 'sonata.admin.pool' );

        $population_service = $this->get( 'magnet.population' );

		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

		$request = $this->get('request_stack')->getCurrentRequest();

		$placement = $this->getDoctrine()
            ->getRepository( 'IIABMagnetBundle:Placement' )
            ->findOneBy( array(
			 'openEnrollment' => $openEnrollment ,
		  ), ['round' => 'DESC'] );

		if( $placement == null ) {
			$placement = new Placement();
			$placement->setOpenEnrollment( $openEnrollment );

			$this->getDoctrine()->getManager()->persist( $placement );
			$this->getDoctrine()->getManager()->flush();
		}

		$alreadyAcceptedDeclinedOffered = $this->getDoctrine()
            ->getRepository( 'IIABMagnetBundle:Offered' )
            ->findBy( array(
                'openEnrollment' => $openEnrollment ,
                'accepted' => 0 ,
                'declined' => 0
            ) );

		$form = $this->createFormBuilder();

		//Settings Tab
		$form->add( 'waitListExpireTime' , 'datetime' , array(
			'label' => 'When should the Wait List Expire?' ,
			'data' => ( $placement->getWaitListExpireTime() == null ) ? new \DateTime( 'midnight +4 months' ) : $placement->getWaitListExpireTime()
		) );

		$form->add( 'saveSettings' , 'submit' , array(
			'label' => 'Save Settings' ,
			'attr' => array( 'class' => 'btn btn-primary' )
		) );


		//All Processing Tab
		$populationCollection = array();
		$schoolsListForIndividualProcessing = array();
		$lastWaitListProcessing = array();

		try {
			$lastWaitListGroup = $this->container
                ->get( 'magnet.lottery' )
                ->getLatestWaitListProcessingDate( $openEnrollment );
		} catch( \Exception $e ) {
			$lastWaitListGroup = null;
		}

        //Enrollment Tab
        $user = $this->getUser();
        $schools = [
            'magnetSchool' => $this->getDoctrine()
                ->getRepository( 'IIABMagnetBundle:MagnetSchool' )
                ->findByUser( $user, $openEnrollment ),
        ];

        $school_hash = [];
        foreach( $schools as $school_type => $type_schools ){
            foreach( $type_schools as $school ){
                $school_hash[$school_type][ $school->getId() ] = $school;
            }
        }

        $zones = $this->getDoctrine()
                ->getRepository( 'IIABMagnetBundle:AddressBoundSchool' )
                ->findAll();

        $zone_hash = [];
        foreach( $zones as $zoned_school ){
            $zone_hash[$zoned_school->getId()] = $zoned_school->getName();
        }

        $programs = [];
        $population_hash = [];

        foreach( $school_hash['magnetSchool'] as $magnetSchool ){
            if( !isset( $programs[$magnetSchool->getProgram()->getId()] ) ){
                $programs[$magnetSchool->getProgram()->getId()] = $magnetSchool->getProgram();
            }

            $current_population = $population_service->getCurrentTotalPopulation( $magnetSchool );
            $population_hash[ $magnetSchool->getId() ] = $current_population['Race'];
        }

        $capacities = $this->getDoctrine()
            ->getRepository( 'IIABMagnetBundle:Capacity' )
            ->findAll();
        $capacity_hash = [];
        foreach( $capacities as $capacity ){
            $focus = $capacity->getFocusArea();
            $focus = ( !empty($focus) )? $focus : 0;
            $capacity_hash[ $capacity->getSchool()->getId() ][$focus] = $capacity;
        }

        $schoolsListForIndividualProcessing = [];
        $populationCollection = [];
        foreach( $school_hash['magnetSchool'] as $magnetSchool ){

            $availableSlots = $capacity_hash[ $magnetSchool->getId() ][0]->getMax() - $population_hash[ $magnetSchool->getId() ];
            $slotsToAward = ( $availableSlots >= 0) ? $availableSlots : 0;

            $waitListTotal = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:WaitList' )->createQueryBuilder('wt')
                ->where('wt.choiceSchool = :school')
                ->setParameter('school', $school)
                ->andWhere('wt.openEnrollment = :openEnrollment')
                ->setParameter('openEnrollment', $openEnrollment)
                ->select('COUNT(wt.submission)')
                ->getQuery()
                ->getSingleScalarResult();

            foreach( $programs as $program ) {

                $program_focus_areas = $program->getAdditionalData( 'focus' );

                $capacity_by_focus = $program->getAdditionalData('capacity_by');
                $capacity_by_focus = ( isset( $capacity_by_focus[0] ) && $capacity_by_focus[0]->getMetaValue() == 'focus');

                $schools = $program->getMagnetSchools();

                foreach( $schools as $school ) {

                    $focus_areas = (
                        $school->getGrade() > 1
                        && $school->getGrade() < 99
                        && $capacity_by_focus
                        && count($program_focus_areas) > 0
                    ) ? $program_focus_areas : [ null ];

                    foreach ($focus_areas as $focus_area) {

                        $focus = ( !empty( $focus_area ) ) ? $focus_area->getMetaValue() : null;
                        $focus_key = ( empty( $focus) ) ? 0 : $focus;

                        $population_totals = $population_service->getCurrentTotalPopulation( $school, $focus_area );

                        if( $lastWaitListGroup != null ) {
                            $waitListProcessing = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:WaitListProcessing' )->findOneBy( array(
                                'magnetSchool' => $school ,
                                'openEnrollment' => $openEnrollment ,
                                'focusArea' => $focus,
                            ) , array( 'addedDateTimeGroup' => 'DESC' ) );

                            //Add it to the Last Wait List Processing if not Null.
                            if( $waitListProcessing != null ) {
                                $lastWaitListProcessing[$school->getId()][$focus_key] = $waitListProcessing;
                                $waitListProcessing = null;
                            }
                        }

                        if( empty( $focus ) ){
                            $waitListTotal = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:WaitList' )->createQueryBuilder('wt')
                                ->where('wt.choiceSchool = :school')
                                ->setParameter('school', $school)
                                ->andWhere('wt.openEnrollment = :openEnrollment')
                                ->setParameter('openEnrollment', $openEnrollment)
                                ->select('COUNT(wt.submission)')
                                ->getQuery()
                                ->getSingleScalarResult();

                        } else {
                            $waitListTotal = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:WaitList' )->createQueryBuilder('wt')
                                ->where('wt.choiceSchool = :school')
                                ->setParameter('school', $school)
                                ->andWhere('wt.openEnrollment = :openEnrollment')
                                ->setParameter('openEnrollment', $openEnrollment)
                                ->andWhere('wt.choiceFocusArea = :focusArea')
                                ->setParameter( 'focusArea', $focus)
                                ->select('COUNT(wt.submission)')
                                ->getQuery()
                                ->getSingleScalarResult();
                        }

                        $availableSlots = $capacity_hash[$school->getId()][$focus_key]->getMax() - $population_totals['Race'];
                        $slotsToAward = ( $availableSlots >= 0) ? $availableSlots : 0;

                        //Used to build the Individual Processing Drop Down.
                        $schoolsListForIndividualProcessing[$school->getId() . str_replace(' ', '_', preg_replace("/[^0-9a-zA-Z ]/", '', $focus_key ) ) ] = array(
                            'magnetSchool' => $school,
                            'individual' => 0 ,
                            'CPBlack' => 0,
                            'CPOther' => 0,
                            'CPWhite' => 0,
                            'waitListTotal' => $waitListTotal,
                            'availableSlots' => $availableSlots,
                            'slotsToAward' => $slotsToAward,
                            'focus_area' => $focus
                        );

                        if( $population_service->doesSchoolUseTracker( $school, 'HomeZone') ){

                            foreach( $population_service->getTrackingValues( $school, 'HomeZone' ) as $zone_id ){

                                $schoolsListForIndividualProcessing
                                    [$school->getId() . str_replace(' ', '_', preg_replace("/[^0-9a-zA-Z ]/", '', $focus_key ) ) ]
                                    ['HomeZone'][$zone_id] = 0;

                                }
                        }

                        $populationCollection[$school->getId() . str_replace(' ', '_', preg_replace("/[^0-9a-zA-Z ]/", '', $focus_key ) ) ] = array(
                            'magnetSchool' => $school,
                            'maxCapacity' => $capacity_hash[ $school->getId() ][$focus_key]->getMax(),
                            'waitListTotal' => $waitListTotal,
                            'availableSlots' => $availableSlots,
                            'slotsToAward' => $slotsToAward,
                            'focus_area' => $focus
                        );

                        $currentPopulation = null;
                        $waitListTotal = 0;
                        $availableSlots = 0;
                        $slotsToAward = 0;
                    }
                }
            }
        }

        $form->add('populationToAward', 'collection', array(
            'entry_type' => WaitListProcessingType::class,
            'data' => $populationCollection,
            'required' => false,
        ));

		$form->add( 'mailDateSettingAll' , 'date' , array(
			'label' => 'Mail Date Setting for Awarded Letters' ,
			'data' => $placement->getAwardedMailedDate() ,
		) );

		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'populationOnlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => ( $placement->getWaitListOnlineEndTime() == null ) ? new \DateTime('midnight +11 days') : $placement->getWaitListOnlineEndTime()
			) );
		} else {
			$form->add( 'populationOnlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => $placement->getWaitListOnlineEndTime() ,
				'attr' => ['readonly'=>'readonly'],
				'disabled' => true
			) );
		}
		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'populationOfflineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => ( $placement->getWaitListOfflineEndTime() == null ) ? new \DateTime( '16:00 +10 days') : $placement->getWaitListOfflineEndTime()
			) );
		} else {
			$form->add( 'populationOfflineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => $placement->getWaitListOfflineEndTime() ,
				'attr' => ['readonly'=>'readonly'],
				'disabled' => true
			) );
		}

		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'processWaitList2' , 'submit' , array(
				'label' => 'Process All (Now)' ,
				'attr' => array( 'class' => 'btn btn-danger' )
			) );
		}

		//Individual Tab
		$form->add( 'individualPopulation', 'collection' , array(
			'entry_type' => WaitListIndividualProcessingType::class ,
			'data' => $schoolsListForIndividualProcessing ,
			'required' => false ,
            //'home_zones' => $schoolsListForIndividualProcessing,
		) );

		$form->add( 'mailDateSettingIndividual' , 'date' , array(
			'label' => 'Mail Date Setting for Awarded Letters' ,
			'data' => $placement->getAwardedMailedDate() ,
		) );

		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'individualOnlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => ( $placement->getWaitListOnlineEndTime() == null ) ? new \DateTime('midnight +11 days') : $placement->getWaitListOnlineEndTime()
			) );
		} else {
			$form->add( 'individualOnlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => $placement->getWaitListOnlineEndTime() ,
				'attr' => ['readonly'=>'readonly'],
				'disabled' => true
			) );
		}
		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'individualOfflineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => ( $placement->getWaitListOfflineEndTime() == null ) ? new \DateTime( '16:00 +10 days') : $placement->getWaitListOfflineEndTime()
			) );
		} else {
			$form->add( 'individualOfflineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => $placement->getWaitListOfflineEndTime() ,
				'attr' => ['readonly'=>'readonly'],
				'disabled' => true
			) );
		}

		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'processWaitListIndividual' , 'submit' , array(
				'label' => 'Process Individual (Now)' ,
				'attr' => array( 'class' => 'btn btn-danger' )
			) );
		}

        $form = $form->getForm();
		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();

			if( $form->get( 'saveSettings' )->isClicked() ) {

				$placement->setWaitListExpireTime( $data['waitListExpireTime'] );
				$this->getDoctrine()->getManager()->persist( $placement );
				$this->getDoctrine()->getManager()->flush();
				return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process_wait_list' ) );
			}

			$processDateTime = new \DateTime();

			//Is the Process All button Clicked???
			if( $form->get( 'processWaitList2' )->isClicked() ) {

				$placement->setWaitListExpireTime( $data['waitListExpireTime'] );
				$placement->setAwardedMailedDate( $data['mailDateSettingAll'] );
				$placement->setOnlineEndTime( $data['populationOnlineEndTime'] );
				$placement->setOfflineEndTime( $data['populationOfflineEndTime'] );
				$this->getDoctrine()->getManager()->persist( $placement );

				foreach( $data['populationToAward'] as $population ) {

                    $waitListProcessing = new WaitListProcessing();
                    $waitListProcessing->setAddedDateTimeGroup( $processDateTime ); //This will Group them all together.
                    $waitListProcessing->setCount( $population['slotsToAward'] );
                    $waitListProcessing->setTrackingValue( '' );
                    $waitListProcessing->setTrackingColumn( 'slotsToAward' );
                    $waitListProcessing->setMagnetSchool( $population['magnetSchool'] );
                    $waitListProcessing->setOpenEnrollment( $openEnrollment );
                    $waitListProcessing->setFocusArea( $population['focus_area'] );

					$this->getDoctrine()->getManager()->persist( $waitListProcessing );
				}

				//Create a new Process for the scheduler to pick and work.
				$process = new Process();
				$process->setOpenEnrollment($openEnrollment);
				$process->setEvent('lottery');
				$process->setType('wait-list');
				$this->getDoctrine()->getManager()->persist( $process );

				//Commit everything to the DB.
				$this->getDoctrine()->getManager()->flush();
				return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process_wait_list' ) );
			}

			//Is the Individual Processing Button Clicked??/
			if( $form->get( 'processWaitListIndividual' )->isClicked() ) {

				$placement->setWaitListExpireTime( $data['waitListExpireTime'] );
				$placement->setAwardedMailedDate( $data['mailDateSettingIndividual'] );
				$placement->setOnlineEndTime( $data['individualOnlineEndTime'] );
				$placement->setOfflineEndTime( $data['individualOfflineEndTime'] );
				$this->getDoctrine()->getManager()->persist( $placement );

				//Loop over all the individual MagnetSchools and see if any of them have bee activated to 1.
				foreach( $data['individualPopulation'] as $individual ) {

					//Only add schools that have been selected to be individually processed.
					//If activated (in other words, shown on the screen), create a WaitListProcessing
					if( $individual['individual'] == 1 ) {

                        foreach( $individual as $key => $value ){

                            if( $individual['fillingSlots'] == 1 ){
                                if( in_array($key, ['CPBlack','CPWhite','CPOther'] )
                                    || is_numeric( $key )

                                ){
                                    $waitListProcessing = new WaitListProcessing();
                                    $waitListProcessing->setAddedDateTimeGroup( $processDateTime ); //This will Group them all together.
                                    $waitListProcessing->setCount( $value );
                                    $waitListProcessing->setTrackingValue( $key );
                                    $waitListProcessing->setTrackingColumn( (is_numeric($key) ) ? 'HomeZone' : 'Race' );
                                    $waitListProcessing->setMagnetSchool( $individual['magnetSchool'] );
                                    $waitListProcessing->setOpenEnrollment( $openEnrollment );
                                    $waitListProcessing->setFocusArea( $individual['focus_area'] );

                                    $this->getDoctrine()->getManager()->persist( $waitListProcessing );
                                }
                            }
                        }

                        $waitListProcessing = new WaitListProcessing();
                        $waitListProcessing->setAddedDateTimeGroup( $processDateTime ); //This will Group them all together.
                        $waitListProcessing->setCount( $individual['slotsToAward'] );
                        $waitListProcessing->setTrackingValue( '' );
                        $waitListProcessing->setTrackingColumn( 'slotsToAward' );
                        $waitListProcessing->setMagnetSchool( $individual['magnetSchool'] );
                        $waitListProcessing->setOpenEnrollment( $openEnrollment );
                        $waitListProcessing->setFocusArea( $individual['focus_area'] );

                        $this->getDoctrine()->getManager()->persist( $waitListProcessing );
					}
				}

				//Create a new Process for the scheduler to pick and work.
				$process = new Process();
				$process->setOpenEnrollment($openEnrollment);
				$process->setEvent('lottery');
				$process->setType('wait-list');
				$this->getDoctrine()->getManager()->persist( $process );

				//Commit everything to the DB.
				$this->getDoctrine()->getManager()->flush();
				return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process_wait_list' ) );

			}

			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process_wait_list' ) );
		}

		return array(
            'admin_pool' => $admin_pool ,
            'openEnrollment' => $openEnrollment ,
            'form' => $form->createView() ,
            'lastWaitListProcessing' => $lastWaitListProcessing,
            'zone_hash' => $zone_hash,
        );
	}

	/**
	 * Edit After Placement Periods
	 *
	 * @Route("late-window/", name="iiab_magnet_program_processing_edit_late_placement")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/latePlacement.html.twig")
	 */
	public function editLatePlacementAction(){

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

		$placements =  $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Placement' )->findBy([
			'openEnrollment' => $openEnrollment
		]);

		$test = '';
		foreach( $placements as $placement ){
			$test .=  $placement->getId() .' '. $placement->getRound() .' '. $placement->getType() .', ' ;
		}

		$form = $this->createFormBuilder();

		$form->add( 'onlineEndTime' , 'datetime' , array(
			'label' => 'Last day and time to accept ONLINE' ,
			'data' => $placement->getOnlineEndTime()
		) )

		->add( 'offlineEndTime' , 'datetime' , array(
			'label' => 'Last day and time to accept OFFLINE' ,
			'data' => $placement->getOfflineEndTime()
		) )

		->add( 'AwardedMailedDate' , 'date' , array(
			'label' => 'Mail Date Setting for Awarded Letters' ,
			'data' => $placement->getAwardedMailedDate() ,
		) )

		->add( 'waitListMailedDate' , 'date' , array(
			'label' => 'Mail Date Setting for Wait List Letters' ,
			'data' => $placement->getAwardedMailedDate() ,
		) )

		->add( 'deniedMailedDate' , 'date' , array(
			'label' => 'Mail Date Setting for Awarded Letters' ,
			'data' => $placement->getAwardedMailedDate() ,
		) )

		->add( 'registrationNewStartDate' , 'date' , array(
			'label' => 'Beginning Registration Date (New Student)' ,
			'data' => $placement->getAwardedMailedDate() ,
		) )

		->add( 'registrationCurrentStartDate' , 'date' , array(
			'label' => 'Beginning Registration Date (Current Student)' ,
			'data' => $placement->getAwardedMailedDate() ,
		) )

		->add( 'waitListOnlineEndTime' , 'date' , array(
			'label' => 'Last day and time to accept ONLINE' ,
			'data' => $placement->getAwardedMailedDate() ,
		) )

		->add( 'waitListOfflineEndTime' , 'date' , array(
			'label' => 'Last day and time to accept OFFLINE' ,
			'data' => $placement->getAwardedMailedDate() ,
		) )

		->add( 'waitListExpireTime' , 'date' , array(
			'label' => 'When should the Wait List Expire?' ,
			'data' => $placement->getAwardedMailedDate() ,
		) )

		->add( 'transcriptDueDate' , 'date' , array(
			'label' => 'Mail Date Setting' ,
			'data' => $placement->getAwardedMailedDate() ,
		) )

		->add( 'nextStepMailedDate' , 'date' , array(
			'label' => 'Mail Date Setting' ,
			'data' => $placement->getAwardedMailedDate() ,
		) );

		$form = $form->getForm();

		/*
		$today = new \DateTime();

		$placement = new Placement();
		$placement->setPreKDateCutOff( new \DateTime( ( $today->format( 'Y' ) -5 ) .'-09-01' ) );
		$placement->setKindergartenDateCutOff( new \DateTime( ( $today->format( 'Y' ) -6 ) .'-09-01' ) );
		$placement->setFirstGradeDateCutOff( new \DateTime( ( $today->format( 'Y' ) -7 ) .'-09-01' ) );
		$placement->setCompleted( false );
		$placement->setOpenEnrollment( $openEnrollment );
		$placement->setEligibility( false );
		$placement->setType( NULL );
		$placement->setRound( 3 );
		*/

		$this->getDoctrine()->getManager()->persist( $placement );
		$this->getDoctrine()->getManager()->flush();

		$request = $this->get('request_stack')->getCurrentRequest();

		return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment, 'placements' => $placements, 'test' => $test , 'form' => $form->createView() );
	}

	/**
	 * Commit Lottery
	 *
	 * @Route("review-lottery/", name="iiab_magnet_program_processing_commit_lottery")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/reviewLottery.html.twig")
	 */
	public function commitLotteryAction()
    {
        ini_set('memory_limit','2048M');
        $admin_pool = $this->get('sonata.admin.pool');

        $request = $this->get('request_stack')->getCurrentRequest();

        $openEnrollment = $this->getActiveOpenEnrollment();
        if (!is_a($openEnrollment, 'IIAB\MagnetBundle\Entity\OpenEnrollment')) {
            return $openEnrollment;
        }

        $submissions = $this->getDoctrine()->getRepository('IIABMagnetBundle:LotteryOutcomeSubmission')->findBy([], ['type' => 'DESC', 'id' => 'ASC']);

        $populations = $this->getDoctrine()->getRepository('IIABMagnetBundle:LotteryOutcomePopulation')->findAll();

        $population_service = $this->get('magnet.population');

        $tracking_column_labels = $population_service->getTrackingColumnLabels();

        $schools = [];
        foreach ($populations as $population) {
            if ($population->getType() != 'withdrawal') {

                $focus = (empty($population->getFocusArea())) ? 0 : $population->getFocusArea();

                $schools [$population->getMagnetSchool()->getId()]
                         [$focus]
                         [$population->getTrackingValue() ]
                         [$population->getType()] = $population;
            }
        }

        foreach( $schools as $index => $focus_areas ){

            uasort( $focus_areas, function( $a, $b ) {

                if ($a['before']->getMagnetSchool()->getGrade() == 99) {
                    return -1;
                }

                if ($b['before']->getMagnetSchool()->getGrade() == 99) {
                    return 1;
                }
                return ($a['before']->getMagnetSchool()->getGrade() < $b['before']->getMagnetSchool()->getGrade()) ? -1 : 1;
            });

            ksort( $focus_areas );
            $schools[ $index ]= $focus_areas;
        }

        $population_order = ( isset( $request->request->get('form')['orderPopulation'] ) ) ? $request->request->get('form')['orderPopulation'] : 'school';
        $submission_order = ( isset( $request->request->get('form')['orderSubmission'] ) ) ? $request->request->get('form')['orderSubmission'] : 'outcome';

		if( $submission_order == 'id' ){
			usort( $submissions, function ($a, $b){
				return ( $a->getSubmission()->getId() < $b->getSubmission()->getId() ) ? -1 : 1;
			});
		} else if( $submission_order == 'name' ){
			usort( $submissions, function ($a, $b){

				if( $a->getSubmission()->getLastName() == $b->getSubmission()->getLastName() ){
					return strcmp( $a->getSubmission()->getFirstName(), $b->getSubmission()->getFirstName() );
				}
				return strcmp( $a->getSubmission()->getLastName() , $b->getSubmission()->getLastName() );
			});
		} else if( $submission_order == 'late' ){
			usort( $submissions, function ($a, $b){

				$a_late = ( $a->getOpenEnrollment()->getEndingDate()->modify('+1 day') < $a->getSubmission()->getCreatedAt() );
				$b_late = ( $b->getOpenEnrollment()->getEndingDate()->modify('+1 day') < $b->getSubmission()->getCreatedAt() );

				if( $a_late == $b_late){
					if( $a->getSubmission()->getLastName() == $b->getSubmission()->getLastName() ){
						return strcmp( $a->getSubmission()->getFirstName(), $b->getSubmission()->getFirstName() );
					}
					return strcmp( $a->getSubmission()->getLastName() , $b->getSubmission()->getLastName() );
				}

				if( $a_late == true ){
					return -1;
				} else if( $b_late == true ){
					return 1;
				}
			});
		} else if( $submission_order == 'school' ){
			usort( $submissions, function ($a, $b){

				if( $a->getMagnetSchool() != null ){

					if( $b->getMagnetSchool() == null ){
						return -1;
					}

					if( $a->getMagnetSchool() == $b->getMagnetSchool() ){
						if( $a->getSubmission()->getLastName() == $b->getSubmission()->getLastName() ){
							return strcmp( $a->getSubmission()->getFirstName(), $b->getSubmission()->getFirstName() );
						}
						return strcmp( $a->getSubmission()->getLastName() , $b->getSubmission()->getLastName() );
					}

					if( $a->getMagnetSchool()->getName() == $b->getMagnetSchool()->getName() ) {

						if( $a->getMagnetSchool()->getGrade() == 99 ){
							return -1;
						}

						if( $b->getMagnetSchool()->getGrade() == 99 ){
							return 1;
						}
						return ( $a->getMagnetSchool()->getGrade() < $b->getMagnetSchool()->getGrade() ) ? -1 : 1;
					}

					return strcmp( $a->getMagnetSchool()->getName() , $b->getMagnetSchool()->getName() );
				}

				if( $a->getMagnetSchool() == $b->getMagnetSchool() ){

					if( $a->getSubmission()->getFirstChoice() == $b->getSubmission()->getFirstChoice() ){
						if( $a->getSubmission()->getLastName() == $b->getSubmission()->getLastName() ){
							return strcmp( $a->getSubmission()->getFirstName(), $b->getSubmission()->getFirstName() );
						}
						return strcmp( $a->getSubmission()->getLastName() , $b->getSubmission()->getLastName() );
					}

					if( $a->getSubmission()->getFirstChoice()->getName() == $b->getSubmission()->getFirstChoice()->getName() ) {

						if( $a->getSubmission()->getFirstChoice()->getGrade() == 99 ){
							return -1;
						}

						if( $b->getSubmission()->getFirstChoice()->getGrade() == 99 ){
							return 1;
						}
						return ( $a->getSubmission()->getFirstChoice()->getGrade() < $b->getSubmission()->getFirstChoice()->getGrade() ) ? -1 : 1;
					}

					return strcmp( $a->getSubmission()->getFirstChoice()->getName() , $b->getSubmission()->getFirstChoice()->getName() );
				}

				return 1;
			});
		}else if( $submission_order == 'outcome' ){
			usort( $submissions, function ($a, $b){

				if( $a->getType() == $b->getType() ){
					if( $a->getSubmission()->getLastName() == $b->getSubmission()->getLastName() ){
						return strcmp( $a->getSubmission()->getFirstName(), $b->getSubmission()->getFirstName() );
					}
					return strcmp( $a->getSubmission()->getLastName() , $b->getSubmission()->getLastName() );
				}
				return strcmp( $a->getType() , $b->getType() );
			});
		}

		$form = $this->createFormBuilder();
		$form
			->add( 'orderSubmission', 'choice', [
				'label'=>'Order by',
				'required' => false,
				'choices' => [
					'id' => 'Submission ID',
					'late_submission' => 'Late Submission',
					'name' => 'Student Name',
					'school' => 'School',
					'outcome' => 'Outcome'
				],
				'placeholder' => 'Sort by column'
			])
			->add( 'submissionSort' , 'submit' , [
				'label' => 'Sort'
			])

			->add( 'commitResults' , 'submit' , array(
			'label' => 'Accept Outcome and Commit Results' ,
			'attr' => array( 'class' => 'btn btn-danger' ) ,
            ))

            ->add( 'downloadPrograms' , 'submit' , array(
                'label' => 'Download Population Changes' ,
                'attr' => array( 'class' => 'btn' ) ,
            ))

            ->add( 'downloadSubmissions' , 'submit' , array(
                'label' => 'Download Submission Results' ,
                'attr' => array( 'class' => 'btn' ) ,
		) );
		$form = $form->getForm();

		$form->handleRequest( $request );

		$active_tab = 'population';

		if( $form->isValid() ) {

			$data = $form->getData();

			if ($form->get('submissionSort')->isClicked()) {
				$active_tab = 'submission';
			}

			if( $form->get('downloadPrograms')->isClicked() ){

                $response = new StreamedResponse();
                $response->setCallback(function() use( $schools, $tracking_column_labels ) {
                    $handle = fopen('php://output', 'w+');

                    $tracking_column = implode( '/', array_keys( $tracking_column_labels ) );
                    fputcsv($handle, [
                        'School',
                        'Grade',
                        'Max Capacity',
                        //'Starting Available Slots',
                        $tracking_column,
                        'Added',
                        //'Ending Available Slots',
                    ]);

                    foreach( $schools as $focus_areas ) {

                        foreach( $focus_areas as $tracking_value ){
                            //var_dump( $focus_area ); die;

                            foreach ($tracking_value as $population) {

                                fputcsv($handle, [
                                    $population['before']->getMagnetSchool()->getName(),
                                    $population['before']->getMagnetSchool()->getGrade(),
                                    $population['before']->getMaxCapacity(),
                                    //($population['before']->getMaxCapacity() - ($population['before']->getCPBlack() + $population['before']->getCPWhite() + $population['before']->getCPOther())),
                                    $tracking_column_labels[ $population['before']->getTrackingColumn() ][$population['before']->getTrackingValue()],
                                    $population['changed']->getCount(),
                                    //$population['changed']->getCPOther(),
                                    //($population['changed']->getCPBlack() + $population['changed']->getCPWhite() + $population['changed']->getCPOther()),
                                    //($population['after']->getMaxCapacity() - ($population['after']->getCPBlack() + $population['after']->getCPWhite() + $population['after']->getCPOther())),
                                ]);
                            }
                        }
                    }
                    fclose($handle);
                });

                $response->setStatusCode(200);
                $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
                $response->headers->set('Content-Disposition', 'attachment; filename="Population_Changes.csv"');

                return $response;

            } else if( $form->get('downloadSubmissions')->isClicked() ){


                $response = new StreamedResponse();
                $response->setCallback(function() use( $submissions, $tracking_value_hash, $tracking_column ) {
                    $handle = fopen('php://output', 'w+');
                    fputcsv($handle, [
                        'Submission ID',
                        'Student Name',
                        'Grade',
                        $tracking_column,
                        'School',
                        'Chosen',
                        'Outcome',
                    ]);

                    $choices = [ '', 'First', 'Second', 'Third' ];

                    foreach( $submissions as $result ) {

                        fputcsv($handle, [
                            $result->getSubmission()->getId(),
                            $result->getSubmission()->getFirstName() .' '. $result->getSubmission()->getLastName(),
                            $result->getSubmission()->getNextGrade(),
                            $tracking_value_hash[ $result->getSubmission()->getId() ],
                            $result->getMagnetSchool(),
                            $choices[ $result->getChoiceNumber() ],
                            $result->getType()
                        ]);
                    }
                    fclose($handle);
                });

                $response->setStatusCode(200);
                $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
                $response->headers->set('Content-Disposition', 'attachment; filename="Submission_Results.csv"');

                return $response;
            } else if ($form->get('commitResults')->isClicked()) {

				$process = new Process();
				$process->setEvent( 'lottery' );
				$process->setType( 'commit' );
				$process->setOpenEnrollment( $openEnrollment );
				$this->getDoctrine()->getManager()->persist( $process );

				$this->getDoctrine()->getManager()->flush();
				return $this->redirect($this->generateUrl('iiab_magnet_program_processing_dashboard'));
			}
		}

		return array(
            'admin_pool' => $admin_pool ,
            'openEnrollment' => $openEnrollment,
            'submissions' => $submissions,
            'schools' => $schools ,
            'form' => $form->createView(),
            'active_tab' => $active_tab,
            'tracking_column_labels' => $tracking_column_labels,
        );
	}


	/**
	 * Process After Placement Submissions
	 *
	 * @Route("process-late-placement/", name="iiab_magnet_program_processing_process_late_placement")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/latePlacement.html.twig")
	 */
	public function processLatePlacementAction(){

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$openEnrollment = $this->getActiveOpenEnrollment();
		if( !is_a( $openEnrollment , 'IIAB\MagnetBundle\Entity\OpenEnrollment' ) ) {
			return $openEnrollment;
		}

	//	$now = new \DateTime();
//		if( $now >   $openEnrollment ) ) {
//			$lateEnrollment = $emLookup->getRepository('IIABMagnetBundle:OpenEnrollment')->findLatePlacementByDate(new \DateTime())[0];




		$request = $this->get('request_stack')->getCurrentRequest();

		$placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy(
			[
				'openEnrollment' => $openEnrollment ,
				'running' => 1
			] ,
			[ 'round' => 'DESC' ]
		);
		if( $placement == null ) {
			$last_placement = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy([	'openEnrollment' => $openEnrollment ], [ 'round' => 'DESC' ]);

			$placement = clone $last_placement;
			$placement->setRound( $placement->getRound() + 1 );
			$placement->setRunning( true );
			$placement->setCompleted( false );

			$this->getDoctrine()->getManager()->persist( $placement );
			$this->getDoctrine()->getManager()->flush();
		}

		$alreadyAcceptedDeclinedOffered = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Offered' )->findBy( array( 'openEnrollment' => $openEnrollment , 'accepted' => 0 , 'declined' => 0 ) );

		$form = $this->createFormBuilder();

		//Settings Tab
		$form->add( 'waitListExpireTime' , 'datetime' , array(
			'label' => 'When should the Wait List Expire?' ,
			'data' => ( $placement->getWaitListExpireTime() == null ) ? new \DateTime( 'midnight +4 months' ) : $placement->getWaitListExpireTime()
		) );

		$form->add( 'saveSettings' , 'submit' , array(
			'label' => 'Save Settings' ,
			'attr' => array( 'class' => 'btn btn-primary' )
		) );


		//All Processing Tab
		$populationCollection = array();
		$schoolsListForIndividualProcessing = array();
		$lastWaitListProcessing = array();

		try {
			$lastWaitListGroup = $this->container->get( 'magnet.lottery' )->getLatestWaitListProcessingDate( $openEnrollment );
		} catch( \Exception $e ) {
			$lastWaitListGroup = null;
		}

		$programs = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Program' )->findBy( array( 'openEnrollment' => $openEnrollment ) , array( 'name' => 'ASC' ) );

		foreach( $programs as $program ) {

			$schools = $program->getMagnetSchools();
			foreach( $schools as $school ) {

				$currentPopulation = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:CurrentPopulation' )->findOneBy( array(
					'openEnrollment' => $openEnrollment ,
					'magnetSchool' => $school
				) );

				$afterPopulation = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
					'magnetSchool' => $school ,
					'openEnrollment' => $openEnrollment ,
				) , array( 'lastUpdatedDateTime' => 'DESC' ) );

				if( $lastWaitListGroup != null ) {
					$waitListProcessing = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:WaitListProcessing' )->findOneBy( array(
						'magnetSchool' => $school ,
						'openEnrollment' => $openEnrollment ,
					) , array( 'addedDateTimeGroup' => 'DESC' ) );

					//Add it to the Last Wait List Processing if not Null.
					if( $waitListProcessing != null ) {
						$lastWaitListProcessing[$school->getId()] = $waitListProcessing;
						$waitListProcessing = null;
					}
				}

				$waitListTotal = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:WaitList' )->createQueryBuilder('wt')
					->where('wt.choiceSchool = :school')
					->setParameter('school', $school)
					->andWhere('wt.openEnrollment = :openEnrollment')
					->setParameter('openEnrollment', $openEnrollment)
					->select('COUNT(wt.submission)')
					->getQuery()
					->getSingleScalarResult();

				//Found an AfterPopulation. This means the lottery ran already on this MagnetSchool.
				//Use the Updated Counts.
				if( $afterPopulation != null ) {
					$currentPopulation = $afterPopulation;
				}

				//Creating a empty CurrentPopulation to allow the page to load for new Open Enrollment
				if( $currentPopulation == null ) {
					$currentPopulation = new CurrentPopulation();
					$currentPopulation->setMagnetSchool( $school );
					$currentPopulation->setOpenEnrollment( $openEnrollment );
				}



				$availableSlots = ( $currentPopulation->getMaxCapacity() - $currentPopulation->getCPSum() );
				$slotsToAward = ( $availableSlots >= 0) ? $availableSlots : 0;

				//Used to build the Individual Processing Drop Down.
				$schoolsListForIndividualProcessing[$currentPopulation->getMagnetSchool()->getId()] = array(
					'magnetSchool' => $currentPopulation->getMagnetSchool(),
					'individual' => 0 ,
					'CPBlack' => 0,
					'CPOther' => 0,
					'CPWhite' => 0,
					'waitListTotal' => $waitListTotal,
					'availableSlots' => $availableSlots,
					'slotsToAward' => $slotsToAward,
				);

				$populationCollection[$currentPopulation->getMagnetSchool()->getId()] = array(
					'magnetSchool' => $currentPopulation->getMagnetSchool(),
					'maxCapacity' => $currentPopulation->getMaxCapacity(),
					'waitListTotal' => $waitListTotal,
					'availableSlots' => $availableSlots,
					'slotsToAward' => $slotsToAward,
				);

				$currentPopulation = null;
				$waitListTotal = 0;
				$availableSlots = 0;
				$slotsToAward = 0;
			}
		}

		$form->add( 'populationToAward' , 'collection' , array(
			'entry_type' => WaitListProcessingType::class ,
			'data' => $populationCollection ,
			'required' => false ,
		) );

		$form->add( 'mailDateSettingAll' , 'date' , array(
			'label' => 'Mail Date Setting for Awarded Letters' ,
			'data' => $placement->getAwardedMailedDate() ,
		) );

		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'populationOnlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => ( $placement->getOnlineEndTime() == null ) ? new \DateTime('midnight +11 days') : $placement->getOnlineEndTime()
			) );
		} else {
			$form->add( 'populationOnlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => $placement->getOnlineEndTime() ,
				'attr' => ['readonly'=>'readonly'],
			) );
		}
		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'populationOfflineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => ( $placement->getOfflineEndTime() == null ) ? new \DateTime( '16:00 +10 days') : $placement->getOfflineEndTime()
			) );
		} else {
			$form->add( 'populationOfflineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => $placement->getOfflineEndTime() ,
				'attr' => ['readonly'=>'readonly'],
			) );
		}

		//if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'processWaitList2' , 'submit' , array(
				'label' => 'Process All (Now)' ,
				'attr' => array( 'class' => 'btn btn-danger' )
			) );
		//}

		//Individual Tab
		$form->add( 'individualPopulation', 'collection' , array(
			'entry_type' => WaitListIndividualProcessingType::class ,
			'data' => $schoolsListForIndividualProcessing ,
			'required' => false ,
		) );

		$form->add( 'mailDateSettingIndividual' , 'date' , array(
			'label' => 'Mail Date Setting for Awarded Letters' ,
			'data' => $placement->getAwardedMailedDate() ,
		) );

		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'individualOnlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => ( $placement->getOnlineEndTime() == null ) ? new \DateTime('midnight +11 days') : $placement->getOnlineEndTime()
			) );
		} else {
			$form->add( 'individualOnlineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept ONLINE' ,
				'data' => $placement->getOnlineEndTime() ,
				'attr' => ['readonly'=>'readonly'],
			) );
		}
		if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'individualOfflineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => ( $placement->getOfflineEndTime() == null ) ? new \DateTime( '16:00 +10 days') : $placement->getOfflineEndTime()
			) );
		} else {
			$form->add( 'individualOfflineEndTime' , 'datetime' , array(
				'label' => 'Last day and time to accept OFFLINE' ,
				'data' => $placement->getOfflineEndTime() ,
				'attr' => ['readonly'=>'readonly'],
			) );
		}

		//if( count( $alreadyAcceptedDeclinedOffered ) == 0 ) {
			$form->add( 'processWaitListIndividual' , 'submit' , array(
				'label' => 'Process Individual (Now)' ,
				'attr' => array( 'class' => 'btn btn-danger' )
			) );
		//}


		//Letters Tab
		$rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/awarded/' . $openEnrollment->getId() . '/';
		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}
		$lastGeneratedFiles = array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) );

		$rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/awarded-wait-list/' . $openEnrollment->getId() . '/';
		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}
		$lastGeneratedFiles = array_merge( $lastGeneratedFiles, array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) ) );

		$rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/denied/' . $openEnrollment->getId() . '/';
		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}
		$lastGeneratedFiles = array_merge( $lastGeneratedFiles, array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) ) );

		$rootDIR = $this->container->get( 'kernel' )->getRootDir() . '/../web/reports/wait-list/' . $openEnrollment->getId() . '/';
		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}
		$lastGeneratedFiles = array_merge( $lastGeneratedFiles, array_diff( scandir( $rootDIR ) , array( '..' , '.' , '.DS_Store' ) ) );

		rsort( $lastGeneratedFiles );
		$lastGeneratedFiles = array_slice( $lastGeneratedFiles , 0 , 5 );


		//Email Tab
		$form->add( 'sendEmailsNow' , 'submit' , array(
			'label' => 'Send Emails Now' ,
			'attr' => array( 'class' => 'btn btn-danger' ) ,
		) );


		$form = $form->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();

			if( $form->get( 'saveSettings' )->isClicked() ) {

				$placement->setWaitListExpireTime( $data['waitListExpireTime'] );
				$this->getDoctrine()->getManager()->persist( $placement );
				$this->getDoctrine()->getManager()->flush();
				return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process_late_placement' ) );
			}

			if( $form->get('sendEmailsNow')->isClicked() ) {

				$process = new Process();
				$process->setEvent( 'email' );
				$process->setType( 'awarded' );
				$process->setOpenEnrollment( $openEnrollment );
				$this->getDoctrine()->getManager()->persist( $process );

				$process = new Process();
				$process->setEvent( 'email' );
				$process->setType( 'wait-list' );
				$process->setOpenEnrollment( $openEnrollment );
				$this->getDoctrine()->getManager()->persist( $process );

				$process = new Process();
				$process->setEvent( 'email' );
				$process->setType( 'denied' );
				$process->setOpenEnrollment( $openEnrollment );
				$this->getDoctrine()->getManager()->persist( $process );

				$this->getDoctrine()->getManager()->flush();
				return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process_late_placement' ) );
			}

			$processDateTime = new \DateTime();

			//Is the Process All button Clicked???
			if( $form->get( 'processWaitList2' )->isClicked() ) {

				$placement->setAwardedMailedDate( $data['mailDateSettingAll'] );
				$placement->setOnlineEndTime( $data['populationOnlineEndTime'] );
				$placement->setOfflineEndTime( $data['populationOfflineEndTime'] );

				$clear_outcomes = $this->getDoctrine()->getManager()->getConnection()->prepare("DELETE FROM `LotteryOutcomeSubmission` WHERE `type`='withdrawal'");
				$clear_outcomes->execute();

				foreach( $data['populationToAward'] as $population ) {
					$outcomePreProcessing = new LotteryOutcomePopulation();
					$outcomePreProcessing->setType( 'withdrawal' );
					$outcomePreProcessing->setMaxCapacity( $population['slotsToAward'] );
					$outcomePreProcessing->setMagnetSchool( $population['magnetSchool'] );
					$outcomePreProcessing->setOpenEnrollment( $openEnrollment );
					$outcomePreProcessing->setPlacement( $placement );

					$this->getDoctrine()->getManager()->persist( $outcomePreProcessing );
				}

				//Create a new Process for the scheduler to pick and work.
				$process = new Process();
				$process->setOpenEnrollment($openEnrollment);
				$process->setEvent('lottery');
				$process->setType('late-period');
				$this->getDoctrine()->getManager()->persist( $process );

				//Commit everything to the DB.
				$this->getDoctrine()->getManager()->flush();
				return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process_late_placement' ) );
			}

			//Is the Individual Processing Button Clicked??/
			if( $form->get( 'processWaitListIndividual' )->isClicked() ) {

				$placement->setAwardedMailedDate( $data['mailDateSettingIndividual'] );
				$placement->setOnlineEndTime( $data['individualOnlineEndTime'] );
				$placement->setOfflineEndTime( $data['individualOfflineEndTime'] );

				$clear_outcomes = $this->getDoctrine()->getManager()->getConnection()->prepare("DELETE FROM `LotteryOutcomePopulation` WHERE `type`='withdrawal'");
				$clear_outcomes->execute();
				$this->getDoctrine()->getManager()->flush();

				//Loop over all the individual MagnetSchools and see if any of them have bee activated to 1.
				foreach( $data['individualPopulation'] as $individual ) {

					//Only add schools that have been selected to be individually processed.
					//If activated (in other words, shown on the screen), create a WaitListProcessing
					if( $individual['individual'] == 1 ) {

						$outcomePreProcessing = new LotteryOutcomePopulation();
						$outcomePreProcessing->setType( 'withdrawal' );
						$outcomePreProcessing->setOpenEnrollment( $openEnrollment );
						$outcomePreProcessing->setPlacement( $placement );

						//If the slots are filling slots where students have LEFT the program. We need to make sure the Racial Composition is UPDATED.
						if( $individual['fillingSlots'] == 1 ) {
							$outcomePreProcessing->setCPBlack( $individual['CPBlack'] );
							$outcomePreProcessing->setCPWhite( $individual['CPWhite'] );
							$outcomePreProcessing->setCPOther( $individual['CPOther'] );
						}

						//Update the Available Slots.
						$outcomePreProcessing->setMaxCapacity( $individual['slotsToAward'] );
						$outcomePreProcessing->setMagnetSchool( $individual['magnetSchool'] );

						$this->getDoctrine()->getManager()->persist( $outcomePreProcessing );
					}
				}

				//Create a new Process for the scheduler to pick and work.
				$process = new Process();
				$process->setOpenEnrollment($openEnrollment);
				$process->setEvent('lottery');
				$process->setType('late-period');
				$this->getDoctrine()->getManager()->persist( $process );

				//Commit everything to the DB.
				$this->getDoctrine()->getManager()->flush();
				return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process_late_placement' ) );

			}

			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_process_late_placement' ) );
		}

		return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'files' => $lastGeneratedFiles , 'lastWaitListProcessing' => $lastWaitListProcessing );
	}


	/**
	 * Current Settings
	 *
	 * @Route("current-settings/", name="iiab_magnet_program_processing_current_settings")
	 */
	public function currentSettingsAction() {

		die( 'current settings' );
	}

	/**
	 * Current Settings
	 *
	 * @Route("change-active/{id}", name="iiab_magnet_program_processing_current_settings")
	 */
	public function changeActiveOpenEnrollmentSettingsAction( $id = 0 ) {

		if( $id == 0 ) {
			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_view_all' ) );
		}
		$openEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->find( $id );

		if( $openEnrollment == null ) {
			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_view_all' ) );
		}

		$openEnrollment->setActive( 1 );
		$activeOpenEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneByActive( 1 );
		if( $activeOpenEnrollment != null ) {
			$activeOpenEnrollment->setActive( 0 );
		}

		$this->getDoctrine()->getManager()->flush();

		return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_view_all' ) );
	}

	/**
	 * View all
	 *
	 * @Route("view-all/", name="iiab_magnet_program_processing_view_all")
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/viewAll.html.twig")
	 */
	public function viewAllAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$openEnrollments = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findAll();

		return ['admin_pool' => $admin_pool , 'openEnrollments' => $openEnrollments ];
	}

	/**
	 * @Template("@IIABMagnet/Admin/ProgramProcessing/status.html.twig")
	 *
	 * @return array
	 */
	public function processingStatusAction() {

		$processes = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Process' )->findlastFiveMinuteProcess();

		return array( 'processes' => $processes );
	}

	/**
	 * @Route("ajax/process/update.json", name="iiab_magnet_program_process_updater")
	 *
	 * @return array
	 */
	public function processStatusAction() {

		$id = $this->get('request_stack')->getCurrentRequest()->get('id' , 0 );
		if( $id == 0 || empty( $id ) ) {
			return array();
		}

		$process = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Process' )->find( $id );

		if( $process == null ) {
			return array();
		}

		$message = '<span aria-hidden="true" class="glyphicon glyphicon-ok"></span> <strong>' . ucwords( $process->getEvent() ) . ' ' . ucwords( $process->getType() ) . '</strong>';
		if( $process->getRunning() == 1 ) {
			$message .= ' is currently running. Please wait.';
		} elseif( $process->getCompleted() == 1 ) {

			$completed_date =$process->getCompletedDateTime();
			if( empty( $completed_date ) ){
				$message .= ' this task has completed '. sprintf( '. <a style="text-decoration: underline;" onclick="location.reload(); return false;" href="#">%s</a>.' , 'Click here to reload this window' );
			} else {
				$message .= ' this task has completed at ' . $completed_date->format( 'm/d/y h:ia' ) . sprintf( '. <a style="text-decoration: underline;" onclick="location.reload(); return false;" href="#">%s</a>.' , 'Click here to reload this window' );
			}
		} else {
			$message .= ' is currently running. Please wait..';
		}

		$responseProcess = array(
			'completed' => $process->getCompleted() ,
			'message' => $message ,
		);

		return new JsonResponse( $responseProcess );
	}

	/**
	 * Gets the active Open Enrollment.
	 * @return \IIAB\MagnetBundle\Entity\OpenEnrollment|null
	 */
	private function getActiveOpenEnrollment() {

		$openEnrollment = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findOneBy( array(
			'active' => '1' ,
		) );

		if( $openEnrollment == null ) {
			return $this->redirect( $this->generateUrl( 'iiab_magnet_program_processing_view_all' ) );
		} else {
			return $openEnrollment;
		}
	}
}