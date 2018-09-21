<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Process
 *
 * @ORM\Table(name="process")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\ProcessRepository")
 */
class Process {

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="addDateTime", type="datetime")
	 */
	private $addDateTime;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="event", type="string", length=255)
	 */
	private $event;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="type", type="string", length=255)
	 */
	private $type;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="running", type="boolean", options={"default":0})
	 */
	private $running = 0;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="completed", type="boolean", options={"default":0})
	 */
	private $completed = 0;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="completedDateTime", type="datetime", nullable=true)
	 */
	private $completedDateTime;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="submissionsAffected", type="integer", options={"default":0})
	 */
	private $submissionsAffected = 0;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(referencedColumnName="id", name="openEnrollment")
	 */
	protected $openEnrollment;

	public function __construct() {

		$this->addDateTime = new \DateTime();
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
	 * Set addDateTime
	 *
	 * @param \DateTime $addDateTime
	 *
	 * @return Process
	 */
	public function setAddDateTime( $addDateTime ) {

		$this->addDateTime = $addDateTime;

		return $this;
	}

	/**
	 * Get addDateTime
	 *
	 * @return \DateTime
	 */
	public function getAddDateTime() {

		return $this->addDateTime;
	}

	/**
	 * Set event
	 *
	 * Example 'Email' , 'Generate' , 'Lottery'
	 *
	 * @param string $event
	 *
	 * @return Process
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
	 * Set completed
	 *
	 * @param boolean $completed
	 *
	 * @return Process
	 */
	public function setCompleted( $completed ) {

		$this->completed = $completed;

		return $this;
	}

	/**
	 * Get completed
	 *
	 * @return boolean
	 */
	public function getCompleted() {

		return $this->completed;
	}

	/**
	 * Set completedDateTime
	 *
	 * @param \DateTime $completedDateTime
	 *
	 * @return Process
	 */
	public function setCompletedDateTime( $completedDateTime ) {

		$this->completedDateTime = $completedDateTime;

		return $this;
	}

	/**
	 * Get completedDateTime
	 *
	 * @return \DateTime
	 */
	public function getCompletedDateTime() {

		return $this->completedDateTime;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType() {

		return $this->type;
	}

	/**
	 * Set type
	 *
	 * Example 'awarded' , 'wait-list' , 'process'
	 *
	 * @param string $type
	 */
	public function setType( $type ) {

		$this->type = $type;
	}

	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return Process
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
	 * @return boolean
	 */
	public function isSubmissionsAffected() {

		return $this->submissionsAffected;
	}

	/**
	 * @param integer $submissionsAffected
	 */
	public function setSubmissionsAffected( $submissionsAffected = 0 ) {

		$this->submissionsAffected = $submissionsAffected;
	}

	/**
	 * Get submissionsAffected
	 *
	 * @return integer
	 */
	public function getSubmissionsAffected() {

		return $this->submissionsAffected;
	}

	/**
	 * @return boolean
	 */
	public function isRunning() {

		return $this->running;
	}

	/**
	 * @param boolean $running
	 */
	public function setRunning( $running ) {

		$this->running = $running;
	}


    /**
     * Get running
     *
     * @return boolean 
     */
    public function getRunning()
    {
        return $this->running;
    }
}
