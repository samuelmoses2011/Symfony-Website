<?php

namespace IIAB\MagnetBundle\Controller;

use IIAB\MagnetBundle\Service\GeneratePDFService;
use IIAB\MagnetBundle\Entity\SubmissionData;
use IIAB\MagnetBundle\Form\Type\LearnerScreeningDeviceType;
use IIAB\MagnetBundle\Service\LearnerScreeningDeviceService;
use IIAB\MagnetBundle\Service\StudentProfileService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class RecommendationController
 * @package IIAB\MagnetBundle\Controller
 */
class LearnerScreeningDeviceController extends Controller {

    /**
     * @Route("/learner-screening-device/{uniqueURL}", name="learner_screening_device_form")
     * @Template("@IIABMagnet/LearnerScreeningDevice/learner-screening-device.html.twig")
     *
     * @param Request $request
     * @param string  $uniqueURL
     *
     * @return array|RedirectResponse
     */
    public function leanerScreeningDeviceAction( Request $request , $uniqueURL ) {

        $screening_device_variables = $this->getScreeningDeviceVariables( $request, $uniqueURL );
        if( !is_array( $screening_device_variables ) ){
            return $screening_device_variables;
        }
        $submission = $screening_device_variables['submission'];
        $teacher_name = $screening_device_variables['teacher_name'];
        $teacher_email = $screening_device_variables['teacher_email'];

        $form = $this->createForm( LearnerScreeningDeviceType::class, null, [ 'submission' => $submission ] );

        $form->handleRequest( $request );

        if( $form->isValid() ) {

            $data = $form->getData();

                foreach( $data as $key => $value){

                    if( !is_null( $value ) ){

                        if( $key == 'name' ){
                            $name_key = 'homeroom_teacher_name';
                            $submission_data = $submission->getAdditionalDataByKey($name_key);
                            if( empty( $submission_data ) ){
                                $submission_data = new SubmissionData();
                            }

                            $submission_data->setSubmission( $submission );
                            $submission_data->setMetaKey( $name_key );
                            $submission_data->setMetaValue( $value );
                            $this->getDoctrine()->getManager()->persist( $submission_data );

                            $submission->addAdditionalDatum( $submission_data );
                        } else {
                            $submission_data = new SubmissionData();

                            $submission_data->setSubmission( $submission );
                            $submission_data->setMetaKey( 'learner_screening_device_'. $key );
                            $submission_data->setMetaValue( $value );
                            $this->getDoctrine()->getManager()->persist( $submission_data );

                            $submission->addAdditionalDatum( $submission_data );
                        }
                    }
                }

                $studentProfileService = new StudentProfileService( $submission, $this->getDoctrine()->getManager() );
                $profile_totals = $studentProfileService->getProfileScores();

                $profile_score_object = $submission->getAdditionalDataByKey('student_profile_score');
                if (empty($profile_score_object)) {
                    $profile_score_object = new SubmissionData();
                    $profile_score_object->setMetaKey('student_profile_score');
                    $profile_score_object->setSubmission($submission);
                }
                $profile_score_object->setMetaValue( ( $profile_totals != null ) ? $profile_totals['total'] : null );
                $this->getDoctrine()->getManager()->persist($profile_score_object);

                $profile_percent_object = $submission->getAdditionalDataByKey('student_profile_score');
                if (empty($profile_percent_object)) {
                    $profile_percent_object = new SubmissionData();
                    $profile_percent_object->setMetaKey('student_profile_percentage');
                    $profile_percent_object->setSubmission($submission);
                }
                $profile_percent_object->setMetaValue( ( $profile_totals != null ) ? $profile_totals['percentage'] : null );
                $this->getDoctrine()->getManager()->persist($profile_percent_object);

                $this->getDoctrine()->getManager()->flush();
                return $this->redirect( $this->generateUrl( 'learner_screening_device_submitted', ['uniqueURL' => $uniqueURL] ) );
        }

        $device_data = $submission->getAdditionalData('true');
        foreach($device_data as $datum ){
            if( $datum->getMetaKey() != 'learner_screening_device_url'
                && strpos( $datum->getMetaKey(), 'learner_screening_device_') === 0
            ){
                return $this->redirect( $this->generateUrl( 'learner_screening_device_submitted', ['uniqueURL' => $uniqueURL] ) );
            }
        }

        //Throw request not found. Error out.
        return array(
            'form' => $form->createView(),
            'submission' => $submission,
            'teacher_name' => $teacher_name,
            'teacher_email' => $teacher_email,
            'rating_descriptions' => LearnerScreeningDeviceService::getRatingDescriptions(),
         );
    }

    /**
     * @Route("/learner-screening-device/{uniqueURL}/submitted/", name="learner_screening_device_submitted")
     * @Template("IIABMagnetBundle:LearnerScreeningDevice:learner-screening-device-submitted.html.twig")
     * @param Request $request
     *
     * @return array
     */
    public function learnerScreeningDeviceSubmittedAction( Request $request , $uniqueURL ) {

        $screening_device_variables = $this->getScreeningDeviceVariables( $request, $uniqueURL );
        if( !is_array( $screening_device_variables ) ){
            return $screening_device_variables;
        }
        $submission = $screening_device_variables['submission'];
        $teacher_name = $screening_device_variables['teacher_name'];
        $teacher_email = $screening_device_variables['teacher_email'];

        return array( 'submission' => $submission );
    }

   /**
     * @Route("/learner-screening-device/not-found/", name="learner_screening_device_notfound")
     * @Template("@IIABMagnet/LearnerScreeningDevice/learner-screening-device-notfound.html.twig")
     *
     * @param Request $request
     *
     * @return array|RedirectResponse
     */
    public function notfoundAction(Request $request) {

        var_dump('NOT FOUND' ); die;


        $admin_pool = $this->get('sonata.admin.pool' );

        return array( 'admin_pool' => $admin_pool , 'id' => $id );
    }

    /**
     * @Route("/learner-screening-device/{uniqueURL}/printout", name="learner_screening_device_printout")
     *
     * @return array
     */
    public function printoutAction( Request $request , $uniqueURL ) {

        $screening_device_variables = $this->getScreeningDeviceVariables( $request, $uniqueURL );

        if( !is_array( $screening_device_variables ) ){
            return $screening_device_variables;
        }

        $screening_device_variables['screening_device_url'] =
            $this->generateUrl( 'learner_screening_device_form' , array(
                        'uniqueURL' => $uniqueURL
                    ), UrlGeneratorInterface::ABSOLUTE_URL);

        $generatePDF = new GeneratePDFService( $this->container );

        $return_pdf = $generatePDF->learnerScreeningDevicePrintout(
            $screening_device_variables['submission'],
            $screening_device_variables['teacher_name'],
            $screening_device_variables['teacher_email'],
            $screening_device_variables['screening_device_url']
        );

        return $return_pdf;
    }

    /**
     * @Route("/admin/submission/learner-screening-device/{uniqueURL}/printout", name="admin_learner_screening_device_printout")
     *
     * @param Request $request
     * @param string  $uniqueURL
     *
     */
    public function adminPrintoutAction( Request $request , $uniqueURL ) {

        $screening_device_variables = $this->getScreeningDeviceVariables( $request, $uniqueURL );
        $submission = $screening_device_variables['submission'];

        $generatePDF = new GeneratePDFService( $this->container );

        $return_pdf = $generatePDF->learnerScreeningDevicePrintForm(
            $submission
        );

        return $return_pdf;
    }

    private function getScreeningDeviceVariables( Request $request , $uniqueURL ){

        if( empty( $uniqueURL ) ){
           return $this->redirect( $this->generateUrl( 'learner_screening_device_notfound' ) );
        }

        $submission_data = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionData' )
            ->findOneBy( [
                'metaKey' => [
                    'learner_screening_device_url'
                ],
                'metaValue' => $uniqueURL
            ]
        );

        if( empty( $submission_data ) ){
            return $this->redirect( $this->generateUrl( 'learner_screening_device_notfound' ) );
        }
        $submission = $submission_data->getSubmission();

        $teacher_name = $submission->getAdditionalDataByKey( 'homeroom_teacher_name' );
        $teacher_name = ( !empty( $teacher_name ) ) ? $teacher_name->getMetaValue() : '';

        $teacher_email = $submission->getAdditionalDataByKey( 'homeroom_teacher_email' );
        $teacher_email = ( !empty( $teacher_email ) ) ? $teacher_email->getMetaValue() : '';

       return [
            'submission' => $submission,
            'teacher_name' => $teacher_name,
            'teacher_email' => $teacher_email,
        ];
    }
}