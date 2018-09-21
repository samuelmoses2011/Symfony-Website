<?php
/**
 * Company: Image In A Box
 * Date: 12/31/14
 * Time: 3:06 PM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Controller;

use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionGrade;
use IIAB\MagnetBundle\Form\Type\ApplicationType;
use IIAB\MagnetBundle\Form\Type\StartApplicationType;
use IIAB\MagnetBundle\Form\Type\OfferedType;
use IIAB\MagnetBundle\Service\HandleFormSubmissionService;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class SubmissionCRUDController extends CRUDController {


	public function customEditAction( $id = null ) {

		$submission = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Submission' );

		if( $submission != null ) {

		} else {
		}

		return $this->render('IIABMagnetBundle:Admin/Edit:edit.html.twig', array(
			'form' => '',
		));
	}

	public function createAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$session = $request->getSession();

		$sessionData = $this->getSessionData( $session );

		$handleFormService = new HandleFormSubmissionService( $this , $this->getDoctrine()->getManager() , true );

		if( isset( $sessionData['step'] ) && isset( $_GET['step'] ) && $sessionData['step'] > 0 && $_GET['step'] == $sessionData['step'] ) {
			$sessionData['isAdmin'] = true;
			$form = $this->createForm( ApplicationType::class , $sessionData );
			$step = $sessionData['step'];
		} else {

			$this->emptySession( $request );
			$form = $this->createForm( StartApplicationType::class , $sessionData );
			$step = 0;
		}

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$formData = $form->getData();

			if( $sessionData['step'] == 0 ) {
				$formData['step']++;
				$session->set( 'magnet-formData' , base64_encode( serialize( $formData ) ) );
				return $this->redirect( $this->generateUrl( 'admin_submission_create' , array( 'step' => $formData['step'] ) ) );
			} else {

				return $handleFormService->handleFormSubmission( $formData , $form , $session );
			}
		}

		$view = $form->createView();
        $this->get('twig')->getExtension('form')->renderer->setTheme($view, array_merge( $this->admin->getFormTheme(), ['IIABMagnetBundle:Admin:formError.html.twig']) );

		return $this->render('IIABMagnetBundle:Admin/Create:create.html.twig', array(
			'form' => $view,
			'step' => $step
		));
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/noOpenEnrollment.html.twig")
	 */
	public function noEnrollmentAction() {
		$admin_pool = $this->get('sonata.admin.pool');

		return array(
			'admin_pool' => $admin_pool
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/noStudentFound.html.twig")
	 */
	public function noStudentFoundAction() {
		$admin_pool = $this->get('sonata.admin.pool');

		return array(
			'admin_pool' => $admin_pool
		);
	}


	/**
	 * @Template("@IIABMagnet/Admin/Create/noZonedSchool.html.twig")
	 */
	public function noZonedSchoolAction() {

		$admin_pool = $this->get('sonata.admin.pool');

		return array(
			'admin_pool' => $admin_pool
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/outOfDistrict.html.twig")
	 */
	public function outOfDistrictAction() {
		$admin_pool = $this->get('sonata.admin.pool');

		return array(
			'admin_pool' => $admin_pool
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/notEligible.html.twig")
	 */
	public function notEligibleAction() {
		$admin_pool = $this->get('sonata.admin.pool');

		return array(
			'admin_pool' => $admin_pool
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/inCorrect.html.twig")
	 */
	public function inCorrectAction() {
		$admin_pool = $this->get('sonata.admin.pool');

		return array(
			'admin_pool' => $admin_pool
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/exitWithSaving.html.twig")
	 */
	public function exitWithSavingAction() {
		$admin_pool = $this->get('sonata.admin.pool');

		return array(
			'admin_pool' => $admin_pool
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/success.html.twig")
	 */
	public function successAction() {
		$admin_pool = $this->get('sonata.admin.pool');

		$session = $this->get('request_stack')->getCurrentRequest()->getSession();
		$sessionData = $this->getSessionData( $session );
		$confirmation = isset( $sessionData['submissionID'] ) ? $sessionData['submissionID'] : 0;
		$studentStatus = isset( $sessionData['student_status'] ) ? $sessionData['student_status'] : 'current';

		return array(
			'admin_pool' => $admin_pool,
			'confirmation' => $confirmation,
			'studentStatus' => $studentStatus
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/onHold.html.twig")
	 */
	public function onHoldAction() {

		$admin_pool = $this->get('sonata.admin.pool');
		$confirmation = isset( $sessionData['submissionID'] ) ? $sessionData['submissionID'] : 0;

		return array(
			'admin_pool' => $admin_pool,
			'highschool' => 1,
			'confirmation' => $confirmation,
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/alreadySubmitted.html.twig")
	 */
	public function alreadySubmittedAction() {
		$admin_pool = $this->get('sonata.admin.pool');



		return array(
			'admin_pool' => $admin_pool
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/offered.html.twig")
	 * @param null $id
	 *
	 * @return array
	 */
	public function offeredAction( $id = null ) {

		$admin_pool = $this->get('sonata.admin.pool');

		$request = $this->get('request_stack')->getCurrentRequest();

		$offered = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Offered' )->findOneBy( array(
			'submission' => $id
		) );

		//URL did not match any Offers, lets redirect them to not-found offered page.
		if( $offered == null ) {
			return $this->redirect( $this->generateUrl( 'admin_submission_offeredNotFound' , array( 'id' => $id ) ) );
		}

		$placement = $this->getDoctrine()->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
			'openEnrollment' => $offered->getOpenEnrollment()
		) );

        $waitlisted_status = $this->getDoctrine()->getRepository('IIABMagnetBundle:SubmissionStatus')->find(9);

        $form = $this->createForm( OfferedType::class, $offered );

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$population_service = $this->get('magnet.population');

            if( $form->get( 'decline_offer' )->isClicked() || ( $form->has('decline_and_waitlist') && $form->get( 'decline_and_waitlist' )->isClicked() ) ) {

                if( $form->has('decline_and_waitlist') && $form->get( 'decline_and_waitlist' )->isClicked() ) {
                    //"Wait Listed" Status
                    $offeredAndDeclined = $waitlisted_status;
                } else {
                    //"Offered and Declined" Status
                    $offeredAndDeclined = $this->getDoctrine()->getRepository('IIABMagnetBundle:SubmissionStatus')->find(8);
                }

				$offered->setAccepted( 0 );
				$offered->setDeclined( 1 );
				$offered->setChangedDateTime( new \DateTime() );
				$offered->getSubmission()->setSubmissionStatus( $offeredAndDeclined );
				$offered->setAcceptedBy( 'phone' );

				$population_service->decline([
					'submission'=>$offered->getSubmission(),
					'school' => $offered->getAwardedSchool(),
				]);

				$afterPopulation = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:AfterPlacementPopulation' )->findOneBy( array(
					'magnetSchool' => $offered->getAwardedSchool() ,
					'openEnrollment' => $offered->getSubmission()->getOpenEnrollment() ,
				) , array( 'lastUpdatedDateTime' => 'DESC' ) );

				if( $afterPopulation != null ){
					$race = strtoupper( $offered->getSubmission()->getRace() );
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

					$this->getDoctrine()->getManager()->persist( $afterPopulation );
				}
				$this->getDoctrine()->getManager()->persist( $offered );
				$population_service->persist_and_flush();
				$this->getDoctrine()->getManager()->flush();

                if( $offeredAndDeclined == $waitlisted_status ){
                    $this->get( 'magnet.email' )->sendDeclinedWaitListEmail( $offered );
                } else {
                    $this->get( 'magnet.email' )->sendDeclinedEmail( $offered );
                }
			}

			if( $form->get( 'accept_offer' )->isClicked() ) {

				$offeredAndAccepted = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 7 );

				$offered->setAccepted( 1 );
				$offered->setDeclined( 0 );
				$offered->setChangedDateTime( new \DateTime() );
				$offered->setAcceptedBy( 'phone' );
				$offered->getSubmission()->setSubmissionStatus( $offeredAndAccepted );

				$this->getDoctrine()->getManager()->persist( $offered );
				$this->getDoctrine()->getManager()->flush();

				$this->get( 'magnet.email' )->sendAcceptedEmail( $offered );
			}

			return $this->redirect( $this->generateUrl( 'admin_submission_offered' , array( 'id' => $id ) ) );
		}

		return array(
			'admin_pool' => $admin_pool ,
			'form' => $form->createView() ,
			'offered' => $offered ,
			'registrationNew' => $placement->getRegistrationNewStartDate() ,
			'registrationCurrent' => $placement->getRegistrationCurrentStartDate() ,
		);
	}

	/**
	 * @Template("@IIABMagnet/Admin/Create/offeredNotFound.html.twig")
	 * @param null $id
	 *
	 * @return array
	 */
	public function offeredNotFoundAction( $id = null ) {

		$admin_pool = $this->get('sonata.admin.pool' );

		return array( 'admin_pool' => $admin_pool , 'id' => $id );
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 */
	private function emptySession( Request $request ) {

		$session = $request->getSession();

		if( $session->has( 'magnet-formData' ) ) {
			$session->remove( 'magnet-formData' );
		}
	}

	/**
	 * @param SessionInterface $session
	 *
	 * @return array
	 */
	private function getSessionData( SessionInterface $session ) {

		$sessionData = unserialize( base64_decode( $session->get( 'magnet-formData' , base64_encode( serialize( array( 'step' => 0 ) ) ) ) ) );

		return $sessionData;
	}
}