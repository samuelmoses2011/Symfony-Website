<?php
/**
 * Created by PhpStorm.
 * User: michellegivens
 * Date: 12/26/14
 * Time: 2:28 PM
 */

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\EntityRepository;

class AddressBoundRepository extends EntityRepository {

	/**
	 * Find all the address like the parameter passed in.
     * limit provided to prevent memory limit issues
	 *
	 * @param $address
	 * @param $zip
     * @param $limit
	 *
	 * @return array
	 */
	public function findAddressLike( $address , $zip, $limit = 100 ) {

		return $this->createQueryBuilder( 'a' )
			->where( 'a.address_fu LIKE :address' )
			->andWhere( 'a.zipcode = :zip' )
			->setParameter( 'address' , $address . '%' )
			->setParameter( 'zip' , $zip )
            ->setMaxResults( $limit )
			->getQuery()
			->getResult();
	}

	/**
	 * Find a specific address without an wildcard parameters
     * limit provided to prevent memory limit issues
	 *
	 * @param $address
	 * @param $zip
     * @param $limit
	 *
	 * @return array
	 */
	public function findSpecificAddress( $address , $zip, $limit = 100 ) {

		return $this->createQueryBuilder( 'a' )
			->where( 'a.address_fu LIKE :address' )
			->andWhere( 'a.zipcode = :zip' )
			->setParameter( 'address' , $address )
			->setParameter( 'zip' , $zip )
            ->setMaxResults( $limit )
			->getQuery()
			->getResult();
	}
}