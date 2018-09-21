<?php
/**
 * Company: Image In A Box
 * Date: 12/31/14
 * Time: 11:43 AM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * User
 * @ORM\Table(name="users")
 * @ORM\Entity
 */
class User extends BaseUser {

	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	protected $id;

	/**
	 * @var string
	 * @ORM\Column(name="firstName", type="string", length=255, nullable=true)
	 */
	private $firstName;

	/**
	 * @var string
	 * @ORM\Column(name="lastName", type="string", length=255, nullable=true)
	 */
	private $lastName;

	/**
	 * @var array
	 *
	 * @ORM\Column(name="schools", type="array", nullable=true)
	 */
	private $schools;

	public function __construct() {

		parent::__construct();

		$this->schools = array();
	}

	/**
	 * @return string
	 */
	public function getFirstName() {

		return $this->firstName;
	}

	/**
	 * @param string $firstName
	 */
	public function setFirstName( $firstName ) {

		$this->firstName = $firstName;
	}

	/**
	 * @return string
	 */
	public function getLastName() {

		return $this->lastName;
	}

	/**
	 * @param string $lastName
	 */
	public function setLastName( $lastName ) {

		$this->lastName = $lastName;
	}

	public function getName() {

		return $this->getLastName() . ', ' . $this->getFirstName();
	}

	/**
	 * @param $school
	 *
	 * @return $this
	 */
	public function addSchool( $school ) {

		$school = strtoupper( $school );

		if( empty( $this->schools) ){
			$this->schools = [];
		}

		if( !in_array( $school , $this->schools , true ) ) {
			$this->schools[] = $school;
		}
		return $this;
	}

	public function removeSchool( $school ) {
		if (false !== $key = array_search(strtoupper($school), $this->schools, true)) {
			unset($this->schools[$key]);
			$this->schools = array_values($this->schools);
		}

		return $this;
	}

	/**
	 * Returns the user schools
	 *
	 * @return array The Schools
	 */
	public function getSchools() {

		$schools = $this->schools;

		if( empty( $schools ) ) {
			return array();
		}

		return array_unique( $schools );
	}

	/**
	 * User has access to a specific school.
	 *
	 * @param string $school
	 *
	 * @return boolean
	 */
	public function hasSchool( $school ) {

		return in_array( strtoupper( $school ) , $this->getSchools() , true );
	}

    /**
     * Get id
     *
     * @return integer
	 */
	public function getId() {

		return $this->id;
	}

    /**
     * Set schools
     *
     * @param array $schools
     * @return User
     */
    public function setSchools($schools)
    {
        $this->schools = $schools;

        return $this;
    }

	/**
	 * Returns the user MagnetSchools for OpenEnrollment
	 *
	 * @return array MagnetSchools
	 */
    public function getMagnetSchoolsByOpenEnrollment( $openEnrollment ) {

    	$schools = $this->getSchools();
    	$programs = $openEnrollment->getPrograms();

    	$magnet_schools = [];

    	foreach( $programs as $program ){

	    	foreach( $program->getMagnetSchools() as $magnet_school ){

	    		if( empty( $schools ) ){
	    			$magnet_schools[] = $magnet_school;
	    		} else {
		    		foreach( $schools as $school ){
		    			if( strpos( $school, $magnet_school->getName() ) !== false ){
		    				$magnet_schools[] = $magnet_school;
		    			}
		    		}
		    	}
	    	}
	    }

    	usort( $magnet_schools, function( $a, $b ){
    		if ($a->getName() == $b->getName() ) {
    			if( $a->getGrade() == $b->getGrade() ){
    				return 0;
    			}

    			return( $a->getGrade() < $b->getGrade() ) ? -1 : 1;
    		}

    		return ( $a->getName() < $b->getName() ) ? -1 : 1;
    	});

    	return $magnet_schools;
    }
}
