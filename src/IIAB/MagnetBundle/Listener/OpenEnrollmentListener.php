<?php
/**
 * Company: Image In A Box
 * Date: 12/28/14
 * Time: 8:40 PM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Listener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class OpenEnrollmentListener {

	/** @var ContainerInterface */
	private $container;

	/**
	 * @param ContainerInterface $container
	 */
	public function __construct( ContainerInterface $container ) {

		$this->container = $container;
	}

	/**
	 * Allows us to redirect the the template load if there is not an open enrollment.
	 *
	 * @param GetResponseEvent $event
	 */
	public function onKernelRequest( GetResponseEvent $event ) {

		//Is this the development environment.
		$debug = in_array( $this->container->get( 'kernel' )->getEnvironment() , array( 'test' , 'dev' ) );

		//Looking for these specific URLs to allow safe passage through without needing an OpenEnrollment available.
		$allowPassUrlTest = preg_match( '/admin|login|logout|_fragment|offered|ajax|recommendation|learner-screening-device|writing/' , $event->getRequest()->getPathInfo() );

		if( !$allowPassUrlTest ) {

			$specialEnrollment = false;
			//If debug is true, allow the request to go through.
			if( !$debug ) {

				$templateEngine = $this->container->get( 'templating' );
				$emLookup = $this->container->get( 'doctrine' )->getManager();

				//Look up for any open enrollments that are currently going on.
				$openEnrollment = $emLookup->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findByDate( new \DateTime() );

				if( !count( $openEnrollment ) ) {
					$lateEnrollment = $emLookup->getRepository('IIABMagnetBundle:OpenEnrollment')->findLatePlacementByDate(new \DateTime());
					$lateEnrollment = ( isset( $lateEnrollment[0] ) ) ? $lateEnrollment[0] : null;

					$specialEnrollment = $this->container->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:SpecialEnrollment' )->createQueryBuilder( 'se' )
						->where( 'se.openEnrollment = :enrollment' )
						->setParameter( 'enrollment' , $lateEnrollment )
						->andWhere( 'se.beginningDate <= :date' )
						->andWhere( 'se.endingDate >= :date' )
						->setParameter( 'date' , new \DateTime() )
						->select( 'count( se.id )')
						->getQuery()
						->getSingleScalarResult();

					$session = $event->getRequest()->getSession();
					$session->set('lateEnrollment', isset( $specialEnrollment) );
				}

				if( count( $openEnrollment ) == 0 && !$specialEnrollment ) {
					//No enrollment found, so lets redirect them.
					$content = $templateEngine->render( '@IIABMagnet/OpenEnrollment/closedEnrollment.html.twig' , array() );
					$event->setResponse( new Response( $content , 200 ) );
					$event->stopPropagation();
				}
			}
		}
	}
}