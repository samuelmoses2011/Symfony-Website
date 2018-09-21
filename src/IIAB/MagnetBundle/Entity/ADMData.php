<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ADMData
 *
 * @ORM\Table(name="admdata")
 * @ORM\Entity
 */
class ADMData {

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="school", type="string", length=255)
	 */
	private $school;

	/**
	 * @var float
	 *
	 * @ORM\Column(name="black", type="float", options={"default":0})
	 */
	private $black = 0;

	/**
	 * @var float
	 *
	 * @ORM\Column(name="white", type="float", options={"default":0})
	 */
	private $white = 0;

	/**
	 * @var float
	 *
	 * @ORM\Column(name="other", type="float", options={"default":0})
	 */
	private $other = 0;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
	 */
	protected $openEnrollment;


	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {

		return $this->id;
	}

	/**
	 * Set black
	 *
	 * @param float $black
	 *
	 * @return ADMData
	 */
	public function setBlack( $black ) {

		$this->black = $black;

		return $this;
	}

	/**
	 * Get black
	 *
	 * @return float
	 */
	public function getBlack() {

		return $this->black;
	}

	/**
	 * Set white
	 *
	 * @param float $white
	 *
	 * @return ADMData
	 */
	public function setWhite( $white ) {

		$this->white = $white;

		return $this;
	}

	/**
	 * Get white
	 *
	 * @return float
	 */
	public function getWhite() {

		return $this->white;
	}

	/**
	 * Set other
	 *
	 * @param float $other
	 *
	 * @return ADMData
	 */
	public function setOther( $other ) {

		$this->other = $other;

		return $this;
	}

	/**
	 * Get other
	 *
	 * @return float
	 */
	public function getOther() {

		return $this->other;
	}

	/**
	 * Set school
	 *
	 * @param string $school
	 *
	 * @return ADMData
	 */
	public function setSchool( $school ) {

		$this->school = $school;

		return $this;
	}

	/**
	 * Get school
	 *
	 * @return string
	 */
	public function getSchool() {

		return $this->school;
	}

	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return ADMData
	 */
	public function setOpenEnrollment( \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment = null ) {

		$this->openEnrollment = $openEnrollment;

		return $this;
	}

	/**
	 * Get openEnrollment
	 *
	 * @return \IIAB\MagnetBundle\Entity\OpenEnrollment
	 */
	public function getOpenEnrollment() {

		return $this->openEnrollment;
	}
}
