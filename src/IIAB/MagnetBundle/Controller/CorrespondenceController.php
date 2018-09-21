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
 * Class CorrespondenceController
 * @package IIAB\MagnetBundle\Controller
 * @Route("/admin/correspondence/", options={"i18n"=false})
 */
class CorrespondenceController extends Controller {
    /**
     * Confirmation Screen
     *
     * @Route("confirmation-screen/", name="iiab_magnet_correspondence_confirmation_screen")
     * @Template("@IIABMagnet/Admin/Correspondence/confirmation.html.twig")
     */
    public function confirmationAction() {

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

        $emailActiveCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'confirmation',
            'type' => 'email'
        ) );

        if($emailActiveCorrespondence == null) {
            $emailActiveCorrespondence = new Correspondence();
            $emailActiveCorrespondence->setName('confirmation');
            $emailActiveCorrespondence->setType('email');
            $emailActiveCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Email/confirmation.email.twig'));
            $emailActiveCorrespondence->setActive(1);
            $emailActiveCorrespondence->setLastUpdateDateTime(new \DateTime());
        }
        $emailActiveBlock = CorrespondenceVariablesService::divideEmailBlocks($emailActiveCorrespondence->getTemplate());


        $emailPendingCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'pending',
            'type' => 'email'
        ) );

        if($emailPendingCorrespondence == null) {
            $emailPendingCorrespondence = new Correspondence();
            $emailPendingCorrespondence->setName('pending');
            $emailPendingCorrespondence->setType('email');
            $emailPendingCorrespondence->setTemplate(file_get_contents($this->container->get('kernel')->getRootDir() . '/../src/IIAB/MagnetBundle/Resources/views/Email/highSchool.email.twig'));
            $emailPendingCorrespondence->setActive(1);
            $emailPendingCorrespondence->setLastUpdateDateTime(new \DateTime());
        }
        $emailPendingBlock = CorrespondenceVariablesService::divideEmailBlocks($emailPendingCorrespondence->getTemplate());

        $activeCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'active',
            'type' => 'screen'
        ) );

        if( $activeCorrespondence == null ) {
            $activeCorrespondence = new Correspondence();
            $activeCorrespondence->setName('success');
            $activeCorrespondence->setType( 'screen' );
            $activeCorrespondence->setTemplate( file_get_contents($this->container->get( 'kernel' )->getRootDir() .'/../src/IIAB/MagnetBundle/Resources/views/Application/successfullySubmitted.html.twig') );
            $activeCorrespondence->setActive(1);
            $activeCorrespondence->setLastUpdateDateTime( new \DateTime() );
        }

        $pendingCorrespondence = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'pending',
            'type' => 'screen'
        ) );

        if( $pendingCorrespondence == null ) {
            $pendingCorrespondence = new Correspondence();
            $pendingCorrespondence->setName('pending');
            $pendingCorrespondence->setType( 'screen' );
            $pendingCorrespondence->setTemplate( file_get_contents($this->container->get( 'kernel' )->getRootDir() .'/../src/IIAB/MagnetBundle/Resources/views/Application/onHoldSubmission.html.twig') );
            $pendingCorrespondence->setActive(1);
            $pendingCorrespondence->setLastUpdateDateTime( new \DateTime() );
        }

        $dynamicVariables = CorrespondenceVariablesService::getDynamicVariables();

        $form = $this->createFormBuilder(null, [ 'attr' => [ 'data-dynamic' => json_encode( $dynamicVariables ) ] ] )

            ->add( 'activeTemplate', CKEditorType::class, array(
                'data' => $activeCorrespondence->getTemplate(),
            ))

            ->add( 'pendingTemplate', CKEditorType::class, array(
                'data' => $pendingCorrespondence->getTemplate(),
            ))

            ->add( 'emailActiveSubject', CKEditorType::class, array(
                'data' => $emailActiveBlock['subject'],
                'attr' => array('class' => 'plain-text single-line')
            ))

            ->add( 'emailActiveBodyHtml', CKEditorType::class, array(
                'data' => $emailActiveBlock['body_html'],
            ))

            ->add( 'emailPendingSubject', CKEditorType::class, array(
                'data' => $emailPendingBlock['subject'],
                'attr' => array('class' => 'plain-text single-line')
            ))

            ->add( 'emailPendingBodyHtml', CKEditorType::class, array(
                'data' => $emailPendingBlock['body_html'],
            ))

            ->add( 'saveEmailActiveChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' ) ,
            ) )

            ->add( 'saveEmailPendingChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' ) ,
            ) )

            ->add( 'saveActiveChanges' , 'submit' , array(
                'label' => 'Save Changes' ,
                'attr' => array( 'class' => 'btn btn-info' )
            ) )

            ->add( 'savePendingChanges' , 'submit' , array(
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

            if( $form->get( 'saveActiveChanges' )->isClicked()
                || $form->get( 'savePendingChanges' )->isClicked()
                || $form->get( 'saveEmailActiveChanges' )->isClicked()
                || $form->get( 'saveEmailPendingChanges' )->isClicked()
            ){
                $template = str_replace( '<span>', '', $data['activeTemplate'] );
                $template = str_replace( '</span>', '', $template );
                $activeCorrespondence->setTemplate( $template );
                $this->getDoctrine()->getManager()
                    ->persist( $activeCorrespondence );

                $template = str_replace( '<span>', '', $data['pendingTemplate'] );
                $template = str_replace( '</span>', '', $template );
                $pendingCorrespondence->setTemplate(
                    $template
                );
                $this->getDoctrine()->getManager()
                    ->persist( $pendingCorrespondence );

                $emailActiveTemplate = CorrespondenceVariablesService::combineEmailBlocks(['subject' => $data['emailActiveSubject'], 'body_html' => $data['emailActiveBodyHtml']]);
                $emailActiveCorrespondence->setTemplate($emailActiveTemplate);
                $this->getDoctrine()->getManager()
                    ->persist( $emailActiveCorrespondence );

                $emailPendingTemplate = CorrespondenceVariablesService::combineEmailBlocks(['subject' => $data['emailPendingSubject'], 'body_html' => $data['emailPendingBodyHtml']]);
                $emailPendingCorrespondence->setTemplate($emailPendingTemplate);
                $this->getDoctrine()->getManager()
                    ->persist( $emailPendingCorrespondence );

                $this->getDoctrine()->getManager()->flush();
            }

            return $this->redirect( $this->generateUrl( 'iiab_magnet_correspondence_confirmation_screen' ) );
        }

        return array( 'admin_pool' => $admin_pool , 'openEnrollment' => $openEnrollment , 'form' => $form->createView() , 'files' => $lastGeneratedFiles , 'page' => 'awarded' );
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