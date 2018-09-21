<?php

namespace IIAB\MagnetBundle\Service;

class MagnetAcademicYearService {

    /** @var EntityManager **/
    private $entity_manager;

    /** Academic Year is equal to the ending year of the acadmic period **/
    private $YearApplyingTo;

    /** Academic Year is equal to the starting year of the acadmic period **/
    private $YearApplyingFrom;

    function __construct( $entity_manager ) {

        $this->entity_manager = $entity_manager;
    }

    public function getAcademicYear(){

        return ( intval( Date('m') ) < 6 ) ? intval( Date('Y') ) : intval( Date('Y') ) + 1;
    }

    public function getYearApplyingTo(){

        if( empty( $this->YearApplyingTo ) ){
            $this->setYear();
        }
        return $this->YearApplyingTo;
    }

    public function getYearApplyingFrom(){
        if( empty( $this->YearApplyingFrom ) ){
            $this->setYear();
        }
        return $this->YearApplyingFrom;
    }

    public function setYear(){

        if( !empty( $this->academic_year ) ){
            return $this->academic_year;
        }

        // Look for an active OpenEnrollment
        $openEnrollment = $this->entity_manager->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findByDate( new \DateTime() );

        // Look for an active Late Enrollment
        if( !count( $openEnrollment ) ) {
            $openEnrollment = $this->entity_manager->getRepository('IIABMagnetBundle:OpenEnrollment')->findLatePlacementByDate(new \DateTime());
        }

        if( !count( $openEnrollment ) ) {

            // Look for a Enrollment Period still accepting transcripts
            $placement = $this->entity_manager->getRepository('IIABMagnetBundle:Placement')
            ->createQueryBuilder('p')
            ->where( 'p.transcriptDueDate >= :now' )
            ->orderBy( 'p.transcriptDueDate' , 'ASC' )
            ->setMaxResults(1)
            ->setParameter( 'now' , date("Y-m-d H:i:s") );
            $placement = $placement->getQuery()->getResult();

            if( $placement ){
                $openEnrollment = [
                    $placement[0]->getOpenEnrollment()
                ];
            } else {
                // Look for the Current Enrollment Period
                $openEnrollment = $this->entity_manager->getRepository('IIABMagnetBundle:OpenEnrollment')
                ->createQueryBuilder('oe')
                ->where( 'oe.endingDate <= :now' )
                ->orderBy( 'oe.endingDate' , 'ASC' )
                ->setMaxResults(1)
                ->setParameter( 'now' , date("Y-m-d H:i:s") );
                $openEnrollment = $openEnrollment->getQuery()->getResult();
            }

            if( !$openEnrollment ){
                // Look for the Next Enrollment Period
                $openEnrollment = $this->entity_manager->getRepository('IIABMagnetBundle:OpenEnrollment')
                ->createQueryBuilder('oe')
                ->where( 'oe.beginningDate > :now' )
                ->orderBy( 'oe.beginningDate' , 'ASC' )
                ->setMaxResults(1)
                ->setParameter( 'now' , date("Y-m-d H:i:s") );
                $openEnrollment = $openEnrollment->getQuery()->getResult();
            }
        }

        $this->YearApplyingTo = ( isset( $openEnrollment[0] ) )
            ? intval( explode('-', $openEnrollment[0]->getYear())[1] )
            : intval( date('Y') );

        $this->YearApplyingFrom = ( isset( $openEnrollment[0] ) )
            ? intval( explode('-', $openEnrollment[0]->getYear())[0] )
            : intval( date('Y') );
    }
}