<?php

namespace IIAB\MagnetBundle\Service;

use IIAB\MagnetBundle\Entity\OpenEnrollment;
use IIAB\MagnetBundle\Entity\Offered;
use IIAB\MagnetBundle\Service\StudentProfileService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;

require_once( __DIR__ . '/../Library/mpdf/mpdf.php' );

class GeneratePDFService {

	/**
	 * @var ContainerInterface
	 */
	private $container;

	function __construct( ContainerInterface $container ) {

		$this->container = $container;
	}

    /**
     * @param $reportDate
     * @return string
     */
	private function getHeader( $reportDate ){
        $correspondence = $this->container->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'header',
            'type' => 'letter'
        ) );
        $template = ($correspondence) ? $this->container->get( 'twig' )->createTemplate($correspondence->getTemplate()) : $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:header.html.twig' );

        return $template->render([ 'reportDate' => $reportDate, ]);
    }

    /**
     * @return string
     */
    private function getFooter(){
        $correspondence = $this->container->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'footer',
            'type' => 'letter'
        ) );
        $template = ($correspondence) ? $this->container->get( 'twig' )->createTemplate($correspondence->getTemplate()) : $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:footer.html.twig' );

        return $template->render([]);
    }

	/**
	 * Generates and saves the awarded report.
	 * @param OpenEnrollment $openEnrollment
	 * @param string $type
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function awardedReport( OpenEnrollment $openEnrollment , $type = 'awarded' ) {

		$em = $this->container->get('doctrine')->getManager();

		$offeredSubmissions = $em->getRepository( 'IIABMagnetBundle:Offered' )->findOfferedByOpenEnrollment( $openEnrollment );
		$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );

        $name = date( 'Y-m-d-H-i' ) . '-Awarded-Report.pdf';
		if( $type == 'awarded' ) {
            $name = date( 'Y-m-d-H-i' ) . '-Awarded-Report.pdf';

            $correspondence = $this->container->get('doctrine')->getRepository('IIABMagnetBundle:Correspondence')->findOneBy(array(
                'active' => 1,
                'name' => 'awarded',
                'type' => 'letter'
            ));
            //If no correspondence found load IIABMagnetBundle:Report:awardedLetter.html.twig
            $template = ($correspondence) ? $this->container->get('twig')->createTemplate($correspondence->getTemplate()) : $this->container->get('twig')->loadTemplate('IIABMagnetBundle:Report:awardedLetter.html.twig');
        } else if ( $type == 'awarded-wait-list'){
            $name = date( 'Y-m-d-H-i' ) . '-Awarded-WaitList-Report.pdf';

            $correspondence = $this->container->get('doctrine')->getRepository('IIABMagnetBundle:Correspondence')->findOneBy(array(
                'active' => 1,
                'name' => 'awardedWaitList',
                'type' => 'letter'
            ));
            //If no correspondence found load IIABMagnetBundle:Report:awardedWaitListLetter.html.twig
            $template = ($correspondence) ? $this->container->get('twig')->createTemplate($correspondence->getTemplate()) : $this->container->get('twig')->loadTemplate('IIABMagnetBundle:Report:awardedWaitListLetter.html.twig');
        }

		if( $openEnrollment != null ) {
			$title = $openEnrollment . ' - Awarded Report';
		} else {
			$title = 'All Enrollment Periods - Awarded Report';
		}

		$placement = $em->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
			'openEnrollment' => $openEnrollment
		), ['round' => 'DESC'] );

		$total = count( $offeredSubmissions );

		$mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; } </style><body>' );

		if( count( $offeredSubmissions ) > 0 ) {

			/** @var Offered $offered */
			foreach( $offeredSubmissions as $offered ) {

			    $was_waitlisted = $offered->getSubmission()->getWaitList();
				$was_waitlisted = ( count( $was_waitlisted ) ) ? true:false;

				if( $type == 'awarded' && !$was_waitlisted ) {

					$mpdf->WriteHTML( $template->render( array(
                        'header' => $this->getHeader(  $placement->getAwardedMailedDate() ),
                        'footer' => $this->getFooter(),
                        'reportDate' => $placement->getAwardedMailedDate() ,
                        'offered' => $offered ,
                        'submission' => $offered->getSubmission() ,
						'awardedSchool' => $offered->getAwardedSchool() ,
                        'awardedFocus' => $offered->getAwardedFocusArea(),
                        'acceptanceURL' => $this->container->get( 'router' )->generate( 'placement_offered' , array( 'uniqueURL' => $offered->getUrl() ) , UrlGeneratorInterface::ABSOLUTE_URL ) ,
                        'acceptOnlineDate' => $offered->getOnlineEndTime()->format( 'm/d/Y' ) ,
                        'acceptOnlineTime' => $offered->getOnlineEndTime()->format( 'g:i a' ) ,
                        'acceptOfflineDate' => $offered->getOfflineEndTime()->format( 'm/d/Y' ) ,
                        'acceptOfflineTime' => $offered->getOfflineEndTime()->format( 'g:i a' ) ) ) );

                    $total--;

                    if( $total > 0 ) {
                        $mpdf->WriteHTML( '<pagebreak></pagebreak>' );
                    }

				} elseif( $type == 'awarded-wait-list' && $was_waitlisted ) {

				    $waiting_school_list = '';
				    $waitingSchools = $offered->getSubmission()->getWaitList();
				    foreach( $waitingSchools as $wait ){
				        $waiting_school_list .= '<li>' . $wait->getChoiceSchool()->__toString() . '</li>';
                    }
                    $waiting_school_list = ($waiting_school_list) ? '<ul>' . $waiting_school_list . '</ul>' : $waiting_school_list;

				    $mpdf->WriteHTML( $template->render(
                        array(
                            'header' => $this->getHeader(  $placement->getAwardedMailedDate() ),
                            'footer' => $this->getFooter(),
                            'reportDate' => $placement->getAwardedMailedDate() ,
                            'offered' => $offered ,
                            'submission' => $offered->getSubmission() ,
							'awardedSchool' => $offered->getAwardedSchool() ,
                            'awardedFocus' => $offered->getAwardedFocusArea(),
                            'waitListedSchools' => $waiting_school_list,
                            'waitlistExpiresDate' => $placement->getWaitListExpireTime(),
							'acceptanceURL' => $this->container->get( 'router' )->generate( 'placement_offered' , array( 'uniqueURL' => $offered->getUrl() ) , UrlGeneratorInterface::ABSOLUTE_URL ) ,
                            'acceptOnlineDate' => $offered->getOnlineEndTime()->format( 'm/d/Y' ) ,
                            'acceptOnlineTime' => $offered->getOnlineEndTime()->format( 'g:i a' ) ,
                            'acceptOfflineDate' => $offered->getOfflineEndTime()->format( 'm/d/Y' ) ,
                            'acceptOfflineTime' => $offered->getOfflineEndTime()->format( 'g:i a' ) ) ) );

                    $total--;

                    if( $total > 0 ) {
                        $mpdf->WriteHTML( '<pagebreak></pagebreak>' );
                    }

				} else if( $type != 'awarded' && $type != 'awarded-wait-list' ) {
					throw new \Exception( 'Tried PDF generation a specific type that is not defined: ' . $type , 2000 );
				}
			}
		} else {
			$mpdf->WriteHTML( '<p>No offer letters found.</p>' );
		}
		$mpdf->WriteHTML( '</body></html>' );

		$mpdf->mirrorMargins = false;
		$mpdf->SetTitle( $title );
		$mpdf->SetDisplayMode( 'fullpage' , 'two' );

		$rootDIR = $this->container->get('kernel')->getRootDir() . '/../web/reports/' . $type . '/' . $openEnrollment->getId() . '/';

		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}

		$fileLocation = $rootDIR . $name;

		$pdfContent = null;
		$mpdf->Output( $fileLocation , 'F' );
		$mpdf = null;

        $fileLocation = '/reports/awarded/' . $openEnrollment->getId() . '/' . $name;

		return $fileLocation;
	}

	/**
	 * Generates and saves the awarded PDF and returns it.
	 *
	 * @param Offered $offered
	 *
	 * @return Response
	 * @throws \Exception
	 */
	public function awardedPDF( Offered $offered ) {

		$em = $this->container->get('doctrine')->getManager();

        $was_waitlisted = $em->getRepository( 'IIABMagnetBundle:SubmissionData' )->findBy([ 'submission' => $offered->getSubmission(), 'metaKey' => 'waitlisted date' ]);
        $was_waitlisted = isset( $was_waitlisted );

		$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );

        if( !$was_waitlisted ) {
            $correspondence = $this->container->get('doctrine')->getRepository('IIABMagnetBundle:Correspondence')->findOneBy(array(
                'active' => 1,
                'name' => 'awarded',
                'type' => 'letter'
            ));
            //If no correspondence found load IIABMagnetBundle:Report:awardedLetter.html.twig
            $template = ($correspondence) ? $this->container->get('twig')->createTemplate($correspondence->getTemplate()) : $this->container->get('twig')->loadTemplate('IIABMagnetBundle:Report:awardedLetter.html.twig');
        } else {
            $waiting_school_list = [];
            $waitingSchools = $offered->getSubmission()->getWaitList();
            foreach( $waitingSchools as $wait ){
                $waiting_school_list[] = $wait->getChoiceSchool()->__toString();
            }
            $waiting_school_list = implode( ' and ', $waiting_school_list );

            $correspondence = $this->container->get('doctrine')->getRepository('IIABMagnetBundle:Correspondence')->findOneBy(array(
                'active' => 1,
                'name' => 'awardedWaitList',
                'type' => 'letter'
            ));
            //If no correspondence found load IIABMagnetBundle:Report:awardedLetter.html.twig
            $template = ($correspondence) ? $this->container->get('twig')->createTemplate($correspondence->getTemplate()) : $this->container->get('twig')->loadTemplate('IIABMagnetBundle:Report:awardedWaitListLetter.html.twig');
        }
		$title = $offered->getOpenEnrollment() . ' - Awarded Report';

		$mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; } </style><body>' );
		$mpdf->WriteHTML( $template->render( array(
            'header' => $this->getHeader(   new \DateTime( '+1 day' ) ),
            'footer' => $this->getFooter(),
            'reportDate' => new \DateTime( '+1 day' ) ,
            'offered' => $offered ,
            'submission' => $offered->getSubmission() ,
			'awardedSchool' => $offered->getAwardedSchool() ,
            'awardedFocus' => $offered->getAwardedFocusArea(),
            'waitListedSchools' => ($was_waitlisted) ? $waiting_school_list : '',
            'acceptanceURL' => $this->container->get( 'router' )->generate( 'placement_offered' , array( 'uniqueURL' => $offered->getUrl() ) , UrlGeneratorInterface::ABSOLUTE_URL ) ,
            'acceptOnlineDate' => $offered->getOnlineEndTime()->format( 'm/d/Y' ) ,
            'acceptOnlineTime' => $offered->getOnlineEndTime()->format( 'g:i a' ) ,
            'acceptOfflineDate' => $offered->getOfflineEndTime()->format( 'm/d/Y' ) ,
            'acceptOfflineTime' => $offered->getOfflineEndTime()->format( 'g:i a' ) ) ) );
		$mpdf->WriteHTML( '</body></html>' );

		$name = date( 'Y-m-d-H-i' ) . '-Awarded-Report.pdf';

		$mpdf->mirrorMargins = false;
		$mpdf->SetTitle( $title );
		$mpdf->SetDisplayMode( 'fullpage' , 'two' );

		$s = $mpdf->Output( '' , 'S' );
		$mpdf = null;
		$name = "{$title}.pdf";

		$response = new Response();
		$response->headers->set( 'Content-Type' , 'application/pdf' );
		$response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $name . '"' );
		$response->setContent( $s );
		$s = null;
		return $response;
	}

	/**
	 * Generates and saves the Waitlist Report.
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return string
	 */
	public function waitListReport( OpenEnrollment $openEnrollment ) {

		$em = $this->container->get('doctrine')->getManager();

		$offeredSubmissions =$em->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
			'openEnrollment' => $openEnrollment ,
			'submissionStatus' => 9
		) , null );
		$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        $correspondence = $this->container->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'waitList',
            'type' => 'letter'
        ) );
        //If no correspondence found load IIABMagnetBundle:Report:waitListLetter.html.twig
        $template = ($correspondence) ? $this->container->get( 'twig' )->createTemplate($correspondence->getTemplate()) : $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:waitListLetter.html.twig' );

		if( $openEnrollment != null ) {
			$title = $openEnrollment . ' - Waiting List Letter';
		} else {
			$title = 'All Enrollment Periods - Waiting List Letter';
		}

		$placement = $em->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
			'openEnrollment' => $openEnrollment
		), ['round' => 'DESC'] );

		$total = count( $offeredSubmissions );

		$mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; }</style><body>' );
		if( $total > 0 ) {

			foreach( $offeredSubmissions as $submission ) {

				$waitLists = $this->container->get('doctrine')->getRepository('IIABMagnetBundle:WaitList')->findBy( array(
					'openEnrollment' => $openEnrollment ,
					'submission' => $submission ,
				) );

				$schools = '';
				foreach( $waitLists as $waitList ) {
					if( $waitList->getChoiceSchool() != null ) {
						$schools .= '<li>' . $waitList->getChoiceSchool()->__toString() . '</li>';
					}
				}
                $schools = ($schools) ? '<ul>' . $schools . '</ul>' : $schools;

				$mpdf->WriteHTML( $template->render( array(
                    'header' => $this->getHeader( $placement->getWaitListMailedDate() ),
                    'footer' => $this->getFooter(),
					'submission' => $submission ,
					'awardedSchools' => $schools ,
                    'waitListedSchools' => $schools,
					'reportDate' => $placement->getWaitListMailedDate()
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

		$name = date( 'Y-m-d-H-i' ) . '-Wait-List-Report.pdf';

		$mpdf->mirrorMargins = false;
		$mpdf->SetTitle( $title );
		$mpdf->SetDisplayMode( 'fullpage' , 'two' );

		$rootDIR = $this->container->get('kernel')->getRootDir() . '/../web/reports/wait-list/' . $openEnrollment->getId() . '/';

		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}

		$fileLocation = $rootDIR . $name;

		$pdfContent = null;
		$mpdf->Output( $fileLocation , 'F' );
		$mpdf = null;

		$fileLocation = '/reports/wait-list/' . $openEnrollment->getId() . '/' . $name;

		return $fileLocation;
	}

	/**
	 * Generates and saves the Denied Due to Space Report.
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return string
	 */
	public function deniedReport( OpenEnrollment $openEnrollment ) {

		$em = $this->container->get('doctrine')->getManager();

		$placement = $em->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
			'openEnrollment' => $openEnrollment
		), ['round' => 'DESC'] );

		$offeredSubmissions = $em->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
			'openEnrollment' => $openEnrollment ,
			'submissionStatus' => 3 //Denied due to Space
		) , null );
		$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        $correspondence = $this->container->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'denied',
            'type' => 'letter'
        ) );
        //If no correspondence found load IIABMagnetBundle:Report:deniedLetter.html.twig
        $template = ($correspondence) ? $this->container->get( 'twig' )->createTemplate($correspondence->getTemplate()) : $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:deniedLetter.html.twig' );

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
                    'header' => $this->getHeader( $placement->getDeniedMailedDate() ),
                    'footer' => $this->getFooter(),
					'submission' => $submission ,
					'nextSchoolsYear' => $placement->getNextSchoolYear() ,
					'nextYear' => $placement->getNextYear() ,
					'reportDate' => $placement->getDeniedMailedDate() ,
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

		$name = date( 'Y-m-d-H-i' ) . '-Denied-List-Report.pdf';

		$mpdf->mirrorMargins = false;
		$mpdf->SetTitle( $title );
		$mpdf->SetDisplayMode( 'fullpage' , 'two' );

		$rootDIR = $this->container->get('kernel')->getRootDir() . '/../web/reports/denied/' . $openEnrollment->getId() . '/';

		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}

		$fileLocation = $rootDIR . $name;

		$pdfContent = null;
		$mpdf->Output( $fileLocation , 'F' );
		$mpdf = null;

		$fileLocation = '/reports/denied/' . $openEnrollment->getId() . '/' . $name;

		return $fileLocation;
	}

    /**
     * Generates and saves the Denied Due to No Transcripts Report.
     * @param OpenEnrollment $openEnrollment
     *
     * @return string
     */
    public function deniedNoTranscriptsReport( OpenEnrollment $openEnrollment ) {

        $em = $this->container->get('doctrine')->getManager();

        $placement = $em->getRepository('IIABMagnetBundle:Placement')->findOneBy( array(
            'openEnrollment' => $openEnrollment
        ) );

        $offeredSubmissions = $em->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
            'openEnrollment' => $openEnrollment ,
            'submissionStatus' => 14 //Inactive Due To No Transcript
        ) , null );
        $mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        $correspondence = $this->container->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
            'active' => 1,
            'name' => 'deniedNoTranscripts',
            'type' => 'letter'
        ) );
        //If no correspondence found load IIABMagnetBundle:Report:deniedNoTranscriptsLetter.html.twig
        $template = ($correspondence) ? $this->container->get( 'twig' )->createTemplate($correspondence->getTemplate()) : $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:deniedNoTranscriptsLetter.html.twig' );

        if( $openEnrollment != null ) {
            $title = $openEnrollment . ' - Denied No Transcripts Letter';
        } else {
            $title = 'All Enrollment Periods - Denied No Transcripts Letter';
        }

        $total = count( $offeredSubmissions );

        $mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; }</style><body>' );
        if( $total > 0 ) {

            foreach( $offeredSubmissions as $submission ) {

                $mpdf->WriteHTML( $template->render( array(
                    'header' => $this->getHeader( $placement->getDeniedMailedDate() ),
                    'footer' => $this->getFooter(),
                    'submission' => $submission ,
                    'nextSchoolsYear' => $placement->getNextSchoolYear() ,
                    'nextYear' => $placement->getNextYear() ,
                    'reportDate' => $placement->getDeniedMailedDate() ,
                ) ) );

                $total--;

                if( $total > 0 ) {
                    $mpdf->WriteHTML( '<pagebreak></pagebreak>' );
                }
            }
        } else {
            $mpdf->WriteHTML( '<p>No denied due to no trnscripts letters found.</p>' );
        }
        $mpdf->WriteHTML( '</body></html>' );

        $name = date( 'Y-m-d-H-i' ) . '-Denied-No-Transcripts-List-Report.pdf';

        $mpdf->mirrorMargins = false;
        $mpdf->SetTitle( $title );
        $mpdf->SetDisplayMode( 'fullpage' , 'two' );

        $rootDIR = $this->container->get('kernel')->getRootDir() . '/../web/reports/denied-no-transcripts/' . $openEnrollment->getId() . '/';

        if( !file_exists( $rootDIR ) ) {
            mkdir( $rootDIR , 0755 , true );
        }

        $fileLocation = $rootDIR . $name;

        $pdfContent = null;
        $mpdf->Output( $fileLocation , 'F' );
        $mpdf = null;

        $fileLocation = '/reports/denied-no-transcripts/' . $openEnrollment->getId() . '/' . $name;

        return $fileLocation;
    }

	public function nextStepLetterReport( OpenEnrollment $openEnrollment ) {

		$em = $this->container->get('doctrine')->getManager();

		$placement = $em->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( array(
			'openEnrollment' => $openEnrollment ,
		), ['round' => 'DESC'] );

		$activeSubmissions = $em->getRepository( 'IIABMagnetBundle:Submission' )->findBy( array(
			'openEnrollment' => $openEnrollment ,
			'submissionStatus' => 1 //Active
		) , null );

		$mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );

		$correspondence = $this->container->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Correspondence' )->findOneBy( array(
			'active' => 1,
			'name' => 'nextStep',
			'type' => 'letter'
		) );
		//If no correspondence found load IIABMagnetBundle:Report:deniedNoTranscriptsLetter.html.twig
		$template = ($correspondence) ? $this->container->get( 'twig' )->createTemplate($correspondence->getTemplate()) : $this->container->get( 'twig' )->loadTemplate( 'IIABMagnetBundle:Report:nextStepLetter.html.twig' );

		if( $openEnrollment != null ) {
			$title = $openEnrollment . ' - Next Step Letter';
		} else {
			$title = 'All Enrollment Periods - Next Step Letter';
		}

		$total = count( $activeSubmissions );

		$mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} p { font-family: serif; } .push { padding: 0 45pt; }</style><body>' );
		if( $total > 0 ) {

			foreach( $activeSubmissions as $submission ) {

				$firstMessaging = false;
				if( $submission->getFirstChoice() != null ) {
					$firstMessaging = $this->container->get('doctrine')->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( array(
						'openEnrollment' => $submission->getOpenEnrollment() ,
						'magnetSchool' => $submission->getFirstChoice() ,
						'interview' => true
					) );
				}
				$secondMessaging = false;
				if( $submission->getSecondChoice() != null ) {
					$secondMessaging = $this->container->get('doctrine')->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( array(
						'openEnrollment' => $submission->getOpenEnrollment() ,
						'magnetSchool' => $submission->getSecondChoice() ,
						'interview' => true
					) );
				}
				$thirdMessaging = false;
				if( $submission->getThirdChoice() != null ) {
					$thirdMessaging = $this->container->get('doctrine')->getRepository( 'IIABMagnetBundle:PlacementMessage' )->findOneBy( array(
						'openEnrollment' => $submission->getOpenEnrollment() ,
						'magnetSchool' => $submission->getThirdChoice() ,
						'interview' => true
					) );
				}

				//Does any option require a next step, if none do not generate a letter.
				if( $firstMessaging || $secondMessaging || $thirdMessaging ) {

					$mpdf->WriteHTML( $template->render( array(
                        'header' => $this->getHeader( $placement->getNextStepMailedDate() ),
                        'footer' => $this->getFooter(),
						'submission' => $submission ,
						'firstChoice' => $submission->getFirstChoice() ,
						'firstChoiceMessage' => $firstMessaging ,
						'secondChoice' => $submission->getSecondChoice() ,
						'secondChoiceMessage' => $secondMessaging ,
						'thirdChoice' => $submission->getThirdChoice() ,
						'thirdChoiceMessage' => $thirdMessaging ,
                    	'reportDate' => $placement->getNextStepMailedDate() ,
					) ) );

					$total--;

					if( $total > 0 ) {
						$mpdf->WriteHTML( '<pagebreak></pagebreak>' );
					}
				} else {
					$total--;
				}
			}
		} else {
			$mpdf->WriteHTML( '<p>No active submissions found.</p>' );
		}
		$mpdf->WriteHTML( '</body></html>' );

		$name = date( 'Y-m-d-H-i' ) . '-Next-Step-Letters-Report.pdf';

		$mpdf->mirrorMargins = false;
		$mpdf->SetTitle( $title );
		$mpdf->SetDisplayMode( 'fullpage' , 'two' );

		$rootDIR = $this->container->get('kernel')->getRootDir() . '/../web/reports/next-step/' . $openEnrollment->getId() . '/';

		if( !file_exists( $rootDIR ) ) {
			mkdir( $rootDIR , 0755 , true );
		}

		$fileLocation = $rootDIR . $name;

		$pdfContent = null;
		$mpdf->Output( $fileLocation , 'F' );
		$mpdf = null;

		$fileLocation = '/reports/next-step/' . $openEnrollment->getId() . '/' . $name;

		return $fileLocation;

	}

    public function recommendationPrintout($submission ){

        $sub_data = $submission->getAdditionalData(true);
        $recommendation_urls = [
            'recommendation_math_url' => '',
            'recommendation_english_url' => '',
            'recommendation_counselor_url' => '',
        ];

        foreach( $sub_data as $datum ){
            if( isset( $recommendation_urls[ $datum->getMetaKey() ] ) ){
                $recommendation_urls[ $datum->getMetaKey() ] = $this->container->get( 'router' )
                    ->generate( 'recommendation_form' , [ 'uniqueURL' => $datum->getMetaValue() ] , UrlGeneratorInterface::ABSOLUTE_URL );
            }
        }

        $mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        $template = $this->container->get('twig')->loadTemplate('IIABMagnetBundle:Recommendation:recommendation-printout-pdf.html.twig');

        $mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} body{margin-top: 20px;} p { font-family: serif; } .push { padding: 0 45pt; } table{ width: 100%; } dt{font-weight: bold;} table{border-spacing: 0px; border-collapse: collapse;} td{border: 1px solid black;padding: 4px 8px 4px 8px;}</style><body>' );
        $mpdf->WriteHTML( $template->render( array(
            'header' => $this->getHeader(   new \DateTime( '+1 day' ) ),
            'footer' => $this->getFooter(),
            'submission' => $submission,
            'recommendation_urls' => $recommendation_urls
        ) ) );
        $mpdf->WriteHTML( '</body></html>' );
        $title = $submission->__toString() . '-Recommendation-'.$recommendation_type.'-Instructions';

        $mpdf->mirrorMargins = false;
        $mpdf->SetTitle( $title );
        $mpdf->SetDisplayMode( 'fullpage' , 'two' );

        $s = $mpdf->Output( '' , 'S' );

        $mpdf = null;

        $response = new Response();
        $response->headers->set( 'Content-Type' , 'application/pdf' );
        $response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $title. '.pdf"' );
        $response->setContent( $s );
        $s = null;
        return $response;
    }

    public function writingSample( $submission ){

        $writing_prompt = $submission->getAdditionalDataByKey('writing_prompt');
        $writing_prompt = ( !empty( $writing_prompt ) ) ? $writing_prompt->getMetaValue() : '';

        $writing_sample = $submission->getAdditionalDataByKey('writing_sample');
        $writing_sample = ( !empty( $writing_sample ) ) ? $writing_sample->getMetaValue() : '';

        $mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        $template = $this->container->get('twig')->loadTemplate('IIABMagnetBundle:Report:writing-sample-pdf.html.twig');
        $mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} body{margin-top: 20px;} p { font-family: serif; } .push { padding: 0 45pt; } table{ width: 100%; } dt{font-weight: bold;} table{border-spacing: 0px; border-collapse: collapse;} td{border: 1px solid black;padding: 4px 8px 4px 8px;}</style><body>' );
        $mpdf->WriteHTML( $template->render( array(
            'header' => $this->getHeader(   new \DateTime( '+1 day' ) ),
            'footer' => $this->getFooter(),
            'submission' => $submission ,
            'writing_prompt' => $writing_prompt,
            'writing_sample' => $writing_sample
        ) ) );
        $mpdf->WriteHTML( '</body></html>' );
        $title = $submission->__toString() . '-Recommendation-'.$recommendation_type;

        $mpdf->mirrorMargins = false;
        $mpdf->SetTitle( $title );
        $mpdf->SetDisplayMode( 'fullpage' , 'two' );

        $s = $mpdf->Output( '' , 'S' );

        $mpdf = null;

        $response = new Response();
        $response->headers->set( 'Content-Type' , 'application/pdf' );
        $response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $title. '.pdf"' );
        $response->setContent( $s );
        $s = null;
        return $response;
    }

    public function writingSamplePrintout( $submission ){

        $writingSampleURL = $this->container->get( 'router' )->generate( 'writing_sample' , [
                    'uniqueURL' => $submission->getId() .'.'. $submission->getUrl()
                ] , UrlGeneratorInterface::ABSOLUTE_URL );

        $mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        $template = $this->container->get('twig')->loadTemplate('IIABMagnetBundle:Report:writing-sample-printout-pdf.html.twig');
        $mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} body{margin-top: 20px;} p { font-family: serif; } .push { padding: 0 45pt; } table{ width: 100%; } dt{font-weight: bold;} table{border-spacing: 0px; border-collapse: collapse;} td{border: 1px solid black;padding: 4px 8px 4px 8px;}</style><body>' );        $mpdf->WriteHTML( $template->render( array(
            'header' => $this->getHeader(   new \DateTime( '+1 day' ) ),
            'footer' => $this->getFooter(),
            'submission' => $submission ,
            'writingSampleURL' => $writingSampleURL,
        ) ) );
        $mpdf->WriteHTML( '</body></html>' );
        $title = $submission->__toString() . '-Writing-Sample';

        $mpdf->mirrorMargins = false;
        $mpdf->SetTitle( $title );
        $mpdf->SetDisplayMode( 'fullpage' , 'two' );

        $s = $mpdf->Output( '' , 'S' );

        $mpdf = null;

        $response = new Response();
        $response->headers->set( 'Content-Type' , 'application/pdf' );
        $response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $title. '.pdf"' );
        $response->setContent( $s );
        $s = null;
        return $response;
    }

    public function recommendationPrintForm( $submission, $recommendation_type ){

        $sub_data = $submission->getAdditionalData(true);
        $merge_data = [];
        foreach( $sub_data as $datum ){
            $merge_data[ str_replace( 'recommendation_'.$recommendation_type.'_', '', $datum->getMetaKey() ) ] = $datum->getMetaValue();
        }

        $mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        if( $recommendation_type == 'counselor' ){

            $data = array_merge([
            'overall_recommendation' => -1,
            'attendance' => -1,
            'workEthic' => -1,
            'maturity' => -1,
            'peerInteraction' => -1,
            'counselor_name' => '',
            'comments' => ''
        ], $merge_data );

            $template = $this->container->get('twig')->loadTemplate('IIABMagnetBundle:Recommendation:recommendation-counselor-print-form-pdf.html.twig');
        } else {

            $data = array_merge( [
                'overall_recommendation' => -1,
                'class_assignments' => -1,
                'homework' => -1,
                'new_concepts' => -1,
                'unique_conclusions' => -1,
                'initiative' => -1,
                'communication' => -1,
                'recall' => -1,
                'loves_learning' => -1,
                'self_correcting' => -1,
                'responsibility' => -1,
                'curiosity' => -1,
                'confidence' => -1,
                'math_teacher_name' => '',
                'english_teacher_name' => '',
                'comments' => ''
            ], $merge_data );

            $template = $this->container->get('twig')->loadTemplate('IIABMagnetBundle:Recommendation:recommendation-teacher-print-form-pdf.html.twig');
        }

        $mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} body{margin-top: 20px;} p { font-family: serif; } .push { padding: 0 45pt; } table{ width: 100%; } dt{font-weight: bold;} table{border-spacing: 0px; border-collapse: collapse;} td{border: 1px solid black;padding: 4px 8px 4px 8px;}</style><body>' );
        $mpdf->WriteHTML( $template->render( array(
            'header' => $this->getHeader(   new \DateTime( '+1 day' ) ),
            'footer' => $this->getFooter(),
            'submission' => $submission ,
            'recommendation_type' => $recommendation_type ,
            'data' => $data
        ) ) );
        $mpdf->WriteHTML( '</body></html>' );
        $title = $submission->__toString() . '-Recommendation-'.$recommendation_type;

        $mpdf->mirrorMargins = false;
        $mpdf->SetTitle( $title );
        $mpdf->SetDisplayMode( 'fullpage' , 'two' );

        $s = $mpdf->Output( '' , 'S' );

        $mpdf = null;

        $response = new Response();
        $response->headers->set( 'Content-Type' , 'application/pdf' );
        $response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $title. '.pdf"' );
        $response->setContent( $s );
        $s = null;
        return $response;
    }

    public function learnerScreeningDevicePrintout($submission){

        $mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        $template = $this->container->get('twig')->loadTemplate('IIABMagnetBundle:LearnerScreeningDevice:learner-screening-device-printout-pdf.html.twig');

        $profile_url = $submission->getAdditionalDataByKey('learner_screening_device_url')->getMetaValue();
        $profile_url = $this->container->get( 'router' )
            ->generate( 'learner_screening_device_form' , [ 'uniqueURL' => $profile_url ] , UrlGeneratorInterface::ABSOLUTE_URL );

        $mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} body{margin-top: 20px;} p { font-family: serif; } .push { padding: 0 45pt; } table{ width: 100%; } dt{font-weight: bold;} table{border-spacing: 0px; border-collapse: collapse;} td{border: 1px solid black;padding: 4px 8px 4px 8px;}</style><body>' );
        $mpdf->WriteHTML( $template->render( array(
            'header' => $this->getHeader(   new \DateTime( '+1 day' ) ),
            'footer' => $this->getFooter(),
            'submission' => $submission ,
            'profile_url' => $profile_url
        ) ) );
        $mpdf->WriteHTML( '</body></html>' );
        $title = $submission->__toString() . '-learner-screening-device-Instructions';

        $mpdf->mirrorMargins = false;
        $mpdf->SetTitle( $title );
        $mpdf->SetDisplayMode( 'fullpage' , 'two' );

        $s = $mpdf->Output( '' , 'S' );

        $mpdf = null;

        $response = new Response();
        $response->headers->set( 'Content-Type' , 'application/pdf' );
        $response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $title. '.pdf"' );
        $response->setContent( $s );
        $s = null;
        return $response;
    }

    public function learnerScreeningDevicePrintForm( $submission ){
        $sub_data = $submission->getAdditionalData(true);
        $merge_data = [];
        foreach( $sub_data as $datum ){
            $merge_data[ $datum->getMetaKey() ] = $datum->getMetaValue();
        }

        $data = array_merge( [
            'learner_screening_device_visual_arts' => '',
            'learner_screening_device_performing_arts' => '',
            'learner_screening_device_leadership' => '',
            'learner_screening_device_psychomotor' => '',
            'learner_screening_device_citizenship' => '',
            'learner_screening_device_creative_thinking' => '',
            'learner_screening_device_abstract_thinking' => '',
            'learner_screening_device_general_intellect' => '',
            'learner_screening_device_cultural' => '',
            'homeroom_teacher_name' => $teacher_name,
            'homeroom_teacher_email' => $teacher_email,
        ], $merge_data );

        $mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        $template = $this->container->get('twig')->loadTemplate('IIABMagnetBundle:LearnerScreeningDevice:learner-screening-device-print-form-pdf.html.twig');

        $mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} body{margin-top: 20px;} p { font-family: serif; } .push { padding: 0 45pt; } table{ width: 100%; } dt{font-weight: bold;} table{border-spacing: 0px; border-collapse: collapse;} td{border: 1px solid black;padding: 4px 8px 4px 8px;}</style><body>' );
        $mpdf->WriteHTML( $template->render( array(
            'header' => $this->getHeader(   new \DateTime( '+1 day' ) ),
            'footer' => $this->getFooter(),
            'submission' => $submission ,
            'data' => $data
        ) ) );
        $mpdf->WriteHTML( '</body></html>' );
        $title = $submission->__toString() . '-Learner-Screening-Device';

        $mpdf->mirrorMargins = false;
        $mpdf->SetTitle( $title );
        $mpdf->SetDisplayMode( 'fullpage' , 'two' );

        $s = $mpdf->Output( '' , 'S' );

        $mpdf = null;

        $response = new Response();
        $response->headers->set( 'Content-Type' , 'application/pdf' );
        $response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $title. '.pdf"' );
        $response->setContent( $s );
        $s = null;
        return $response;
    }

    public function studentProfilePrintForm( $submission ){
        $sub_data = $submission->getAdditionalData(true);

        $data = [];
        foreach( $sub_data as $datum ){
            $data[ $datum->getMetaKey() ] = $datum->getMetaValue();
        }

        $studentProfileService = new StudentProfileService( $submission );

        $data['scores'] = $studentProfileService->getProfileScores(false);

        $profile_settings = [];
        foreach( MYPICK_CONFIG['student_profiles'] as $settings ){

            if( in_array($submission->getNextGrade(), $settings['next_grade_levels'] ) ){
                $profile_settings = $settings;
            }
        }

        $year = ( intval( Date('m') ) < 6 ) ? intval( Date('Y') ) : intval( Date('Y') ) + 1;
        $data['grades'] = [];
        $grades = $submission->getGrades();
        foreach( $grades as $grade ){
            if( $grade->getAcademicYear() == 0
                || $grade->getAcademicYear() == -1
            ){
                $term = str_replace('9wk', '9 weeks', $grade->getAcademicTerm() );
                $data['grades'][$grade->getAcademicYear()][ $term ][ $grade->getCourseType() ] = [
                    'grade' => $grade->getNumericGrade(),
                    'score' => ( isset($data['scores']['grades'][$term][$grade->getCourseType()]) ) ? $data['scores']['grades'][$term][$grade->getCourseType()] : null,
                ];
            }
        }

        $mpdf = new \mPDF( '' , 'Letter' , 0 , '' , 10 , 10 , 0 , 10 );
        $template = $this->container->get('twig')->loadTemplate('IIABMagnetBundle:StudentProfile:student-profile-form-pdf.html.twig');

        $mpdf->WriteHTML( '<html><style type="text/css">body,td,th,p {font-family:sans-serif;font-style: normal;font-weight: normal; font-size: 12px;color: #000000;} body{margin-top: 20px;} p { font-family: serif; } .push { padding: 0 45pt; } table{ width: 100%; } dt{font-weight: bold;} table{border-spacing: 0px; border-collapse: collapse;} td{border: 1px solid black;padding: 4px 8px 4px 8px;}</style><body>' );
        $mpdf->WriteHTML( $template->render( array(
            'header' => $this->getHeader(   new \DateTime() ),
            'footer' => $this->getFooter(),
            'submission' => $submission ,
            'data' => $data,
            'profile_settings' => $profile_settings
        ) ) );
        $mpdf->WriteHTML( '</body></html>' );
        $title = $submission->__toString() . '-Student-Profile';

        $mpdf->mirrorMargins = false;
        $mpdf->SetTitle( $title );
        $mpdf->SetDisplayMode( 'fullpage' , 'two' );

        $s = $mpdf->Output( '' , 'S' );

        $mpdf = null;

        $response = new Response();
        $response->headers->set( 'Content-Type' , 'application/pdf' );
        $response->headers->set( 'Content-Disposition' , 'attachment;filename="' . $title. '.pdf"' );
        $response->setContent( $s );
        $s = null;
        return $response;

    }
}