<?php
/**
 * Company: Image In A Box
 * Date: 3/10/15
 * Time: 2:17 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Controller;

use IIAB\MagnetBundle\Command\GeneratePDFCommand;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use IIAB\MagnetBundle\Entity\SubmissionData;

/**
 * Class AjaxController
 * @package IIAB\MagnetBundle\Controller
 * @Route("ajax/")
 */
class AjaxController extends Controller {


	/**
	 * @Route( "process/" , name="ajax_process")
	 */
	public function processAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$action = strtolower( $request->get('action') );

		if( $action == '' ) {
			exit();
		}

		switch( $action ) {

			case 'awarded';
				$openEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneBy(array(
					'active' => 1
				));
				$fileLocation = $this->get('magnet.pdf')->awardedReport( $openEnrollment );
				echo $fileLocation;
				break;

			case 'waitlist';
				$openEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneBy(array(
					'active' => 1
				));
				$fileLocation = $this->get('magnet.pdf')->waitListReport( $openEnrollment );
				echo $fileLocation;
				break;

			case 'denied';
				$openEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneBy(array(
					'active' => 1
				));
				$fileLocation = $this->get('magnet.pdf')->deniedReport( $openEnrollment );
				echo $fileLocation;
				break;
		}

		exit();
	}

	/**
	 * @Route( "keep-alive/" , name="ajax_keep_alive")
	 */
	public function keepAliveAction(){

		$securityContext = $this->get('security.authorization_checker');

		if( $securityContext->isGranted('ROLE_ADMIN') ){
			return new JsonResponse(array('role' => 'admin'));
		}

		return new JsonResponse(array('role' => 'user'));
	}

	  	/**
   	* @Route( "resend-email/" , name="ajax_resend_email")
   	*/
  	public function resendEmailAction(){

  		$response = [
  			'error' => false,
  			'success' => false
  		];

  		$securityContext = $this->get('security.authorization_checker');

	    if( $securityContext->isGranted('ROLE_ADMIN') ){

	    	$request = $this->get('request_stack')->getCurrentRequest();
			$action = strtolower( $request->get('email_type') );
			$submission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )
				->find( $request->get('submission_id') );

			if( empty( $submission ) ){
				$response['error'] = 'No Submission Found';
					return new JsonResponse( $response );
			}

			if( $request->get('email_address') ){
				$submission->setParentEmail( $request->get('email_address') );
			}

			$mailer = $this->get('magnet.email');
			switch( $action ){
				case '':
					$response['error'] = 'No Action Selected';
					break;
				case 'confirmation':

					$mailer->sendConfirmationEmail( $submission );

					$result_data = '';
					$data = $submission->getAdditionalData(true);
					foreach( $data as $datum ){

						if( strpos($datum->getMetaKey(), 'Confirmation') !== false ){
							if( empty($result_data)
								|| $result_data->getId() < $datum->getId()
							){
								$result_data = $datum;
							}
						}
					}

					$response['success'] = ( !empty( $result_data ) ) ? ': '. $result_data->getMetaKey() .' '. $result_data->getMetaValue() : '';
					break;
				case 'recommendation_english':
				case 'recommendation_math':
				case 'recommendation_counselor':
				case 'learner_screening_device':

					$keys = [
		                'english' => 'English Recommendation Resend',
		                'math' => 'Math Recommendation Resend',
		                'counselor' => 'Counselor Recommendation Resend',
		                'learner_screening_device' => 'Learner Screening Device Resend',
		            ];

					if( strpos($action,'recommendation') !== false ){
						$recommendation_type = explode( '_', $action )[1];
						$key = $keys[ $recommendation_type ];
					} else {
						$key = $keys[ $action ];
					}
					$is_pending = $submission->getAdditionalDataByKey( $key );

					if(
						$is_pending != null
						&& $is_pending->getMetaValue() == 'pending'
					){
						$response['error'] = 'Email Pending';
						break;
					}

					$submission_data = new SubmissionData();
					$submission_data->setSubmission( $submission );
					$submission_data->setMetaKey( $key );
					$submission_data->setMetaValue( 'pending' );

					$submission->addAdditionalDatum( $submission_data );

					$this->getDoctrine()->getManager()->persist( $submission_data );
					$this->getDoctrine()->getManager()->persist( $submission );
	  				$this->getDoctrine()->getManager()->flush();

					$response['success'] = ' Resent';
					break;
				case 'submission-status':

					switch( $submission->getSubmissionStatus()->getId() ){
						case 6:
							$offer = $submission->getOffered();
							$mailer->sendAwardedEmail( $offer );
							break;

						case 7:
							$offer = $submission->getOffered();
							$mailer->sendAcceptedEmail( $offer );
							break;

						case 8:
							$offer = $submission->getOffered();
							$mailer->sendDeclinedEmail( $offer );
							break;

						case 9:
							$mailer->sendWaitListEmail( $submission );
							break;

						case 13:
							$offer = $submission->getOffered();
							$mailer->sendDeclinedWaitListEmail( $offer );
							break;

						case 3:
							$mailer->sendDeniedEmail( $submission );
							break;

					}

					$mailer->sendConfirmationEmail( $submission );

					$response['success'] = ' Resent';
					break;
			}

			return new JsonResponse( $response );
	    }

    	exit();
  	}
}