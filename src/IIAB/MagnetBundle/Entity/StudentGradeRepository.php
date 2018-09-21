<?php
/**
 * Company: Image In A Box
 * Date: 1/12/15
 * Time: 12:39 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\EntityRepository;

class StudentGradeRepository extends EntityRepository {


	/**
	 * Get all the grades for the student.
	 * @param string $stateID
	 *
	 * @return array
	 */
	public function findGradesByStateID( $stateID = '' ) {

		return $this->createQueryBuilder( 'g' )
			->where( 'g.stateID = :stateID' )
			->andWhere( 'g.academicYear IS NOT NULL' )
			->setParameter( 'stateID' , $stateID )
			->getQuery()
			->getResult();
	}
}