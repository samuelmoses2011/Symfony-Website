<?php

namespace IIAB\MagnetBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use LeanFrog\SharedDataBundle\Entity\Population;
use LeanFrog\SharedDataBundle\Entity\AcademicYear;
use LeanFrog\SharedDataBundle\Entity\ProgramSchool;

class SharedPopulationCommand extends ContainerAwareCommand {

    protected function configure() {

        $this
            ->setName( 'magnet:shared:population:sync' )
            ->setDescription( 'Updates the Shared Populatin Table' )
            ->setHelp( <<<EOF
The <info>%command.name%</info>

EOF
            );
    }

    private $shared_manager = null;
    private $magnet_manager = null;
    private $address_service = null;

    protected function execute( InputInterface $input , OutputInterface $output ) {

        $em = $this->getContainer()->get( 'doctrine' );
        $this->address_service = $this->getContainer()->get('magnet.check.address');

        $this->shared_manager = $this->getContainer()->get( 'doctrine' )->getManager('shared');
        $this->magnet_manager = $this->getContainer()->get( 'doctrine' )->getManager();

        $last_shared_update = $this->shared_manager
            ->getRepository('lfSharedDataBundle:Population')
            ->createQueryBuilder( 'p' )
            ->where( "p.updateType like 'mpw%'")
            ->setMaxResults(1)
            ->orderBy( 'p.updateDateTime', 'DESC')
            ->getQuery()
            ->getResult();

        $last_shared_update = ( $last_shared_update )
            ? $last_shared_update[0]
            : null;

        $this->updateSharedFromMagnet( $last_shared_update );

        // $last_magnet_update = $this->magnet_manager
        //     ->getRepository('IIABMagnetBundle:Population')
        //     ->findOneBy(
        //         [],
        //         ['id' => 'DESC']
        //     );
        //$this->updateMagnetFromShared( $last_magnet_update );
    }

    private function getSharedAcademicYearFromOpenEnrollment( $openEnrollment ){

        //try using the year field
        $year_parts = explode( '-', $openEnrollment->getYear() );
        $year_key = false;
        if( count( $year_parts ) == 2 ){

            foreach( $year_parts as $index => $year ){

                $year = ( is_numeric( $year ) ) ? $year : false;

                if( $year ){
                    switch( strlen( (string)$year) ){
                        case 4:
                            break;
                        case 2:
                        case 3:
                            $year = 2000 + $year;
                            break;
                        default:
                            $year = false;
                            break;
                    }
                    $year_parts[ $index ] = $year;
                }
            }

            if( $year_parts[0] && $year_parts[1] ){
                $year_key = implode( '-', $year_parts );
            }
        }

        if( !$year_key ){
            $year_key = intval( $openEnrollment->getEndingDate()->format('Y') );
            $year_key = $year_key .'-'. ($year_key+1);
        }

        $academicYear = $this->shared_manager
            ->getRepository( 'lfSharedDataBundle:AcademicYear')
            ->findBy([
                'name' => $year_key
            ]);

        if( $academicYear == null ){
            $endDate = clone $openEnrollment->getBeginningDate();
            $endDate->modify( '+364 days' );
            $academicYear = new AcademicYear();
            $academicYear
                ->setName( $year_key )
                ->setStartDate( $openEnrollment->getBeginningDate() )
                ->setEndDate( $endDate );
            $this->shared_manager->persist( $academicYear );
            $this->shared_manager->flush();
        }

        return ( is_array( $academicYear ) ) ? $academicYear[0] : $academicYear;
    }

    private function getSharedSchoolFromMagnet( $school ){

        $shared_school_data = $this->shared_manager
            ->getRepository( 'lfSharedDataBundle:ProgramSchoolData' )
            ->findOneBy([
                'metaKey' => 'mpw_magnet_school',
                'metaValue' => $school->getId()
            ]);

        if( empty($shared_school_data ) ){

            $shared_school = new \LeanFrog\SharedDataBundle\Entity\ProgramSchool();
            $shared_school
                ->setName( $school->getName() )
                ->setGradeLevel( $school->getGrade() );

            $shared_school_data = new \LeanFrog\SharedDataBundle\Entity\ProgramSchoolData();
            $shared_school_data
                ->setProgramSchool( $shared_school )
                ->setMetaKey('mpw_magnet_school')
                ->setMetaValue( $school->getId() );
            $shared_school->addAdditionalDatum( $shared_school_data );

            $this->shared_manager->persist( $shared_school );
            $this->shared_manager->persist( $shared_school_data );
            $this->shared_manager->flush();
        }

        $school = $shared_school_data->getProgramSchool();

        return $school;
    }

    private function getSharedSchoolFromCurrent( $school_name, $grade_level ){

        $school = $this->shared_manager
            ->getRepository( 'lfSharedDataBundle:ProgramSchool' )
            ->findOneBy([
                'name' => $school_name,
                'gradeLevel' => $grade_level
            ]);

        if( empty($school ) ){

            $parent = $this->shared_manager
                ->getRepository( 'lfSharedDataBundle:ProgramSchool' )
                ->findOneBy([
                    'name' => $school_name,
                    'parent' => null
                ]);

            if( empty( $parent ) ){
                $parent = new ProgramSchool();
                $parent
                    ->setName( $school_name );
                $this->shared_manager->persist( $parent );
            }

            $school = new ProgramSchool();
            $school
                ->setName( $school_name )
                ->setGradeLevel( $grade_level )
                ->setParent( $parent );
            $this->shared_manager->persist( $school );

            $this->shared_manager->flush();
        }

        return $school;
    }

    private function getMagnetSchoolFromShared( $school ){

        $shared_school_data = $this->shared_manager
            ->getRepository( 'lfSharedDataBundle:ProgramSchoolData' )
            ->findOneBy([
                'metaKey' => 'mpw_magnet_school',
                'programSchool' => $school->getId()
            ]);
        $magnet = $this->shared_manager
            ->getRepository( 'IIABMagnetBundle:MagnetSchool' )
            ->find( $shared_school_data->getMetaValue() );

        if( $magnet == null ){
            return false;
        }
        return $magnet;
    }

    private function updateSharedFromMagnet( $last_shared_update  ){

        $magnet_shared_school_hash = [];

        $new_offers = $this->magnet_manager
            ->getRepository('IIABMagnetBundle:Offered')
            ->createQueryBuilder( 'o' );

        if( $last_shared_update != null ){
            $new_offers
                ->where( 'o.offeredDateTime > :last_update' )
                ->setParameter( 'last_update' , $last_shared_update->getUpdateDateTime() );
        }

        $new_offers = $new_offers
            ->orderBy( 'o.offeredDateTime', 'DESC')
            ->getQuery()
            ->getResult();

        $zoned_school_hash = [];
        $offered_race_count = [];


        foreach( $new_offers as $offer ){
            if( $offer->getSubmission()->getStateId() != null ){
                $zoned_school = $this->getZonedSchool( $offer );

                if( empty( $zoned_school_hash
                        [ $zoned_school ]
                        [ $offer->getSubmission()->getNextGrade() ]
                    )
                ){
                    $zoned_school_hash
                        [ $zoned_school ]
                        [ $offer->getSubmission()->getNextGrade() ] =
                            $this->getSharedSchoolFromCurrent(
                                $zoned_school,
                                $offer->getSubmission()->getNextGrade()
                            );
                }

                if( empty( $current_school_hash
                        [ $offer->getSubmission()->getCurrentSchool() ]
                        [ $offer->getSubmission()->getCurrentGrade() ]
                    )
                ){
                    $current_school_hash
                        [ $offer->getSubmission()->getCurrentSchool() ]
                        [ $offer->getSubmission()->getCurrentGrade() ] =
                            $this->getSharedSchoolFromCurrent(
                                $offer->getSubmission()->getCurrentSchool(),
                                $offer->getSubmission()->getCurrentGrade()
                            );
                }

                if( $zoned_school_hash
                        [ $zoned_school ]
                        [ $offer->getSubmission()->getNextGrade() ]
                ){
                    $race = $offer->getSubmission()->getRace();

                    if( $race->getReportAsOther() ){
                        $race = 'other';
                    } else if( $race->getReportAsNoAnswer() ){
                        $race = 'none';
                    } else {
                        $race = $race->getId();
                    }

                    $offer_date_key = $offer->getOfferedDateTime()->format( 'Y-m-d H:i:s' );

                    if( empty( $offered_race_count
                            [ $offer_date_key ]
                            [ $zoned_school ]
                            [ $offer->getSubmission()->getNextGrade() ]
                            [ $race ]
                        )
                    ){
                        $offered_race_count
                            [ $offer_date_key ]
                            [ $zoned_school ]
                            [ $offer->getSubmission()->getNextGrade() ]
                            [ $race ] = [
                                'count' => 0,
                                'openEnrollment' => $offer->getOpenEnrollment(),
                            ];
                    }

                    $offered_race_count
                            [ $offer_date_key ]
                            [ $zoned_school ]
                            [ $offer->getSubmission()->getNextGrade() ]
                            [ $race ]['count'] -= 1;
                }
            }
            var_dump( $offer->getId() );
        }

        var_dump( 'end offers' );

        foreach( $offered_race_count as $date_key => $schools ){

            foreach( $schools as $school => $grades ){

                foreach( $grades as $grade => $races ){

                    foreach( $races as $race => $count ){

                        $new_shared_population = new \LeanFrog\SharedDataBundle\Entity\Population();
                        $new_shared_population
                            ->setProgramSchool( $zoned_school_hash[ $school ][ $grade ] )
                            ->setTrackingColumn( 'Race' )
                            ->setTrackingValue( $race )
                            ->setCount( $count['count'] )
                            ->setUpdateType( 'mpw offer' )
                            ->setUpdateDateTime( new \DateTime( $date_key ) )
                            ->setAcademicYear( $this->getSharedAcademicYearFromOpenEnrollment( $count[ 'openEnrollment' ] ) );
                        $this->shared_manager->persist( $new_shared_population );
                        $this->shared_manager->flush();
                    }
                }
            }
        }

        var_dump( 'start declined' );
        $declined_offers = $this->magnet_manager
            ->getRepository('IIABMagnetBundle:Offered')
            ->createQueryBuilder( 'o' )
            ->where( 'o.declined = 1');

        if( $last_shared_update != null ){
            $declined_offers
                ->andWhere( 'o.changedDateTime > :last_update' )
                ->setParameter( 'last_update' , $last_shared_update->getUpdateDateTime() );
        }
        $declined_offers = $declined_offers
            ->getQuery()
            ->getResult();

        foreach( $declined_offers as $offer ){
            if( $offer->getSubmission()->getStateId() != null ){

                $zoned_school = $this->getZonedSchool( $offer );

                if( empty( $zoned_school_hash
                        [ $zoned_school ]
                        [ $offer->getSubmission()->getNextGrade() ]
                 ) ){
                    $current_school_hash
                        [ $zoned_school ]
                        [ $offer->getSubmission()->getNextGrade() ] =
                            $this->getSharedSchoolFromCurrent( $offer->getSubmission()->getCurrentSchool(), $offer->getSubmission()->getCurrentGrade() );
                }

                if( $current_school_hash
                    [ $zoned_school ]
                    [ $offer->getSubmission()->getNextGrade() ]
                ){

                    $race = $offer->getSubmission()->getRace();

                    if( $race->getReportAsOther() ){
                        $race = 'other';
                    } else if( $race->getReportAsNoAnswer() ){
                        $race = 'none';
                    } else {
                        $race = $race->getId();
                    }

                    // $last_shared_population = $this->shared_manager
                    //     ->getRepository( 'lfSharedDataBundle:Population' )
                    //     ->findOneBy([
                    //         'programSchool' => $current_school_hash
                    //             [ $offer->getSubmission()->getCurrentSchool() ]
                    //             [ $offer->getSubmission()->getCurrentGrade() ],
                    //         'trackingColumn' => 'Race',
                    //         'trackingValue' => $race
                    //     ],
                    //     ['updateDateTime', 'DESC']
                    // );

                    // $last_count = ( $last_shared_population != null )
                    //     ? $last_shared_population->getCount()
                    //     : 0;

                    $new_shared_population = new \LeanFrog\SharedDataBundle\Entity\Population();
                    $new_shared_population
                        ->setProgramSchool( $current_school_hash
                            [ $zoned_school ]
                            [ $offer->getSubmission()->getNextGrade() ]
                        )
                        ->setTrackingColumn( 'Race' )
                        ->setTrackingValue( $race )
                        ->setCount( 1 )
                        ->setUpdateType( 'mpw decline' )
                        ->setUpdateDateTime( $offer->getChangedDateTime() )
                        ->setAcademicYear( $this->getSharedAcademicYearFromOpenEnrollment( $offer->getOpenEnrollment() ) );
                    $this->shared_manager->persist( $new_shared_population );
                    $this->shared_manager->flush();
                }
            }
        }
        var_dump( 'finished' );
    }

    private function updateMagnetFromShared( $last_magnet_update ){

        $populations_to_copy = $this->shared_manager
            ->getRepository( 'lfSharedDataBundle:Population' )
            ->createQueryBuilder( 'p' )
            ->where( 'p.updateDateTime > :last_update' )
            ->andWhere( "p.trackingColumn = 'Race'")
            ->setParameter( 'last_update' , $last_magnet_update->getUpdateDateTime() )
            ->orderBy( 'p.id', 'ASC' )
            ->getQuery()
            ->getResult();

        $magnet_shared_school_hash = [];
        foreach( $populations_to_copy as $population ){
            if( empty( $magnet_shared_school_hash[ $population->getProgramSchool()->getId() ] ) ){

                $magnet_shared_school_hash[ $population->getProgramSchool()->getId() ] =
                    $this->getMagnetSchoolFromShared( $population->getProgramSchool() );
            }

            $magnet_school = $magnet_shared_school_hash[ $population->getProgramSchool()->getId() ];

            if( $magnet_school ){
                $new_population = new \IIAB\MagnetBundle\Entity\Population();
                $new_population
                    ->setMagnetSchool( $magnet_school )
                    ->setTrackingColumn( $population->getTrackingColumn() )
                    ->setTrackingValue( $population->getTrackingValue() )
                    ->setCount( $population->getCount() )
                    ->setUpdateType( $population->getUpdateType() )
                    ->setUpdateDateTime( $population->getUpdateDateTime() );
                $this->magnet_manager->persist( $new_population );
                $this->magnet_manager->flush();
            }
        }
    }

    function getZonedSchool( $offer ){
        $zoned_school = $offer->getSubmission()->getZonedSchool();

        if( !$zoned_school ){

            $zoned_schools = $this->address_service->checkAddress([
                'student_status' => '',
                'address' => $offer->getSubmission()->getAddress(),
                'zip' => $offer->getSubmission()->getZip(),
            ]);
            $zoned_school = $this->address_service->getZonedSchoolFromAddressResponse(
                $zoned_schools,
                $offer->getSubmission()->getNextGrade()
            );
            $zoned_school = (!empty( $zoned_school ) ) ? $zoned_school->getAlias() : '';
        }

        return (!empty( $zoned_school ) ) ? $zoned_school : 'none';
    }
}