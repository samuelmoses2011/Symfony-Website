<?php
namespace IIAB\MagnetBundle\Service;


use IIAB\MagnetBundle\Entity\Offered;
use IIAB\MagnetBundle\Entity\Placement;
use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Finder\Finder;

class EmailService {

    private $from_email_alias = 'TCS Specialty Schools';

	private $emLookup;

    private $fromEmail;

    private $twigLookup;

    private $mailerLookup;

    private $routerLookup;

	function __construct(
        $fromEmail,
        $emLookup ,
        $twigLookup,
        $mailerLookup,
        $routerLookup
    ) {

		$this->fromEmail = $fromEmail;
        $this->emLookup = $emLookup;
        $this->twigLookup = $twigLookup;
        $this->mailerLookup = $mailerLookup;
        $this->routerLookup = $routerLookup;
	}

    /**
     * @param $reportDate
     * @return string
     */
    private function getHeader( $reportDate = null){
        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'header',
            'type' => 'email'
        ) );
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:header.email.twig' );

        return $template->render([ 'reportDate' => $reportDate, ]);
    }

    /**
     * @return string
     */
    private function getFooter(){
        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'footer',
            'type' => 'email'
        ) );
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:footer.email.twig' );

        return $template->render([]);
    }

	/**
	 * Sends the confirmation email to the parent's email address.
	 *
	 * TODO: Move the confirmation email here to group all emails into one class.
	 * @param Submission $submission
	 *
	 * @return bool
	 */
	public function sendConfirmationEmail( Submission $submission ) {

        if( empty( $submission->getParentEmail() ) ){
            return false;
        }

        $attemptedData = new SubmissionData();
        $attemptedData
            ->setMetaKey( 'Confirmation Attempted' )
            ->setMetaValue( date('M-d-Y') )
            ->setSubmission( $submission );

        $this->emLookup->persist( $attemptedData );
        $this->emLookup->flush();

        $afterSubmissionDocuments = [];
        $finder = new Finder();
        $choices = ['First','Second','Third'];
        foreach( $choices as $choice ){
            if( !empty( $submission->{'get'.$choice.'Choice'}() ) ){

                $school = $submission->{'get'.$choice.'Choice'}();
                $program = $school->getProgram();
                $directory = str_replace('/src/IIAB/MagnetBundle/Service', '', __DIR__). '/web/uploads/program/'. $program->getId() .'/pdfs/';

                if( is_dir( $directory ) ){

                    $label = $program->getAdditionalData('file_display_after_submission_label' );
                    $label = ( count( $label ) ) ? $label[0]->getMetaValue() : 'Click here for important information: '. $school->__toString();

                    $display_document = $program->getAdditionalData('file_display_after_submission' );

                    if( count( $display_document ) && $display_document[0]->getMetaValue() ){

                        $finder->files()->in($directory);
                        foreach( $finder as $found ){
                            $afterSubmissionDocuments[] = [
                                'url' => 'uploads/program/'. $program->getId() .'/pdfs/' . $found->getFileName(),
                                'label' => $label
                            ];
                        }
                    }
                }
            }
        }

        $learner_screening_device_printout_url = '';
        if( $submission->doesRequire( 'learner_screening_device' )
            && empty( $submission->getAdditionalDataByKey('homeroom_teacher_email') )
        ){
            $learner_screening_device_url = $submission->getAdditionalDataByKey('learner_screening_device_url')->getMetaValue();
            $learner_screening_device_printout_url = $this->routerLookup->generate( 'learner_screening_device_printout' , [ 'uniqueURL' => $learner_screening_device_url], UrlGeneratorInterface::ABSOLUTE_URL );
        }


        $recommendations_printout_url = '';
        if( $submission->doesRequire( 'recommendations' )
            && (
                empty( $submission->getAdditionalDataByKey('math_teacher_email') )
                || empty( $submission->getAdditionalDataByKey('english_teacher_email') )
                || empty( $submission->getAdditionalDataByKey('counselor_email') )
            )
        ){
            $recommendations_printout_url = $this->routerLookup->generate( 'recommendation_printout', [ 'uniqueURL' =>  $submission->getId() .'.'. $submission->getUrl()], UrlGeneratorInterface::ABSOLUTE_URL  );
        }

        $writing_sample_printout_url = '';
        if( $submission->doesRequire( 'writing_prompt' )
            && empty( $submission->getAdditionalDataByKey('student_email') )
        ){
            $writing_sample_printout_url = $this->routerLookup->generate( 'writing_sample_printout', [ 'uniqueURL' =>  $submission->getId() .'.'. $submission->getUrl()], UrlGeneratorInterface::ABSOLUTE_URL  );
        }

        if( $submission->getSubmissionStatus()->getId() != 1 ){
            //High School Template

            $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
                'active' => 1,
                'name' => 'highSchool',
                'type' => 'email'
            ) );
            //If no correspondence found load IIABMagnetBundle:Email:highSchool.email.twig
            $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:highSchool.email.twig' );
        } else {
            //Active Template

            $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
                'active' => 1,
                'name' => 'confirmation',
                'type' => 'email'
            ) );
            //If no correspondence found load IIABMagnetBundle:Email:confirmation.email.twig
            $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:confirmation.email.twig' );
        }

        $context = array(
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
            'submission' => $submission,
            'enrollment' => $submission->getOpenEnrollment()->getYear(),
            'confirmation' => 'SPECIAL-' . $submission->getOpenEnrollment()->getConfirmationStyle() . '-' . $submission->getId(),
            'studentStatus' => ( $submission->getSubmissionStatus()->getId() == 5) ? 'new' : 'current' ,
            'highschool' => ( $submission->getNextGrade() > 5 ),
            'afterSubmissionDocuments' => $afterSubmissionDocuments,
            'writing_sample_printout_url' => $writing_sample_printout_url,
            'learner_screening_device_printout_url' => $learner_screening_device_printout_url,
            'recommendations_printout_url' => $recommendations_printout_url,
        );

        $fromEmail = $this->fromEmail;
        $subject = $template->renderBlock( 'subject' , $context );
        $textBody = $template->renderBlock( 'body_text' , $context );
        $htmlBody = $template->renderBlock( 'body_html' , $context );

        $message = \Swift_Message::newInstance()
            ->setSubject( $subject )
            ->setFrom( $fromEmail, $this->from_email_alias )
            ->setBcc( 'mypickconfirm@gmail.com' )
            ->setTo( $submission->getParentEmail() );

        if( !empty( $htmlBody ) ) {
            $message->setBody( $htmlBody , 'text/html' )
                ->addPart( $textBody , 'text/plain' );
        } else {
            $message->setBody( $textBody );
        }
        //Enabled
        try {
            $return = $this->mailerLookup->send( $message );
            if( $return ){
                $attemptedData
                    ->setMetaKey( 'Confirmation Sent' );
                $this->emLookup->flush();
            }
            return $return;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return true;
        }
	}

    /**
     * Sends the student writing prompt email to the students's email address.
     *
     * @param Submission $submission
     *
     * @return bool
     */
    public function sendStudentWritingPromptEmail( Submission $submission ) {

        if( !$submission->doesRequire( 'writing_prompt' ) ){
            return;
        }

        $student_email = $submission->getAdditionalDataByKey( 'student_email' );
        $student_email = ( !empty( $student_email ) ) ? $student_email->getMetaValue() : '';

        if( $student_email ){

            $attemptedData = new SubmissionData();
            $attemptedData
                ->setMetaKey( 'Writing Prompt Attempted' )
                ->setMetaValue( date('M-d-Y') )
                ->setSubmission( $submission );

            $this->emLookup->persist( $attemptedData );
            $this->emLookup->flush();

            $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
                'active' => 1,
                'name' => 'writingPrompt',
                'type' => 'email'
            ) );
            //If no correspondence found load IIABMagnetBundle:Email:highSchool.email.twig
            $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:student.WritingPrompt.email.twig' );

            $context = array(
                'header' => $this->getHeader(),
                'footer' => $this->getFooter(),
                'submission' => $submission,
                'writingSampleURL' => $this->routerLookup->generate( 'writing_sample' , array(
                        'uniqueURL' => $submission->getId() .'.'. $submission->getUrl()
                ) , UrlGeneratorInterface::ABSOLUTE_URL ) ,
            );
            $fromEmail = $this->fromEmail;
            $subject = $template->renderBlock( 'subject' , $context );
            $textBody = $template->renderBlock( 'body_text' , $context );
            $htmlBody = $template->renderBlock( 'body_html' , $context );

            $message = \Swift_Message::newInstance()
                ->setSubject( $subject )
                ->setFrom( $fromEmail, $this->from_email_alias )
                ->setBcc( 'mypickconfirm@gmail.com' )
                ->setTo( $student_email );

            if( !empty( $htmlBody ) ) {
                $message->setBody( $htmlBody , 'text/html' )
                    ->addPart( $textBody , 'text/plain' );
            } else {
                $message->setBody( $textBody );
            }
            //Enabled
            try {
                $return = $this->mailerLookup->send( $message );
                if( $return ){
                    $attemptedData
                        ->setMetaKey( 'Writing Prompt Sent' );
                    $this->emLookup->flush();
                }
                return $return;
            } catch (Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return true;
            }
        }
    }

    /**
     * Sends the teacher recommendation forms to the students's teachers.
     *
     * @param Submission $submission
     *
     * @return bool
     */
    public function sendTeacherRecommendationFormsEmail( Submission $submission ) {

        if( !$submission->doesRequire('recommendations') ){
            return false;
        }

        $send_forms = [];
        foreach( MYPICK_CONFIG['eligibility_fields']['recommendations']['info_field'] as $key => $settings ){
            $send_forms[] = explode( '_', $key )[1];
        }

        $sent_forms = 0;
        foreach( $send_forms as $form ){

            $maybe_skip = false;

            $already_sent = $submission->getAdditionalDataByKey( ucwords( $form ).' Recommendation Sent' );
            if( !empty( $already_sent ) ){
                $maybe_skip = true;
            }

            $resend = $submission->getAdditionalDataByKey( ucwords( $form ).' Recommendation Resend' );
            if( !empty( $resend )
                && $resend->getmetaValue() != 'complete'
            ){
                $maybe_skip = false;
            }

            if( $maybe_skip ){
                continue;
            }

            $attemptedData = new SubmissionData();
            $attemptedData
                ->setMetaKey( ucwords($form) .' Recommendation Attempted' )
                ->setMetaValue( date('M-d-Y') )
                ->setSubmission( $submission );

            $this->emLookup->persist( $attemptedData );
            $this->emLookup->flush();

            $send_to_email = $submission->getAdditionalDataByKey( $form.'_teacher_email' );
            $send_to_email = ( !empty( $send_to_email ) ) ? $send_to_email->getMetaValue() : '';

            $schools = [];
            if( !empty( $submission->getFirstChoice() )
                && $submission->getFirstChoice()->doesRequire( 'recommendations' )
            ){
                $schools[] = $submission->getFirstChoice()->__toString();
            }
            if( !empty( $submission->getSecondChoice() )
                && $submission->getSecondChoice()->doesRequire( 'recommendations' )
            ){
                $schools[] = $submission->getSecondChoice()->__toString() .' ';
            }
            if( !empty( $submission->getThirdChoice() )
                && $submission->getThirdChoice()->doesRequire( 'recommendations' )
            ){
                $schools[] = $submission->getThirdChoice()->__toString();
            }
            $school_name = implode(' and ', $schools );

            if( $send_to_email ){

                $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
                    'active' => 1,
                    'name' => 'recommendation_teacher',
                    'type' => 'email'
                ) );

                //If no correspondence found
                $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:recommendation.teacher.email.twig' );
            } else {

                $send_to_email = $submission->getAdditionalDataByKey( $form.'_email' );
                $send_to_email = ( !empty( $send_to_email ) ) ? $send_to_email->getMetaValue() : '';

                if( $send_to_email ){

                    $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
                        'active' => 1,
                        'name' => 'recommendation_'.$form,
                        'type' => 'email'
                    ) );

                    //If no correspondence found
                    $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:recommendation.'.$form.'.email.twig' );
                }
            }

            if( $send_to_email ){

                $recommendation_url = $submission->getAdditionalDataByKey( 'recommendation_'.$form.'_url' );
                $recommendation_url = ( !empty( $recommendation_url ) ) ? $recommendation_url->getMetaValue() : 'ERROR';

                $context = array(
                    'header' => $this->getHeader(),
                    'footer' => $this->getFooter(),
                    'submission' => $submission,
                    'school_name' => $school_name,
                    'recommendation_url' => $this->routerLookup->generate( 'recommendation_form' , array(
                        'uniqueURL' => $recommendation_url
                    ) , UrlGeneratorInterface::ABSOLUTE_URL ) ,
                );
                $fromEmail = $this->fromEmail;
                $subject = $template->renderBlock( 'subject' , $context );
                $textBody = $template->renderBlock( 'body_text' , $context );
                $htmlBody = $template->renderBlock( 'body_html' , $context );

                $message = \Swift_Message::newInstance()
                    ->setSubject( $subject )
                    ->setFrom( $fromEmail, $this->from_email_alias )
                    ->setBcc( 'mypickconfirm@gmail.com' )
                    ->setTo( $send_to_email );

                if( !empty( $htmlBody ) ) {
                    $message->setBody( $htmlBody , 'text/html' )
                        ->addPart( $textBody , 'text/plain' );
                } else {
                    $message->setBody( $textBody );
                }
                //Enabled
                try {
                    $return = $this->mailerLookup->send( $message );

                    if( $return ){

                        $attemptedData
                            ->setMetaKey( ucwords( $form ) .' Recommendation Sent' );
                        $this->emLookup->flush();

                        if( !empty( $resend) ){
                            $resend->setMetaValue( 'complete' );
                        }
                    }

                    $sent_forms += ( $return ) ? 1 : 0;
                } catch (Exception $e) {
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                    return true;
                }
            }
        }
        return ( $sent_forms == count( $send_forms) );
    }

    /**
     * Sends the Learner Screening device email
     *
     * @param Submission $submission
     *
     * @return bool
     */
    public function sendLearnerScreeningDeviceEmail( Submission $submission ) {

        if( !$submission->doesRequire('learner_screening_device') ){
            return false;
        }

        $teacher_email = $submission->getAdditionalDataByKey( 'homeroom_teacher_email' );
        $teacher_email = ( !empty( $teacher_email ) ) ? $teacher_email->getMetaValue() : '';

        $schools = [];
        if( !empty( $submission->getFirstChoice() )
            && $submission->getFirstChoice()->doesRequire( 'learner_screening_device' )
        ){
            $schools[] = $submission->getFirstChoice()->__toString();
        }
        if( !empty( $submission->getSecondChoice() )
            && $submission->getSecondChoice()->doesRequire( 'learner_screening_device' )
        ){
            $schools[] = $submission->getSecondChoice()->__toString() .' ';
        }
        if( !empty( $submission->getThirdChoice() )
            && $submission->getThirdChoice()->doesRequire( 'learner_screening_device' )
        ){
            $schools[] = $submission->getThirdChoice()->__toString();
        }
        $school_name = implode(' and ', $schools );

        if( $teacher_email ){

            $maybe_skip = false;

            $already_sent = $submission->getAdditionalDataByKey( 'Learner Screening Device Sent' );
            if( !empty( $already_sent ) ){
                $maybe_skip = true;
            }

            $resend = $submission->getAdditionalDataByKey( 'Learner Screening Device Resend' );
            if( !empty( $resend )
                && $resend->getmetaValue() != 'complete'
            ){
                $maybe_skip = false;
            }

            if( $maybe_skip ){
                return false;
            }

            $attemptedData = new SubmissionData();
            $attemptedData
                ->setMetaKey( 'Learner Screening Device Attempted' )
                ->setMetaValue( date('M-d-Y') )
                ->setSubmission( $submission );

            $this->emLookup->persist( $attemptedData );
            $this->emLookup->flush();

            $learner_screening_device_url = $submission->getAdditionalDataByKey( 'learner_screening_device_url' );
            $learner_screening_device_url = ( !empty( $learner_screening_device_url ) ) ? $learner_screening_device_url->getMetaValue() : 'ERROR';

            $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
                'active' => 1,
                'name' => 'learnerProfile',
                'type' => 'email'
            ) );
            $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:learner.screening.device.email.twig' );

            $context = array(
                'header' => $this->getHeader(),
                'footer' => $this->getFooter(),
                'submission' => $submission,
                'school_name' => $school_name,
                'learnerProfileUrl' => $this->routerLookup->generate( 'learner_screening_device_form' , array(
                    'uniqueURL' => $learner_screening_device_url ) , UrlGeneratorInterface::ABSOLUTE_URL
                ) ,
            );
            $fromEmail = $this->fromEmail;
            $subject = $template->renderBlock( 'subject' , $context );
            $textBody = $template->renderBlock( 'body_text' , $context );
            $htmlBody = $template->renderBlock( 'body_html' , $context );

            $message = \Swift_Message::newInstance()
                ->setSubject( $subject )
                ->setFrom( $fromEmail, $this->from_email_alias )
                ->setBcc( 'mypickconfirm@gmail.com' )
                ->setTo( $teacher_email );

            if( !empty( $htmlBody ) ) {
                $message->setBody( $htmlBody , 'text/html' )
                    ->addPart( $textBody , 'text/plain' );
            } else {
                $message->setBody( $textBody );
            }
            //Enabled
            try {
                $return = $this->mailerLookup->send( $message );

                if( $return ){
                    $attemptedData
                        ->setMetaKey( 'Learner Screening Device Sent' );

                    if( !empty( $resend) ){
                            $resend->setMetaValue( 'complete' );
                        }
                    $this->emLookup->flush();
                }

                return $return;
            } catch (Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return true;
            }
        }
    }


	/**
	 * Sends the awarded email.
	 *
	 * @param Offered $offered
	 * @param string $type
	 *
	 * @return integer
	 * @throws \Exception
	 */
	public function sendAwardedEmail( Offered $offered , $type = 'awarded' ) {

		$submission = $offered->getSubmission();

		if( $type == 'awarded' ) {
            $correspondence = $this->emLookup->getRepository('IIABMagnetBundle:Correspondence')->findOneBy(array(
                'active' => 1,
                'name' => $type,
                'type' => 'email'
            ));
            //If no correspondence found load IIABMagnetBundle:Email:awarded.email.twig
            $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate('IIABMagnetBundle:Email:awarded.email.twig');
        } else if( $type == 'awarded-wait-list'){
            $correspondence = $this->emLookup->getRepository('IIABMagnetBundle:Correspondence')->findOneBy(array(
                'active' => 1,
                'name' => 'awardedWaitList',
                'type' => 'email'
            ));
            //If no correspondence found load IIABMagnetBundle:Email:awarded.email.twig
            $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate('IIABMagnetBundle:Email:awardedWaitList.email.twig');
        }
		$placement = $this->emLookup->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
			'openEnrollment' => $offered->getOpenEnrollment()
		) );

        $was_waitlisted = $offered->getSubmission()->getWaitList();
        $was_waitlisted = ( count( $was_waitlisted ) ) ? true:false;

		if( $type == 'awarded' && !$was_waitlisted) {
			$context = array(
                'header' => $this->getHeader(),
                'footer' => $this->getFooter(),
				'submission' => $submission ,
				'enrollment' => $submission->getOpenEnrollment()->getYear() ,
				'awardedSchool' => $offered->getAwardedSchool()->__toString() ,
                'awardedFocus' => $offered->getAwardedFocusArea(),
				'acceptanceURL' => $this->routerLookup->generate( 'placement_offered' , array( 'uniqueURL' => $offered->getUrl() ) , UrlGeneratorInterface::ABSOLUTE_URL ) ,
				'acceptOnlineDate' => $offered->getOnlineEndTime()->format( 'm/d/Y' ) ,
				'acceptOnlineTime' => $offered->getOnlineEndTime()->format( 'g:i a' ) ,
				'acceptOfflineDate' => $offered->getOfflineEndTime()->format( 'm/d/Y' ) ,
				'acceptOfflineTime' => $offered->getOfflineEndTime()->format( 'g:i a' ) ,
			);
		} elseif( $type == 'awarded-wait-list' && $was_waitlisted ) {

            $waiting_school_list = [];
            $waitingSchools = $offered->getSubmission()->getWaitList();
            foreach( $waitingSchools as $wait ){
                $waiting_school_list[] = $wait->getChoiceSchool()->__toString();
            }
            $waiting_school_list = implode( ' and ', $waiting_school_list );

			$context = array(
                'header' => $this->getHeader(),
                'footer' => $this->getFooter(),
				'submission' => $submission ,
				'enrollment' => $submission->getOpenEnrollment()->getYear() ,
				'awardedSchool' => $offered->getAwardedSchool()->__toString() ,
                'awardedFocus' => $offered->getAwardedFocusArea(),
                'waitListedSchools' => $waiting_school_list,
                'waitlistExpiresDate' => $placement->getWaitListExpireTime(),
                'acceptanceURL' => $this->routerLookup->generate( 'placement_offered' , array( 'uniqueURL' => $offered->getUrl() ) , UrlGeneratorInterface::ABSOLUTE_URL ) ,
				'acceptOnlineDate' => $offered->getOnlineEndTime()->format( 'm/d/Y' ) ,
				'acceptOnlineTime' => $offered->getOnlineEndTime()->format( 'g:i a' ) ,
				'acceptOfflineDate' => $offered->getOfflineEndTime()->format( 'm/d/Y' ) ,
				'acceptOfflineTime' => $offered->getOfflineEndTime()->format( 'g:i a' ) ,
			);
		} else {
			//throw new \Exception( 'Tried email a specific type that is not defined: ' . $type , 2000 );
		}

		if( isset( $context) ) {
            $fromEmail = $this->fromEmail;
            $subject = $template->renderBlock('subject', $context);
            $textBody = $template->renderBlock('body_text', $context);
            $htmlBody = $template->renderBlock('body_html', $context);

            $message = \Swift_Message::newInstance()
                ->setSubject($subject)
                ->setFrom($fromEmail, $this->from_email_alias)
                ->setBcc( 'mypickconfirm@gmail.com' )
                ->setTo($submission->getParentEmail());

            if (!empty($htmlBody)) {
                $message->setBody($htmlBody, 'text/html')
                    ->addPart($textBody, 'text/plain');
            } else {
                $message->setBody($textBody);
            }

            //Enabled
            try {
                $return = $this->mailerLookup->send($message);
                return $return;
            } catch (Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
                return true;
            }
        }
        return true;
	}

	/**
	 * Sends the accepted email.
	 *
	 * @param Offered $offered
	 *
	 * @return integer
	 */
	public function sendAcceptedEmail( Offered $offered ) {

		$submission = $offered->getSubmission();

        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'accepted',
            'type' => 'email'
        ) );
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:accepted.email.twig' );

		$placement = $this->emLookup->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
			'openEnrollment' => $submission->getOpenEnrollment()
		) );

		$context = array(
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
			'submission' => $submission,
			'enrollment' => $submission->getOpenEnrollment()->getYear(),
			'awardedSchool' => $offered->getAwardedSchool()->getName() ,
			'awardedGrade' => $offered->getAwardedSchool()->getGrade() ,
            'awardedFocus' => $offered->getAwardedFocusArea(),
			'registrationNew' => $placement->getRegistrationNewStartDate() ,
			'registrationCurrent' => $placement->getRegistrationCurrentStartDate() ,
		);

		$fromEmail = $this->fromEmail;
		$subject = $template->renderBlock( 'subject' , $context );
		$textBody = $template->renderBlock( 'body_text' , $context );
		$htmlBody = $template->renderBlock( 'body_html' , $context );

		$message = \Swift_Message::newInstance()
			->setSubject( $subject )
			->setFrom( $fromEmail , $this->from_email_alias )
            ->setBcc( 'mypickconfirm@gmail.com' )
			->setTo( $submission->getParentEmail() );

		if( !empty( $htmlBody ) ) {
			$message->setBody( $htmlBody , 'text/html' )
				->addPart( $textBody , 'text/plain' );
		} else {
			$message->setBody( $textBody );
		}

		//Enabled
        try {
            $return = $this->mailerLookup->send( $message );
            return $return;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return true;
        }
	}

	/**
	 * Sends the declined email.
	 *
	 * @param Offered $offered
	 *
	 * @return integer
	 */
	public function sendDeclinedEmail( Offered $offered ) {

		$submission = $offered->getSubmission();

        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'declined',
            'type' => 'email'
        ) );
        //If no correspondence found load IIABMagnetBundle:Email:declined.email.twig
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:declined.email.twig' );

		$placement = $this->emLookup->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
			'openEnrollment' => $submission->getOpenEnrollment()
		) );

		$context = array(
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
			'submission' => $submission,
			'enrollment' => $submission->getOpenEnrollment()->getYear(),
			'awardedSchool' => $offered->getAwardedSchool()->getName() ,
			'awardedGrade' => $offered->getAwardedSchool()->getGrade() ,
            'awardedFocus' => $offered->getAwardedFocusArea(),
			'registrationNew' => $placement->getRegistrationNewStartDate() ,
			'registrationCurrent' => $placement->getRegistrationCurrentStartDate() ,
		);

		$fromEmail = $this->fromEmail;
		$subject = $template->renderBlock( 'subject' , $context );
		$textBody = $template->renderBlock( 'body_text' , $context );
		$htmlBody = $template->renderBlock( 'body_html' , $context );

		$message = \Swift_Message::newInstance()
			->setSubject( $subject )
			->setFrom( $fromEmail , $this->from_email_alias )
            ->setBcc( 'mypickconfirm@gmail.com' )
			->setTo( $submission->getParentEmail() );

		if( !empty( $htmlBody ) ) {
			$message->setBody( $htmlBody , 'text/html' )
				->addPart( $textBody , 'text/plain' );
		} else {
			$message->setBody( $textBody );
		}

		//Enabled
        try {
            $return = $this->mailerLookup->send( $message );
            return $return;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return true;
        }
	}

	/**
	 * Sends the declined email.
	 *
	 * @param Offered $offered
	 *
	 * @return integer
	 */
	public function sendAutoDeclinedEmail( Offered $offered ) {

		$submission = $offered->getSubmission();

        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'autoDeclined',
            'type' => 'email'
        ) );
        //If no correspondence found load IIABMagnetBundle:Email:auto.declined.email.twig
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:auto.declined.email.twig' );

		$placement = $this->emLookup->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
			'openEnrollment' => $submission->getOpenEnrollment()
		) );

		$context = array(
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
			'submission' => $submission,
			'enrollment' => $submission->getOpenEnrollment()->getYear(),
			'awardedSchool' => $offered->getAwardedSchool()->getName() ,
			'awardedGrade' => $offered->getAwardedSchool()->getGrade() ,
            'awardedFocus' => $offered->getAwardedFocusArea(),
			'registrationNew' => $placement->getRegistrationNewStartDate() ,
			'registrationCurrent' => $placement->getRegistrationCurrentStartDate() ,
		);

		$fromEmail = $this->fromEmail;
		$subject = $template->renderBlock( 'subject' , $context );
		$textBody = $template->renderBlock( 'body_text' , $context );
		$htmlBody = $template->renderBlock( 'body_html' , $context );

		$message = \Swift_Message::newInstance()
			->setSubject( $subject )
			->setFrom( $fromEmail , $this->from_email_alias )
            ->setBcc( 'mypickconfirm@gmail.com' )
			->setTo( $submission->getParentEmail() );

		if( !empty( $htmlBody ) ) {
			$message->setBody( $htmlBody , 'text/html' )
				->addPart( $textBody , 'text/plain' );
		} else {
			$message->setBody( $textBody );
		}

		//Enabled
        try {
            $return = $this->mailerLookup->send( $message );
            return $return;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return true;
        }
	}

	/**s
	 * Sends the WaitList email.
	 *
	 * @param Submission $submission
	 *
	 * @return integer
	 */
	public function sendWaitListEmail( Submission $submission ) {

        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'waitList',
            'type' => 'email'
        ) );
        //If no correspondence found load IIABMagnetBundle:Email:waitingList.email.twig
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:waitingList.email.twig' );

        $waitLists = $this->emLookup->getRepository('IIABMagnetBundle:WaitList')->findBy( array(
            'openEnrollment' => $submission->getOpenEnrollment() ,
            'submission' => $submission ,
        ) );

        $schools = '';
        foreach( $waitLists as $waitList ) {
            if( $waitList->getChoiceSchool() != null ) {
                $schools .= '<li>' . $waitList->getChoiceSchool()->__toString() . "\r\n" . '</li>';
            }
        }
        $schools = ($schools) ? '<ul style="margin: 0 0 10px 20px;padding: 0;font-family: Helvetica, Helvetica, Arial, sans-serif;font-weight: 400;font-size: 14px;line-height: 1.6">' . $schools . '</ul>' : $schools;

		$context = array(
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
			'submission' => $submission ,
			'awardedSchools' => $schools ,
		);

		$fromEmail = $this->fromEmail;
		$subject = $template->renderBlock( 'subject' , $context );
		$textBody = $template->renderBlock( 'body_text' , $context );
		$htmlBody = $template->renderBlock( 'body_html' , $context );

		$message = \Swift_Message::newInstance()
			->setSubject( $subject )
			->setFrom( $fromEmail , $this->from_email_alias )
            ->setBcc( 'mypickconfirm@gmail.com' )
			->setTo( $submission->getParentEmail() );

		if( !empty( $htmlBody ) ) {
			$message->setBody( $htmlBody , 'text/html' )
				->addPart( $textBody , 'text/plain' );
		} else {
			$message->setBody( $textBody );
		}

		//Enabled
        try {
            $return = $this->mailerLookup->send( $message );
            return $return;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return true;
        }
	}

	/**s
	 * Sends the Declined WaitList email.
	 *
	 * @param Offered $offered
	 *
	 * @return integer
	 */
	public function sendDeclinedWaitListEmail( Offered $offered ) {

        $submission = $offered->getSubmission();
        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'declinedWaitList',
            'type' => 'email'
        ) );
        //If no correspondence found load IIABMagnetBundle:Email:declined.waitList.email.twig
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:declined.waitList.email.twig' );

        $waitlists = $submission->getWaitList();

        $waiting_schools = [];
        foreach( $waitlists as $waiting_school ){
            $waiting_schools[] = $waiting_school->getChoiceSchool()->getName();
        }

        $last  = array_slice($waiting_schools, -1);
        $first = join(', ', array_slice($waiting_schools, 0, -1));
        $both  = array_filter(array_merge(array($first), $last), 'strlen');
        $waiting_schools = join(' and ', $both);

		$context = array(
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
			'submission' => $submission ,
            'waiting_schools' => $waiting_schools,
		);

		$fromEmail = $this->fromEmail;
		$subject = $template->renderBlock( 'subject' , $context );
		$textBody = $template->renderBlock( 'body_text' , $context );
		$htmlBody = $template->renderBlock( 'body_html' , $context );

		$message = \Swift_Message::newInstance()
			->setSubject( $subject )
			->setFrom( $fromEmail , $this->from_email_alias )
            ->setBcc( 'mypickconfirm@gmail.com' )
			->setTo( $submission->getParentEmail() );

		if( !empty( $htmlBody ) ) {
			$message->setBody( $htmlBody , 'text/html' )
				->addPart( $textBody , 'text/plain' );
		} else {
			$message->setBody( $textBody );
		}

		//Enabled
        try {
            $return = $this->mailerLookup->send( $message );
            return $return;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return true;
        }
	}

	/**
	 * Sends the Denied Due to Space email.
	 *
	 * @param Submission $submission
	 * @param string $nextSchoolYear
	 * @param string $nextYear
	 *
	 * @return integer
	 */
	public function sendDeniedEmail( Submission $submission , $nextSchoolYear = '' , $nextYear = '' ) {

        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'denied',
            'type' => 'email'
        ) );
        //If no correspondence found load IIABMagnetBundle:Email:denied.email.twig
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:denied.email.twig' );

		$context = array(
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
			'submission' => $submission ,
			'nextSchoolsYear' => $nextSchoolYear,
			'nextYear' => $nextYear,
		);

		$fromEmail = $this->fromEmail;
		$subject = $template->renderBlock( 'subject' , $context );
		$textBody = $template->renderBlock( 'body_text' , $context );
		$htmlBody = $template->renderBlock( 'body_html' , $context );

		$message = \Swift_Message::newInstance()
			->setSubject( $subject )
			->setFrom( $fromEmail , $this->from_email_alias )
            ->setBcc( 'mypickconfirm@gmail.com' )
			->setTo( $submission->getParentEmail() );

		if( !empty( $htmlBody ) ) {
			$message->setBody( $htmlBody , 'text/html' )
				->addPart( $textBody , 'text/plain' );
		} else {
			$message->setBody( $textBody );
		}

		//Enabled
        try {
            $return = $this->mailerLookup->send( $message );
            return $return;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return true;
        }
	}

    /**
     * Sends the Denied Due to No Transcripts email.
     *
     * @param Submission $submission
     * @param string $nextSchoolYear
     * @param string $nextYear
     *
     * @return integer
     */
    public function sendDeniedNoTranscriptsEmail( Submission $submission , $nextSchoolYear = '' , $nextYear = '' ) {

        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'deniedNoTranscripts',
            'type' => 'email'
        ) );
        //If no correspondence found load IIABMagnetBundle:Email:denied.email.twig
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:deniedNoTranscripts.email.twig' );

        $context = array(
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
            'submission' => $submission ,
            'nextSchoolsYear' => $nextSchoolYear,
            'nextYear' => $nextYear,
        );

        $fromEmail = $this->fromEmail;
        $subject = $template->renderBlock( 'subject' , $context );
        $textBody = $template->renderBlock( 'body_text' , $context );
        $htmlBody = $template->renderBlock( 'body_html' , $context );

        $message = \Swift_Message::newInstance()
            ->setSubject( $subject )
            ->setFrom( $fromEmail , $this->from_email_alias )
            ->setBcc( 'mypickconfirm@gmail.com' )
            ->setTo( $submission->getParentEmail() );

        if( !empty( $htmlBody ) ) {
            $message->setBody( $htmlBody , 'text/html' )
                ->addPart( $textBody , 'text/plain' );
        } else {
            $message->setBody( $textBody );
        }

        //Enabled
        try {
            $return = $this->mailerLookup->send( $message );
            return $return;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return true;
        }
    }

	/**
	 * Send Placement Completed Email to Admin User.
	 *
	 * @param Placement $placement
	 *
	 * @return int
	 */
	public function sendPlacementCompletedEmail( Placement $placement ) {

        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'placementCompleted',
            'type' => 'email'
        ) );
        //If no correspondence found load IIABMagnetBundle:Email:placementCompleted.email.twig
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:placementCompleted.email.twig' );

		$context = array(
            'header' => $this->getHeader(),
            'footer' => $this->getFooter(),
			'placement' => $placement,
			'subject' => 'Placement ran successfully for ' . $placement->getOpenEnrollment() . ' - Grades: ' . implode( ', ' , $placement->getGrades() ) ,
		);

		$fromEmail = $this->fromEmail;
		$subject = $template->renderBlock( 'subject' , $context );
		$textBody = $template->renderBlock( 'body_text' , $context );
		$htmlBody = $template->renderBlock( 'body_html' , $context );

		$message = \Swift_Message::newInstance()
			->setSubject( $subject )
			->setFrom( $fromEmail , $this->from_email_alias )
            ->setBcc( 'mypickconfirm@gmail.com' )
			->setTo( $placement->getEmailAddress() );

		if( !empty( $htmlBody ) ) {
			$message->setBody( $htmlBody , 'text/html' )
				->addPart( $textBody , 'text/plain' );
		} else {
			$message->setBody( $textBody );
		}

		//Enabled

        try {
            $return = $this->mailerLookup->send( $message );
            return $return;
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            return true;
        }
	}

	/**
	 * Send Next Step email.
	 * @param Submission $submission
	 *
	 * @return int
	 */
	public function sendNextStepEmail( Submission $submission ) {

        $correspondence = $this->emLookup->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'nextStep',
            'type' => 'email'
        ) );
        //If no correspondence found load IIABMagnetBundle:Email:nextStep.email.twig
        $template = ($correspondence) ? $this->twigLookup->createTemplate($correspondence->getTemplate()) : $this->twigLookup->loadTemplate( 'IIABMagnetBundle:Email:nextStep.email.twig' );

		$firstMessaging = false;
		if( $submission->getFirstChoice() != null ) {
			$firstMessaging = $this->emLookup->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( array(
				'openEnrollment' => $submission->getOpenEnrollment() ,
				'magnetSchool' => $submission->getFirstChoice() ,
				'interview' => true
			) );
		}
		$secondMessaging = false;
		if( $submission->getSecondChoice() != null ) {
			$secondMessaging = $this->emLookup->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( array(
				'openEnrollment' => $submission->getOpenEnrollment() ,
				'magnetSchool' => $submission->getSecondChoice() ,
				'interview' => true
			) );
		}
		$thirdMessaging = false;
		if( $submission->getThirdChoice() != null ) {
			$thirdMessaging = $this->emLookup->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( array(
				'openEnrollment' => $submission->getOpenEnrollment() ,
				'magnetSchool' => $submission->getThirdChoice() ,
				'interview' => true
			) );
		}

		//Does any option require a next step, if none do not generate a letter.
		if( $firstMessaging || $secondMessaging || $thirdMessaging ) {
			$context = array(
                'header' => $this->getHeader(),
                'footer' => $this->getFooter(),
				'submission' => $submission ,
				'firstChoice' => $submission->getFirstChoice() ,
				'firstChoiceMessage' => $firstMessaging ,
				'secondChoice' => $submission->getSecondChoice() ,
				'secondChoiceMessage' => $secondMessaging ,
				'thirdChoice' => $submission->getThirdChoice() ,
				'thirdChoiceMessage' => $thirdMessaging ,
			);

			$fromEmail = $this->fromEmail;
			$subject = $template->renderBlock( 'subject' , $context );
			$textBody = $template->renderBlock( 'body_text' , $context );
			$htmlBody = $template->renderBlock( 'body_html' , $context );

			$message = \Swift_Message::newInstance()
				->setSubject( $subject )
				->setFrom( $fromEmail , $this->from_email_alias )
                ->setBcc( 'mypickconfirm@gmail.com' )
				->setTo( $submission->getParentEmail() );

			if( !empty( $htmlBody ) ) {
				$message->setBody( $htmlBody , 'text/html' )
					->addPart( $textBody , 'text/plain' );
			} else {
				$message->setBody( $textBody );
			}

			//Enabled
            try {
                $return = $this->mailerLookup->send( $message );
                return $return;
            } catch (Exception $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                return true;
            }
		} else {
			return 0;
		}
	}

}