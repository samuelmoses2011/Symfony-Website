<?php
/**
 * Company: Image In A Box
 * Date: 12/28/14
 * Time: 8:59 PM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\EntityRepository;

class OpenEnrollmentRepository extends EntityRepository {

	/**
	 * @param \DateTime $dateTime
	 *
	 * @return array
	 */
	public function findByDate( \DateTime $dateTime ) {

		return $this->createQueryBuilder( 'o' )
			->where( 'o.beginningDate <= :date' )
			->andWhere( 'o.endingDate >= :date' )
			->setParameter( 'date' , $dateTime )
			->setMaxResults( 1 )
			->getQuery()
			->getResult();
	}

	/**
	 * @param \DateTime $dateTime
	 *
	 * @return array
	 */
	public function findLatePlacementByDate( \DateTime $dateTime ) {

		return $this->createQueryBuilder( 'o' )
			->where( 'o.latePlacementBeginningDate <= :date' )
			->andWhere( 'o.latePlacementEndingDate >= :date' )
			->setParameter( 'date' , $dateTime )
			->setMaxResults( 1 )
			->getQuery()
			->getResult();
	}
}