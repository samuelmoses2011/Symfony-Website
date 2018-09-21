<?php

namespace IIAB\MagnetBundle\Command;

use IIAB\MagnetBundle\Entity\Process;
use IIAB\MagnetBundle\Entity\Student;
use IIAB\MagnetBundle\Entity\SubmissionStatus;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use IIAB\MagnetBundle\Service\PopulationService;

class TestAPICommand extends ContainerAwareCommand {

    protected function configure() {

        $this
            ->setName( 'magnet:test:api' )
            ->setDescription( 'Import Student data from API' )
            ->setHelp( <<<EOF
The <info>%command.name%</info> command imports student data from the iNow database API.

<info>php %command.full_name%</info>

EOF
            );
    }

    protected function execute( InputInterface $input , OutputInterface $output ) {

        ini_set('memory_limit','2048M');

        $env = $this->getContainer()->get( 'kernel' )->getEnvironment();

        $em = $this->getContainer()->get( 'doctrine' )->getManager();

        $populations = $em->getRepository( 'IIABMagnetBundle:Population' )
            ->findBy( ['updateType' => ['offer','decline'] ] );
        foreach( $populations as $population ){
            $em->remove( $population );
        }
        $em->flush();

        $population_service = $this->getContainer()->get( 'magnet.population' );

        $offers = $em->getRepository( 'IIABMagnetBundle:Offered' )->findAll();
        foreach( $offers as $offer ){

            $population_service->offer([
                'date_time' => $offer->getOfferedDateTime(),
                'school' => $offer->getAwardedSchool(),
                'submission' => $offer->getSubmission(),
            ]);

        }
        $population_service->persist_and_flush();

        $declines = $em->getRepository( 'IIABMagnetBundle:Offered' )->findBy([
            'declined' => 1
        ]);
        foreach( $declines as $offer ){

            $population_service->decline([
                'date_time' => $offer->getChangedDateTime(),
                'school' => $offer->getAwardedSchool(),
                'submission' => $offer->getSubmission(),
            ]);

        }
        $population_service->persist_and_flush();

        $magnet_school = $em->getRepository( 'IIABMagnetBundle:MagnetSchool' )->find(19);

        var_dump( $population_service->getCurrentTotalPopulation( $magnet_school ) );
        var_dump( 'FINISHED' );

    }
}