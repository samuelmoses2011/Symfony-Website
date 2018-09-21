<?php
namespace IIAB\MagnetBundle\Command;

use IIAB\MagnetBundle\Entity\Process;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResendRecommendationsCommand extends ContainerAwareCommand {

    protected function configure() {

        $this
            ->setName( 'magnet:resend:recommendations' )
            ->setDescription( 'Resend any recommendation emails that are marked Resend' )
            ->setHelp( <<<EOF
The <info>%command.name%</info> command sends any recommendation emails where the submission has a submissiondata with meta_key '?formType? Recommendation Resend' and the meta_value is not complete.

<info>php %command.full_name%</info>

EOF
            );
    }

    protected function execute( InputInterface $input , OutputInterface $output ) {

        ini_set('memory_limit','2048M');

        $output->writeln( 'Resending Emails' );

        $sumbission_data = $this->getContainer()->get( 'doctrine' )->getManager()->getRepository( 'IIABMagnetBundle:SubmissionData' )->findBy( array(
            'metaKey' => [
                'English Recommendation Resend',
                'Math Recommendation Resend',
                'Counselor Recommendation Resend',
                'Learner Screening Device Resend',
            ] ,
            'metaValue' => 'pending',
        ));

        $learner_submissions = [];
        $recommenation_submissions = [];

        foreach( $sumbission_data as $datum ){
            if( $datum->getMetaKey() == 'Learner Screening Device Resend'){
                if( !isset($learner_submissions[ $datum->getSubmission()->getId() ] ) ){
                    $learner_submissions[ $datum->getSubmission()->getId() ] = $datum->getSubmission();
                }
            } else {
                if( !isset($recommenation_submissions[ $datum->getSubmission()->getId() ] ) ){
                    $recommenation_submissions[ $datum->getSubmission()->getId() ] = $datum->getSubmission();
                }
            }
        }

        $mailer = $this->getContainer()->get( 'magnet.email' );
        var_dump( $recommenation_submissions );
        foreach( $recommenation_submissions as $submission ){
            sleep(1);
            echo ' r '. $submission->getId();
            $mailer->sendTeacherRecommendationFormsEmail( $submission );
        }

        var_dump( count( $learner_submissions ) );
        foreach( $learner_submissions as $submission ){
            sleep(1);
            echo ' L '. $submission->getId();
            $mailer->sendLearnerScreeningDeviceEmail( $submission );
        }
    }
}