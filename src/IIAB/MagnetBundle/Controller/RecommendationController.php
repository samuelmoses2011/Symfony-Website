<?php

namespace IIAB\MagnetBundle\Controller;

use IIAB\MagnetBundle\Service\GeneratePDFService;
use IIAB\MagnetBundle\Entity\SubmissionData;
use IIAB\MagnetBundle\Form\Type\RecommendationTeacherType;
use IIAB\MagnetBundle\Form\Type\RecommendationCounselorType;
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
class RecommendationController extends Controller {

    /**
     * @Route("/recommendation/{uniqueURL}", name="recommendation_form")
     * @Template("@IIABMagnet/Recommendation/recommendation.html.twig")
     *
     * @param Request $request
     * @param string  $uniqueURL
     *
     * @return array|RedirectResponse
     */
    public function recommendAction( Request $request , $uniqueURL ) {

        $recommendation_variables = $this->getRecommendationVariables( $request, $uniqueURL );

        if( !is_array( $recommendation_variables ) ){
            return $recommendation_variables;
        }
        $submission = $recommendation_variables['submission'];
        $recommendation_type = $recommendation_variables['recommendation_type'];
        $teacher_name = $recommendation_variables['teacher_name'];
        $teacher_email = $recommendation_variables['teacher_email'];

        if( $recommendation_type == 'counselor' ){

            $form = $this->createForm( RecommendationCounselorType::class, null, [
                'submission' => $submission,
                'recommendation_type' => $recommendation_type
            ]);

        } else {

            $form = $this->createForm( RecommendationTeacherType::class, null, [
                'submission' => $submission,
                'recommendation_type' => $recommendation_type
            ]);
        }

        $form->handleRequest( $request );

        if( $form->isValid() ) {

            $data = $form->getData();

            foreach( $data as $key => $value){

                if( !is_null( $value ) ){
                    if( is_array( $value ) && $key = 'supportFiles' ){

                        foreach( $value as $supportFile ){

                            if( isset( $supportFile['pdfFile'] ) && !empty( $supportFile['pdfFile'] ) ){

                                $file = $supportFile['pdfFile'];

                                $usage = ( isset( $supportFile['usage']) && !empty( $supportFile['usage'] ) ) ? $supportFile['usage'] : '';

                                $meta_key = 'recommendation_'. $recommendation_type .'_support_file';
                                $meta_key .= ( $usage ) ? '_'. $usage : $usage;

                                $directory = 'uploads/submission/'. $submission->getId() .'/support/';

                                if( !file_exists( $directory ) ) {
                                    mkdir( $directory , 0777 , true );
                                }

                                $file->move( $directory , $file->getClientOriginalName() );

                                $submission_data = new SubmissionData();
                                $submission_data->setSubmission( $submission );
                                $submission_data->setMetaKey( $meta_key );
                                $submission_data->setMetaValue( $directory . $file->getClientOriginalName() );
                                $this->getDoctrine()->getManager()->persist( $submission_data );

                                $submission->addAdditionalDatum( $submission_data );
                            }
                        }
                    } else if( $key == 'name' ){
                        $name_key = ( $recommendation_type == 'counselor' )
                            ? 'counselor_name'
                            : $recommendation_type .'_teacher_name';
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
                        $submission_data->setMetaKey( 'recommendation_'. $recommendation_type .'_'. $key );
                        $submission_data->setMetaValue( $value );
                        $this->getDoctrine()->getManager()->persist( $submission_data );

                        $submission->addAdditionalDatum( $submission_data );
                    }
                }
            }
            $this->getDoctrine()->getManager()->flush();
            //return $this->redirect( $this->generateUrl( 'recommendation_submitted', ['uniqueURL' => $uniqueURL] ) );

        }

        $url_key = 'recommendation_'. $recommendation_type .'_';
        $recomendation_data = $submission->getAdditionalData('true');
        foreach($recomendation_data as $datum ){
            if( $datum->getMetaKey() != $url_key.'url'
                && strpos( $datum->getMetaKey(), $url_key) === 0
            ){
                //return $this->redirect( $this->generateUrl( 'recommendation_submitted', ['uniqueURL' => $uniqueURL] ) );
            }
        }

        //Throw request not found. Error out.
        return array(
            'form' => $form->createView(),
            'submission' => $submission,
            'recommendation_type' => $recommendation_type,
            'teacher_name' => $teacher_name,
            'teacher_email' => $teacher_email,
         );
    }

    /**
     * @Route("/recommendation/{uniqueURL}/submitted/", name="recommendation_submitted")
     * @Template("IIABMagnetBundle:Recommendation:recommendationSubmitted.html.twig")
     * @param Request $request
     *
     * @return array
     */
    public function recommendationSubmittedAction( Request $request , $uniqueURL ) {

        if( empty( $uniqueURL ) ) {
            return $this->redirect( $this->generateUrl( 'submission_notfound' ) );
        }

        $recommendation_variables = $this->getRecommendationVariables( $request, $uniqueURL );
        $submission = $recommendation_variables['submission'];
        $recommendation_type = $recommendation_variables['recommendation_type'];
        $teacher_name = $recommendation_variables['teacher_name'];
        $teacher_email = $recommendation_variables['teacher_email'];

        return array( 'submission' => $submission );
    }

    /**
     * @Route("/recommendation/not-found/", name="recommendation_notfound")
     * @Template("@IIABMagnet/Recommendation/recommendation_notfound.html.twig")
     *
     * @param Request $request
     *
     * @return array|RedirectResponse
     */
    public function notfoundAction() {

        var_dump('NOT FOUND' ); die;


        $admin_pool = $this->get('sonata.admin.pool' );

        return array( 'admin_pool' => $admin_pool , 'id' => $id );
    }

    /**
     * @Route("/recommendation/{uniqueURL}/printout", name="recommendation_printout")
     *
     * @return array
     */
    public function printoutAction( Request $request , $uniqueURL ) {

        $submisson_lookup = explode( '.', $uniqueURL );

        if( count( $submisson_lookup ) != 2 ){
           return $this->redirect( $this->generateUrl( 'recommendation_notfound' ) );
        }

        $submission_id = $submisson_lookup[0];
        $url_key = $submisson_lookup[1];

        $submission = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )
            ->find( $submission_id );

        if( empty( $submission )
            || $submission->getUrl() != $url_key
         ){
            return $this->redirect( $this->generateUrl( 'recommendation_notfound' ) );
        }

        $generatePDF = new GeneratePDFService( $this->container );

        $return_pdf = $generatePDF->recommendationPrintout( $submission );

        return $return_pdf;
    }

    /**
     * @Route("/admin/submission/recommendation/{formType}/{uniqueURL}/printout", name="admin_recommendation_printout")
     * @Template("@IIABMagnet/Recommendation/learner-screening-device-print-form-pdf.html.twig")
     *
     * @param Request $request
     * @param string  $formType
     * @param string  $uniqueURL
     *
     */
    public function adminPrintoutAction( Request $request , $formType, $uniqueURL ) {

        $recommendation_variables = $this->getRecommendationVariables( $request, $uniqueURL );

        if( !is_array( $recommendation_variables ) ){
            return $recommendation_variables;
        }

        $submission = $recommendation_variables['submission'];

        $generatePDF = new GeneratePDFService( $this->container );

        $return_pdf = $generatePDF->recommendationPrintForm(
            $submission,
            $formType
        );

        return $return_pdf;
    }

    private function getRecommendationVariables( Request $request , $uniqueURL ){

        if( empty( $uniqueURL ) ){
            return $this->redirect( $this->generateUrl( 'recommendation_notfound' ) );
        }

        $submission_data = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionData' )
            ->findOneBy( [
                'metaKey' => [
                    'recommendation_english_url',
                    'recommendation_math_url',
                    'recommendation_counselor_url'
                ],
                'metaValue' => $uniqueURL
            ]
        );

        if( empty( $submission_data ) ){
            return $this->redirect( $this->generateUrl( 'recommendation_notfound' ) );
        }

        $recommendation_type = explode('_', $submission_data->getMetaKey() );

        if(count( $recommendation_type ) != 3 ){
            return $this->redirect( $this->generateUrl( 'recommendation_notfound' ) );
        }

        $recommendation_type = $recommendation_type[1];
        $submission = $submission_data->getSubmission();

        $teacher_name = $submission->getAdditionalDataByKey( 'recommendation_'. $recommendation_type .'_teacher_name' );
        $teacher_name = ( !empty( $teacher_name ) ) ? $teacher_name->getMetaValue() : '';

        $teacher_email = $submission->getAdditionalDataByKey( 'recommendation_'. $recommendation_type .'_teacher_email' );
        $teacher_email = ( !empty( $teacher_email ) ) ? $teacher_email->getMetaValue() : '';

        return [
            'submission' => $submission,
            'recommendation_type' => $recommendation_type,
            'teacher_name' => $teacher_name,
            'teacher_email' => $teacher_email,
        ];
    }
}