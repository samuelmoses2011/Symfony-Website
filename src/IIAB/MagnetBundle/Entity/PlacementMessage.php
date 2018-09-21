<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PlacementMessage
 *
 * @ORM\Table(name="placementmessage")
 * @ORM\Entity
 */
class PlacementMessage {

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
	 * @ORM\Column(name="interview", type="boolean")
	 */
	private $interview = 0;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="event", type="string", length=255, nullable=true)
	 */
	private $event;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="location", type="string", length=255, nullable=true)
	 */
	private $location;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="eventDate", type="date", nullable=true)
	 */
	private $eventDate;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="eventTime", type="string", length=255, nullable=true)
	 */
	private $eventTime;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="schoolName", type="string", length=255, nullable=true)
	 */
	private $schoolName;

	/**
	 * @var string
	 *
	 * @ORM\Column(type="text", nullable=true)
	 */
	private $specialRequirement;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="schoolPhoneNumber", type="string", length=12, nullable=true)
	 */
	private $schoolPhoneNumber;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
	 */
	protected $openEnrollment;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
	 * @ORM\JoinColumn(name="magnetSchool" , referencedColumnName="id")
	 */
	protected $magnetSchool;


	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {

		return $this->id;
	}

	/**
	 * Set interview
	 *
	 * @param boolean $interview
	 *
	 * @return PlacementMessage
	 */
	public function setInterview( $interview ) {

		$this->interview = $interview;

		return $this;
	}

	/**
	 * Get interview
	 *
	 * @return boolean
	 */
	public function getInterview() {

		return $this->interview;
	}

	/**
	 * Set event
	 *
	 * @param string $event
	 *
	 * @return PlacementMessage
	 */
	public function setEvent( $event ) {

		$this->event = $event;

		return $this;
	}

	/**
	 * Get event
	 *
	 * @return string
	 */
	public function getEvent() {

		return $this->event;
	}

	/**
	 * Set location
	 *
	 * @param string $location
	 *
	 * @return PlacementMessage
	 */
	public function setLocation( $location ) {

		$this->location = $location;

		return $this;
	}

	/**
	 * Get location
	 *
	 * @return string
	 */
	public function getLocation() {

		return $this->location;
	}

	/**
	 * Set eventDate
	 *
	 * @param \DateTime $eventDate
	 *
	 * @return PlacementMessage
	 */
	public function setEventDate( $eventDate ) {

		$this->eventDate = $eventDate;

		return $this;
	}

	/**
	 * Get eventDate
	 *
	 * @return \DateTime
	 */
	public function getEventDate() {

		return $this->eventDate;
	}

	/**
	 * Set eventTime
	 *
	 * @param string $eventTime
	 *
	 * @return PlacementMessage
	 */
	public function setEventTime( $eventTime ) {

		$this->eventTime = $eventTime;

		return $this;
	}

	/**
	 * Get eventTime
	 *
	 * @return string
	 */
	public function getEventTime() {

		return $this->eventTime;
	}

	/**
	 * Set schoolName
	 *
	 * @param string $schoolName
	 *
	 * @return PlacementMessage
	 */
	public function setSchoolName( $schoolName ) {

		$this->schoolName = $schoolName;

		return $this;
	}

	/**
	 * Get schoolName
	 *
	 * @return string
	 */
	public function getSchoolName() {

		return $this->schoolName;
	}

	/**
	 * Set specialRequirement
	 *
	 * @param string $specialRequirement
	 *
	 * @return PlacementMessage
	 */
	public function setSpecialRequirement( $specialRequirement ) {

		$this->specialRequirement = $specialRequirement;

		return $this;
	}

	/**
	 * Get specialRequirement
	 *
	 * @return string
	 */
	public function getSpecialRequirement() {

		return $this->specialRequirement;
	}

	/**
	 * Set schoolPhoneNumber
	 *
	 * @param string $schoolPhoneNumber
	 *
	 * @return PlacementMessage
	 */
	public function setSchoolPhoneNumber( $schoolPhoneNumber ) {

		$this->schoolPhoneNumber = $schoolPhoneNumber;

		return $this;
	}

	/**
	 * Get schoolPhoneNumber
	 *
	 * @return string
	 */
	public function getSchoolPhoneNumber() {

		return $this->schoolPhoneNumber;
	}

	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return PlacementMessage
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
	 * @return PlacementMessage
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
}
