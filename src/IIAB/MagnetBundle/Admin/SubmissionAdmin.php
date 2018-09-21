<?php

namespace IIAB\MagnetBundle\Admin;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use IIAB\MagnetBundle\Entity\AfterPlacementPopulation;
use IIAB\MagnetBundle\Entity\Offered;
use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionData;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;
use IIAB\MagnetBundle\Service\CalculateGPAService;
use IIAB\MagnetBundle\Service\StudentProfileService;
use IIAB\MagnetBundle\Admin\Traits\SubmissionAdminChoiceTabTraits;
use IIAB\MagnetBundle\Admin\Traits\SubmissionAdminRecommendationTabTraits;
use IIAB\MagnetBundle\Admin\Traits\SubmissionAdminLearnerScreeningDeviceTabTraits;
use IIAB\MagnetBundle\Admin\Traits\SubmissionAdminGradesTabTraits;
use IIAB\MagnetBundle\Admin\Traits\SubmissionAdminStudentProfileTabTraits;
use IIAB\MagnetBundle\Admin\Traits\SubmissionAdminAuditionTabTraits;

class SubmissionAdmin extends AbstractAdmin {
    use SubmissionAdminChoiceTabTraits;
    use SubmissionAdminRecommendationTabTraits;
    use SubmissionAdminLearnerScreeningDeviceTabTraits;
    use SubmissionAdminGradesTabTraits;
    use SubmissionAdminStudentProfileTabTraits;
    use SubmissionAdminAuditionTabTraits;

    //Defined in SubmissionAdminChoiceTabsTraits
    private $choice_tabs = [];

    private $user;
    private $user_schools;

	/**
	 * @var string
	 */
	protected $baseRouteName = 'admin_submission';

	/**
	 * Default Datagrid values
	 *
	 * @var array
	 */
	protected $datagridValues = array(
		'_page' => 1 ,            // display the first page (default = 1)
		'_sort_order' => 'DESC' , // reverse order (default = 'ASC')
		'_sort_by' => 'createdAt'  // name of the ordered field
		// (default = the model's id field, if any)

		// the '_sort_by' key can be of the form 'mySubModel.mySubSubModel.myField'.
	);

	/**
	 * @var string
	 */
	protected $baseRoutePattern = 'submission';

	/**
	 * @inheritdoc
	 */
	protected function configureFormFields( FormMapper $form )
    {

        ini_set('memory_limit','2048M');
        global $object;

        $grades = array('98' => 'None', '99' => 'PreK', '0' => 'K');
        foreach (range(1, 12, 1) as $grade) {
            $grades[sprintf('%1$01d', $grade)] = sprintf('%1$02d', $grade);
        }

        $object = $this->getSubject();
        $this->user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
        $this->user_schools = $this->user->getSchools();

        $additional_data = $object->getAdditionalData(true);
        $submission_data = [];
        foreach( $additional_data as $datum ){
            $submission_data[ $datum->getMetaKey() ] = $datum->getMetaValue();
        }

        $securityContext = $this->getConfigurationPool()->getContainer()->get('security.authorization_checker');

        if( $securityContext->isGranted('ROLE_SUPER_ADMIN') ) {
            $available_user_assigned_status_list = [
                1, //'active'
                2, //'denied due to space'
                3, //'denied'
                5, //'on hold for additional information'
                6, //'offered'
                7, //'offered and accepted'
                8, //'offered and declined'
                9, //'wait listed'
                10, //'inactive'
                11, //'application withdrawn'
                12, //'inactive due to ineligibility'
                13, //'declined wait list'
                14, //'inactive due to transcript'
            ];
        } else {

            $object->getSubmissionStatus()->getId();

            $available_user_assigned_status_list = [
                1, //'active'
                5, //'on hold for additional information'
                11, //'application withdrawn'
                14, //'inactive due to transcript'
            ];

            //if current status is 'offered and accepted'
            if( $object->getSubmissionStatus()->getId() == 7 ){
                $available_user_assigned_status_list[] = 12; //'inactive due to ineligibility'
                $available_user_assigned_status_list[] = 8; //'offered and declined'
            }

        }

        $placements = $object->getOpenEnrollment()->getPlacement();

        foreach( $placements as $placement ){
            if( $placement->getCompleted() ){
                if(($key = array_search(1, $available_user_assigned_status_list)) !== false) {
                    unset($available_user_assigned_status_list[$key]);
                }
            }
        }

        $form
            ->tab('Submission')
            ->with('Student Information', array('class' => 'col-md-6'))->end()
            ->with('Submission Information', array('class' => 'col-md-6'))->end()
            ->with('Choices', array('class' => 'col-md-6'))
            ->end()
            ->with('Submission Status', array('class' => 'col-md-12'))->end()
            ->end();

        //Defined in SubmissionAdminChoiceTabsTraits
        $this->addChoiceTabs( $form, $object );

        //Defined in SubmissionAdminRecommendationTabsTraits
        $this->addRecommendationTabs( $form, $object );

        //Defined in SubmissionAdminLearnerScreeningDeviceTabsTraits
        $this->addLearnerScreeningDeviceTabs( $form, $object );

        //Defined in SubmissionAdminGradesTabsTraits
        $this->addGradesTabs( $form, $object );

        //Defined in SubmissionAdminStudentProfileTabsTraits
        $this->addStudentProfileTabs( $form, $object );

        //Defined in SubmissionAdminAuditionTabsTraits
        $this->addAuditionTabs( $form, $object );


        $form
            ->tab('Comments')
                ->with('Comments', array('class' => 'col-md-12'))->end()
            ->end();

        $resend_button = ( !in_array( $object->getSubmissionStatus()->getId(), [1,5,10,11,12,14] ) )
            ?
            '<button '.
            'title="Resend Outcome"'.
            'type="button" class="btn btn-info resend-email" data-email-type="submission-status" data-submission-id="'.$object->getId().'">'.
            '<i class="fa fa-paper-plane"></i> Resend '. $object->getSubmissionStatus() .'<span></span></button>'
            :
            '<button '.
            'title="Resend EmailConfirmation "'.
            'type="button" class="btn btn-info resend-email" data-email-type="confirmation" data-submission-id="'.$object->getId().'">'.
            '<i class="fa fa-paper-plane"></i> Resend Confirmation<span></span></button>';


        $form
            ->tab('Submission')
            ->with('Student Information')
            ->add('stateID', null, array('label' => 'State ID'))
            ->add('firstName')
            ->add('lastName')
            ->add('birthday', 'birthday', array(
                'format' => IntlDateFormatter::LONG,
                'view_timezone' => 'UTC',
                'model_timezone' => 'UTC'
            ))
            ->add('race')
            ->add('gender')
            ->add('address')
            ->add('city')
            ->add('state', null, array('sonata_help' => 'Format: AL, TN. Max 2 Characters.'))
            ->add('zip')
            ->add('phoneNumber', null, array('required' => false, 'sonata_help' => 'Format: 2565551234. Max 10 Characters.'))
            ->add('alternateNumber', null, array('required' => false, 'sonata_help' => 'Format: 2565551234. Max 10 Characters.'))
            ->add('currentSchool')
            ->add('currentGrade', 'choice', array(
                'choices' => array_flip( $grades ),
                'data' => $object->getCurrentGrade()
            ))
            ->add('nextGrade', 'choice', array(
                'choices' => array_flip( $grades ),
                'data' => $object->getNextGrade()
            ))
            ->add('parentEmail', null, array(
                'label' => 'Parent\'s Email',
                'sonata_help' => 'Changes to this field will only affect new messages that go out.',
                'help' =>  $resend_button,
            ))
            ->add('parentFirstName', null, array('label' => 'Parent\'s First Name',))
            ->add('parentLastName', null, array('label' => 'Parent\'s Last Name',))

            ->add('parent_employment', 'choice', [
                'choices' => array_flip( [
                    0 => 'No',
                    1 => 'Yes',
                ]),
                'data' => $object->getParentEmployment(),
                'placeholder' => 'Choose an Option',
                'required' => false,
                'mapped' => false,
            ])
            ->add('parent_employee_name', 'text', array(
                'label' => 'Parent Employee Name',
                'mapped' => false,
                'required' => false,
                'data' => ( isset($submission_data['parent_employee_name'])) ? $submission_data['parent_employee_name'] : null,
            ))
            ->add('parent_employee_location', 'text', array(
                'label' => 'Parent Employee Location',
                'mapped' => false,
                'required' => false,
                'data' => ( isset($submission_data['parent_employee_location'])) ? $submission_data['parent_parent_employmentee_location'] : null,
            ))
            // ->add('emergencyContact', null, array('label' => 'Emergency Contact',))
            // ->add('emergencyContactRelationship', null, array('label' => 'Emergency Contact Relationship',))
            // ->add('emergencyContactPhone', null, array('label' => 'Emergency Contact Phone',))
            ->end()
            ->with('Submission Information')

            ->add('openEnrollment')
//            ->add('lateSubmission', 'text', array(
//                'mapped' => false,
//                'disabled' => true,
//                'required' => false,
//                'data' => ($object->getOpenEnrollment()->getEndingDate()->modify('+1 day') < $object->getCreatedAt()) ? 'Late Submission' : 'Standard Submission',
//            ))
            ->add('zonedSchool', 'text', array(
                'label' => 'Zoned School',
                'disabled' => true,
                'required' => false
            ))
//            ->add('specialAccommodations', 'choice', array(
//                'label' => 'Special Accommodations',
//                'placeholder' => 'Choose an Option',
//                'choices' => array(
//                    1 => 'Yes',
//                    0 => 'No'
//                ),
//                'empty_data' => 0,
//                'required' => false
//            ))

            ->add('additionalData', 'sonata_type_collection', array(
                'btn_add' => false,
                'type_options' => array(
                    'delete' => false,
                    )
                ), array(
                'edit' => 'inline',
                'inline' => 'table',
            ));

        if( $object->getNextGrade() > 1 ) {
            $calculated_gpa = $object->getAdditionalDataByKey('calculated_gpa');
            $calculated_gpa = (empty($calculated_gpa)) ? null : $calculated_gpa->getMetaValue();
            $form->add('calculated_gpa', 'number', array(
                'label' => 'GPA',
                'scale' => 2,
                'required' => false,
                'mapped' => false,
                'data' => $calculated_gpa,
                'attr' => ['min' => 0, 'max' => 4, 'readonly' => 'readonly'],
            ));
        }

        if( $object->doesRequire( 'conduct_gpa') ) {
            $conduct_gpa = $object->getAdditionalDataByKey('conduct_gpa');
            $conduct_gpa = (empty($conduct_gpa)) ? null : $conduct_gpa->getMetaValue();
            $form->add('conduct_gpa', 'text', array(
                'label' => 'Conduct GPA',
                //'scale' => 1,
                'required' => false,
                'mapped' => false,
                'data' => $conduct_gpa,
                //'attr' => ['min' => 0, 'max' => 4]
            ));
        }

        if( false && $object->doesRequire( 'conduct_eligible') ) {
            $conduct_eligible = $object->getAdditionalDataByKey('conduct_eligible');
            $conduct_eligible = (empty($conduct_eligible)) ? null : $conduct_eligible->getMetaValue();
            $form->add('conduct_eligible', 'choice', array(
                'label' => 'Conduct',
                'placeholder' => 'Choose an Option',
                'choices' => array_flip( array(
                    1 => 'Eligible',
                    0 => 'Ineligible'
                )),
                'required' => false,
                'mapped' => false,
                'data' => $conduct_eligible,
            ));
        }

        if( $object->doesRequire( 'holistic_committee') ) {
            $holistic_committee = $object->getAdditionalDataByKey('holistic_committee');
            $holistic_committee = (empty($holistic_committee)) ? null : $holistic_committee->getMetaValue();
            $form->add('holistic_committee', 'choice', array(
                'label' => 'Holistic Committee Recommendation',
                'required' => false,
                'mapped' => false,
                'data' => $holistic_committee,
                'choices' => [
                            'Recommend' => 1,
                            'Do Not Recommend' => 0,
                        ],
            ));
        }

        if( $object->doesRequire( 'orientation' ) ) {
            $orientation = $object->getAdditionalDataByKey('orientation');
            $orientation = (empty($orientation)) ? null : $orientation->getMetaValue();
            $form->add('orientation', 'choice', array(
                'label' => 'Orientation',
                'placeholder' => 'Choose an Option',
                'choices' => array_flip( array(
                    1 => 'Completed',
                    0 => 'Not Completed'
                )),
                'required' => false,
                'mapped' => false,
                'data' => $orientation,
            ));
        }

        $eligibility_numeric_fields = [
            'reading_test' => 'Reading Standard Testing Percentile',
            'math_test' => 'Math Standard Testing Percentile',
        ];

        foreach( $eligibility_numeric_fields as $key => $label ){
            if( $object->doesRequire( $key ) ) {
                $data = $object->getAdditionalDataByKey($key);
                $data = (empty($data)) ? null : $data->getMetaValue();

                $form->add($key, 'number', array(
                    'label' => $label,
                    'required' => false,
                    'mapped' => false,
                    'data' => $data,
                    //'attr' => ['readonly' => 'readonly'],
                ));
            }
        }

        $recommendation_fields = [
            'recommendation_english' => 'English Teacher Recommendation',
            'recommendation_math' => 'Math Teacher Recommendation',
            'recommendation_counselor' => 'School Counselor/IB Coordinator Recommendation',
        ];
        foreach( $recommendation_fields as $key => $label ){
            if( $object->doesRequire( $key ) ) {
                $data = $object->getAdditionalDataByKey($key);
                $data = (empty($data)) ? null : $data->getMetaValue();
                $form->add($key, 'choice', array(
                    'label' => $label,
                    'placeholder' => '',
                    'choices' => array_flip( array(
                        0 => 'Not Ready',
                        1 => 'Ready',
                        2 => 'Exceptional',
                    )),
                    'required' => false,
                    'mapped' => false,
                    'data' => $data,
                    'attr' => ['readonly' => 'readonly'],
                    'disabled' => true,
                ));
            }
        }

        $form->end();

        $this->addChoiceList( $form, $object );

        $form->with( 'Submission Status' );

		$status = $object->getSubmissionStatus()->getId();
        if ( !in_array( $status, $available_user_assigned_status_list ) ) {
            $available_user_assigned_status_list[] = $status;
        }

		/*
		 * If status in one of the following:
		 *
		 *  1: active
		 *  5: on hold for additional information
		 * 10: inactive
		 * 14: inactive due to no transcript
		 *
		 */
		if( in_array( $status , array( 1 , 5 , 10 , 14 ) ) ) {

			//If Status is Active
			if( $status == 1 ) {

				/*
				 * Allow Admins to change to the following statuses:
				 *
				 *  1: active
				 *  3: denied
				 *  5: on hold for additional information
				 *  6: offered
				 * 10: inactive
				 * 11: application withdrawn
				 * 12: inactive due to ineligibility
				 * 14: inactive due to no transcript
				 */
                $switch_status = [1,3,5,6,10,11,12,14];
                $switch_status = implode(',', array_intersect( $switch_status, $available_user_assigned_status_list ) );

				$form->add( 'submissionStatus' , null , array(
					'required' => true ,
					'query_builder' => function ( EntityRepository $er ) use( $switch_status) {
						return $er->createQueryBuilder( 's' )->where( 's.id IN ('.$switch_status.')' )->orderBy('s.status' , 'ASC');
					}
				) );
				$schoolChoices = [];
				if( !empty( $firstChoice ) ) {
					$schoolChoices[$firstChoice->getId()] = $firstChoice->__toString();
				}
				if( !empty( $secondChoice ) ) {
					$schoolChoices[$secondChoice->getId()] = $secondChoice->__toString();
				}
				if( !empty( $thirdChoice ) ) {
					$schoolChoices[$thirdChoice->getId()] = $thirdChoice->__toString();
				}
				$form->add( 'offeredCreation' , 'choice' , array(
					'label' => 'Manual Offer Submission' ,
					'required' => false ,
					'placeholder' => 'Select a school to Offer' ,
					'choices' => array_flip( $schoolChoices )
				) );
				$form->add( 'offeredCreationEndOnlineTime' , 'datetime' , array(
					'label' => 'Last Date to Accept Online' ,
					'format' => IntlDateFormatter::LONG ,
					'data' => new \DateTime( 'midnight +9 days' ) ,
					'attr' => array( 'style' => 'margin-bottom: 10px;' )
				) );
				$form->add( 'offeredCreationEndOfflineTime' , 'datetime' , array(
					'label' => 'Last Date to Accept Offline' ,
					'data' => new \DateTime( '16:00 +8 days' ) ,
					'format' => IntlDateFormatter::LONG ,
					'attr' => array( 'style' => 'margin-bottom: 10px;' )

				) );
			} else { //Status is either 5, 10, 14.
				/*
				 * Allow Admins to change to the following statuses:
				 *
				 *  1: active
				 *  3: denied
				 *  5: on hold for additional information
				 * 10: inactive
				 * 11: application withdrawn
				 * 12: inactive due to ineligibility
				 * 14: inactive due to no transcript
				 */
                $switch_status = [1,3,5,10,11,12,14];
                $switch_status = implode(',', array_intersect( $switch_status, $available_user_assigned_status_list ) );

				$form->add( 'submissionStatus' , null , array(
					'required' => true ,
					'query_builder' => function ( EntityRepository $er ) use( $switch_status ) {
						return $er->createQueryBuilder( 's' )->where( 's.id IN ('. $switch_status .')' )->orderBy('s.status' , 'ASC');
					}
				) );
			}
		} else {
			//If Status is "Offered and Accepted" OR "Offered and Declined"
			if( in_array( $status , array( 7 , 8 ) ) ) {

				/*
				 * Allow Admins to change to the following statuses:
				 *
				 *  7: offered and accepted
				 *  8: offered and declined
				 * 11: application withdrawn
				 * 12: inactive due to ineligibility
				 */
                $switch_status = [7,8,11,12];
                $switch_status = implode(',', array_intersect( $switch_status, $available_user_assigned_status_list ) );

				$form->add( 'submissionStatus' , null , array(
					'required' => true ,
					'query_builder' => function( EntityRepository $er ) use( $switch_status) {
						return $er->createQueryBuilder('s')->where('s.id IN ('.$switch_status.')')->orderBy('s.status' , 'ASC');
					}
				) );
			} elseif( $status == 9 ) { //If status is wait listed.

				/*
				 * Allow Admins to change to the following statuses:
				 *
				 *  6: offered
				 *  9: wait listed
				 * 11: application withdrawn
				 * 12: inactive due to ineligibility
				 * 13: declined wait list
				 */
                $switch_status = [6,9,11,12,13];
                $switch_status = implode(',', array_intersect( $switch_status, $available_user_assigned_status_list ) );

				$form->add( 'submissionStatus' , null , array(
					'required' => true ,
					'query_builder' => function( EntityRepository $er ) use( $switch_status ) {
						return $er->createQueryBuilder('s')->where('s.id IN ('.$switch_status.')')->orderBy('s.status' , 'ASC');
					}
				) );
				$choices = array();
				foreach( $object->getWaitList() as $waitList ) {
					$choices[ $waitList->getChoiceSchool()->getId() ] = $waitList->getChoiceSchool()->getName();
				}

				$form->add( 'offeredCreation' , 'choice' , array(
					'label' => 'Offered' ,
					'required' => false ,
					'placeholder' => 'Select a school to Offer' ,
					'choices' => array_flip( $choices ),
				) );
				$form->add( 'offeredCreationEndOnlineTime' , 'datetime' , array(
					'label' => 'Last Date to Accept Online' ,
					'format' => IntlDateFormatter::LONG ,
					'data' => new \DateTime( 'midnight +9 days') ,
					'attr' => array( 'style' => 'margin-bottom: 10px;' )
				) );
				$form->add( 'offeredCreationEndOfflineTime' , 'datetime' , array(
					'label' => 'Last Date to Accept Offline' ,
					'data' => new \DateTime( '16:00 +8 days') ,
					'format' => IntlDateFormatter::LONG ,
					'attr' => array( 'style' => 'margin-bottom: 10px;' )

				) );
			} elseif( $status == 6 ) { //If status is "offered.

				/*
				 * Allow Admins to change to the following statuses:
				 *
				 *  6: offered
				 * 12: inactive due to ineligibility
				 */
                $switch_status = [6,12];
                $switch_status = implode(',', array_intersect( $switch_status, $available_user_assigned_status_list ) );

				$form->add( 'submissionStatus' , null , array(
					'required' => true ,
					'query_builder' => function( EntityRepository $er ) use($switch_status){
						return $er->createQueryBuilder('s')->where('s.id IN ('.$switch_status.')')->orderBy('s.status' , 'ASC');
					}
				) );
			} else {
				//Any other status, it is now allowed to be changed.
				$form->add( 'submissionStatus' , null , array(
					'disabled' => true ,
				) );
			}
			//If the status is "Offered", "Offered and Accepted" or "Offered and Declined"
			if( in_array( $status , array( 6 , 7 , 8 ) ) ) {
				//Not allowed to change the "offered entity.
				$form->add( 'offered' , null , array(
					'label' => $object->getSubmissionStatus()->__toString(),
					'disabled' => true ,
				) );
			}
		}
		$form->end()->end();

		if( $this->getSubject()->getNonHSVStudent() ) {
			//New Student, allow them to enter in grades.
		}

        $this->addChoiceTabForms( $form, $object );

        $this->addRecommendationTabForms( $form, $object );

        $this->addLearnerScreeningDeviceTabForms( $form, $object );

        $this->addGradesTabForms( $form, $object );

        $this->addStudentProfileTabForms( $form, $object );

        $this->addAuditionTabForms( $form, $object );

		$form->tab( 'Comments' )
			->with( 'Comments' )
			->add( 'userComments' , 'sonata_type_collection' , array(
				'required' => false ,
				'type_options' => array(
					'delete' => false ,
				)
			) , array(
				'edit' => 'inline' ,
				'inline' => 'table'
			) )
			->end()
			->end();

		$firstChoice = null;
		$secondChoice = null;
		$thirdChoice = null;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configureShowFields( ShowMapper $show ) {

		$object = $this->getSubject();
		$this->user = $this->getConfigurationPool()->getContainer()->get( 'security.token_storage' )->getToken()->getUser();
		$schools = $this->user->getSchools();

		$show
			->add( 'id' , null , [ 'label' => 'Submission ID' ] )
			->add( 'stateID' , null , array( 'label' => 'State ID' ) )
			->add( 'firstName' )
			->add( 'lastName' )
			->add( 'birthday' )
			->add( 'race' )
			->add( 'address' )
			->add( 'city' )
			->add( 'state' )
			->add( 'zip' )
			->add( 'phoneNumber' )
			->add( 'alternateNumber' )
			->add( 'currentSchool' )
			->add( 'currentGrade' )
			->add( 'nextGrade' )
			->add( 'submissionStatus' )
			->add( 'parentEmail' );

		$firstChoice = $object->getFirstChoice();
		if( ( !empty( $firstChoice ) && $this->user->hasSchool( $firstChoice->getName() ) || empty( $schools ) ) ) {
			$show->add( 'firstChoice' , 'text' );
		}
		$secondChoice = $object->getSecondChoice();
		if( ( !empty( $secondChoice ) && $this->user->hasSchool( $secondChoice->getName() ) || empty( $schools ) ) ) {
			$show->add( 'secondChoice' , 'text' );
		}
		$thirdChoice = $object->getThirdChoice();
		if( ( !empty( $thirdChoice ) && $this->user->hasSchool( $thirdChoice->getName() ) || empty( $schools ) ) ) {
			$show->add( 'thirdChoice' , 'text' );
		}
	}


	/**
	 * @param string $context
	 *
	 * @return \Sonata\AdminBundle\Datagrid\ProxyQueryInterface
	 */
	public function createQuery( $context = 'list' ) {

		$query = parent::createQuery( $context );

		$this->user = $this->getConfigurationPool()->getContainer()->get( 'security.token_storage' )->getToken()->getUser();

		$schools = $this->user->getSchools();

		if( !empty( $schools ) ) {

			$specificSchools = array();
			foreach( $schools as $school ) {
				$foundIDs = $this->getConfigurationPool()->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
					->select( 'm.id' )
					->where( 'm.name LIKE :name' )
					->setParameter( 'name' , $school )
					->distinct( true )
					->getQuery()
					->getResult();
				foreach( $foundIDs as $id ) {
					$specificSchools[] = $id['id'];
				}
			}

			$query->orWhere(
				$query->expr()->in( $query->getRootAlias() . '.firstChoice' , ':schools' ),
				$query->expr()->in( $query->getRootAlias() . '.secondChoice' , ':schools' ),
				$query->expr()->in( $query->getRootAlias() . '.thirdChoice' , ':schools' )
			);
			$query->setParameter( 'schools' , $specificSchools );
		}

		return $query;
	}

	/**
	 * @inheritdoc
	 */
	protected function configureListFields( ListMapper $list ) {

		$list
			->addIdentifier( 'id' , null , array(
				'label' => 'Submission ID' ,
				//'route' => array( 'name' => 'customEdit' )
			) )
			->addIdentifier( 'stateID' , null , array(
				'label' => 'State ID' ,
				//'route' => array( 'name' => 'customEdit' )
			) )
			->add( 'openEnrollment' , null , array( 'label' => 'Enrollment' ) )
			->add( 'name' )
			->add( 'race' )
			->add( 'birthday' )
			->add( 'currentSchool' , null , array( 'label' => 'School' ) )
			->add( 'currentGradeString' , null , array( 'label' => 'Grade' , 'sortable' => true , 'sort_field_mapping' => array( 'fieldName' => 'currentGrade' ) , 'sort_parent_association_mappings' => array() ) )
			->add( 'nextGradeString' , null , array( 'label' => 'Next Grade' , 'sortable' => true , 'sort_field_mapping' => array( 'fieldName' => 'nextGrade' ) , 'sort_parent_association_mappings' => array() ) )
			->add( 'createdAt' , null , array( 'label' => 'Created At' ) )
			->add( 'submissionStatus.status' , null , array( 'label' => 'Status' ) )
			->add( 'offered' , null , array( 'label' => 'Awarded School' ) )
			->add( 'nonHSVStudentString' , null , array( 'label' => 'TCS/New' , 'sortable' => true , 'sort_field_mapping' => array( 'fieldName' => 'nonHSVStudent' ) , 'sort_parent_association_mappings' => array() ) );
	}

	/**
	 * @inheritdoc
	 */
	protected function configureDatagridFilters( DatagridMapper $filter ) {

		$filter
			->add( 'id' , null , array( 'label' => 'Submission ID' ) )
			->add( 'stateID' , null , array( 'label' => 'State ID' ) )
			->add( 'openEnrollment' , null , array( 'label' => 'Open Enrollment' ) )
			->add( 'firstName' )
			->add( 'lastName' )
			->add( 'race' )
			->add( 'birthday' )
			->add( 'currentSchool' )
			->add( 'currentGrade' , null , array( 'label' => 'Current Grade' ) )
			->add( 'nextGrade' , null , array( 'label' => 'Next Grade' ) )
			->add( 'submissionStatus.status' , null , array( 'label' => 'Status' ) )
			->add( 'offered.awardedSchool' , null , array( 'label' => 'Awarded School' ) )
			->add( 'firstChoice' )
			->add( 'secondChoice' )
			->add( 'thirdChoice' )
			->add( 'nonHSVStudent' , 'doctrine_orm_callback' , array(
				'callback' => array( $this , 'getNonStudentFilter' ) ,
				'field_type' => 'choice' ,
				'label' => 'Student Status'
			) , null , array( 'choices' => array( 'Current' => 'Current' , 'New' => 'New' ) ) );
//			->add( 'specialAccommodations' , 'doctrine_orm_callback' , array(
//				'callback' => array( $this , 'getSpecialAccommodationsFilter' ) ,
//				'field_type' => 'choice' ,
//				'label' => 'Special Accommodations'
//			) , null , array( 'choices' => array( 'No' => 'No' , 'Yes' => 'Yes' ) ) );
	}

	/**
	 * @inheritdoc
	 */
	protected function configureRoutes( RouteCollection $collection ) {

		//Clear all routes except list.
		$collection->remove( 'batch' );
		$collection->remove( 'delete' );
		//$collection->remove( 'export' );

		$collection->add( 'print-applicant' , '{id}/print-applicant/{choice}/print' , array( '_controller' => 'IIABMagnetBundle:Report:generateApplicantReport' ) , array( 'id' => '\d+' , 'choice' => '\d+' ) );
		$collection->add( 'print-offered' , '{id}/print-offered' , array( '_controller' => 'IIABMagnetBundle:Report:generateAwardPDF' ) , array( 'id' => '\d+' ) );
        $collection->add( 'print-learner-screening-device' , 'learner-screening-device/{url}/printout' , array( '_controller' => 'IIABMagnetBundle:LearnerScreeningDevice:adminPrintout' ) , array( 'url' ) );
        $collection->add( 'print-recommendation-math' , 'recommendation/math/{url}/printout' , array( '_controller' => 'IIABMagnetBundle:Recommendation:adminPrintout' ) , array( 'url' ) );
        $collection->add( 'print-recommendation-english' , 'recommendation/english/{url}/printout' , array( '_controller' => 'IIABMagnetBundle:Recommendation:adminPrintout' ) , array( 'url' ) );
        $collection->add( 'print-recommendation-counselor' , 'recommendation/counselor/{url}/printout' , array( '_controller' => 'IIABMagnetBundle:Recommendation:adminPrintout' ) , array( 'url' ) );
        $collection->add( 'print-writing-sample' , '{id}/writing-sample/print' , array( '_controller' => 'IIABMagnetBundle:Report:print_writing_sample' ) , array( 'id' => '\d+' ) );
        $collection->add( 'print-student-profile' , '{id}/student-profile/print' , array( '_controller' => 'IIABMagnetBundle:Report:print_student_profile' ) , array( 'id' => '\d+' ) );

		//Custom Routes for the admin pages when steping through the form.
		$collection->add( 'noStudentFound' , 'no-student-found' );
		$collection->add( 'notEligible' , 'not-eligible' );
		$collection->add( 'outOfDistrict' , 'out-of-district' );
		$collection->add( 'inCorrect' , 'incorrect' );
		$collection->add( 'exitWithSaving' , 'exit-without-saving' );
		$collection->add( 'success' , 'success' );
		$collection->add( 'alreadySubmitted' , 'already-submitted' );
		$collection->add( 'onHold' , 'on-hold-submission' );
		$collection->add( 'noEnrollment' , 'no-enrollment' );
		$collection->add( 'noZonedSchool' , 'no-zoned-school' );
		$collection->add( 'offered' , '{id}/offered/' );
		$collection->add( 'offeredNotFound' , '{id}/offer-not-found/' );
	}

	/**
	 * @inheritdoc
	 */
	public function getExportFields() {

		return array(
			'Submission ID' => 'confirmationStyleID' ,
			'Open Enrollment' => 'openEnrollment' ,
			'State ID' => 'stateID' ,
			'First Name' => 'firstName' ,
			'Last Name' => 'lastName' ,
            'Race' => 'race',
			'Address' => 'address' ,
			'City' => 'city' ,
            'State' => 'state',
			'Zip' => 'zip' ,
			'Phone Number' => 'phoneNumber' ,
			'Alternate Number' => 'alternateNumber' ,
			'Birthday' => 'birthdayFormatted' ,
			'Current School' => 'currentSchool' ,
			'Current Grade' => 'currentGrade' ,
			'Next Grade' => 'nextGrade' ,
			'Parents Email' => 'parentEmail' ,
			'Submission Status' => 'submissionStatus.status' ,
			'First School Choice' => 'firstChoice' ,
			'Second School Choice' => 'secondChoice' ,
			'Third School Choice' => 'thirdChoice' ,
			'Awarded Choice' => 'offered.awardedSchool' ,
			'TCS/New' => 'nonHSVStudentString' ,
            'Parent Employment' => 'parentEmploymentFormatted',
			'Created Date/Time' => 'createdAtFormatted',
            'GPA' => 'gpa',
            'Conduct GPA' => 'conductGPA',
            'Conduct Eligible' => 'conductEligible',

		);
	}

	/**
	 * @inheritdoc
	 */
	public function getExportFormats() {

		return array(
			'csv'
		);
	}

	/**
	 * Custom Filter for NonStudent display.
	 *
	 * @param $queryBuilder QueryBuilder
	 * @param $alias
	 * @param $field
	 * @param $value
	 *
	 * @return bool|void
	 */
	public function getNonStudentFilter( $queryBuilder , $alias , $field , $value ) {

		if( $value['value'] == '' ) {
			return;
		}

		if( $value['value'] == 'New' ) {
			$queryBuilder->andWhere( sprintf( '%s.nonHSVStudent = :student' , $alias ) );
			$queryBuilder->setParameter( 'student' , 1 );
		}
		if( $value['value'] == 'Current' ) {
			$queryBuilder->andWhere( sprintf( '%s.nonHSVStudent = :student' , $alias ) );
			$queryBuilder->setParameter( 'student' , 0 );
		}

		return true;
	}

	/**
	 * Custom Filter for Special Accommodations display.
	 *
	 * @param $queryBuilder QueryBuilder
	 * @param $alias
	 * @param $field
	 * @param $value
	 *
	 * @return bool|void
	 */
	public function getSpecialAccommodationsFilter( $queryBuilder , $alias , $field , $value ) {

		if( $value['value'] == '' ) {
			return;
		}

		if( $value['value'] == 'Yes' ) {
			$queryBuilder->andWhere( sprintf( '%s.specialAccommodations = :special' , $alias ) );
			$queryBuilder->setParameter( 'special' , 1 );
		}
		if( $value['value'] == 'No' ) {
			$queryBuilder->andWhere( sprintf( '%s.specialAccommodations = :special' , $alias ) );
			$queryBuilder->setParameter( 'special' , 0 );
		}

		return true;
	}


	/**
	 * Sets the Submission data, comments and grades for the SubmissionID.
	 *
	 * @param \IIAB\MagnetBundle\Entity\Submission $object
	 *
	 * @return void
	 */
	public function preUpdate( $object ) {

		$uniqid = $this->getRequest()->query->get( 'uniqid' );
		$formData = $this->getRequest()->request->get( $uniqid );

        $DM = $this->getConfigurationPool()->getContainer()->get('doctrine')->getManager();
        $uow = $DM->getUnitOfWork();
        $OriginalEntityData = $uow->getOriginalEntityData( $object );

		$allow_zero_values = [
			'committeeReviewScoreFirstChoice',
			'committeeReviewScoreSecondChoice',
			'committeeReviewScoreSecondChoice',
		];

        $datum = $DM->getRepository('IIABMagnetBundle:SubmissionData')->findBy( [
            'submission' => $object ,
        ] );

        foreach( $datum as $data ) {

            $skip = false;
            foreach( $object->getAdditionalData( true ) as $object_data ){
                if( $object_data->getId() == $data->getId() ){
                    $skip = true;
                }
            }

            if( !$skip ){
                $object->addAdditionalDatum( $data );
            }
        }

		foreach( $allow_zero_values as $field ){
			if( isset( $formData[ $field ] ) && $formData[ $field ] === '0' ) {
				$method = 'set'.ucfirst($field);

                $object->$method( $formData[ $field ] );
			}
		}
		foreach( $object->getGrades() as $grade ) {
			$grade->setSubmission( $object );
		    $DM->persist( $grade );
		}

        foreach( $object->getAdditionalData( true) as $maybe_grade_warning ){
            if( in_array( $maybe_grade_warning->getMetaKey(), ['missing_grade', 'duplicate_grade'] ) ){
                $object->removeAdditionalDatum( $maybe_grade_warning );
                $DM->remove($maybe_grade_warning);
            }
        }

        foreach( $object->getAdditionalData( true ) as $data ) {
			$data->setSubmission( $object );
		}

		foreach( $object->getUserComments() as $comment ) {
			$comment->setSubmission( $object );
            $comment->setUser( $this->user );
		}

        $gpa_service = new CalculateGPAService( $DM );
        $calculated_gpa = $gpa_service->calculateGPA( $object );

        if( $calculated_gpa != null ){
            $gpa_data = $object->getAdditionalDataByKey('calculated_gpa');
            if (empty($gpa_data)) {
                $gpa_data = new SubmissionData();
                $gpa_data->setMetaKey('calculated_gpa');
                $gpa_data->setSubmission($object);
            }
            $gpa_data->setMetaValue($calculated_gpa);
            $DM->persist($gpa_data);
        }

        $studentProfileService = new StudentProfileService( $object, $DM );
        $profile_totals = $studentProfileService->getProfileScores();

        if( $profile_totals != null ){
            $profile_score_object = $object->getAdditionalDataByKey('student_profile_score');
            if (empty($profile_score_object)) {
                $profile_score_object = new SubmissionData();
                $profile_score_object->setMetaKey('student_profile_score');
                $profile_score_object->setSubmission($object);
            }
            $profile_score_object->setMetaValue( ( $profile_totals != null ) ? $profile_totals['total'] : null );
            $DM->persist($profile_score_object);

            $profile_percentage_object = $object->getAdditionalDataByKey('student_profile_percentage');
            if (empty($profile_percentage_object)) {
                $profile_percentage_object = new SubmissionData();
                $profile_percentage_object->setMetaKey('student_profile_percentage');
                $profile_percentage_object->setSubmission($object);
            }
            $profile_percentage_object->setMetaValue( ( $profile_totals != null ) ? $profile_totals['percentage'] : null );
            $DM->persist($profile_percentage_object);
        }

		$choice_keys = [
		    'first',
            'second',
            'third'
        ];

		foreach( $choice_keys as $choice ) {
            $focus_data = $object->getFocusDataByChoice($choice);

            foreach ($focus_data as $key => $data) {

                $foundData = $object->getAdditionalDataByKey($key);
                if ($foundData == null) {
                    if (!empty($formData[$key])) {
                        $subData = new SubmissionData();
                        $subData->setMetaKey($key);
                        $subData->setMetaValue($formData[$key]);
                        $subData->setSubmission($object);
                        $DM->persist($subData);
                    }
                } else {
                    if (!empty($formData[$key])) {
                        $foundData->setMetaValue($formData[$key]);
                        $DM->persist($foundData);
                    } else {

                        //$object->removeAdditionalDatum($foundData);
                    }
                }

                $foundData = $object->getAdditionalDataByKey($key . '_extra');
                if ($foundData == null) {
                    if (!empty($formData[$key . '_extra'])) {
                        $subData = new SubmissionData();
                        $subData->setMetaKey($key . '_extra');
                        $subData->setMetaValue($formData[$key . '_extra']);
                        $subData->setSubmission($object);
                        $DM->persist($subData);
                    }
                } else {
                    if (!empty($formData[$key . '_extra'])) {
                        $foundData->setMetaValue($formData[$key . '_extra']);
                        $DM->persist($foundData);
                    } else {
                        //$object->removeAdditionalDatum($foundData);
                    }
                }

                foreach ($data['extra'] as $extra_key => $extra_data) {
                    $foundData = $object->getAdditionalDataByKey($extra_key);

                    if ($foundData == null) {
                        if (!empty($formData[$extra_key])) {
                            $subData = new SubmissionData();
                            $subData->setMetaKey($extra_key);
                            $subData->setMetaValue($formData[$extra_key]);
                            $subData->setSubmission($object);
                            $DM->persist($subData);
                        }
                    } else {
                        if (!empty($formData[$extra_key])) {
                            $foundData->setMetaValue($formData[$extra_key]);
                            $DM->persist($foundData);
                        } else {
                            //$object->removeAdditionalDatum($foundData);
                        }
                    }
                }
            }
        }

        $eligibility_fields = [
            //'calculated_gpa', this value is set above
            'conduct_gpa',
            'conduct_eligible',
            'first_choice_writing_prompt',
            'second_choice_writing_prompt',
            'third_choice_writing_prompt',
            'learner_screening_device',
            'student_profile',
            'holistic_committee',

            'reading_test',
            'math_test',
            'recommendation_english',
            'recommendation_math',
            'recommendation_counselor',

            'audition_1',
            'audition_2',
            'audition_total',

            'attendance',

            'first_choice_interview',
            'second_choice_interview',
            'third_choice_interview',

            'first_choice_interest',
            'second_choice_interest',
            'third_choice_interest',
            'parent_employment',
            'parent_employee_name',
            'parent_employee_location',
            'student_email',
        ];
        foreach( $eligibility_fields as $field_key ){

            if( isset( $formData[ $field_key ] ) ) {
                $foundData = $object->getAdditionalDataByKey( $field_key );

                if( $foundData == null ) {
                    if( $formData[ $field_key ] || $formData[ $field_key ] === '0' ) {
                        $subData = new SubmissionData();
                        $subData->setMetaKey($field_key);
                        $subData->setMetaValue($formData[$field_key]);
                        $subData->setSubmission($object);
                        $DM->persist($subData);
                    }
                } else {
                    if( $formData[ $field_key ] || $formData[ $field_key ] === '0' ){
                        $foundData->setMetaValue( $formData[ $field_key ] );
                        $DM->persist( $foundData );
                    } else {
                        //$object->removeAdditionalDatum( $foundData );
                    }
                }
            }
        }

        $originalStatus = $OriginalEntityData['submissionStatus']->getId();
		$newStatus = $object->getSubmissionStatus()->getId();

		$race = strtoupper( $object->getRaceFormatted() );

		$offered = $DM->getRepository('IIABMagnetBundle:Offered')->findOneBy( array(
			'submission' => $object ,
		), ['id' => 'DESC'] );

		//If originalStatus is 'Active' and going to 'Offered'
		if( $originalStatus == 1 && $newStatus == 6 ) {

			$magnetSchoolID = $object->getOfferedCreation();
			if( empty( $magnetSchoolID ) ) {
				$magnetSchoolID = 0;
			}
			$magnetSchool = $DM->getRepository('IIABMagnetBundle:MagnetSchool')->find( $magnetSchoolID );

			if( !empty ($magnetSchool ) && $magnetSchool != null ) {

				$url = $object->getId() . '.' . rand( 10 , 999 );

				$offer = new Offered();
				$offer->setOpenEnrollment( $object->getOpenEnrollment() );
				$offer->setSubmission( $object );
				$offer->setAwardedSchool( $magnetSchool );
				$offer->setUrl( $url );
				$offer->setOnlineEndTime( $object->getOfferedCreationEndOnlineTime() );
				$offer->setOfflineEndTime( $object->getOfferedCreationEndOfflineTime() );
				$DM->persist( $offer );

				$this->recordManualAwardPopulationChange( $object , $magnetSchool , $race );

				$this->getConfigurationPool()->getContainer()->get('magnet.email')->sendAwardedEmail( $offer , 'awarded' );

			}
		}

		//If originalStatus is 'Offered' and going to 'Inactive Due Ineligibility'
		if( $originalStatus == 6 && $newStatus == 12 ) {

			$this->minusSlotForPopulation( $object , $offered , $race );

			//Remove the Offered
			if( $offered != null ) {
				$DM->remove( $offered );
			}
		}

		//If originalStatus is 'Offered And Accepted' and going to 'Offered And Declined'
		if( $originalStatus == 7 && $newStatus == 8 ) {

			$this->minusSlotForPopulation( $object , $offered , $race );

			$this->getConfigurationPool()->getContainer()->get('magnet.email')->sendDeclinedEmail( $object->getOffered() );

		}

		//If originalStatus is 'Offered and Accepted' and going to 'Application Withdrawn'
		if( $originalStatus == 7 && $newStatus == 11 ) {

			$this->minusSlotForPopulation( $object , $offered , $race );

			//Remove the Offered
			if( $offered != null ) {
				$DM->remove( $offered );
			}
		}

		//If originalStatus is 'Offered and Accepted' and going to 'Inactive Due Ineligibility'
		if( $originalStatus == 7 && $newStatus == 12 ) {

			$this->minusSlotForPopulation( $object , $offered , $race );

			//Remove the Offered
			if( $offered != null ) {
				$DM->remove( $offered );
			}
		}

		//If originalStatus is 'Offered And Declined' and going to 'Offered And Accepted'
		if( $originalStatus == 8 && $newStatus == 7 ) {

			$this->addSlotForPopulation( $object, $offered , $race );

			$this->getConfigurationPool()->getContainer()->get('magnet.email')->sendAcceptedEmail( $object->getOffered() );
		}

		//If originalStatus is 'Offered and Declined' and going to 'Application Withdrawn'
		if( $originalStatus == 8 && $newStatus == 11 ) {

			//Remove the Offered
			if( $offered != null ) {
				$DM->remove( $offered );
			}
		}

		//If originalStatus is 'Offered and Declined' and going to 'Inactive Due Ineligibility'
		if( $originalStatus == 8 && $newStatus == 12 ) {

			//Remove the Offered
			if( $offered != null ) {
				$DM->remove( $offered );
			}
		}

		//If originalStatus is 'Wait Listed' and going to 'Offered'
		if( $originalStatus == 9 && $newStatus == 6 ) {

			$magnetSchoolID = $object->getOfferedCreation();
			if( empty( $magnetSchoolID ) ) {
				$magnetSchoolID = 0;
			}
			$magnetSchool = $DM->getRepository('IIABMagnetBundle:MagnetSchool')->find( $magnetSchoolID );
			if( !empty ($magnetSchool ) && $magnetSchool != null ) {

				$url = $object->getId() . '.' . rand( 10 , 999 );

				$offer = new Offered();
				$offer->setOpenEnrollment( $object->getOpenEnrollment() );
				$offer->setSubmission( $object );
				$offer->setAwardedSchool( $magnetSchool );
				$offer->setUrl( $url );
				$offer->setOnlineEndTime( $object->getOfferedCreationEndOnlineTime() );
				$offer->setOfflineEndTime( $object->getOfferedCreationEndOfflineTime() );
				$DM->persist( $offer );

				$this->recordManualAwardPopulationChange( $object , $magnetSchool , $race );

				$this->getConfigurationPool()->getContainer()->get('magnet.email')->sendAwardedEmail( $offer , 'awarded' );

				$this->removeWaitListItems( $object );

			} else {
				//They did not select a school. Change status back to Wait Listed.
				//Flip status BACK to Wait List.
				$waitListStatus = $DM->getRepository('IIABMagnetBundle:SubmissionStatus')->find( $originalStatus );
				$object->setSubmissionStatus( $waitListStatus );
			}
		}

		//If originalStatus is 'Wait Listed' and going to 'Application Withdrawn'
		if( $originalStatus == 9 && $newStatus == 11 ) {

			$this->removeWaitListItems( $object );
		}

		//If originalStatus is 'Wait Listed' and going to 'Inactive Due Ineligibility'
		if( $originalStatus == 9 && $newStatus == 12 ) {

			$this->removeWaitListItems( $object );
		}

		//If originalStatus is 'Wait Listed' and going to 'Declined Wait List'
		if( $originalStatus == 9 && $newStatus == 13 ) {

			$this->removeWaitListItems( $object );
		}
	}

	/**
	 * Records the Manual awarding of a submission in the Current/After population.
	 *
	 * @param $object
	 * @param $magnetSchool
	 * @param $race
	 */
	private function recordManualAwardPopulationChange( $object , $magnetSchool , $race, $focus = null ) {

		$DM = $this->getConfigurationPool()->getContainer()->get('doctrine')->getManager();

		$afterPopulation = $DM->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
			'magnetSchool' => $magnetSchool ,
			'openEnrollment' => $object->getOpenEnrollment() ,
            'focusArea' => ( !empty( $focus)  ) ? $focus : null,
		) , array( 'lastUpdatedDateTime' => 'DESC' ) );

		//If the afterPopulation is Null, lets add one in.
		if( $afterPopulation == null ) {

			$currentPopulation = $DM->getRepository('IIABMagnetBundle:CurrentPopulation' )->findOneBy( array(
				'magnetSchool' => $magnetSchool ,
				'openEnrollment' => $object->getOpenEnrollment()
			) );

			$afterPopulation = new AfterPlacementPopulation();
			$afterPopulation->setOpenEnrollment( $object->getOpenEnrollment() );
			$afterPopulation->setMagnetSchool( $magnetSchool );
			$afterPopulation->setFocusArea( $focus );
			$afterPopulation->setCPBlack( $currentPopulation->getCPBlack() );
			$afterPopulation->setCPWhite( $currentPopulation->getCPWhite() );
			$afterPopulation->setCPOther( $currentPopulation->getCPSumOther() );
			$afterPopulation->setLastUpdatedDateTime( new \DateTime() );
			$afterPopulation->setMaxCapacity( $currentPopulation->getMaxCapacity() );

			$DM->persist( $afterPopulation );
		}

		switch( $race ) {
			case 'WHITE':
				$newWhite = $afterPopulation->getCPWhite();
				$newWhite++;
				$afterPopulation->setCPWhite( $newWhite );
				break;

			case 'BLACK':
				$newBlack = $afterPopulation->getCPBlack();
				$newBlack++;
				$afterPopulation->setCPBlack( $newBlack );
				break;

			default:
				$newOther = $afterPopulation->getCPOther();
				$newOther++;
				$afterPopulation->setCPOther( $newOther );
				break;
		}

		$DM->persist( $afterPopulation );
	}

	/**
	 * Remove Wait List Entries.
	 * @param Submission $submission
	 */
	private function removeWaitListItems( Submission $submission ) {

		$DM = $this->getConfigurationPool()->getContainer()->get('doctrine')->getManager();

		//Looping over all the waitList items and queue them up to be deleted from the DB.
		//Since this function is called from the preUpdate, there is not need to call Flush.
		foreach( $submission->getWaitList() as $waitList ) {

			//Removing each entry.
			$DM->remove($waitList);
		}
	}

	/**
	 * Add an race slot for an After Population item.
	 *
	 * @param Submission $object
	 * @param Offered $offered
	 * @param string $race
	 */
	private function addSlotForPopulation( $object, $offered , $race ) {

		$DM = $this->getConfigurationPool()->getContainer()->get('doctrine')->getManager();

		$afterPopulation = $DM->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
			'magnetSchool' => $object->getOffered()->getAwardedSchool() ,
			'openEnrollment' => $object->getOpenEnrollment() ,
            'focusArea' => ( $offered->getAwardedFocusArea() ) ? $offered->getAwardedFocusArea() : null,
		) , array( 'lastUpdatedDateTime' => 'DESC' ) );

		switch( $race ) {
			case 'WHITE':
				$newWhite = $afterPopulation->getCPWhite();
				$newWhite++;
				$afterPopulation->setCPWhite( $newWhite );
				break;

			case 'BLACK':
				$newBlack = $afterPopulation->getCPBlack();
				$newBlack++;
				$afterPopulation->setCPBlack( $newBlack );
				break;

			default:
				$newOther = $afterPopulation->getCPOther();
				$newOther++;
				$afterPopulation->setCPOther( $newOther );
				break;
		}

		if( $offered != null ) {

			$offered->setAccepted( 1 );
			$offered->setDeclined( 0 );
			$offered->setChangedDateTime( new \Datetime() );
			$DM->persist( $offered );
		}

		$DM->persist( $afterPopulation );
	}

	/**
	 * Subtract an race slot from an After Population.
	 *
	 * @param Submission $object
	 * @param Offered $offered
	 * @param string $race
	 */
	private function minusSlotForPopulation( $object , $offered , $race ) {

		$DM = $this->getConfigurationPool()->getContainer()->get('doctrine')->getManager();

		$afterPopulation = $DM->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
			'magnetSchool' => $object->getOffered()->getAwardedSchool() ,
			'openEnrollment' => $object->getOpenEnrollment() ,
            'focusArea' => ( $offered->getAwardedFocusArea() ) ? $offered->getAwardedFocusArea() : null,
		) , array( 'lastUpdatedDateTime' => 'DESC' ) );

		switch( $race ) {
			case 'WHITE':
				$newWhite = $afterPopulation->getCPWhite();
				$newWhite--;
				$afterPopulation->setCPWhite( $newWhite );
				break;

			case 'BLACK':
				$newBlack = $afterPopulation->getCPBlack();
				$newBlack--;
				$afterPopulation->setCPBlack( $newBlack );
				break;

			default:
				$newOther = $afterPopulation->getCPOther();
				$newOther--;
				$afterPopulation->setCPOther( $newOther );
				break;
		}

		if( $offered != null ) {

			$offered->setAccepted( 0 );
			$offered->setDeclined( 1 );
			$offered->setChangedDateTime( new \Datetime() );
			$DM->persist( $offered );
		}

		$DM->persist( $afterPopulation );

	}
}