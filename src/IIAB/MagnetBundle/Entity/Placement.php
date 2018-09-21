<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Placement
 *
 * @ORM\Table(name="placement")
 * @ORM\Entity
 */
class Placement {

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
	 * @ORM\Column(name="emailAddress", type="text", nullable=true)
	 */
	private $emailAddress;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="addedDateTime", type="datetime")
	 */
	private $addedDateTime;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(name="eligibility", type="boolean")
	 */
	private $eligibility;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="awardedMailedDate", type="date", nullable=true)
	 */
	private $awardedMailedDate;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="waitListMailedDate", type="date", nullable=true)
	 */
	private $waitListMailedDate;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="nextStepMailedDate", type="date", nullable=true)
	 */
	private $nextStepMailedDate;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="deniedMailedDate", type="date", nullable=true)
	 */
	private $deniedMailedDate;

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
	 * @var \DateTime
	 *
	 * @ORM\Column(name="waitListOnlineEndTime", type="datetime", nullable=true)
	 */
	private $waitListOnlineEndTime;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="waitListOfflineEndTime", type="datetime", nullable=true)
	 */
	private $waitListOfflineEndTime;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="waitListExpireTime", type="datetime", nullable=true)
	 */
	private $waitListExpireTime;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="registrationNewStartDate", type="date", nullable=true)
	 */
	private $registrationNewStartDate;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="registrationCurrentStartDate", type="date", nullable=true)
	 */
	private $registrationCurrentStartDate;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="nextSchoolYear", type="string", length=255, nullable=true)
	 */
	private $nextSchoolYear;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="nextYear", type="string", length=255, nullable=true)
	 */
	private $nextYear;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="running", type="integer", length=1, options={"default":0})
	 */
	private $running = 0;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="completed", type="integer", length=1, options={"default":0})
	 */
	private $completed = 0;

	/**
	 * PreK Birthday Date Cut Off
	 *
	 * @var \DateTime
	 *
	 * @ORM\Column(name="preKDateCutOff", type="date", nullable=true)
	 */
	private $preKDateCutOff;

	/**
	 * Kindergarten Birthday Date Cut Off
	 *
	 * @var \DateTime
	 *
	 * @ORM\Column(name="kindergartenDateCutOff", type="date", nullable=true)
	 */
	private $kindergartenDateCutOff;

	/**
	 * First Grade Birthday Date Cut Off
	 *
	 * @var \DateTime
	 *
	 * @ORM\Column(name="firstGradeDateCutOff", type="date", nullable=true)
	 */
	private $firstGradeDateCutOff;

	/**
	 * Transcripts Due By Date
	 *
	 * @var \DateTime
	 *
	 * @ORM\Column(name="transcriptDueDate", type="date", nullable=true)
	 */
	private $transcriptDueDate;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
	 */
	protected $openEnrollment;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="round", type="integer", length=11, nullable=true, options={"default":0})
	 */
	private $round;

	/**
	 * @var string
	 *
	 * @Assert\Choice(choices = {"open", "late"}, message = "Choose a placement type.")
	 * @ORM\Column(name="type", type="string", length=255, nullable=true)
	 */
	private $type;

	/**
	 * @var array
	 */
	private $committeeSettings;

	/**
	 * @var array
	 */
	private $eligibilitySettings;

    /**
     * @var array
     */
    private $gpaSettings;

    /**
     * @var array
     */
    private $nextStep;

	/**
	 * @var array
	 */
	private $selectedSchools;


	public function __construct() {

		$this->addedDateTime = new \DateTime();
		$this->committeeSettings = [];
		$this->eligibilitySettings = [];
        $this->nextStep = [];
		$this->selectedSchools = [];
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
	 * Set emailAddress
	 *
	 * @param string $emailAddress
	 *
	 * @return Placement
	 */
	public function setEmailAddress( $emailAddress ) {

		$this->emailAddress = $emailAddress;

		return $this;
	}

	/**
	 * Get emailAddress
	 *
	 * @return string
	 */
	public function getEmailAddress() {

		return $this->emailAddress;
	}

	/**
	 * Set addedDateTime
	 *
	 * @param \DateTime $addedDateTime
	 *
	 * @return Placement
	 */
	public function setAddedDateTime( $addedDateTime ) {

		$this->addedDateTime = $addedDateTime;

		return $this;
	}

	/**
	 * Get addedDateTime
	 *
	 * @return \DateTime
	 */
	public function getAddedDateTime() {

		if( $this->addedDateTime == null ) {
			$this->addedDateTime = new \DateTime();
		}

		return $this->addedDateTime;
	}

	/**
	 * Set onlineEndTime
	 *
	 * @param \DateTime $onlineEndTime
	 *
	 * @return Placement
	 */
	public function setOnlineEndTime( $onlineEndTime ) {

		$this->onlineEndTime = $onlineEndTime;

		return $this;
	}

	/**
	 * Get onlineEndTime
	 *
	 * @return \DateTime
	 */
	public function getOnlineEndTime() {

		if( $this->onlineEndTime == null ) {
			$this->onlineEndTime = new \DateTime( 'midnight +30 day' );
		}

		return $this->onlineEndTime;
	}

	/**
	 * Set offlineEndTime
	 *
	 * @param \DateTime $offlineEndTime
	 *
	 * @return Placement
	 */
	public function setOfflineEndTime( $offlineEndTime ) {

		$this->offlineEndTime = $offlineEndTime;

		return $this;
	}

	/**
	 * Get offlineEndTime
	 *
	 * @return \DateTime
	 */
	public function getOfflineEndTime() {

		if( $this->offlineEndTime == null ) {
			$this->offlineEndTime = new \DateTime( '16:00 +30 day' );
		}

		return $this->offlineEndTime;
	}

	/**
	 * Set running
	 *
	 * @param integer $running
	 *
	 * @return Placement
	 */
	public function setRunning( $running ) {

		$this->running = $running;

		return $this;
	}

	/**
	 * Get running
	 *
	 * @return integer
	 */
	public function getRunning() {

		return $this->running;
	}

	/**
	 * Set completed
	 *
	 * @param integer $completed
	 *
	 * @return Placement
	 */
	public function setCompleted( $completed ) {

		$this->completed = $completed;

		return $this;
	}

	/**
	 * Get completed
	 *
	 * @return integer
	 */
	public function getCompleted() {

		return $this->completed;
	}

	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return Placement
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
	 * @return \DateTime
	 */
	public function getAwardedMailedDate() {

		if( $this->awardedMailedDate == null ) {
			$this->awardedMailedDate = new \DateTime( '+1 day' );
		}

		return $this->awardedMailedDate;
	}

	/**
	 * @param \DateTime $awardedMailedDate
	 */
	public function setAwardedMailedDate( $awardedMailedDate ) {

		$this->awardedMailedDate = $awardedMailedDate;
	}

	/**
	 * @return string
	 */
	public function getNextSchoolYear() {

		return $this->nextSchoolYear;
	}

	/**
	 * @param string $nextSchoolYear
	 */
	public function setNextSchoolYear( $nextSchoolYear ) {

		$this->nextSchoolYear = $nextSchoolYear;
	}

	/**
	 * @return string
	 */
	public function getNextYear() {

		return $this->nextYear;
	}

	/**
	 * @param string $nextYear
	 */
	public function setNextYear( $nextYear ) {

		$this->nextYear = $nextYear;
	}

	/**
	 * @return \DateTime
	 */
	public function getWaitListMailedDate() {

		if( $this->waitListMailedDate == null ) {
			$this->waitListMailedDate = new \DateTime( '+1 day' );
		}

		return $this->waitListMailedDate;
	}

	/**
	 * @param \DateTime $waitListMailedDate
	 */
	public function setWaitListMailedDate( $waitListMailedDate ) {

		$this->waitListMailedDate = $waitListMailedDate;
	}

	/**
	 * @return \DateTime
	 */
	public function getDeniedMailedDate() {

		if( $this->deniedMailedDate == null ) {
			$this->deniedMailedDate = new \DateTime( '+1 day' );
		}

		return $this->deniedMailedDate;
	}

	/**
	 * @param \DateTime $deniedMailedDate
	 */
	public function setDeniedMailedDate( $deniedMailedDate ) {

		$this->deniedMailedDate = $deniedMailedDate;
	}

	/**
	 * @return \DateTime
	 */
	public function getRegistrationNewStartDate() {

		if( $this->registrationNewStartDate == null ) {
			$this->registrationNewStartDate = new \DateTime( '+3 months' );
		}

		return $this->registrationNewStartDate;
	}

	/**
	 * @param \DateTime $registrationNewStartDate
	 */
	public function setRegistrationNewStartDate( $registrationNewStartDate ) {

		$this->registrationNewStartDate = $registrationNewStartDate;
	}

	/**
	 * @return \DateTime
	 */
	public function getRegistrationCurrentStartDate() {

		if( $this->registrationCurrentStartDate == null ) {
			$this->registrationCurrentStartDate = new \DateTime( '+4 months' );
		}

		return $this->registrationCurrentStartDate;
	}

	/**
	 * @param \DateTime $registrationCurrentStartDate
	 */
	public function setRegistrationCurrentStartDate( $registrationCurrentStartDate ) {

		$this->registrationCurrentStartDate = $registrationCurrentStartDate;
	}

	/**
	 * @return \DateTime
	 */
	public function getWaitListOnlineEndTime() {

		return $this->waitListOnlineEndTime;
	}

	/**
	 * @param \DateTime $waitListOnlineEndTime
	 */
	public function setWaitListOnlineEndTime( $waitListOnlineEndTime ) {

		$this->waitListOnlineEndTime = $waitListOnlineEndTime;
	}

	/**
	 * @return \DateTime
	 */
	public function getWaitListOfflineEndTime() {

		return $this->waitListOfflineEndTime;
	}

	/**
	 * @param \DateTime $waitListOfflineEndTime
	 */
	public function setWaitListOfflineEndTime( $waitListOfflineEndTime ) {

		$this->waitListOfflineEndTime = $waitListOfflineEndTime;
	}

	/**
	 * @return \DateTime
	 */
	public function getWaitListExpireTime() {

		return $this->waitListExpireTime;
	}

	/**
	 * @param \DateTime $waitListExpireTime
	 */
	public function setWaitListExpireTime( $waitListExpireTime ) {

		$this->waitListExpireTime = $waitListExpireTime;
	}

	/**
	 * Does the Placement require Eligibility
	 *
	 * @return boolean
	 */
	public function isEligibility() {

		return $this->eligibility;
	}

	/**
	 * @param boolean $eligibility
	 */
	public function setEligibility( $eligibility ) {

		$this->eligibility = $eligibility;
	}

	/**
	 * Get eligibility
	 *
	 * @return boolean
	 */
	public function getEligibility() {

		return $this->eligibility;
	}

	/**
	 * @return array
	 */
	public function getCommitteeSettings() {

		return $this->committeeSettings;
	}

	/**
	 * @param array $committeeSettings
	 */
	public function setCommitteeSettings( $committeeSettings ) {

		$this->committeeSettings = $committeeSettings;
	}

    /**
     * @return array
     */
    public function getGPASettings() {

        return $this->gpaSettings;
    }

    /**
     * @param array $gpaSettings
     */
    public function setGpaSettings( $gpaSettings ) {

        $this->gpaSettings = $gpaSettings;
    }

	/**
	 * @return array
	 */
	public function getEligibilitySettings() {

		return $this->eligibilitySettings;
	}

	/**
	 * @param array $eligibilitySettings
	 */
	public function setEligibilitySettings( $eligibilitySettings = [] ) {

		$this->eligibilitySettings = $eligibilitySettings;
	}

    /**
     * @return array
     */
    public function getnextStep() {

        return $this->nextStep;
    }

    /**
     * @param array $nextStep
     */
    public function setnextStep( $nextStep ) {

        $this->nextStep = $nextStep;
    }

	/**
	 * @return array
	 */
	public function getselectedSchools() {

		return $this->selectedSchools;
	}

	/**
	 * @param array $selectedSchools
	 */
	public function setselectedSchools( $selectedSchools ) {

		$this->selectedSchools = $selectedSchools;
	}

	/**
	 * @return \DateTime
	 */
	public function getPreKDateCutOff() {

		return $this->preKDateCutOff;
	}

	/**
	 * @param \DateTime $preKDateCutOff
	 */
	public function setPreKDateCutOff( $preKDateCutOff ) {

		$this->preKDateCutOff = $preKDateCutOff;
	}

	/**
	 * @return \DateTime
	 */
	public function getKindergartenDateCutOff() {

		return $this->kindergartenDateCutOff;
	}

	/**
	 * @param \DateTime $kindergartenDateCutOff
	 */
	public function setKindergartenDateCutOff( $kindergartenDateCutOff ) {

		$this->kindergartenDateCutOff = $kindergartenDateCutOff;
	}

	/**
	 * @return \DateTime
	 */
	public function getFirstGradeDateCutOff() {

		return $this->firstGradeDateCutOff;
	}

	/**
	 * @param \DateTime $firstGradeDateCutOff
	 */
	public function setFirstGradeDateCutOff( $firstGradeDateCutOff ) {

		$this->firstGradeDateCutOff = $firstGradeDateCutOff;
	}

	/**
	 * @return \DateTime
	 */
	public function getTranscriptDueDate() {

		return $this->transcriptDueDate;
	}

	/**
	 * @param \DateTime $transcriptDueDate
	 */
	public function setTranscriptDueDate( $transcriptDueDate ) {

		$this->transcriptDueDate = $transcriptDueDate;
	}

	/**
	 * @return \DateTime
	 */
	public function getNextStepMailedDate() {

		if( $this->nextStepMailedDate == null ) {
			$this->nextStepMailedDate = new \DateTime( '+1 day' );
		}

		return $this->nextStepMailedDate;
	}

	/**
	 * @param \DateTime $nextStepMailedDate
	 */
	public function setNextStepMailedDate( $nextStepMailedDate ) {

		$this->nextStepMailedDate = $nextStepMailedDate;
	}

    /**
     * Set round
     *
     * @param integer $round
     * @return Placement
     */
    public function setRound($round)
    {
        $this->round = $round;

        return $this;
    }

    /**
     * Get round
     *
     * @return integer 
     */
    public function getRound()
    {
        return $this->round;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return Placement
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string 
     */
    public function getType()
    {
        return $this->type;
    }
}
