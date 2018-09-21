<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WaitList
 *
 * @ORM\Table(name="waitlist")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\WaitListRepository")
 */
class WaitList {

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
	 * @ORM\Column(name="waitListDateTime", type="datetime")
	 */
	private $waitListDateTime;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Submission", inversedBy="waitList")
	 * @ORM\JoinColumn(referencedColumnName="id", name="submission")
	 */
	protected $submission;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
	 * @ORM\JoinColumn(referencedColumnName="id", name="choiceSchool")
	 */
	protected $choiceSchool;

    /**
     * @var string
     * @ORM\Column(name="choiceFocusArea", type="string", length=255, nullable=true)
     */
	protected $choiceFocusArea;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(referencedColumnName="id", name="openEnrollment")
	 */
	protected $openEnrollment;

	function __construct() {

		$this->waitListDateTime = new \DateTime();
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
	 * Set waitListDateTime
	 *
	 * @param \DateTime $waitListDateTime
	 *
	 * @return WaitList
	 */
	public function setWaitListDateTime( $waitListDateTime ) {

		$this->waitListDateTime = $waitListDateTime;

		return $this;
	}

	/**
	 * Get waitListDateTime
	 *
	 * @return \DateTime
	 */
	public function getWaitListDateTime() {

		return $this->waitListDateTime;
	}

	/**
	 * Set submission
	 *
	 * @param \IIAB\MagnetBundle\Entity\Submission $submission
	 *
	 * @return WaitList
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
	 * Set choiceSchool
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $choiceSchool
	 *
	 * @return WaitList
	 */
	public function setChoiceSchool( \IIAB\MagnetBundle\Entity\MagnetSchool $choiceSchool = null ) {

		$this->choiceSchool = $choiceSchool;

		return $this;
	}

	/**
	 * Get choiceSchool
	 *
	 * @return \IIAB\MagnetBundle\Entity\MagnetSchool
	 */
	public function getChoiceSchool() {

		return $this->choiceSchool;
	}

	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return WaitList
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
     * Set choiceFocusArea
     *
     * @param string $choiceFocusArea
     * @return WaitList
     */
    public function setChoiceFocusArea($choiceFocusArea)
    {
        $this->choiceFocusArea = $choiceFocusArea;

        return $this;
    }

    /**
     * Get choiceFocusArea
     *
     * @return string 
     */
    public function getChoiceFocusArea()
    {
        return $this->choiceFocusArea;
    }
}
