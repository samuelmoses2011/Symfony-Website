<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MagnetSchoolSetting
 *
 * @ORM\Table(name="magnetschoolsetting")
 * @ORM\Entity
 */
class MagnetSchoolSetting {

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="waitList", type="boolean", options={"default":false})
	 */
	private $waitList;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="committeeScoreRequired", type="boolean", options={"default":false})
	 */
	private $committeeScoreRequired;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="minimumCommitteeScore", type="integer", options={"default":0})
	 */
	private $minimumCommitteeScore = 0;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
	 * @ORM\JoinColumn(referencedColumnName="id", name="magnetSchool")
	 */
	protected $magnetSchool;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(referencedColumnName="id", name="openEnrollment")
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
	 * Set waitList
	 *
	 * @param boolean $waitList
	 *
	 * @return MagnetSchoolSetting
	 */
	public function setWaitList( $waitList ) {

		$this->waitList = $waitList;

		return $this;
	}

	/**
	 * Get waitList
	 *
	 * @return boolean
	 */
	public function getWaitList() {

		return $this->waitList;
	}

	/**
	 * Set committeeScoreRequired
	 *
	 * @param boolean $committeeScoreRequired
	 *
	 * @return MagnetSchoolSetting
	 */
	public function setCommitteeScoreRequired( $committeeScoreRequired ) {

		$this->committeeScoreRequired = $committeeScoreRequired;

		return $this;
	}

	/**
	 * Get committeeScoreRequired
	 *
	 * @return boolean
	 */
	public function getCommitteeScoreRequired() {

		return $this->committeeScoreRequired;
	}

	/**
	 * Set minimumCommitteeScore
	 *
	 * @param integer $minimumCommitteeScore
	 *
	 * @return MagnetSchoolSetting
	 */
	public function setMinimumCommitteeScore( $minimumCommitteeScore ) {

		$this->minimumCommitteeScore = $minimumCommitteeScore;

		return $this;
	}

	/**
	 * Get minimumCommitteeScore
	 *
	 * @return integer
	 */
	public function getMinimumCommitteeScore() {

		return $this->minimumCommitteeScore;
	}

	/**
	 * Set magnetSchool
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool
	 *
	 * @return MagnetSchoolSetting
	 */
	public function setMagnetSchool( \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool= null ) {

		$this->magnetSchool= $magnetSchool;

		return $this;
	}

	/**
	 * Get magnetSchool
	 *
	 * @return \IIAB\MagnetBundle\Entity\MagnetSchool
	 */
	public function getMagnetSchool() {

		return $this->magnetSchool;
	}

	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return MagnetSchoolSetting
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
