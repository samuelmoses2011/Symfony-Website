<?php

namespace IIAB\MagnetBundle\Controller\Report;

use IIAB\MagnetBundle\Service\EligibilityRequirementsService;
use IIAB\MagnetBundle\Service\PopulationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicantOutcomeByProgramReport {

    private $entity_manager;
    private $population;

    public function __construct( $doctrine ){
        $this->entity_manager = $doctrine->getManager();
        $this->population = new PopulationService( $doctrine );
    }

    public function buildReport( $data, $openEnrollment ){

        if( $data['magnetschool'] != 'all' ){
                $magnet_school = $this->entity_manager
                    ->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('school')
                    ->where('school.openEnrollment = :openEnrollment')
                    ->addOrderBy('school.name', 'ASC')
                    ->addOrderBy('school.grade', 'ASC')
                    ->setParameter('openEnrollment', $openEnrollment)
                    ->andWhere('school.name LIKE :school_name')
                    ->setParameter('school_name', '%' . $data['magnetschool'] . '%')
                    ->andWhere('school.active = 1')
                    ->getQuery()
                    ->getResult();
        } else {
            $magnet_school = $this->entity_manager
                ->getRepository('IIABMagnetBundle:MagnetSchool')
                ->findBy([
                    'openEnrollment' => $openEnrollment,
                    'active' => 1
                ]);
        }

        $report_data = [];
        foreach( $magnet_school as $school ){

            if( empty( $report_data[ $school->getProgram()->getId() ] ) ){
                $report_data[ $school->getProgram()->getId() ] = [
                    'program_name' => $school->getProgram()->getName(),
                    'first_choice' => [
                        'total' => 0,
                        'offered_first_choice' => 0,
                        'offered_second_choice' => 0,
                        'offered_and_waitlisted' => 0,
                        'waitlisted' => 0,
                        'denied' => 0,
                    ],
                    'second_choice' => [
                        'total' => 0,
                        'offered_second_choice' => 0,
                        'offered_first_choice' => 0,
                        'denied' => 0,
                    ],
                ];
            }
        }

        $submissionStatus = $this->entity_manager
            ->getRepository('IIABMagnetBundle:SubmissionStatus')
            ->findBy([
                'id' => [2,3,6,7,8,9,13]
            ]
        );

        $submissions = $this->entity_manager
            ->getRepository('IIABMagnetBundle:Submission')
            ->findBy([
                'openEnrollment' => $openEnrollment,
                'submissionStatus' => $submissionStatus,
            ]
        );

        $offers = $this->entity_manager
            ->getRepository('IIABMagnetBundle:Offered')
            ->findBy([
                'openEnrollment' => $openEnrollment
            ]
        );
        $offers_submission_hash = [];
        foreach( $offers as $offer ){

            if( empty( $offers_submission_hash[ $offer->getSubmission()->getId() ] ) ){
                $offers_submission_hash[ $offer->getSubmission()->getId() ] = [];
            }
            $offers_submission_hash[ $offer->getSubmission()->getId() ] = $offer;
        }

        $waitlists = $this->entity_manager
            ->getRepository('IIABMagnetBundle:Waitlist')
            ->findBy([
                'openEnrollment' => $openEnrollment
            ]
        );
        $waitlist_submission_hash = [];

        foreach( $submissions as $submission ){


            if( isset( $report_data[ $submission->getFirstChoice()->getProgram()->getId() ] ) ){

                $report_data[ $submission->getFirstChoice()->getProgram()->getId() ]
                ['first_choice']
                ['total'] ++;

                $first_offer = (
                    isset( $offers_submission_hash[ $submission->getId() ] )
                    && $offers_submission_hash[ $submission->getId() ]->getAwardedSchool()->getId()
                        == $submission->getFirstChoice()->getId()
                );

                $second_offer = (
                    $submission->getSecondChoice() != null
                    && isset( $offers_submission_hash[ $submission->getId() ] )
                    && $offers_submission_hash[ $submission->getId() ]->getAwardedSchool()->getId()
                        == $submission->getSecondChoice()->getId()
                );

                $waitlisted = (
                    isset( $waitlist_submission_hash[ $submission->getId() ] )
                    && $waitlist_submission_hash[ $submission->getId() ]->getChoiceSchool()->getId()
                        == $submission->getFirstChoice()->getId()
                );

                if( $first_offer ){
                    $report_data[ $submission->getFirstChoice()->getProgram()->getId() ]
                    ['first_choice']
                    ['offered_first_choice'] ++;
                }

                else if( $second_offer && !$waitlisted ){
                    $report_data[ $submission->getFirstChoice()->getProgram()->getId() ]
                    ['first_choice']
                    ['offered_second_choice'] ++;
                }

                else if( $second_offer && $waitlisted ){
                    $report_data[ $submission->getFirstChoice()->getProgram()->getId() ]
                    ['first_choice']
                    ['offered_second_choice'] ++;
                }

                else if ( $waitlisted ){
                    $report_data[ $submission->getFirstChoice()->getProgram()->getId() ]
                    ['first_choice']
                    ['waitlisted'] ++;
                }

                else if ( $submission->getSubmissionStatus()->getId() == 3 ){
                    $report_data[ $submission->getFirstChoice()->getProgram()->getId() ]
                    ['first_choice']
                    ['denied'] ++;
                }

                else {
                    //var_dump( $submission->getId() .' error' );
                }
            }

            if( $submission->getSecondChoice() != null
                && isset( $report_data[ $submission->getSecondChoice()->getProgram()->getId() ] )
            ){
                $report_data[ $submission->getSecondChoice()->getProgram()->getId() ]
                    ['second_choice']
                    ['total'] ++;

                if( $first_offer ){
                    $report_data[ $submission->getSecondChoice()->getProgram()->getId() ]
                    ['second_choice']
                    ['offered_first_choice'] ++;
                }

                else if( $second_offer ){
                    $report_data[ $submission->getFirstChoice()->getProgram()->getId() ]
                    ['second_choice']
                    ['offered_second_choice'] ++;
                }

                else if ( $submission->getSubmissionStatus()->getId() == 3 ){
                    $report_data[ $submission->getSecondChoice()->getProgram()->getId() ]
                    ['second_choice']
                    ['denied'] ++;
                }
            }
        }

        return $report_data;
    }

    public function streamResponse( $report_data, $magnet_school_name, $generationDate ){

        $response = new StreamedResponse();
        $response->setCallback( function() use(
            $report_data,
            $generationDate,
            $magnet_school_name
        ) {
            $handle = fopen('php://output', 'w+');

            $short_school_name = $magnet_school_name;

            // Add the header of the CSV file
            fputcsv($handle, ['Note: '. $short_school_name .' Applicant Outcome by Program Report on ' . $generationDate] );

            fputcsv($handle, [
                '',
                'First Choice Total Applicants',
                'Offered First Choice',
                'Offered Second Choice',
                'Offered & Waitlisted',
                'Waitlisted',
                'Denied',
                '',
                'Second Choice Total Applicants',
                'Offered Second Choice',
                'Offered First Choice',
                'Denied'
            ] );

            foreach( $report_data as $program_id => $program_array ){

                fputcsv($handle, [
                    $program_array['program_name'],
                    $program_array['first_choice']['total'],
                    $program_array['first_choice']['offered_first_choice'],
                    $program_array['first_choice']['offered_second_choice'],
                    $program_array['first_choice']['offered_and_waitlisted'],
                    $program_array['first_choice']['waitlisted'],
                    $program_array['first_choice']['denied'],
                    '',
                    $program_array['second_choice']['total'],
                    $program_array['second_choice']['offered_second_choice'],
                    $program_array['second_choice']['offered_first_choice'],
                    $program_array['second_choice']['denied']
                ] );
            }

            fclose($handle);
        });

        $short_school_name = $magnet_school_name;
        $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
        $short_school_name = str_replace( ' ', '_', $short_school_name );

        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Applicant_Outcome_By_Program.csv"');

        return $response;
    }
}