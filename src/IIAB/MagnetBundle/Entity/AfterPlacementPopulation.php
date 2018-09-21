<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AfterPlacementPopulation
 *
 * @ORM\Table(name="afterplacementpopulation")
 * @ORM\Entity
 */
class AfterPlacementPopulation {

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="maxCapacity", type="integer", options={"default":0})
	 */
	private $maxCapacity = 0;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="CPWhite", type="integer", options={"default":0})
	 */
	private $CPWhite = 0;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="CPBlack", type="integer", options={"default":0})
	 */
	private $CPBlack = 0;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="CPOther", type="integer", options={"default":0})
	 */
	private $CPOther = 0;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="lastUpdateDateTime", type="datetime")
	 */
	private $lastUpdatedDateTime;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
	 */
	protected $openEnrollment;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
	 * @ORM\JoinColumn(name="magnetSchool", referencedColumnName="id")
	 */
	protected $magnetSchool;

    /**
     * @var string
     *
     * @ORM\Column(name="focus_area", type="string", length=255, nullable=true)
     */
    protected $focusArea;

	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {

		return $this->id;
	}

	/**
	 * @return int
	 */
	public function getMaxCapacity() {

		return $this->maxCapacity;
	}

	/**
	 * @param int $maxCapacity
	 */
	public function setMaxCapacity( $maxCapacity ) {

		$this->maxCapacity = $maxCapacity;
	}

	/**
	 * Set iPWhite
	 *
	 * @param integer $CPWhite
	 *
	 * @return CurrentPopulation
	 */
	public function setCPWhite( $CPWhite ) {

		$this->CPWhite = $CPWhite;

		return $this;
	}

	/**
	 * Get iPWhite
	 *
	 * @return integer
	 */
	public function getCPWhite() {

		return $this->CPWhite;
	}

	/**
	 * Set iPBlack
	 *
	 * @param integer $CPBlack
	 *
	 * @return CurrentPopulation
	 */
	public function setCPBlack( $CPBlack ) {

		$this->CPBlack = $CPBlack;

		return $this;
	}

	/**
	 * Get iPBlack
	 *
	 * @return integer
	 */
	public function getCPBlack() {

		return $this->CPBlack;
	}

	/**
	 * Set iPOther
	 *
	 * @param integer $CPOther
	 *
	 * @return CurrentPopulation
	 */
	public function setCPOther( $CPOther ) {

		$this->CPOther = $CPOther;

		return $this;
	}

	/**
	 * Get iPOther
	 *
	 * @return integer
	 */
	public function getCPOther() {

		return $this->CPOther;
	}

	/**
	 * This this class inline with CurrentPopulation.
	 * @return int
	 */
	public function getCPSumOther() {

		return $this->getCPOther();
	}

	/**
	 * @return \DateTime
	 */
	public function getLastUpdatedDateTime() {

		return $this->lastUpdatedDateTime;
	}

	/**
	 * @param \DateTime $lastUpdatedDateTime
	 */
	public function setLastUpdatedDateTime( $lastUpdatedDateTime ) {

		$this->lastUpdatedDateTime = $lastUpdatedDateTime;
	}

	/**
	 * Get the sum of the current population.
	 *
	 * @return int
	 */
	public function getCPSum() {

		return $this->CPBlack + $this->CPOther + $this->CPWhite;
	}

	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return CurrentPopulation
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

	/**
	 * Set magnetSchool
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool
	 *
	 * @return CurrentPopulation
	 */
	public function setMagnetSchool( \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool = null ) {

		$this->magnetSchool = $magnetSchool;

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
     * Set focusArea
     *
     * @param string $focusArea
     * @return AfterPlacementPopulation
     */
    public function setFocusArea($focusArea)
    {
        $this->focusArea = $focusArea;

        return $this;
    }

    /**
     * Get focusArea
     *
     * @return string 
     */
    public function getFocusArea()
    {
        return $this->focusArea;
    }
}
