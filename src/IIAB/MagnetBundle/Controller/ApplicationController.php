<?php
namespace IIAB\MagnetBundle\Controller;

use IIAB\MagnetBundle\Form\Type\ApplicationType;
use IIAB\MagnetBundle\Form\Type\StartApplicationType;
use IIAB\MagnetBundle\Form\Type\WritingSampleType;
use IIAB\MagnetBundle\Service\HandleFormSubmissionService;
use IIAB\MagnetBundle\Service\EmailService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use IIAB\MagnetBundle\Service\RecommendationService;
use IIAB\MagnetBundle\Entity\SubmissionData;

class ApplicationController extends Controller {

	/**
	 * @Route("/" , name="magnet_index")
	 * @Template()
	 *
	 * @param Request $request
	 *
	 * @return array
	 */
	public function indexAction( Request $request ) {

		$this->emptySession( $request );

		$session = $request->getSession();

		$sessionData = $this->getSessionData( $session );

		$lateEnrollment = $session->get( 'lateEnrollment' );
		$lateEnrollment = ( isset( $lateEnrollment ) ) ? $lateEnrollment : false;

		$form = $this->createForm( StartApplicationType::class, $sessionData );

		$form->handleRequest( $request );

		if( $form->isSubmitted() && $form->isValid() ) {

			$formData = $form->getData();
			$formData['step']++;
			$session->set( 'magnet-formData' , base64_encode( serialize( $formData ) ) );
			return $this->redirect( $this->generateUrl( 'magnet_app_step' , array( 'step' => $formData['step'] ) ) );
		}

		return array( 'form' => $form->createView(), 'lateEnrollment' => $lateEnrollment );
	}

	/**
	 * Use this to pass in the file locations
	 *
	 * @Template("@IIABMagnet/Application/help.documents.html.twig")
	 *
     * @param Request $request
     *
	 * @return array
	 */
	public function helpDocumentsAction( Request $request ) {

		$links = [ ];

        $session = $request->getSession();
        $sessionData = $this->getSessionData( $session );

        // if ( isset( $sessionData['next_grade'] ) && ( $sessionData['next_grade'] < 6 || $sessionData['next_grade'] == 99 ) ) {
        //     $pdfs = [
        //         'magnet.application.information' => 'magnet.application.information.file',
        //         'magnet.application.faqs' => 'magnet.application.faqs.file',
        //         'magnet.application.overview' => 'magnet.application.overview.file',
        //         'magnet.application.details.elementary' => 'magnet.application.details.elementary.file',
        //     ];
        // } else if ( isset( $sessionData['next_grade'] ) && $sessionData['next_grade'] > 5 && $sessionData['next_grade'] < 9) {
        //     $pdfs = [
        //         'magnet.application.information' => 'magnet.application.information.file',
        //         'magnet.application.faqs' => 'magnet.application.faqs.file',
        //         'magnet.application.overview' => 'magnet.application.overview.file',
        //         'magnet.application.details.middle' => 'magnet.application.details.middle.file',
        //     ];
        // } else if ( isset( $sessionData['next_grade'] ) && $sessionData['next_grade'] > 8 && $sessionData['next_grade'] < 13) {
        //     $pdfs = [
        //         'magnet.application.information' => 'magnet.application.information.file',
        //         'magnet.application.faqs' => 'magnet.application.faqs.file',
        //         'magnet.application.overview' => 'magnet.application.overview.file',
        //         'magnet.application.details.high' => 'magnet.application.details.high.file',
        //     ];
        // } else {
        //     $pdfs = [
        //         'magnet.application.information' => 'magnet.application.information.file',
        //         'magnet.application.faqs' => 'magnet.application.faqs.file',
        //         'magnet.application.overview' => 'magnet.application.overview.file',
        //         'magnet.application.details.elementary' => 'magnet.application.details.elementary.file',
        //         'magnet.application.details.middle' => 'magnet.application.details.middle.file',
        //         'magnet.application.details.high' => 'magnet.application.details.high.file',
        //     ];
        // }

        $pdfs = [
			'Specialty Schools Application Information' => 'Specialty_Schools_Application_Information.docx',
			'Specialty School Guidelines' => 'Specialty_School_Guidelines-CLEAN.pdf',
			'TCS Magnet Guidelines' => 'Magnet_Guidelines-CLEAN.pdf',
			'TASPA Guidelines' => 'TASPA-Clean.pdf',
			'Fine Arts Academy Guidelines' => 'BHS-Arts_Guidelines-CLEAN.pdf',
			'Central High School IB Guidelines' => 'CHs_IB_Guidelines-CLEAN.pdf',
			'Audition Dates for TFAA and TASPA' => '2018_AuditionDates.TFAA.TASPA.pdf',
		];

        $directory = ( $sessionData['step'] > 0 ) ? '../' : '';
		$directory .= ( $this->get('kernel')->getEnvironment() ) ? '../pdf/' : 'pdf/';
	    // foreach( $pdfs as $label => $file_name ){

		   //  if(  strip_tags( $this->container->get( 'translator' )->trans( $file_name ) ) != $file_name ) {
     //            $links[] = sprintf('<a onclick="window.open(this.href);return false;" href="%s">%s</a>',
     //                $directory . strip_tags($this->container->get('translator')->trans($file_name)),
     //                strip_tags($this->container->get('translator')->trans($label))
     //            );
     //        }
     //    }
		foreach( $pdfs as $label => $file_name ){

	    	$links[] = sprintf('<a onclick="window.open(this.href);return false;" href="%s">%s</a>',
                    $directory . strip_tags($file_name),
                    strip_tags($label)
                );
        }

		$links[] = sprintf( '<a class="helper" href="#">%s</a>' , strip_tags( $this->container->get( 'translator' )->trans( 'magnet.application.help' ) ) );


		return [ 'links' => $links ];
	}

	/**
	 * @Route( "/step-{step}/" , name="magnet_app_step" , defaults={"step":1})
	 * @Template("IIABMagnetBundle:Application:index.html.twig")
	 *
	 * @param Request $request
	 * @param int     $step
	 *
	 * @return array
	 */
	public function stepAction( Request $request , $step = 1 ) {

		$session = $request->getSession();
		$sessionData = $this->getSessionData( $session );

		foreach( $sessionData as $index => $datum ){

			if ( is_object( $datum ) ) {
        		$class = ($datum instanceof Proxy)
            		? get_parent_class($datum)
            		: get_class($datum);

	    		if( !$this->getDoctrine()->getManager()->getMetadataFactory()
					->isTransient($class) ){

					$sessionData[$index] = $this->getDoctrine()->getEntityManager()->merge($datum);
				}
			}
			// an array of form data from session
			//$entity = $data['my_entity'];

			// merge() returns the managed entity
			//$entity = $this->getDoctrine()->getEntityManager()->merge($entity);

			// update the form data array
			//$data['my_entity'] = $entity;
		}

		if( isset( $sessionData['race'] ) && $step == 1 && isset( $sessionData['student_status'] ) && $sessionData['student_status'] == 'new' ) {
			$race = $this->getDoctrine()->getManager()->merge( $sessionData['race'] );
			$sessionData['race'] = $race;
		}

        $sessionData['open_enrollment_selector'] = in_array( $this->container->get( 'kernel' )->getEnvironment() , array( 'test' , 'dev' ) );

        $emailService = new EmailService(
			$this->getParameter('swiftmailer.sender_address'),
         	$this->getDoctrine()->getManager() ,
         	$this->container->get( 'twig' ),
         	$this->container->get( 'mailer' ),
         	$this->container->get( 'router' )
        );

		$handleFormService = new HandleFormSubmissionService (
			false,
			$this->getDoctrine() ,
			$this->container->get( 'translator' ),
			$emailService
		);

		if( $sessionData['step'] == 0 || !isset( $sessionData['student_status'] ) ) {
			return $this->redirect( $this->generateUrl( 'magnet_index' ) );
		}
		$sessionData['step'] = $step;

		//$form = $this->createForm( ApplicationType::class);

		$form = $this->createForm( ApplicationType::class, $sessionData, [
		 	'emLookup' => $this->getDoctrine()->getManager()
		]);
		$form->handleRequest( $request );

		if( $form->isSubmitted() && $form->isValid() ) {
			$formData = $form->getData();

			$url = $handleFormService->handleFormSubmission( $formData , $form , $session );

			if( isset( $url[1]) ){
				return $this->redirect( $this->generateUrl( $url[0] , $url[1] ) );
			} else {
				return $this->redirect( $this->generateUrl( $url[0] ) );
			}
		}

		return array( 'form' => $form->createView() , 'step' => $step, 'session' => $sessionData );
	}

	/**
	 * @Route("/successful-submission/" , name="magnet_successful" )
	 * @param Request $request
	 *
	 * @return array
	 */
	public function successfullySubmittedAction( Request $request ) {

		//Store the submission ID first, then clear out session.
		$session = $request->getSession();
		$sessionData = $this->getSessionData( $session );
		$confirmation = isset( $sessionData['submissionID'] ) ? $sessionData['submissionID'] : 0;
		$studentStatus = isset( $sessionData['student_status'] ) ? $sessionData['student_status'] : 'current';
		$this->emptySession( $request );

		$sessionData['submissionID'] = '123-1016';
		$submission_id = explode( '-', $sessionData['submissionID'] );
		$submission_id = array_values(array_slice($submission_id, -1))[0];

		$submission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->findOneBy( [
			'id' => $submission_id
		]);

		$afterSubmissionDocuments = $this->getAfterSubmissionDocuments( $submission );

		$learner_screening_device_printout_url = '';
		if( $submission->doesRequire( 'learner_screening_device' )
			&& empty( $submission->getAdditionalDataByKey('homeroom_teacher_email') )
		){
			$learner_screening_device_url = $submission->getAdditionalDataByKey('learner_screening_device_url')->getMetaValue();
			$learner_screening_device_printout_url = $this->generateUrl( 'learner_screening_device_printout' , [ 'uniqueURL' => $learner_screening_device_url], UrlGeneratorInterface::ABSOLUTE_URL );
		}

		$recommendations_printout_url = '';
		if( $submission->doesRequire( 'recommendations' )
			&& (
				empty( $submission->getAdditionalDataByKey('math_teacher_email') )
				|| empty( $submission->getAdditionalDataByKey('english_teacher_email') )
				|| empty( $submission->getAdditionalDataByKey('counselor_email') )
			)
		){
			$recommendations_printout_url = $this->generateUrl( 'recommendation_printout', [ 'uniqueURL' =>  $submission->getId() .'.'. $submission->getUrl()], UrlGeneratorInterface::ABSOLUTE_URL );
		}

		$writing_sample_printout_url = '';
		if( $submission->doesRequire( 'writing_prompt' )
			&& empty( $submission->getAdditionalDataByKey('student_email') )
		){
			$writing_sample_printout_url = $this->generateUrl( 'writing_sample_printout', [ 'uniqueURL' =>  $submission->getId() .'.'. $submission->getUrl()], UrlGeneratorInterface::ABSOLUTE_URL );
		}

		$activeCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'active',
            'type' => 'screen'
        ) );

        if( $activeCorrespondence == null ) {

			$template = $this->get('twig')->createTemplate(
				file_get_contents( $this->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Application/successfullySubmitted.html.twig')
			);
		} else {
			$template = $this->get('twig')->createTemplate( $activeCorrespondence->getTemplate() );
		}

		return $this->render( $template, [
			'confirmation' => $confirmation ,
			'studentStatus' => $studentStatus,
			'submission' => $submission,
			'afterSubmissionDocuments' => $afterSubmissionDocuments,
			'writing_sample_printout_url' => $writing_sample_printout_url,
			'learner_screening_device_printout_url' => $learner_screening_device_printout_url,
			'recommendations_printout_url' => $recommendations_printout_url,
		]);
	}

	/**
	 * @Route("/on-hold-submission/" , name="magnet_new_submission")
	 * @Template("IIABMagnetBundle:Application:onHoldSubmission.html.twig")
	 * @param Request $request
	 *
	 * @return array
	 */
	public function onHoldSubmissionAction( Request $request ) {

		$session = $request->getSession();
		$sessionData = $this->getSessionData( $session );
		$confirmation = isset( $sessionData['submissionID'] ) ? $sessionData['submissionID'] : 0;
		$grade = isset( $sessionData['next_grade'] ) ? $sessionData['next_grade'] : 0 ;
		if( $grade > 5 && $grade < 13 ) {
			$highSchool = 1;
		} else {
			$highSchool = 0;
		}

		$submission_id = explode( '-', $sessionData['submissionID'] );
		$submission_id = array_values(array_slice($submission_id, -1))[0];

		$submission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->findOneBy( [
			'id' => $submission_id
		]);

		$recommendationService = $this->get( 'magnet.recommendation' );
		$recommendationService->setAllRecommendationURLs( $submission );

		$this->emptySession( $request );

		$afterSubmissionDocuments = $this->getAfterSubmissionDocuments( $submission );

		$learner_screening_device_printout_url = '';
		if( $submission->doesRequire( 'learner_screening_device' )
			&& empty( $submission->getAdditionalDataByKey('homeroom_teacher_email') )
		){
			$learner_screening_device_url = $submission->getAdditionalDataByKey('learner_screening_device_url')->getMetaValue();
			$learner_screening_device_printout_url = $this->generateUrl( 'learner_screening_device_printout' , [ 'uniqueURL' => $learner_screening_device_url], UrlGeneratorInterface::ABSOLUTE_URL );
		}

		$recommendations_printout_url = '';
		if( $submission->doesRequire( 'recommendations' )
			&& (
				empty( $submission->getAdditionalDataByKey('math_teacher_email') )
				|| empty( $submission->getAdditionalDataByKey('english_teacher_email') )
				|| empty( $submission->getAdditionalDataByKey('counselor_email') )
			)
		){
			$recommendations_printout_url = $this->generateUrl( 'recommendation_printout', [ 'uniqueURL' =>  $submission->getId() .'.'. $submission->getUrl()], UrlGeneratorInterface::ABSOLUTE_URL );
		}

		$writing_sample_printout_url = '';
		if( $submission->doesRequire( 'writing_prompt' )
			&& empty( $submission->getAdditionalDataByKey('student_email') )
		){
			$writing_sample_printout_url = $this->generateUrl( 'writing_sample_printout', [ 'uniqueURL' =>  $submission->getId() .'.'. $submission->getUrl()], UrlGeneratorInterface::ABSOLUTE_URL );
		}
		return array(
			'confirmation' => $confirmation ,
			'highschool' => $highSchool,
			'submission' => $submission,
			'afterSubmissionDocuments' => $afterSubmissionDocuments,
			'writing_sample_printout_url' => $writing_sample_printout_url,
			'learner_screening_device_printout_url' => $learner_screening_device_printout_url,
			'recommendations_printout_url' => $recommendations_printout_url,
		);
	}

	/**
	 * @Route("/no-student-found/" , name="magnet_no_student_found")
	 * @Template()
	 * @param Request $request
	 *
	 * @return array
	 */
	public function noStudentFoundAction( Request $request ) {

		$this->emptySession( $request );

		return array();
	}

	/**
	 * @Route("/no-open-enrollment/" , name="magnet_no_open_enrollment")
	 * @Template("@IIABMagnet/OpenEnrollment/closedEnrollment.html.twig")
	 * @param Request $request
	 *
	 * @return array
	 */
	public function noEnrollmentAction( Request $request ) {

		$this->emptySession( $request );

		return array();
	}

	/**
	 * @Route("/incorrect-information/" , name="magnet_incorrect")
	 * @Template()
	 *
	 * @param Request $request
	 *
	 * @return array
	 */
	public function incorrectInformationAction( Request $request ) {

		$this->emptySession( $request );

		return array();
	}

	/**
	 * @Route("/no-zoned-school/" , name="magnet_no_zoned_school")
	 * @Template()
	 *
	 * @param Request $request
	 *
	 * @return array
	 */
	public function noZonedSchoolAction( Request $request ) {

		$this->emptySession( $request );

		return array();
	}

	/**
	 * @Route("/out-of-district/" , name="magnet_out_of_district")
	 * @Template()
	 * @param Request $request
	 *
	 * @return array
	 */
	public function outOfDistrictAction( Request $request ) {

		$this->emptySession( $request );

		return array();
	}

	/**
	 * @Route("/already-submitted/", name="magnet_already_submitted")
	 * @Template()
	 * @param Request $request
	 *
	 * @return array
	 */
	public function alreadySubmittedAction( Request $request ) {
		$this->emptySession( $request );

		return array();
	}

	/**
	 * @Route("/not-eligible/" , name="magnet_not_eligible")
	 * @Template()
	 * @param Request $request
	 *
	 * @return array
	 */
	public function notEligibleAction( Request $request ) {

		$this->emptySession( $request );

		return array();
	}

	/**
	 * @Route("/exit-application/" , name="magnet_exit_application")
	 * @Template()
	 * @param Request $request
	 *
	 * @return array
	 */
	public function exitApplicationAction( Request $request ) {

		$this->emptySession( $request );

		return array();
	}

	/**
	 * @Template()
	 *
	 * @return array
	 */
	//public function displaySessionDataAction( $step = 0 ) {
	public function displaySessionDataAction( $step = 0, Request $request = null ) {

		if( !empty( $request ) ){
			$session = $request->getSession();
		} else {
			$session = $this->get('request_stack')->getCurrentRequest()->getSession();
		}

		$sessionData = $this->getSessionData( $session );

		return array( 'session' => $sessionData , 'step' => $step );
	}

/**
	 * @Route("/writing/{uniqueURL}", name="writing_sample")
	 * @Template("IIABMagnetBundle:Application:writingSample.html.twig")
	 *
	 * @param Request $request
	 * @param string  $uniqueURL
	 *
	 * @return array|RedirectResponse
	 */
	public function writingSampleAction( Request $request , $uniqueURL ) {

		if( !empty( $uniqueURL ) ) {

			$url_parts = explode( '.', $uniqueURL );

			if( empty( $url_parts[1] ) ){
				return $this->redirect( $this->generateUrl( 'submission_notfound' ) );
			}

			$submission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->findOneBy( array(
					'id' => $url_parts[0],
					'url' => $url_parts[1],
			) );

			//URL did not match any Submissions, lets redirect them to not-found offered page.
			if( $submission == null ) {
				return $this->redirect( $this->generateUrl( 'submission_notfound' ) );
			}

			$writing_sample = $submission->getAdditionalDataByKey( 'writing_sample' );

			if( !empty( $writing_sample ) ){
				return $this->redirect(
					$this->generateUrl( 'writing_sample_submitted', [ 'uniqueURL' => $uniqueURL ] )
				);
			}

			$token = $this->getDoctrine()->getRepository( 'IIABTranslationBundle:LanguageToken' )->findOneBy([
				'token' => 'writing.prompt'
			]);
			$prompt = '';
			if( !empty( $token ) ){
				$language = $this->getDoctrine()->getRepository( 'IIABTranslationBundle:LanguageTranslation' )->find(1);

				$prompt = $this->getDoctrine()->getRepository( 'IIABTranslationBundle:LanguageTranslation' )->findOneBy([
					'languageToken' => $token->getId(),
					'language' => $language
				]);
				$prompt = $prompt->getTranslation();
			}

			$form = $this->createForm( WritingSampleType::class, null, [
	            'submission' => $submission,
	            'prompt' => $prompt
            ]);

			$form->handleRequest( $request );

			if( $form->isValid() ) {

				$formData = $form->getData();

				$subData = new SubmissionData();
				$subData->setMetaKey( 'writing_sample' );
				$subData->setMetaValue( $formData['writing_sample'] );
				$subData->setSubmission( $submission );
				$submission->addAdditionalDatum( $subData );
				$this->getDoctrine()->getManager()->persist( $subData );

				$subData = new SubmissionData();
				$subData->setMetaKey( 'writing_prompt' );
				$subData->setMetaValue( $formData['writing_prompt'] );
				$subData->setSubmission( $submission );
				$submission->addAdditionalDatum( $subData );
				$this->getDoctrine()->getManager()->persist( $subData );

				$this->getDoctrine()->getManager()->persist( $submission );
				$this->getDoctrine()->getManager()->flush();

				return $this->redirect( $this->generateUrl( 'writing_sample_submitted' , [ 'uniqueURL' => $uniqueURL ] ) );
			}

			return array(
				'form' => $form->createView() ,
				'submission' => $submission ,
			);
		}
		//Throw request not found. Error out.
		return $this->redirect( $this->generateUrl( 'submission_notfound' ) );
	}

	/**
	 * @Route("/writing/{uniqueURL}/submitted/", name="writing_sample_submitted")
	 * @Template("IIABMagnetBundle:Application:writingSampleSubmitted.html.twig")
	 * @param Request $request
	 *
	 * @return array
	 */
	public function writingSampleSubmittedAction( Request $request , $uniqueURL ) {

		if( empty( $uniqueURL ) ) {
			return $this->redirect( $this->generateUrl( 'submission_notfound' ) );
		}

		$url_parts = explode( '.', $uniqueURL );
		if( empty( $url_parts[1] ) ){
			return $this->redirect( $this->generateUrl( 'submission_notfound' ) );
		}

		$submission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->findOneBy( 	array(
				'id' => $url_parts[0],
				'url' => $url_parts[1],
		) );

		//URL did not match any Submissions, lets redirect them to not-found offered page.
		if( $submission == null ) {
			return $this->redirect( $this->generateUrl( 'submission_notfound' ) );
		}

		return array( 'submission' => $submission );
	}

	/**
	 * @Route("/submission_notfound/", name="submission_notfound")
	 * @Template("IIABMagnetBundle:Application:submission_notfound.html.twig")
	 * @param Request $request
	 *
	 * @return array
	 */
	public function noSubmissionFoundAction( Request $request ) {
		$this->emptySession( $request );

		return array();
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

	private function getAfterSubmissionDocuments( $submission ){

		$afterSubmissionDocuments = [];
		$finder = new Finder();

		$choices = ['First','Second','Third'];
		foreach( $choices as $choice ){
			if( !empty( $submission->{'get'.$choice.'Choice'}() ) ){

				$school = $submission->{'get'.$choice.'Choice'}();
				$program = $school->getProgram();
				$directory = 'uploads/program/'. $program->getId() .'/pdfs/';

				if( is_dir( $directory ) ){

					$label = $program->getAdditionalData('file_display_after_submission_label' );
					$label = ( count( $label ) ) ? $label[0]->getMetaValue() : 'Click here for important information: '. $school->__toString();

					$display_document = $program->getAdditionalData('file_display_after_submission' );

					if( count( $display_document ) && $display_document[0]->getMetaValue() ){

						$finder->files()->in($directory);
	            		foreach( $finder as $found ){
	                		$afterSubmissionDocuments[] = [
	                			'url' => $directory . $found->getFileName(),
	                			'label' => $label
	                		];
	            		}
	            	}
				}
			}
		}

		return $afterSubmissionDocuments;
	}
}