<?php
/**
 * Company: Image In A Box
 * Date: 2/9/15
 * Time: 3:08 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\EntityRepository;

class OfferedRepository extends EntityRepository {

	/**
	 * Gets all the Offered Submissions by OpenEnrollments
	 *
	 * @param OpenEnrollment $openEnrollment
	 *
	 * @return array
	 */
	public function findOfferedByOpenEnrollment( OpenEnrollment $openEnrollment ) {

		return $this->createQueryBuilder('offered')
			->leftJoin('offered.submission' , 'submission' )
			->where('submission.openEnrollment = :enrollment')
			->andWhere( 'offered.accepted = 0')
			->andWhere( 'offered.declined = 0')
			->setParameter('enrollment', $openEnrollment )
			->getQuery()
			->getResult();
	}
}