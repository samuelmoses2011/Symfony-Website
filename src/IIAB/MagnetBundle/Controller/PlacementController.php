<?php
/**
 * Company: Image In A Box
 * Date: 2/2/15
 * Time: 2:28 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Controller;

use IIAB\MagnetBundle\Command\GeneratePDFCommand;
use IIAB\MagnetBundle\Entity\Placement;
use IIAB\MagnetBundle\Form\Type\OfferedType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;

require_once( __DIR__ . '/../Library/mpdf/mpdf.php' );

/**
 * Class PlacementController
 * @package IIAB\MagnetBundle\Controller
 */
class PlacementController extends Controller {

	/**
	 * @Route( "/admin/lottery/" , name="placement_index", options={"i18n"=false})
	 * @return array
	 */
	public function indexAction() {

		//$openEnrollment = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->find( 1 );

		//$this->get( 'magnet.lottery' )->runWaitList( $openEnrollment );

		die( 'nothing here' );
		return array();
	}

	/**
	 * @Route("/offered/{uniqueURL}", name="placement_offered")
	 * @Template("@IIABMagnet/Offered/offered.html.twig")
	 *
	 * @param Request $request
	 * @param string  $uniqueURL
	 *
	 * @return array|RedirectResponse
	 */
	public function acceptDeclineAction( Request $request , $uniqueURL ) {

		if( !empty( $uniqueURL ) ) {

			$offered = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Offered' )->findOneBy( array(
				'url' => $uniqueURL
			) );

			//URL did not match any Offers, lets redirect them to not-found offered page.
			if( $offered == null ) {

				return $this->redirect( $this->generateUrl( 'placement_notfound' ) );
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
					$offered->setAcceptedBy( 'online' );

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

					//"Offered and Accepted" Status
					$offeredAndAccepted = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:SubmissionStatus' )->find( 7 );

					$offered->setAccepted( 1 );
					$offered->setDeclined( 0 );
					$offered->setChangedDateTime( new \DateTime() );
					$offered->setAcceptedBy( 'online' );
					$offered->getSubmission()->setSubmissionStatus( $offeredAndAccepted );

					$this->getDoctrine()->getManager()->persist( $offered );
					$this->getDoctrine()->getManager()->flush();

					$this->get( 'magnet.email' )->sendAcceptedEmail( $offered );
				}

				return $this->redirect( $this->generateUrl( 'placement_offered' , array( 'uniqueURL' => $offered->getUrl() ) ) );
			}

			//Offer was found, lets continue.
			$status = new \stdClass();

			$status->declined = $offered->getDeclined();
			$status->accepted = $offered->getAccepted();
			$status->waitlisted = ( $offered->getSubmission()->getSubmissionStatus() == $waitlisted_status ) ? 1 : 0;
			$status->outoftime = 0;

			$now = new \DateTime();

			if( $offered->getOnlineEndTime() <= $now ) {
				$status->outoftime = 1;
			}

			if( $status->declined != 0 || $status->accepted != 0 || $status->outoftime != 0 ) {
				//They have either accepted or declined. so remove the action buttons.
				$form->remove( 'decline_offer' );
				$form->remove( 'accept_offer' );
			}

			return array(
				'form' => $form->createView() ,
				'status' => $status ,
				'offered' => $offered ,
				'acceptOnlineDate' => $offered->getOnlineEndTime()->format( 'm/d/Y' ) ,
				'acceptOnlineTime' => $offered->getOnlineEndTime()->format( 'g:i a' ) ,
				'acceptOfflineDate' => $offered->getOfflineEndTime()->format( 'm/d/Y' ) ,
				'acceptOfflineTime' => $offered->getOfflineEndTime()->format( 'g:i a' ) ,
				'registrationNew' => $placement->getRegistrationNewStartDate() ,
				'registrationCurrent' => $placement->getRegistrationCurrentStartDate() ,
			);
		}
		//Throw request not found. Error out.
		return $this->redirect( $this->generateUrl( 'placement_notfound' ) );
	}

	/**
	 * @Route("/offered/not-found/", name="placement_notfound" )
	 * @Route("/offered/", name="placement_offer_empty")
	 *
	 * @Template("@IIABMagnet/Offered/offeredUrlWrong.html.twig")
	 */
	public function offeredUrlWrongAction() {

		return array();
	}

	/**
	 *
	 * @Route( "/admin/placement/notification/", name="admin_notification_placement", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Create/notification.html.twig")
	 */
	public function customizedNotificationAdminAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'placeholder' => 'Select an Enrollment Period' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'magnetSchool' , 'entity' , array(
				'class' => 'IIABMagnetBundle:MagnetSchool' ,
				'label' => 'Program & Grade' ,
				'required' => true ,
				'placeholder' => 'Select an Program and Grade' ,
				'query_builder' => function ( EntityRepository $er ) {

					$user = $this->getUser();

					$schools = $user->getSchools();
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

						$query = $er->createQueryBuilder( 'magnet' )
							->where( 'magnet.id IN (:schools)' )
							->setParameter( 'schools' , $specificSchools )
							->orderBy( 'magnet.name' , 'ASC' );
					} else {
						$query = $er->createQueryBuilder( 'magnet' )
							->orderBy( 'magnet.name' , 'ASC' );
					}

					return $query;
				} ,
			) )
			->add( 'notification' , 'textarea' , array(
				'label' => 'Notification' ,
				'attr' => array( 'style' => 'width: 100%; height: 100px;' )
			) )
			->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();

			$today = new \DateTime();
			if( $today > $data['openenrollment']->getEndingDate() ) {

				if( count( $data['grades'] ) > 0 ) {

					//var_dump( 'Running Placement' );
					//var_dump( $data['grades'] );
					$placement = new Placement();

					$placement->setOpenEnrollment( $data['openenrollment'] );
					$placement->setAddedDateTime( new \DateTime() );
					$placement->setEmailAddress( $data['notificationEmail'] );
					$placement->setGrades( $data['grades'] );
					$placement->setOnlineEndTime( $data['onlineEndTime'] );
					$placement->setOfflineEndTime( $data['offlineEndTime'] );

					$this->container->get( 'doctrine' )->getManager()->persist( $placement );
					$this->container->get( 'doctrine' )->getManager()->flush();

					return $this->redirect( $this->generateUrl( 'admin_running_placement' ) );
				}

			} else {
				//OpenEnrollment is still going on! BREAK!!!
				return $this->redirect( $this->generateUrl( 'admin_failed_placement' ) );
			}
		}

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool );
	}


	/**
	 * @Route( "/admin/placement/start/", name="admin_execute_placement", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Create/placement.html.twig")
	 *
	 * @return array
	 */
	public function executePlacementAdminAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'placeholder' => 'Select an Enrollment Period' ,
				'query_builder' => function ( EntityRepository $er ) {

					$user = $this->getUser();

					$schools = $user->getSchools();

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'notificationEmail' , 'email' , array( 'label' => 'Notification Email Address' , 'attr' => array( 'style' => 'width:100%;' ) ) )
			->add( 'grades' , 'choice' , array(
				'label' => 'Select the Grades' ,
				'choices' => array(
					'99' => 'PreK' ,
					'0' => 'K' ,
					'1' => '1' ,
					'2' => '2' ,
					'3' => '3' ,
					'4' => '4' ,
					'5' => '5' ,/*
					'6' => '6' ,
					'7' => '7' ,
					'8' => '8' ,
					'9' => '9' ,
					'10' => '10' ,
					'11' => '11' ,
					'12' => '12' ,*/
				) ,
				'expanded' => true ,
				'multiple' => true ,
			) )
			->add( 'onlineEndTime' , 'datetime' , array(
				'label' => 'Online End Date Time' ,
				'format' => IntlDateFormatter::LONG ,
				'data' => new \DateTime( 'midnight next week Sunday' ) ,
			) )
			->add( 'offlineEndTime' , 'datetime' , array(
				'label' => 'Offline End Date Time' ,
				'format' => IntlDateFormatter::LONG ,
				'data' => new \DateTime( 'next week Friday 16:30' )
			) )
			->add( 'run_placement' , 'submit' , array( 'label' => 'Start Placement' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();

			$today = new \DateTime();
			if( $today > $data['openenrollment']->getEndingDate() ) {

				if( count( $data['grades'] ) > 0 ) {

					//var_dump( 'Running Placement' );
					//var_dump( $data['grades'] );
					$placement = new Placement();

					$placement->setOpenEnrollment( $data['openenrollment'] );
					$placement->setAddedDateTime( new \DateTime() );
					$placement->setEmailAddress( $data['notificationEmail'] );
					$placement->setGrades( $data['grades'] );
					$placement->setOnlineEndTime( $data['onlineEndTime'] );
					$placement->setOfflineEndTime( $data['offlineEndTime'] );

					$this->container->get( 'doctrine' )->getManager()->persist( $placement );
					$this->container->get( 'doctrine' )->getManager()->flush();

					return $this->redirect( $this->generateUrl( 'admin_running_placement' ) );
				}

			} else {
				//OpenEnrollment is still going on! BREAK!!!
				return $this->redirect( $this->generateUrl( 'admin_failed_placement' ) );
			}
		}

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool );

	}

	/**
	 * @Route( "/admin/placement/running/", name="admin_running_placement", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Create/placementRunning.html.twig")
	 *
	 * @return array
	 */
	public function placementRunningAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		return array( 'admin_pool' => $admin_pool );

	}

	/**
	 * @Route( "/admin/placement/failed/", name="admin_failed_placement", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Create/placementFailed.html.twig")
	 *
	 * @return array
	 */
	public function placementFailedAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		return array( 'admin_pool' => $admin_pool );

	}

	/**
	 * @Route( "/admin/report/submission-awarded-letter/", name="admin_letter_awarded", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/awardedReport.html.twig")
	 *
	 * @return array
	 */
	public function generateAwardedReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'placeholder' => 'Any Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Awarded Letters' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();
			$openEnrollment = $data['openenrollment'];

			/*

			$offeredSubmissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Offered' )->findOfferedByOpenEnrollment( $openEnrollment );
			$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 10 , 10 );
			$template = $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:awardedLetter.html.twig' );

			if( $openEnrollment != null ) {
				$title = $openEnrollment . ' - Awarded Report';
			} else {
				$title = 'All Enrollment Periods - Awarded Report';
			}

			$total = count( $offeredSubmissions );

			$mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; } </style><body>' );
			if( count( $offeredSubmissions ) > 0 ) {

				foreach( $offeredSubmissions as $offered ) {

					$mpdf->WriteHTML( $template->render( array( 'offered' => $offered , 'acceptOnlineDate' => $offered->getEndOnlineDateTime()->format( 'm/d/Y' ) , 'acceptOnlineTime' => $offered->getEndOnlineDateTime()->format( 'g:i a' ) , 'acceptOfflineDate' => $offered->getEndOfflineDateTime()->format( 'm/d/Y' ) , 'acceptOfflineTime' => $offered->getEndOfflineDateTime()->format( 'g:i a' ) ) ) );

					$total--;

					if( $total > 0 ) {
						$mpdf->WriteHTML( '<pagebreak></pagebreak>' );
					}
					break;
				}
			} else {
				$mpdf->WriteHTML( '<p>No offer letters found.</p>' );
			}
			$mpdf->WriteHTML( '</body></html>' );


			$name = "{$title}";

			$mpdf->mirrorMargins = false;
			$mpdf->SetTitle( $title );
			$mpdf->SetDisplayMode( 'fullpage' , 'two' );

			$pdfContent = null;
			$s = $mpdf->Output( '' , 'S' );
			$mpdf = null;
			*/

			$fileLocation = $this->get('magnet.pdf')->awardedReport( $openEnrollment );

			$name = $openEnrollment . ' - Awarded Report';
			$s = file_get_contents( $fileLocation );

			$response = new Response();
			$response->headers->set( 'Content-Type' , 'application/pdf' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '.pdf"' );
			$response->setContent( $s );
			$s = null;

			return $response;
		}

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool );
	}

	/**
	 * @Route( "/admin/report/submission-waiting-list-letter/", name="admin_letter_wait_list", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function generateWaitListReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'placeholder' => 'Any Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Waiting List Letters' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();
			$openEnrollment = $data['openenrollment'];

			$offeredSubmissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
				'openEnrollment' => $openEnrollment ,
				'submissionStatus' => 9
			) , null );
			$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 10 , 10 );
			$template = $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:waitListLetter.html.twig' );

			if( $openEnrollment != null ) {
				$title = $openEnrollment . ' - Waiting List Letter';
			} else {
				$title = 'All Enrollment Periods - Waiting List Letter';
			}

			$total = count( $offeredSubmissions );

			$mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; }</style><body>' );
			if( $total > 0 ) {

				foreach( $offeredSubmissions as $submission ) {

					$schools = '';
					if( $submission->getFirstChoice() != null ) {
						$schools = '<li>' . $submission->getFirstChoice()->getName() . ' : Grade: ' . $submission->getFirstChoice()->getGrade() . '</li>';
					}
					if( $submission->getSecondChoice() != null ) {
						$schools .= '<li>' . $submission->getSecondChoice()->getName() . ' : Grade: ' . $submission->getSecondChoice()->getGrade() . '</li>';
					}
					if( $submission->getThirdChoice() != null ) {
						$schools .= '<li>' . $submission->getThirdChoice()->getName() . ' : Grade: ' . $submission->getThirdChoice()->getGrade() . '</li>';
					}

					$mpdf->WriteHTML( $template->render( array(
						'submission' => $submission ,
						'awardedSchools' => $schools ,
					) ) );

					$total--;

					if( $total > 0 ) {
						$mpdf->WriteHTML( '<pagebreak></pagebreak>' );
					}
				}
			} else {
				$mpdf->WriteHTML( '<p>No waitling list letters found.</p>' );
			}
			$mpdf->WriteHTML( '</body></html>' );

			$name = "{$title}";

			$mpdf->mirrorMargins = false;
			$mpdf->SetTitle( $title );
			$mpdf->SetDisplayMode( 'fullpage' , 'two' );

			$pdfContent = null;
			$s = $mpdf->Output( '' , 'S' );
			$mpdf = null;

			$response = new Response();
			$response->headers->set( 'Content-Type' , 'application/pdf' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '.pdf"' );
			$response->setContent( $s );
			$s = null;

			return $response;
		}

		$title = 'Report - Download WaitList Report for a Specific Enrollment';
		$subTitle = 'WaitList Report';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subTitle );
	}

	/**
	 * @Route( "/admin/report/submission-denied-letter/", name="admin_letter_denied", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function generateDeniedReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'placeholder' => 'Any Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'nextSchoolYear' , 'text' , array(
				'label' => 'Next School Year (Ex: 2016-2017)' ,
				'attr' => array( 'style' => 'margin:15px 0;' ) ,
				'required' => true
			) )
			->add( 'nextYear' , 'text' , array(
				'label' => 'Next Year (Ex: 2016)' ,
				'required' => true
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Denied Letters' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
			->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();
			$openEnrollment = $data['openenrollment'];
			$nextSchoolYear = $data['nextSchoolYear'];
			$nextYear = $data['nextYear'];

			$offeredSubmissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
				'openEnrollment' => $openEnrollment ,
				'submissionStatus' => 2 //Denied due to Space
			) , null );
			$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 10 , 10 );
			$template = $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:deniedLetter.html.twig' );

			if( $openEnrollment != null ) {
				$title = $openEnrollment . ' - Denied Letter';
			} else {
				$title = 'All Enrollment Periods - Denied Letter';
			}

			$total = count( $offeredSubmissions );

			$mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; }</style><body>' );
			if( $total > 0 ) {

				foreach( $offeredSubmissions as $submission ) {

					$mpdf->WriteHTML( $template->render( array(
						'submission' => $submission ,
						'nextSchoolsYear' => $nextSchoolYear ,
						'nextYear' => $nextYear ,
					) ) );

					$total--;

					if( $total > 0 ) {
						$mpdf->WriteHTML( '<pagebreak></pagebreak>' );
					}
				}
			} else {
				$mpdf->WriteHTML( '<p>No denied letters found.</p>' );
			}
			$mpdf->WriteHTML( '</body></html>' );

			$name = "{$title}";

			$mpdf->mirrorMargins = false;
			$mpdf->SetTitle( $title );
			$mpdf->SetDisplayMode( 'fullpage' , 'two' );

			$pdfContent = null;
			$s = $mpdf->Output( '' , 'S' );
			$mpdf = null;

			$response = new Response();
			$response->headers->set( 'Content-Type' , 'application/pdf' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '.pdf"' );
			$response->setContent( $s );
			$s = null;

			return $response;
		}

		$title = 'Report - Download Denied Report for a Specific Enrollment';
		$subTitle = 'Denied Report';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subTitle );
	}

    /**
     * @Route( "/admin/report/submission-denied-no-transcripts-letter/", name="admin_letter_denied_no_transcripts", options={"i18n"=false})
     * @Template("@IIABMagnet/Admin/Report/report.html.twig")
     *
     * @return array
     */
    public function generateDeniedNoTranscriptsReportAction() {

        $request = $this->get('request_stack')->getCurrentRequest();

        $admin_pool = $this->get( 'sonata.admin.pool' );

        $form = $this->createFormBuilder()
            ->add( 'openenrollment' , 'entity' , array(
                'class' => 'IIABMagnetBundle:OpenEnrollment' ,
                'label' => 'Enrollment' ,
                'required' => true ,
                'placeholder' => 'Any Enrollment Periods' ,
                'query_builder' => function ( EntityRepository $er ) {

                    $query = $er->createQueryBuilder( 'enrollment' )
                        ->orderBy( 'enrollment.year' , 'ASC' );

                    return $query;
                } ,
            ) )
            ->add( 'nextSchoolYear' , 'text' , array(
                'label' => 'Next School Year (Ex: 2016-2017)' ,
                'attr' => array( 'style' => 'margin:15px 0;' ) ,
                'required' => true
            ) )
            ->add( 'nextYear' , 'text' , array(
                'label' => 'Next Year (Ex: 2016)' ,
                'required' => true
            ) )
            ->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Denied to No Transcript Letters' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) )
            ->getForm();

        $form->handleRequest( $request );

        if( $form->isValid() ) {

            $data = $form->getData();
            $openEnrollment = $data['openenrollment'];
            $nextSchoolYear = $data['nextSchoolYear'];
            $nextYear = $data['nextYear'];

            $offeredSubmissions = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
                'openEnrollment' => $openEnrollment ,
                'submissionStatus' => 14 //Denied due to No Transcripts
            ) , null );
            $mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 10 , 10 );
            $template = $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:deniedNoTranscriptsLetter.html.twig' );

            if( $openEnrollment != null ) {
                $title = $openEnrollment . ' - Denied Due to No Transcripts Letter';
            } else {
                $title = 'All Enrollment Periods - Denied Due to No Transcripts Letter';
            }

            $total = count( $offeredSubmissions );

            $mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; }</style><body>' );
            if( $total > 0 ) {

                foreach( $offeredSubmissions as $submission ) {

                    $mpdf->WriteHTML( $template->render( array(
                        'submission' => $submission ,
                        'nextSchoolsYear' => $nextSchoolYear ,
                        'nextYear' => $nextYear ,
                    ) ) );

                    $total--;

                    if( $total > 0 ) {
                        $mpdf->WriteHTML( '<pagebreak></pagebreak>' );
                    }
                }
            } else {
                $mpdf->WriteHTML( '<p>No denied due to no transcripts letters found.</p>' );
            }
            $mpdf->WriteHTML( '</body></html>' );

            $name = "{$title}";

            $mpdf->mirrorMargins = false;
            $mpdf->SetTitle( $title );
            $mpdf->SetDisplayMode( 'fullpage' , 'two' );

            $pdfContent = null;
            $s = $mpdf->Output( '' , 'S' );
            $mpdf = null;

            $response = new Response();
            $response->headers->set( 'Content-Type' , 'application/pdf' );
            $response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '.pdf"' );
            $response->setContent( $s );
            $s = null;

            return $response;
        }

        $title = 'Report - Download Denied Due to No Transcripts Report for a Specific Enrollment';
        $subTitle = 'Denied Due to No Transcripts Report';

        return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subTitle );
    }

	/**
	 * @Route( "/admin/emails/submission-next-step-emails/" , name="admin_emails_next_step", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function sendNextStepEmailsAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'placeholder' => 'Choose an Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'send_emails' , 'submit' , array( 'label' => 'Send Next Step Emails' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' , 'onclick' => 'return confirm( "This will take a few minutes to completed. \r\n\r\nDO NOT LEAVE THIS PAGE until your browser is done reloading. \r\n\r\nAre you sure you want to continue?" );' ) ) )
			->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();
			$openEnrollment = $data['openenrollment'];

			$offeredSubmissions = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->where( 's.openEnrollment = :enrollment' )
				->andWhere( 's.submissionStatus IN (:statuses)' )
				->andWhere( 's.nextGrade IN (:grades)' )
				->andWhere( "s.parentEmail != '' " )
				->setParameters( array(
					'enrollment' => $openEnrollment ,
					'statuses' => array( 1 , 5 ) ,
					'grades' => array( 6 , 7 , 8 , 9 , 10 , 11 , 12 ) ,
				) )
				->getQuery()
				->getResult();

			set_time_limit( 0 );

			$total = count( $offeredSubmissions );

			if( $total > 0 ) {

				foreach( $offeredSubmissions as $submission ) {

					$this->get('magnet.email')->sendNextStepEmail( $submission );
				}
			}

			set_time_limit( 60 );

			return $this->redirect( $this->generateUrl( 'admin_emails_next_step' ) );
		}

		$title = 'Next Step Emails';
		$subtitle = 'Send Next Step Emails Now';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle , 'hideWarning' => true );
	}

	/**
	 * @Route( "/admin/report/submission-next-step-letter/" , name="admin_letter_next_step", options={"i18n"=false})
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 *
	 * @return array
	 */
	public function generateNextStepReportAction() {

		$request = $this->get('request_stack')->getCurrentRequest();

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder()
			->add( 'openenrollment' , 'entity' , array(
				'class' => 'IIABMagnetBundle:OpenEnrollment' ,
				'label' => 'Enrollment' ,
				'required' => true ,
				'attr' => array( 'style' => 'margin-bottom: 20px;' ) ,
				'placeholder' => 'Any Enrollment Periods' ,
				'query_builder' => function ( EntityRepository $er ) {

					$query = $er->createQueryBuilder( 'enrollment' )
						->orderBy( 'enrollment.year' , 'ASC' );

					return $query;
				} ,
			) )
			->add( 'reportDate' , 'date' , array(
				'label' => 'Mailing Date' ,
				'data' => new \DateTime( '+1 day' ) ,
				'format' => IntlDateFormatter::FULL,
			) )
			->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Next Step Letters' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' , 'onclick' => 'return confirm( "This will take a few minutes to completed. \r\n\r\nDO NOT LEAVE THIS PAGE until your downloaded PDF is available. \r\n\r\nAre you sure you want to continue?" );' ) ) )
			->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {

			$data = $form->getData();
			$openEnrollment = $data['openenrollment'];
			$reportDate = $data['reportDate'];

			$offeredSubmissions = $this->getDoctrine()->getManager()->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->where( 's.openEnrollment = :enrollment' )
				->andWhere( 's.submissionStatus IN (:statuses)' )
				->andWhere( 's.nextGrade IN (:grades)' )
				->setParameters( array(
					'enrollment' => $openEnrollment ,
					'statuses' => array( 1 , 5 ) , // ONLY ACTIVE STATUSES & ON HOLD
					'grades' => array( 6 , 7 , 8 , 9 , 10 , 11 , 12 ) ,
				) )
				->getQuery()
				->getResult();

			set_time_limit( 0 );

			$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 10 , 10 );
			$template = $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:nextStepLetter.html.twig' );

			if( $openEnrollment != null ) {
				$title = $openEnrollment . ' - Next Step Letter';
			} else {
				$title = 'All Enrollment Periods - Next Step Letter';
			}

			$total = count( $offeredSubmissions );

			$mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; }</style><body>' );
			if( $total > 0 ) {

				foreach( $offeredSubmissions as $submission ) {

					$firstMessaging = false;
					if( $submission->getFirstChoice() != null ) {
						$firstMessaging = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( array(
							'openEnrollment' => $openEnrollment ,
							'magnetSchool' => $submission->getFirstChoice() ,
						) );
					}
					$secondMessaging = false;
					if( $submission->getSecondChoice() != null ) {
						$secondMessaging = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( array(
							'openEnrollment' => $openEnrollment ,
							'magnetSchool' => $submission->getSecondChoice() ,
						) );
					}
					$thirdMessaging = false;
					if( $submission->getThirdChoice() != null ) {
						$thirdMessaging = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( array(
							'openEnrollment' => $openEnrollment ,
							'magnetSchool' => $submission->getThirdChoice() ,
						) );
					}

					$context = array(
						'reportDate' => $reportDate ,
						'submission' => $submission ,
						'firstChoice' => $submission->getFirstChoice() ,
						'firstChoiceMessage' => $firstMessaging ,
						'secondChoice' => $submission->getSecondChoice() ,
						'secondChoiceMessage' => $secondMessaging ,
						'thirdChoice' => $submission->getThirdChoice() ,
						'thirdChoiceMessage' => $thirdMessaging ,
					);

					//TODO: Need to add in dynamic information.
					$mpdf->WriteHTML( $template->render( $context ) );

					$total--;

					if( $total > 0 ) {
						$mpdf->WriteHTML( '<pagebreak></pagebreak>' );
					}

					$firstMessaging = null;
					$secondMessaging = null;
					$thirdMessaging = null;
				}
			} else {
				$mpdf->WriteHTML( '<p>No next step letters found.</p>' );
			}
			$mpdf->WriteHTML( '</body></html>' );

			$name = "{$title}";

			$mpdf->mirrorMargins = false;
			$mpdf->SetTitle( $title );
			$mpdf->SetDisplayMode( 'fullpage' , 'two' );

			$pdfContent = null;
			$s = $mpdf->Output( '' , 'S' );
			$mpdf = null;

			$response = new Response();
			$response->headers->set( 'Content-Type' , 'application/pdf' );
			$response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '.pdf"' );
			$response->setContent( $s );
			$s = null;

			set_time_limit( 60 );

			return $response;
		}

		$title = 'Next Step Letters';
		$subtitle = 'Generate Next Step Letters (for Interviews / Auditions)';

		return array( 'form' => $form->createView() , 'admin_pool' => $admin_pool , 'title' => $title , 'subtitle' => $subtitle );
	}
}