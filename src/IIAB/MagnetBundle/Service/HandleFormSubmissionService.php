<?php

namespace IIAB\MagnetBundle\Service;

use Doctrine\Common\Persistence\ObjectManager;
use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionData;
use IIAB\MagnetBundle\Entity\SubmissionGrade;
use IIAB\MagnetBundle\Service\RecommendationService;
use IIAB\MagnetBundle\Service\LearnerScreeningDeviceService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use IIAB\MagnetBundle\Service\LotteryService;
use IIAB\MagnetBundle\Service\CalculateGPAService;
use IIAB\MagnetBundle\Service\StudentProfileService;
use IIAB\MagnetBundle\Service\CheckAddressService;
use IIAB\MagnetBundle\Service\MagnetAcademicYearService;
use \stdClass;


class HandleFormSubmissionService {

	/**
	 * These variables are all provided
	 * by the input function
	 */
	private $formData;
	private $clickedButton;
	private $form;
	private $getStudentService;

	private $recommendationService;
	private $learnerScreeningDeviceService;

	/** @var ObjectManager */
	private $emLookup;

	private $doctrine;

	/** @var bool */
	private $isAdmin;

	/** @var SessionInterface **/
	private $session;

	/** @var Symfony\Component\Translation\DataCollectorTranslator **/
	private $translator;

	/**
	 * @param Controller		$container
	 * @param ObjectManager $emLookup
	 * @param bool					$isAdmin
	 */
	public function __construct(
		$isAdmin = false,
		$doctrine,
		$translator = null,
		$emailService
	) {
		$this->isAdmin = $isAdmin;
		$this->doctrine = $doctrine;
		$this->emLookup = $this->doctrine->getManager();
		$this->translator = $translator;
		$this->emailLookup = $emailService;
		$this->recommendationService = new RecommendationService( $this->emLookup );
		$this->learnerScreeningDeviceService = new LearnerScreeningDeviceService( $this->emLookup );
	}

	/**
	 * @param array						$this->formData
	 * @param Form						 $form
	 * @param SessionInterface $session
	 *
	 * @return URL
	 * @throws \Exception
	 */

	public function handleFormSubmission( array $formData , Form $form , SessionInterface $session ) {

		/**
		 * Stores our input variables within the parent object...
		 * As long as the order of operations goes as planned,
		 * the continuity of our instance variables should be okay...
		 */

		$this->formData = $formData;
		$this->session = $session;
		$this->form = $form;

		/**
		 * Initialize the Get Student Service
		 */
		$this->getStudentService = new GetStudentService( $this->formData , $this->doctrine );

		/**
		 * Initialize an output object!
		 */
		$_local_button_object = new stdClass();


		/**
		 * Start of function
		 */
		$this->clickedButton = $this->form->getClickedButton()->getName();

		$this->formData = $this->cleanFormData( $this->formData );

		/**
		 * This is our application switched / logic router
		 */

		 /**
		  * Point of debug
			*/
		//echo $this->clickedButton;


		switch ( $this->clickedButton ) {

			case "look_up_student":
				/**
				 * Current Student, need to look student up in the database.
				 */
				$_local_button_object = $this->_handler_look_up_student();
				break;

			case "info_correct":
				/**
				 *
				 */
				$_local_button_object = $this->_handler_info_correct ();
				break;

			case "info_incorrect":
				/**
				 * The following button breaks the flow and needs to redirect the user.
				 * BUTTON: info_incorrect
				 */
				$_local_button_object = $this->_handler_info_incorrect ();
				break;

			case "resubmit_form":
				$_local_button_object = $this->_handler_resubmit_form();
				break;

			case "proceed_with_choices":
				/**
				 * Proceed with selected, and send on to confirmation screen.
				 * Also send email if email address was provided.
				 */
				$_local_button_object = $this->_handler_proceed_with_choices();
				break;

			case "exit_without_savings":
				$_local_button_object = $this->_handler_exit_without_saving();
				break;

		}

		/**
		 * Check previous checks for return property.
		 * If property exists, return it, and move along.
		 */

			if ( isset( $_local_button_object ) ) {
				if( property_exists( $_local_button_object, "return" ) ) {

						if ( $_local_button_object->return === true ) {
							//  do nothing
						} else {
							return $_local_button_object->return;
							//  and we're out of this function
						}

				}
		 }

		/**
		 * Continue processing form
		*/

		if( isset( $this->formData['parentEmployment'] ) ){
			$this->formData['parentEmployment'] = ( $this->formData['parentEmployment'] == 1 ) ? 'Yes' : 'No';
		}

		if( isset( $this->formData['confirmStatus']) && $this->formData['confirmStatus'] != 1 ){
			return array( 'magnet_incorrect' );
		}

		//  We're still alive

		/**
		 * If it not a special button (like above), continue the flow.
		 */
		if ( isset( $_local_button_object ) ) {
 			if( property_exists( $_local_button_object, "stepback" ) ) {
				//  do nothing
			} else {
					$this->formData['step']++;
			}
		}


		unset( $this->formData[ 'emLookup' ] );

		$this->session->set( 'magnet-formData' , base64_encode( serialize( $this->formData ) ) );

		/**
		 * Last return
		 */
		if( ! $this->isAdmin ) {
			return array( 'magnet_app_step' , array( 'step' => $this->formData['step'] ) );
		} else {
			return array( 'admin_submission_create' , array( 'step' => $this->formData['step'] ) );
		}

	}

	private function _handler_look_up_student () {
		/**
		 * All of out input should be in class variables
		 */

		/**
		 * Initialize output object
		 */
		$_output_object = new stdClass();

		/**
		 * Find student.
		 * Need to send $this->formData to the command.
		 **/

		$studentFound = $this->getStudentService->getStudent();

		if( $studentFound ) {  //  student found!!!!!!

			/**
			 * Check for previous submission
			 */

			$now = new \DateTime;

			$openEnrollment = $this->emLookup->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findByDate( $now );

			$openEnrollment = ( $openEnrollment != null) ? $openEnrollment : $this->emLookup->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findLatePlacementByDate( $now );

			if( $openEnrollment != null && count( $openEnrollment ) == 1 ) {
				$openEnrollment = $openEnrollment[0];
			}

			$isLatePlacement = ( $openEnrollment ) ? ( $openEnrollment->getLatePlacementBeginningDate() < $now && $openEnrollment->getLatePlacementEndingDate() > $now ) : false;

			$last_submission = $this->emLookup->getRepository('IIABMagnetBundle:Submission')->findOneBy([ 'stateID' => $studentFound['stateID'], 'openEnrollment' => $openEnrollment ], [ 'createdAt' => 'DESC' ]);

			if( isset( $last_submission ) ){

				if( $isLatePlacement && $openEnrollment->getLatePlacementBeginningDate() > $last_submission->getCreatedAt() && ( in_array( $last_submission->getSubmissionStatus()->getId(), [ 3, 8, 10, 11, 14 ] ) ) ) {

					$this->formData[ 'submission' ] = $last_submission->getId();

					$first_choice = $last_submission->getFirstChoice();
					$second_choice = $last_submission->getSecondChoice();
					$third_choice = $last_submission->getThirdChoice();

					if( isset( $first_choice ) ){
						$first_choice = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findOneBy([ 'id' => $first_choice->getId() ]);
					}

					if( isset( $second_choice ) ){
						$second_choice = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findOneBy([ 'id' => $second_choice->getId() ]);
					}

					if( isset( $third_choice ) ){
						$third_choice = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findOneBy([ 'id' => $third_choice->getId() ]);
					}

					$this->formData[ 'submission' ] = $last_submission;

				} else {

					$_output_object->return = array( 'magnet_already_submitted' );
					return $_output_object;

				}

			}

		} else {  //  student not found previously

			if( ! $this->isAdmin ) {  //  is not admin

				$_output_object->return = array( 'magnet_no_student_found' );
				return $_output_object;

			} else {  //  is admin

				$_output_object->return = array( 'admin_submission_noStudentFound' );
				return $_output_object;

			}
		}  //  end of student if statment...

		/**
		 * Process student data
		 */
		$this->formData = array_merge($this->formData, $studentFound );

		if( !is_object( $this->formData['race'] ) ){
			$this->formData['race'] = $this->emLookup->getRepository( 'IIABMagnetBundle:Race' )->findOneBy(
				array ( 'race' => $this->formData['race'] ) );
		}

		$this->formData['step']++;

		$this->session->set( 'magnet-formData' , base64_encode( serialize( $this->formData ) ) );

		if( ! $this->isAdmin ) {  //  is not admin

			$_output_object->return = array( 'magnet_app_step' , array( 'step' => $this->formData['step'] ) );
			return $_output_object;

		} else {

			$_output_object->return = array( 'admin_submission_create' , array( 'step' => $this->formData['step'] ) );
			return $_output_object;

		}

		/**
		 * If you're this far, the programming is broken!!!!
		 * NOT POSSIBLE
		 */
		$_output_object->return = true;
		return $_output_object;

	}

	private function _handler_info_incorrect () {
		/**
		 * All of out input should be in class variables
		 */

		/**
		 * Initialize output object
		 */
		$_output_object->return = array( 'magnet_incorrect' );
				return $_output_object;

	}

	private function _handler_info_correct () {
		/**
		 * All of out input should be in class variables
		 */

		/**
		 * Initialize output object
		 */
		$_output_object = new stdClass();


		/**
		 * The following button requires an additional check before allowing the user to continue.
		 * BUTTON: info_correct
		 * Test the address against the AddressBounds
		 **/

		$changeBackStudentStatus = false;

		if( $this->formData['student_status'] == 'current' ) {
			$this->formData['student_status'] = 'new';
			$changeBackStudentStatus = true;
		}

		$checkAddressService = new CheckAddressService( $this->emLookup );
		$addressResponse = $checkAddressService->checkAddress( $this->formData );

		if( $changeBackStudentStatus ) {
			$this->formData['student_status'] = 'current';
		}

		$zonedSchool = '';

		if( $addressResponse ) {

			switch ( $this->formData['next_grade'] ) {

				case 99:
					$zonedSchool = $this->emLookup->getRepository('IIABMagnetBundle:AddressBoundSchool')->createQueryBuilder('a')
						->where('a.startGrade = 99')
						->andWhere('a.name = :school')
						->setParameter('school', $addressResponse->getESBND())
						->setMaxResults(1)
						->getQuery()
						->getResult();
					break;

				default:
					$zonedSchool = $this->emLookup->getRepository('IIABMagnetBundle:AddressBoundSchool')->createQueryBuilder('a')
							->where('a.startGrade <= :grade OR a.startGrade = 99')
							->andWhere('a.endGrade >= :grade')
							->andWhere('a.name = :elemschool OR a.name = :middleschool OR a.name = :highschool')
							->setParameter('grade', number_format($this->formData['next_grade'], 0))
							->setParameter('elemschool', $addressResponse->getESBND())
							->setParameter('middleschool', $addressResponse->getMSBND())
							->setParameter('highschool', $addressResponse->getHSBND())
							->setMaxResults(1)
							->getQuery()
							->getResult();
					break;
			}

		}

		if( !empty( $zonedSchool ) ) {

			$zonedSchool = $zonedSchool[0];
			$zonedSchool = $zonedSchool->getName();

		} else {

			$_output_object->return = array( 'magnet_no_zoned_school' );
			return $_output_object;
		}

		$this->formData['zonedSchool'] = ucwords( strtolower( $zonedSchool ) );

		$getEligibleSchoolService = new GetEligibleSchoolService( $this->emLookup, $this->translator );
		$eligible = $getEligibleSchoolService->getEligibleSchools( $this->formData );

		$eligible = ( $eligible ) ? $eligible : [];
			$foci = $getEligibleSchoolService->getFoci( $eligible );
			$exclusions = $getEligibleSchoolService->getExclusions( $eligible );
			$focus_extras = $getEligibleSchoolService->getFocusExtras( $eligible );
			$focus_labels = $getEligibleSchoolService->getFocusLabels( $eligible );

		$this->formData['foci'] = json_encode( $foci );
		$this->formData['exclusions'] = json_encode( $exclusions );
		$this->formData['focus_extras'] = json_encode( $focus_extras );
		$this->formData['focus_labels'] = json_encode( $focus_labels );

		if( $eligible ) {

			$this->formData['step']++;

			$this->formData['schools'] = $eligible;

			$this->session->set( 'magnet-formData' , base64_encode( serialize( $this->formData ) ) );

			if( ! $this->isAdmin ) {

				$_output_object->return = array( 'magnet_app_step' , array( 'step' => $this->formData['step'] ) );
				return $_output_object;

			} else {

				$_output_object->return = array( 'admin_submission_create' , array( 'step' => $this->formData['step'] ) );
				return $_output_object;

			}

		} else {

			if( ! $this->isAdmin ) {

				//Student is not eligible. Redirecting to not eligible.
				$_output_object->return = array( 'magnet_not_eligible' );
				return $_output_object;

			} else {

				$_output_object->return = array( 'admin_submission_notEligible' );
				return $_output_object;

			}
		}

		/**
		 * If you're this far, the programming is broken!!!!
		 * NOT POSSIBLE
		 */
		$_output_object->return = true;
		return $_output_object;

	}

	private function _handler_resubmit_form () {
		/**
		 * All of out input should be in class variables
		 */

		/**
		 * Initialize output object
		 */
		$_output_object = new stdClass();


		$submission_id = explode('-', $this->formData['submission']);
		$submission_id = end( $submission_id );
		$submission = $this->emLookup->getRepository( 'IIABMagnetBundle:Submission' )->find($submission_id);
		$active_status = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find(1);
		$inactive_status = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find(10);

		$new_submission = clone $submission;
		$new_submission->setCreatedAt( new \DateTime() );
		$new_submission->setSubmissionStatus( $active_status );
		$lotteryService = new lotteryService( null, $this->emLookup );
		$lotteryNumber = $lotteryService->getLotteryNumber( $new_submission->getOpenEnrollment() );
		$new_submission->setLotteryNumber( $lotteryNumber );

		$waitlists = $submission->getWaitList();
		foreach( $waitlists as $waitlist ){
			$this->emLookup->getManager()->remove( $waitlist );
		}
		$submission->setSubmissionStatus( $inactive_status );

		$this->emLookup->getManager()->persist( $submission );
		$this->emLookup->getManager()->persist( $new_submission );
		$this->emLookup->getManager()->flush();

		$grades = $submission->getGrades();
		if( $grades != null && count( $grades ) > 0 ) {
			foreach( $grades as $grade ) {
				$submissionGrade = clone $grade;
				$submissionGrade->setSubmission( $new_submission );
				$this->emLookup->persist( $submissionGrade );
			}
		}
		$this->emLookup->getManager()->flush();

		//Store the new SubmissionID into the session.
		$this->formData['submissionID'] = 'SPECIAL-';
		$this->formData['submissionID'] .= $new_submission->getOpenEnrollment()->getConfirmationStyle();
		$this->formData['submissionID'] .= '-' . $new_submission->getId();

		$this->session->set( 'magnet-formData' , base64_encode( serialize( $this->formData ) ) );

		$this->startConfirmations( $new_submission );

		// if( isset( $this->formData['parentEmail'] ) && !empty( $this->formData['parentEmail'] ) ) {

		// 	//Send Email to parent email if there is a parent Email passed in.
		// 	$this->emailLookup->sendConfirmationEmail( $new_submission );

		// }

		// $this->emailLookup->sendStudentWritingPromptEmail( $new_submission );
		// $this->emailLookup->sendTeacherRecommendationFormsEmail( $new_submission );
		// $this->emailLookup->sendLearnerScreeningDeviceEmail( $new_submission );

		$_output_object->return = array( 'magnet_successful' );
		return $_output_object;

	}

	private function _utility_check_student_status ( $min, $max ) {
		if( isset( $this->formData['student_status'] )
			&& $this->formData['student_status'] == 'new'
			&& ( $this->formData['next_grade'] > $min && $this->formData['next_grade'] < $max )
			) {
				return true;
			} else {
				return false;
			}
	}

	private function _handler_proceed_with_choices () {
		/**
		 * All of out input should be in class variables
		 */

		/**
		 * Initialize output object
		 */
		$_output_object = new stdClass();

		/**
		 * Mark previous submission inactive
		 */
		if( isset( $this->formData['submission'] ) && $this->formData['submission'] ) {
			$submission_id = explode('-', $this->formData['submission']);
			$submission = $this->emLookup->getRepository('IIABMagnetBundle:Submission')->findOneBy([ 'id' => $submission_id ]);
			$inactive_status = $this->emLookup->getRepository('IIABMagnetBundle:SubmissionStatus')->find(10);
			$submission->setSubmissionStatus( $inactive_status );
			$this->emLookup->getManager()->persist( $submission );
			$this->emLookup->getManager()->flush();
		}

		/**
		 * Created the submission record, and store the information.
		 * Store submission before redirecting
		 */
		$saveSubmissionResponse = $this->saveSubmission();

		if( ! $this->isAdmin ) {

			switch ( $saveSubmissionResponse ) {
				case "no-open-enrollment":
					$_output_object->return = array( 'magnet_no_open_enrollment' );
					return $_output_object;
					break;
				case "duplicate-choices":
					$_output_object->return = array( 'magnet_app_step' , array( 'step' => $this->formData['step'] , 'duplicate' => 1 ) );
					return $_output_object;
					break;
			}

			if ( $this->_utility_check_student_status( 1, 13 ) ) {  //  grades 1 - 13

					/**
					 * IS NEW STUDENT
					 */
					switch ( $saveSubmissionResponse ) {

						case "success":
							$_output_object->return = array( 'magnet_new_submission' );
							return $_output_object;
							break;

						case "already-submitted":
							//There already exists a submission for this Student. Error out.
							$_output_object->return = array( 'magnet_already_submitted' );
							return $_output_object;
							break;

						default:
							throw new \Exception( 'Save Submission Response Match not found. Please fix.' );
							break;
					}

			} else {

				/**
				 * New student if statment failed,
				 * running alternatives
				 *
				 * Need to check the submission response.
				 */

				switch ( $saveSubmissionResponse ) {
					case "success":
						//Successful in creating the new submission. redirect to success page.
						$_output_object->return = array( 'magnet_successful' );
						return $_output_object;
						break;

					case "already-submitted":
						//There already exists a submission for this State ID. Error out.
						$_output_object->return = array( 'magnet_already_submitted' );
						return $_output_object;
						break;

					default:
						throw new \Exception( 'Save Submission Response Match not found. Please fix.' );
						break;

				}

			}

		} else { //  then, is admin

			switch ( $saveSubmissionResponse ) {

				case "no-open-enrollment":
					$_output_object->return = array( 'admin_submission_noEnrollment' );
					return $_output_object;
					break;

				case "duplicate-choices":
					$_output_object->return = array( 'admin_submission_create' , array( 'step' => $this->formData['step'] , 'duplicate' => 1 ) );
					return $_output_object;
					break;

				case "duplicate-choices":
					$_output_object->return = array( 'admin_submission_create' , array( 'step' => $this->formData['step'] , 'duplicate' => 1 ) );
					return $_output_object;
					break;
			}


			if ( $this->_utility_check_student_status( 1, 13 ) ) {  //  grades 1 - 13

					switch ( $saveSubmissionResponse ) {

						case "success":
							$_output_object->return = array( 'admin_submission_onHold' );
							return $_output_object;
							break;

						case "already-submitted":
							$_output_object->return = array( 'admin_submission_alreadySubmitted' );
							return $_output_object;
							break;

						default:
							throw new \Exception( 'Save Submission Response Match not found. Please fix.' );
							break;
					}

			} else {

				switch ( $saveSubmissionResponse ) {

					case "success":
						$_output_object->return = array( 'admin_submission_success' );
						return $_output_object;
						break;

					case "already-submitted":
						$_output_object->return = array( 'admin_submission_alreadySubmitted' );
						return $_output_object;
						break;

					default:
						throw new \Exception( 'Save Submission Response Match not found. Please fix.' );
						break;

				}
			}

		}

		/**
		 * If you're this far, the programming is broken!!!!
		 * NOT POSSIBLE
		 */
		return $_output_object;

	}



	private function _handler_exit_without_saving () {
		/**
		 * All of out input should be in class variables
		 */

		/**
		 * Initialize output object
		 */
		$_output_object = new stdClass();

		// User decided not to proceed with choices and exited the application.
		if( ! $this->isAdmin ) {

			$_output_object->return = array( 'magnet_exit_application' );
			return $_output_object;

		} else {

			$_output_object->return = array( 'admin_submission_exitWithSaving' );
			return $_output_object;

		}

	}

	/**
	 * @param array $this->formData
	 *
	 * @return array
	 */
	private function cleanFormData( $_input_formData = array() ) {

		if( isset( $_input_formData['confirm_correct'] ) ) {

			unset( $_input_formData['confirm_correct'] );

		}

		return $_input_formData;
	}

	/**
	 * Creates new submission
	 *
	 * @param array $this->formData
	 *
	 * @return string
	 */
	private function saveSubmission() {

		$submission = new Submission();

		$openEnrollment = $this->emLookup->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findByDate( new \DateTime );
		$openEnrollment = ( $openEnrollment != null) ? $openEnrollment :	$this->emLookup->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findLatePlacementByDate( new \DateTime );

		if( $openEnrollment != null && count( $openEnrollment ) == 1 ) {
			$submission->setOpenEnrollment( $openEnrollment[0] );
		}

		if( empty( $openEnrollment ) || $this->isAdmin ) {

			if( isset( $this->formData['openEnrollment'] ) && !empty( $this->formData['openEnrollment'] ) ) {

				$openEnrollment = $this->emLookup->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findBy([ 'id' => $this->formData['openEnrollment']->getId() ]);

				$submission->setOpenEnrollment( $openEnrollment[0] );
			}

		}

		if( $openEnrollment == null ) {

			return 'no-open-enrollment';

		}

		//Find the "Active" Status by ID of 1
		$activeStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 1 );

		//Find the "On Hold" Status by ID of 5
		$onHoldStatus = $this->emLookup->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 5 );

		if( $this->formData['student_status'] == 'current' ) {

			$submission->setStateID( $this->formData['stateID'] );

			$alreadySubmitted = $this->emLookup->getRepository('IIABMagnetBundle:Submission')->findOneBy( array(
				'openEnrollment' => $openEnrollment[0],
				'stateID' => $this->formData['stateID'],
				'submissionStatus' => $activeStatus,
			) );

			if( $alreadySubmitted == null ) {

				$submission->setSubmissionStatus( $activeStatus );

			} else {

				return 'already-submitted';

			}

		}

		//Find the "Active" Status Submissions by New Student Information.
		if( $this->formData['student_status'] == 'new' ) {

			$alreadySubmitted = $this->emLookup->getRepository('IIABMagnetBundle:Submission')->findOneBy( array(
				'openEnrollment' => $openEnrollment[0],
				'firstName' => $this->formData['first_name'],
				'lastName' => $this->formData['last_name'],
				'birthday' => $this->formData['dob'],
				'submissionStatus' => $activeStatus,
			) );

			if( $alreadySubmitted == null ) {

				$alreadySubmittedOnHold = $this->emLookup->getRepository('IIABMagnetBundle:Submission')->findOneBy( array(
					'openEnrollment' => $openEnrollment[0],
					'firstName' => $this->formData['first_name'],
					'lastName' => $this->formData['last_name'],
					'birthday' => $this->formData['dob'],
					'submissionStatus' => $onHoldStatus,
				) );

				if( $alreadySubmittedOnHold == null ) {

					$submission->setSubmissionStatus( $activeStatus );

				} else {

					return 'already-submitted';

				}

			} else {

				return 'already-submitted';

			}

		}

		/**
		 * Checking to make sure the same choice isn't listed multiple times.
		 */
		$choices = [];
		if( isset( $this->formData['first_choice'] ) && !empty( $this->formData['first_choice'] ) ) {
			if( isset( $this->formData['first_choice']['school'] ) && !empty( $this->formData['first_choice']['school'] ) ) {
				$choices[] = $this->formData['first_choice']['school'];
			}
		}

		if( isset( $this->formData['second_choice'] ) && !empty( $this->formData['second_choice'] ) ) {
			if( isset( $this->formData['second_choice']['school'] ) && !empty( $this->formData['second_choice']['school'] ) ) {
				$choices[] = $this->formData['second_choice']['school'];
			}
		}

		if( isset( $this->formData['third_choice'] ) && !empty( $this->formData['third_choice'] ) ) {
			if( isset( $this->formData['third_choice']['school'] ) && !empty( $this->formData['third_choice']['school'] ) ) {
				$choices[] = $this->formData['third_choice']['school'];
			}
		}

		foreach( array_count_values($choices) as $choice => $count ) {
			if( $count > 1 ) {
				return 'duplicate-choices';
			}
		}
		//No duplicates, continue on.

		//Grab and store the Lottery Number for this submission.
		$lotteryService = new lotteryService( null, $this->emLookup );
		$lotteryNumber = $lotteryService->getLotteryNumber( $submission->getOpenEnrollment() );

		$race = $this->emLookup->getRepository( 'IIABMagnetBundle:Race' )->find( $this->formData['race']->getId() );

		$submission->setLotteryNumber( $lotteryNumber );
		$submission->setFirstName( $this->formData['first_name'] );
		$submission->setLastName( $this->formData['last_name'] );
		$submission->setBirthday( $this->formData['dob'] );
		$submission->setRace( $race );
		$submission->setAddress( $this->formData['address'] );
		$submission->setCity( $this->formData['city'] );
		$submission->setState( $this->formData['state'] );
		$submission->setZip( sprintf( '%05d', $this->formData['zip'] ) );
		$submission->setCurrentSchool( $this->formData['current_school'] );
		$submission->setCurrentGrade( $this->formData['current_grade'] );
		$submission->setNextGrade( $this->formData['next_grade'] );
		$submission->setPhoneNumber( $this->formData['phoneNumber']);
		$submission->setAlternateNumber( $this->formData['alternateNumber'] );
		$submission->setZonedSchool( $this->formData['zonedSchool'] );
		$submission->setSpecialAccommodations( isset( $this->formData['special_accommodations']) ? $this->formData['special_accommodations'] : 0 );
		$submission->setEmergencyContact(
			( isset( $this->formData['emergencyContact']) ) ? $this->formData['emergencyContact'] : '' );
				$submission->setEmergencyContactRelationship(
					( isset( $this->formData['emergencyContactRelationship']) ) ? $this->formData['emergencyContactRelationship'] : ''	);
				$submission->setEmergencyContactPhone(
					( isset( $this->formData['emergencyContactPhone']) ) ? $this->formData['emergencyContactPhone'] : null );
				$submission->setGender( $this->formData['gender'] );
				$submission->setParentFirstName( $this->formData['parentFirstName'] );
				$submission->setParentLastName( $this->formData['parentLastName'] );
				$submission->setUrl( rand( 10 , 999 ) );

		if( !empty( $this->formData['parentEmployment'] ) ) {

			if( !is_numeric( $this->formData['parentEmployment'] ) ){
				$this->formData['parentEmployment'] = $this->formData['parentEmployment'] == 'Yes';
			}

			$subData = new SubmissionData();
			$subData->setMetaKey( 'parent_employment' );
			$subData->setMetaValue( $this->formData['parentEmployment'] );
			$submission->addAdditionalDatum( $subData );

			if( !empty( $this->formData['parentEmployeeName'] ) ) {
				$subData = new SubmissionData();
				$subData->setMetaKey( 'parent_employee_name' );
				$subData->setMetaValue( $this->formData['parentEmployeeName'] );
				$submission->addAdditionalDatum( $subData );
			}

			if( !empty( $this->formData['parentEmployeeLocation'] ) ) {
				$subData = new SubmissionData();
				$subData->setMetaKey( 'parent_employee_location' );
				$subData->setMetaValue( $this->formData['parentEmployeeLocation'] );
				$submission->addAdditionalDatum( $subData );
			}
		}

		if( !empty( $this->formData['teacher'] ) ) {

			$subData = new SubmissionData();
			$subData->setMetaKey( 'teacher' );
			$subData->setMetaValue( $this->formData['teacher'] );
			$submission->addAdditionalDatum( $subData );
		}

		if( !empty( $this->formData['studentEmail'] ) ) {

			$subData = new SubmissionData();
			$subData->setMetaKey( 'student_email' );
			$subData->setMetaValue( $this->formData['studentEmail'] );
			$submission->addAdditionalDatum( $subData );
		}

		if( isset( $this->formData['student_status'] ) && $this->formData['student_status'] == 'new' ) {
			$submission->setNonHSVStudent( 1 );

			if( $this->formData['next_grade'] > 0 && $this->formData['next_grade'] < 13 ) {
				/****************************************
				 * Find the "on hold for additional information" Status by ID of 5
				 * On hold is for only High School NEW!!!!
				 ****************************************/
				$submission->setSubmissionStatus( $onHoldStatus );
			} else {
				$submission->setSubmissionStatus( $activeStatus );
			}
		}

		if( isset( $this->formData['parentEmail'] ) && !empty( $this->formData['parentEmail'] ) ) {
			$submission->setParentEmail( $this->formData['parentEmail'] );
		}

		$this->emLookup->persist( $submission );
		$magnetSchools = $this->emLookup->getRepository( 'IIABMagnetBundle:MagnetSchool' );

		/**
		 * Storing all the choices and the extra data that goes along with the choices.
		 */
		if( isset( $this->formData['first_choice'] ) && !empty( $this->formData['first_choice'] ) ) {
			if( isset( $this->formData['first_choice']['school'] ) && !empty( $this->formData['first_choice']['school'] ) ) {
				$firstChoice = $magnetSchools->find( $this->formData['first_choice']['school'] );
				$submission->setFirstChoice( $firstChoice );

				if( isset( $this->formData['first_choice']['sibling'] ) && $this->formData['first_choice']['sibling'] == 1 ) {
					//Added in Sibling Data.
					$firstChoiceSubmissionData = new SubmissionData();
					$firstChoiceSubmissionData->setMetaKey( 'First Choice Sibling ID' );
					$firstChoiceSubmissionData->setMetaValue( $this->formData['first_choice']['siblingID'] );
					$firstChoiceSubmissionData->setSubmission( $submission );
					$this->emLookup->persist( $firstChoiceSubmissionData );
				}
			}
		}

		if( isset( $this->formData['second_choice'] ) && !empty( $this->formData['second_choice'] ) ) {
			if( isset( $this->formData['second_choice']['school'] ) && !empty( $this->formData['second_choice']['school'] ) ) {
				$secondChoice = $magnetSchools->find( $this->formData['second_choice']['school'] );
				$submission->setSecondChoice( $secondChoice );

				if( isset( $this->formData['second_choice']['sibling'] ) && $this->formData['second_choice']['sibling'] == 1 ) {
					//Added in Sibling Data.
					$secondChoiceSubmissionData = new SubmissionData();
					$secondChoiceSubmissionData->setMetaKey( 'Second Choice Sibling ID' );
					$secondChoiceSubmissionData->setMetaValue( $this->formData['second_choice']['siblingID'] );
					$secondChoiceSubmissionData->setSubmission( $submission );
					$this->emLookup->persist( $secondChoiceSubmissionData );
				}
			}
		}

		if( isset( $this->formData['third_choice'] ) && !empty( $this->formData['third_choice'] ) ) {
			if( isset( $this->formData['third_choice']['school'] ) && !empty( $this->formData['third_choice']['school'] ) ) {
				$thirdChoice = $magnetSchools->find( $this->formData['third_choice']['school'] );
				$submission->setThirdChoice( $thirdChoice );

				if( isset( $this->formData['third_choice']['sibling'] ) && $this->formData['third_choice']['sibling'] == 1 ) {
					//Added in Sibling Data.
					$thirdChoiceSubmissionData = new SubmissionData();
					$thirdChoiceSubmissionData->setMetaKey( 'Third Choice Sibling ID' );
					$thirdChoiceSubmissionData->setMetaValue( $this->formData['third_choice']['siblingID'] );
					$thirdChoiceSubmissionData->setSubmission( $submission );
					$this->emLookup->persist( $thirdChoiceSubmissionData );
				}
			}
		}

		$choices = [
				'first_choice',
				'second_choice',
				'third_choice',
		];

		foreach( $choices as $school_choice_id ){

				if( isset( $this->formData[$school_choice_id] ) && $this->formData[$school_choice_id] ) {

						foreach( $choices as $focus_choice_id ){

								if (isset($this->formData[$school_choice_id][$focus_choice_id.'_focus']) && $this->formData[$school_choice_id][$focus_choice_id.'_focus']) {

										$choiceFocus = new SubmissionData();
										$choiceFocus->setMetaKey( $school_choice_id.'_'.$focus_choice_id.'_focus');
										$choiceFocus->setMetaValue($this->formData[$school_choice_id][$focus_choice_id.'_focus']);
										$choiceFocus->setSubmission($submission);
										$this->emLookup->persist( $choiceFocus );

										for( $i = 1; $i <= 3; $i++ ){
												if (isset($this->formData[$school_choice_id][$focus_choice_id.'_focus_extra_'.$i]) && $this->formData[$school_choice_id][$focus_choice_id.'_focus_extra_'.$i]) {
														$choiceFocusExtra = new SubmissionData();
														$choiceFocusExtra->setMetaKey($school_choice_id.'_'.$focus_choice_id.'_focus_extra_'.$i);
														$choiceFocusExtra->setMetaValue($this->formData[$school_choice_id][$focus_choice_id.'_focus_extra_'.$i]);
														$choiceFocusExtra->setSubmission($submission);
														$this->emLookup->persist( $choiceFocusExtra );
												}
										}
								}
						}

				}

		}

		/**
		 * Store the current Student Grades into the SubmissionGrades, so the can be used to look up why a student got into the submission.
		 */

		$grades = $this->getStudentService->getGrades( $submission->getStateID() );

		if( $grades != null && count( $grades ) > 0 ) {
			foreach( $grades as $grade ) {

				$year_offset = $grade->getAcademicYear();
				if( $grade->getAcademicYear() > 1 ){
					$academicYearService = new MagnetAcademicYearService( $this->emLookup );
					$academic_year = $academicYearService->getAcademicYear();
					$year_offset = $year_offset - $academic_year;
				}

				$submissionGrade = new SubmissionGrade();
				$submissionGrade->setSubmission( $submission );
				$submissionGrade->setAcademicYear( $year_offset );
				$submissionGrade->setAcademicTerm( $grade->getAcademicTerm() );
				$submissionGrade->setCourseTypeID( $grade->getCourseTypeID() );
				$submissionGrade->setCourseType( $grade->getCourseType() );
				$submissionGrade->setCourseName( $grade->getCourseName() );
				$submissionGrade->setSectionNumber( $grade->getSectionNumber() );
				$submissionGrade->setNumericGrade( $grade->getNumericGrade() );
				$this->emLookup->persist( $submissionGrade );

				$submission->addGrade( $submissionGrade );
			}
		}

		$additional_data = $this->getStudentService->getAdditionalData( $submission->getStateID() );
		if( $additional_data != null && count( $additional_data ) > 0 ) {
			foreach( $additional_data as $datum ) {

				$submissionData = new submissionData();
				$submissionData->setSubmission( $submission );
				$submissionData->setMetaKey( $datum->getMetaKey() );
				$submissionData->setMetaValue( $datum->getMetaValue() );
				$this->emLookup->persist( $submissionData );
			}
		}

		//Flush the Database to store the information.
		$this->emLookup->flush();

		$calculate_gpa = new CalculateGPAService( $this->emLookup );
		$calculated_gpa = $calculate_gpa->calculateGPA( $submission );
		if( !empty( $calculated_gpa ) ) {
			$gpa_data = new SubmissionData();
			$gpa_data->setSubmission( $submission );
			$gpa_data->setMetaValue( $calculated_gpa );
			$gpa_data->setMetaKey('calculated_gpa');
			$this->emLookup->persist( $gpa_data );
		}

		$recommendation_urls = $this->recommendationService->getAllRecommendationURLs( $submission, true );
		foreach( $recommendation_urls as $key => $value ) {
			$submissionData = new submissionData();
			$submissionData->setSubmission( $submission );
			$submissionData->setMetaKey( $key );
			$submissionData->setMetaValue( $value );
			$this->emLookup->persist( $submissionData );
		}

		$learner_screening_device_url = $this->learnerScreeningDeviceService->getLearnerScreeningDeviceURL( $submission, true );
		if( !empty( $learner_screening_device_url ) ) {
			$submissionData = new submissionData();
			$submissionData->setSubmission( $submission );
			$submissionData->setMetaKey( 'learner_screening_device_url' );
			$submissionData->setMetaValue( $learner_screening_device_url );
			$this->emLookup->persist( $submissionData );
		}

		//Flush the Database to store the information.
		$this->emLookup->flush();

		//Store the new SubmissionID into the session.

		$this->formData['submissionID'] = 'SPECIAL-' . $openEnrollment[0]->getConfirmationStyle() . '-' . $submission->getId();

		$this->session->set( 'magnet-formData' , base64_encode( serialize( $this->formData ) ) );

		// if( isset( $this->formData['parentEmail'] ) && !empty( $this->formData['parentEmail'] ) ) {
		// 	//Send Email to parent email if there is a parent Email passed in.

		// 	$this->emailLookup->sendConfirmationEmail( $submission );
		// }
		// $this->emailLookup->sendStudentWritingPromptEmail( $submission );
		// $this->emailLookup->sendTeacherRecommendationFormsEmail( $submission );
		// $this->emailLookup->sendLearnerScreeningDeviceEmail( $submission );
		$this->startConfirmations( $submission );

		return 'success';
	}

	/**
	 * Starts sending confirmations if not already started
	 *
	 * @param $submission
	 *
	 */
	private function startConfirmations( $submission ) {
		$maybe_process = $this->emLookup->getRepository('IIABMagnetBundle:Process')
			->findBy([
				'openEnrollment' => $submission->getOpenEnrollment(),
				'event' => 'email',
				'type' => 'confirmation'
			]);

		if( !$maybe_process ){
			$maybe_process = new \IIAB\MagnetBundle\Entity\Process();
			$maybe_process
				->setOpenEnrollment( $submission->getOpenEnrollment() )
				->setEvent('email')
				->setType('confirmation');

			$this->emLookup->persist( $maybe_process );
			$this->emLookup->flush();
		}
	}
}