<?php
/**
 * Company: Image In A Box
 * Date: 1/22/15
 * Time: 11:31 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Command;


use IIAB\MagnetBundle\Service\LotteryService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GeneratedLotteryNumberCommand extends ContainerAwareCommand {

	protected function configure() {

		$this
			->setName( 'magnet:generate:lotteryNumber' )
			->addOption( 'open' , null , InputOption::VALUE_REQUIRED , 'Open Enrollment' )
			->setDescription( 'Generates a lottery Number' );
	}

	protected function execute( InputInterface $input , OutputInterface $output ) {

		$openEnrollment = $input->getOption( 'open' );

		$lotteryService = new LotteryService( $this->getContainer() , $this->getContainer()->get( 'doctrine.orm.default_entity_manager' ) );
		$openEnrollment = $this->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->find( $openEnrollment );

		if( $openEnrollment == null ) {
			$output->writeln( 'No Open Enrollment found. Try again.' );
		} else {
			for( $x = 1; $x < 11; $x++ ) {
				$output->writeln( 'Lottery Number[' . $x . ']: ' . $lotteryService->getLotteryNumber( $openEnrollment ) );
			}
		}
	}


}