<?php

namespace IIAB\MagnetBundle\Controller\Report;

use IIAB\MagnetBundle\Service\EligibilityRequirementsService;
use IIAB\MagnetBundle\Service\PopulationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicantOutcomeReport {

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

            if( empty( $report_data[ $school->getGrade() ] ) ){
                $report_data[ $school->getGrade() ] = [
                    'label' => $school->getGradeString(),
                    'school' => [],
                    'total' => 0,
                    'qualified' => 0,
                    'offered' => 0,
                ];
            }
            $report_data[ $school->getGrade() ]['school'][$school->getId()] = [
                'name' => $school->__toString(),
                'total' => 0,
                'qualified' => 0,
                'offered' => 0,
            ];
        }

        $submissionStatus = $this->entity_manager
            ->getRepository('IIABMagnetBundle:SubmissionStatus')
            ->findBy([
                'id' => [2,3,6,7,8,9,13]
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
            $offers_submission_hash[ $offer->getSubmission()->getId() ][] = $offer;
        }

        $all_submissions =[];
        $submissions = [

            'first' => $this->entity_manager
                ->getRepository('IIABMagnetBundle:Submission')
                ->findBy([
                    'openEnrollment' => $openEnrollment,
                    'firstChoice' => ( isset( $magnet_school ) ) ? $magnet_school : null,
                    'submissionStatus' => $submissionStatus,
                ], [
                    'nextGrade' => 'DESC',
                    'lotteryNumber' => 'ASC'
                ]
            ),
            'second' => $this->entity_manager
                ->getRepository('IIABMagnetBundle:Submission')
                ->findBy([
                    'openEnrollment' => $openEnrollment,
                    'secondChoice' => ( isset( $magnet_school ) ) ? $magnet_school : null,
                    'submissionStatus' => $submissionStatus,
                ], [
                    'nextGrade' => 'DESC',
                    'lotteryNumber' => 'ASC'
                ]
            ),
            'third' => $this->entity_manager
                ->getRepository('IIABMagnetBundle:Submission')
                ->findBy([
                    'openEnrollment' => $openEnrollment,
                    'thirdChoice' => ( isset( $magnet_school ) ) ? $magnet_school : null,
                    'submissionStatus' => $submissionStatus,
                ], [
                    'nextGrade' => 'DESC',
                    'lotteryNumber' => 'ASC'
                ]
            )
        ];

        $eligibility = new EligibilityRequirementsService( $this->entity_manager );

        foreach( $submissions as $choice => $choice_list ){

            foreach( $choice_list as $submission ){

                $magnetSchool = $submission->{'get'. ucfirst($choice) .'Choice'}();

                $tracking_column = ( $this->population
                    ->doesSchoolUseTracker($magnetSchool, 'HomeZone') )
                    ? 'HomeZone'
                    : 'Race';

                $tracking_value = $this->population
                    ->getSubmissionTrackingValueName( $submission, $tracking_column );

                $tracking_value = ( is_object( $tracking_value ) )
                    ? $tracking_value->getName()
                    : $tracking_value;

                $report_data[ $magnetSchool->getGrade() ]
                        ['school'][$magnetSchool->getId()]['column'] = ( $tracking_column == 'HomeZone' ) ? 'School' : 'Race';

                if( empty( $report_data[ $magnetSchool->getGrade() ]['school'][$magnetSchool->getId()]['tracking'][$tracking_value] ) ){
                    $report_data[ $magnetSchool->getGrade() ]
                        ['school'][$magnetSchool->getId()]
                        ['tracking'][$tracking_value] = [
                            'total' => 0,
                            'qualified' => 0,
                            'offered' => 0
                        ];
                }

                $report_data[$magnetSchool->getGrade()]['total'] ++;

                $report_data[ $magnetSchool->getGrade() ]
                    ['school'][$magnetSchool->getId()]['total'] ++;

                $report_data[ $magnetSchool->getGrade() ]
                        ['school'][$magnetSchool->getId()]
                        ['tracking'][$tracking_value]['total'] ++;

                $is_qualified = false;
                $was_offered = false;

                if( $submission->getSubmissionStatus()->getId() != 3 ){
                    if( isset( $offers_submission_hash[ $submission->getId() ] ) ){
                        foreach( $offers_submission_hash[ $submission->getId() ] as $offer ){
                            if( $offer->getAwardedSchool()->getId() == $magnetSchool->getId() ){
                                $is_qualified = true;
                                $was_offered = true;
                            }
                        }
                    }

                    if( !$is_qualified ){
                        $is_qualified = $eligibility->doesSubmissionHaveAllEligibility(
                            $submission,
                            $magnetSchool
                        );
                    }
                }

                if( $is_qualified ){

                    $report_data[$magnetSchool->getGrade()]['qualified'] ++;

                    $report_data[ $magnetSchool->getGrade() ]
                    ['school'][$magnetSchool->getId()]['qualified'] ++;

                    $report_data[ $magnetSchool->getGrade() ]
                        ['school'][$magnetSchool->getId()]
                        ['tracking'][$tracking_value]['qualified'] ++;
                }

                if( $was_offered ){

                    $report_data[$magnetSchool->getGrade()]['offered'] ++;

                    $report_data[ $magnetSchool->getGrade() ]
                    ['school'][$magnetSchool->getId()]['offered'] ++;

                    $report_data[ $magnetSchool->getGrade() ]
                        ['school'][$magnetSchool->getId()]
                        ['tracking'][$tracking_value]['offered'] ++;
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
            fputcsv($handle, ['Note: '. $short_school_name .' Applicant Outcome Summary on ' . $generationDate] );

            foreach( $report_data as $grade => $grade_array ){

                $grade_label = MYPICK_CONFIG['grade_titles'][$grade];

                $key = array_search( $grade, MYPICK_CONFIG['grade_progression_order'] );
                $last_grade_label = ( $key > 0 )
                    ? MYPICK_CONFIG['grade_titles'][MYPICK_CONFIG['grade_progression_order'][$key - 1]]
                    : 'New Student';

                fputcsv($handle, [] );
                fputcsv($handle, [] );
                $row = [$last_grade_label .' to '. $grade_label];
                fputcsv($handle, $row );

                $qualified_percent = round( ($grade_array['qualified'] / $grade_array['total'] ) * 100 );
                $row = [$qualified_percent .'% of applicants qualify'];
                fputcsv($handle, $row );

                $offered_percent = round( ($grade_array['offered'] / $grade_array['qualified'] ) * 100 );
                $row = [$offered_percent .'% of qualified applicants invited'];
                fputcsv($handle, $row );

                foreach( $grade_array['school'] as $school_id => $school_array ){

                    fputcsv($handle, [] );
                    $row = [ $school_array['name'] ];
                    fputcsv($handle, $row );
                    $row = [ $school_array['column'], 'Number of Applicants', 'Number of Qualified', 'Number Invited' ];
                    fputcsv($handle, $row );

                    foreach( $school_array['tracking'] as $tracking_value => $tracking_array ){
                        $row = [
                            $tracking_value,
                            $tracking_array['total'],
                            $tracking_array['qualified'],
                            $tracking_array['offered'],
                        ];
                        fputcsv($handle, $row );
                    }
                    $row = ['Total', $school_array['total'], $school_array['qualified'], $school_array['offered'] ];
                    fputcsv($handle, $row );
                }
            }

            fclose($handle);
        });

        $short_school_name = $magnet_school_name;
        $short_school_name = preg_replace("/[^A-Za-z0-9 ]/", "", $short_school_name );
        $short_school_name = str_replace( ' ', '_', $short_school_name );

        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$short_school_name.'_Applicant_Outcome.csv"');

        return $response;
    }
}