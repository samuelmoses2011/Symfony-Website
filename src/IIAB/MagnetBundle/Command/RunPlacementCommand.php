<?php
/**
 * Company: Image In A Box
 * Date: 2/13/15
 * Time: 4:02 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunPlacementCommand extends ContainerAwareCommand {

	protected function configure() {

		$this
			->setName( 'magnet:run:placement' )
			->setDescription( 'Looks for any placements and executes it.' )
		;
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		$em = $this->getContainer()->get('doctrine')->getManager();
		$placements = $em->getRepository('IIABMagnetBundle:Placement')->findBy( array(
			'completed' => 0,
		) , array( 'addedDateTime' => 'ASC' ) );

		foreach( $placements as $placement ) {

			if( $placement->getRunning() == 1 ) {
				continue;
			}

			$output->writeln( '<fg=green>Placement running for: ' . $placement->getId() . '</fg=green>' );

			$placement->setRunning( 1 );
			$em->persist( $placement );
			$em->flush( $placement );

			ob_start();
			$this->getContainer()->get('magnet.lottery')->runLottery( $placement->getOpenEnrollment() , $placement->getGrades() , $placement->getOnlineEndTime() , $placement->getOfflineEndTime() );
			$content = ob_get_contents();
			ob_end_clean();

			$content = strip_tags( $content );

			$placement->setCompleted( 1 );
			$placement->setRunning( 0 );
			$em->persist( $placement );

			$output->writeln( '<fg=green>Placement completed for: ' . $placement->getId() . '</fg=green>' );
			$output->writeln( $content );

			$content = null;
			$this->getContainer()->get('magnet.email')->sendPlacementCompletedEmail( $placement );
		}
		$em->flush();
	}

}