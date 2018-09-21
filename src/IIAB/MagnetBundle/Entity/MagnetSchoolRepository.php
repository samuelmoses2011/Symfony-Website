<?php
/**
 * Created by PhpStorm.
 * User: michellegivens
 * Date: 12/26/14
 * Time: 3:40 PM
 */

namespace IIAB\MagnetBundle\Entity;


use Doctrine\ORM\EntityRepository;

class MagnetSchoolRepository extends EntityRepository {

	/**
	 * Get all the Magnet Schools by Grade and return the list in Ascending order.
	 *
	 * @param int $grade
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return array
	 */
	public function getSchoolsByGrade( $grade = 0, OpenEnrollment $openEnrollment = null ) {

		if( $openEnrollment == null ) {
			$openEnrollment = $this->_em->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findByDate( new \DateTime );
			$openEnrollment = ( $openEnrollment != null) ? $openEnrollment :  $this->_em->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findLatePlacementByDate( new \DateTime );

			if( $openEnrollment != null && count( $openEnrollment ) == 1 ) {
				$openEnrollment = $openEnrollment[0];
			} else {
				return [];
			}
		}

		return $this->createQueryBuilder( 'm' )
			->where( 'm.grade = :grade' )
			->andWhere( 'm.active = 1' )
			->setParameter( 'grade' , sprintf( '%d' , $grade ) )
			->andWhere( 'm.openEnrollment = :openEnrollment' )
			->setParameter( 'openEnrollment' , $openEnrollment )
			->orderBy( 'm.name' , 'ASC' )
			->getQuery()
			->getResult();
	}

	/**
	 * Get all the Magnet Schools which require the $requires.
	 *
	 * @param string $requires
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return array
	 */
	public function findByRequires( $requires, $openEnrollment ){
		$programs = $openEnrollment->getPrograms();

		$magnet_schools = [];
        foreach( $programs as $program ){
            foreach( $program->getMagnetSchools() as $magnet_school ){
            	if( $magnet_school->doesRequire($requires) ){
                	$magnet_schools[] = $magnet_school;
                }
            }
        }
        return $magnet_schools;
	}

	/**
	 * Get all Magnet School which the user has access to
	 */
	public function findByUser( $user, $openEnrollment ){

		$schools = $user->getSchools();

        // Get an array of MagnetSchools for the User
        $query = $this->createQueryBuilder('school')
            ->where( 'school.openEnrollment = :openEnrollment' )
            ->addOrderBy( 'school.name' , 'ASC' )
            ->addOrderBy( 'school.grade' , 'ASC' )
            ->setParameter( 'openEnrollment' , $openEnrollment );

        if( !empty( $schools ) && count( $schools ) == 1 ) {
            $query->andWhere( 'school.name LIKE :schools' )->setParameter( 'schools' , $schools );
        } else if( !empty( $schools ) && count( $schools ) > 1 ) {
            foreach( $schools as $key => $school ) {
                $query->orWhere( "school.name LIKE :school{$key}" )->setParameter( "school{$key}" , $school );
            }
        }
        return $query->getQuery()->getResult();
	}
}