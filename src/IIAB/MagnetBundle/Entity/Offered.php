<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Offered
 *
 * @ORM\Table(name="offered")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\OfferedRepository")
 */
class Offered {

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
	 * @ORM\Column(name="url", type="string", length=255)
	 */
	private $url;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="offeredDateTime", type="datetime", nullable=true)
	 */
	private $offeredDateTime;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="onlineEndTime", type="datetime", nullable=true)
	 */
	private $onlineEndTime;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="offlineEndTime", type="datetime", nullable=true)
	 */
	private $offlineEndTime;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="accepted", type="integer", length=1, options={"default":0})
	 */
	private $accepted = 0;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="declined", type="integer", length=1, options={"default":0})
	 */
	private $declined = 0;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="changedDateTime", type="datetime", nullable=true)
	 */
	private $changedDateTime;

	/**
	 * @var string
	 *
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $acceptedBy;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Submission", inversedBy="offered")
	 * @ORM\JoinColumn(referencedColumnName="id", name="submission")
	 */
	protected $submission;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
	 * @ORM\JoinColumn(referencedColumnName="id", name="awardedSchool")
	 */
	protected $awardedSchool;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=true)
     */
	protected $awardedFocusArea;


	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(referencedColumnName="id", name="openEnrollment")
	 */
	protected $openEnrollment;


	function __construct() {

		$this->offeredDateTime = new \DateTime();
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
	 * Set url
	 *
	 * @param string $url
	 *
	 * @return Offered
	 */
	public function setUrl( $url ) {

		$this->url = $url;

		return $this;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	public function getUrl() {

		return $this->url;
	}

	/**
	 * Set offeredDateTime
	 *
	 * @param \DateTime $offeredDateTime
	 *
	 * @return Offered
	 */
	public function setOfferedDateTime( $offeredDateTime ) {

		$this->offeredDateTime = $offeredDateTime;

		return $this;
	}

	/**
	 * Get offeredDateTime
	 *
	 * @return \DateTime
	 */
	public function getOfferedDateTime() {

		return $this->offeredDateTime;
	}

	/**
	 * Set accepted
	 *
	 * @param integer $accepted
	 *
	 * @return Offered
	 */
	public function setAccepted( $accepted ) {

		$this->accepted = $accepted;

		return $this;
	}

	/**
	 * Get accepted
	 *
	 * @return integer
	 */
	public function getAccepted() {

		return $this->accepted;
	}

	/**
	 * Set declined
	 *
	 * @param integer $declined
	 *
	 * @return Offered
	 */
	public function setDeclined( $declined ) {

		$this->declined = $declined;

		return $this;
	}

	/**
	 * Get declined
	 *
	 * @return integer
	 */
	public function getDeclined() {

		return $this->declined;
	}

	/**
	 * Set changedDateTime
	 *
	 * @param \DateTime $changedDateTime
	 *
	 * @return Offered
	 */
	public function setChangedDateTime( $changedDateTime ) {

		$this->changedDateTime = $changedDateTime;

		return $this;
	}

	/**
	 * Get changedDateTime
	 *
	 * @return \DateTime
	 */
	public function getChangedDateTime() {

		return $this->changedDateTime;
	}

	/**
	 * @return mixed
	 */
	public function getAcceptedBy() {

		return $this->acceptedBy;
	}

	/**
	 * @param mixed $acceptedBy
	 */
	public function setAcceptedBy( $acceptedBy ) {

		$this->acceptedBy = $acceptedBy;
	}


	/**
	 * Set submission
	 *
	 * @param \IIAB\MagnetBundle\Entity\Submission $submission
	 *
	 * @return Offered
	 */
	public function setSubmission( \IIAB\MagnetBundle\Entity\Submission $submission = null ) {

		$this->submission = $submission;

		return $this;
	}

	/**
	 * Get submission
	 *
	 * @return \IIAB\MagnetBundle\Entity\Submission
	 */
	public function getSubmission() {

		return $this->submission;
	}

	/**
	 * Set awardedSchool
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $awardedSchool
	 *
	 * @return Offered
	 */
	public function setAwardedSchool( \IIAB\MagnetBundle\Entity\MagnetSchool $awardedSchool = null ) {

		$this->awardedSchool = $awardedSchool;

		return $this;
	}

	/**
	 * Get awardedSchool
	 *
	 * @return \IIAB\MagnetBundle\Entity\MagnetSchool
	 */
	public function getAwardedSchool() {

		return $this->awardedSchool;
	}


	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return Offered
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
	 * To String Function
	 *
	 * @return null|string
	 */
	function __toString() {

		if( $this->getAwardedSchool() != null ) {

			return $this->getAwardedSchool()->__toString();
		}
		return null;
	}

	/**
	 * @return \DateTime
	 */
	public function getOnlineEndTime() {

		return $this->onlineEndTime;
	}

	/**
	 * @param \DateTime $onlineEndTime
	 */
	public function setOnlineEndTime( $onlineEndTime ) {

		$this->onlineEndTime = $onlineEndTime;
	}

	/**
	 * @return \DateTime
	 */
	public function getOfflineEndTime() {

		return $this->offlineEndTime;
	}

	/**
	 * @param \DateTime $offlineEndTime
	 */
	public function setOfflineEndTime( $offlineEndTime ) {

		$this->offlineEndTime = $offlineEndTime;
	}

    /**
     * Set awardedFocusArea
     *
     * @param string $awardedFocusArea
     * @return Offered
     */
    public function setAwardedFocusArea($awardedFocusArea)
    {
        $this->awardedFocusArea = $awardedFocusArea;

        return $this;
    }

    /**
     * Get awardedFocusArea
     *
     * @return string 
     */
    public function getAwardedFocusArea()
    {
        return $this->awardedFocusArea;
    }
}
