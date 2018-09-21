<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use IIAB\MagnetBundle\Service\OrdinalService;

/**
 * Submission
 *
 * @ORM\Table(name="submission")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\SubmissionRepository")
 */
class Submission {

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
	 * @ORM\Column(name="stateID", type="integer", nullable=true)
	 */
	private $stateID;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="firstName", type="string", length=255)
	 */
	private $firstName;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="lastName", type="string", length=255)
	 */
	private $lastName;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Race")
	 * @ORM\JoinColumn(name="race", referencedColumnName="id", nullable=true)
	 */
	protected $race;

    /**
     * @var string
     *
     * @ORM\Column(name="gender", type="string", length=255, nullable=true)
     */
    private $gender;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="birthday", type="date")
	 */
	private $birthday;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="address", type="string", length=255)
	 */
	private $address;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="city", type="string", length=255)
	 */
	private $city;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="state", type="string", length=2)
	 */
	private $state;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="zip", type="string", length=255)
	 */
	private $zip;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="currentSchool", type="string", length=255)
	 */
	private $currentSchool;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="current_grade", type="integer")
	 */
	private $currentGrade;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="next_grade", type="integer")
	 */
	private $nextGrade;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="nonHSVStudent", type="integer", length=1)
	 */
	private $nonHSVStudent = 0;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="specialAccommodations", type="integer", length=1, options={"default":0})
	 */
	private $specialAccommodations = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="parentFirstName", type="string", length=255, nullable=true)
     */
	private $parentFirstName;

    /**
     * @var string
     *
     * @ORM\Column(name="parentLastName", type="string", length=255, nullable=true)
     */
	private $parentLastName;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="parentEmail", type="string", length=500, nullable=true)
	 */
	private $parentEmail;

    /**
     * @var string
     *
     * @ORM\Column(name="emergencyContact", type="string", length=255, nullable=true)
     */
    private $emergencyContact;

    /**
     * @var string
     *
     * @ORM\Column(name="emergencyContactPhone", type="string", length=255, nullable=true)
     */
    private $emergencyContactPhone;

    /**
     * @var string
     *
     * @ORM\Column(name="emergencyContactRelationship", type="string", length=255, nullable=true)
     */
    private $emergencyContactRelationship;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="createdAt", type="datetime", nullable=true)
	 */
	private $createdAt;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\SubmissionStatus")
	 * @ORM\JoinColumn(name="submissionStatus",referencedColumnName="id")
	 */
	protected $submissionStatus;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
	 * @ORM\JoinColumn(name="firstChoice", referencedColumnName="id", nullable=true)
	 */
	protected $firstChoice;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
	 * @ORM\JoinColumn(name="secondChoice", referencedColumnName="id", nullable=true)
	 */
	protected $secondChoice;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
	 * @ORM\JoinColumn(name="thirdChoice", referencedColumnName="id", nullable=true)
	 */
	protected $thirdChoice;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
	 */
	protected $openEnrollment;

	/**
	 * @var ArrayCollection
	 * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\SubmissionGrade", mappedBy="submission", cascade={"all"}, orphanRemoval=true)
	 * @ORM\OrderBy({"academicYear"="DESC", "academicTerm"="DESC"})
	 */
	protected $grades;

	/**
	 * @var ArrayCollection
	 * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\WaitList", mappedBy="submission")
	 */
	protected $waitList;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\Offered", mappedBy="submission")
     */
    protected $allOffers;

	/**
	 * @var Offered
	 * @ORM\OneToOne(targetEntity="IIAB\MagnetBundle\Entity\Offered", mappedBy="submission")
	 */
	protected $offered;

	/**
	 * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\SubmissionComment", mappedBy="submission", cascade={"all"} )
	 */
	protected $userComments;

	/**
	 * @var ArrayCollection
	 * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\SubmissionData", mappedBy="submission", cascade={"all"})
	 */
	private $additionalData;

	/**
	 * @var string
	 * @ORM\Column(name="phoneNumber", type="string", length=10, nullable=true)
	 */
	private $phoneNumber;

	/**
	 * @var string
	 * @ORM\Column(name="alternateNumber", type="string", length=10, nullable=true)
	 */
	private $alternateNumber;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="zonedSchool", type="string", length=500, nullable=true)
	 */
	private $zonedSchool;

	/** @var int Only used within the Admin System to change status to Offered. */
	private $offeredCreation;

	/** @var \DateTime Only used within the Admin System to change status to Offered. */
	private $offeredCreationEndOnlineTime;

	/** @var \DateTime Only used within the Admin System to change status to Offered. */
	private $offeredCreationEndOfflineTime;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="url", type="string", length=255)
	 */
	private $url;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="lotteryNumber", type="bigint")
	 */
	private $lotteryNumber;

	public function __construct() {

		$this->grades = new ArrayCollection();
		$this->additionalData = new ArrayCollection();
		$this->waitList = new ArrayCollection();
		$this->createdAt = new \DateTime();
		$this->userComments = new ArrayCollection();
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
	 * Set stateID
	 *
	 * @param integer $stateID
	 *
	 * @return Submission
	 */
	public function setStateID( $stateID ) {

		$this->stateID = $stateID;

		return $this;
	}

	/**
	 * Get stateID
	 *
	 * @return integer
	 */
	public function getStateID() {

		return $this->stateID;
	}

	/**
	 * Set firstName
	 *
	 * @param string $firstName
	 *
	 * @return Submission
	 */
	public function setFirstName( $firstName ) {

		$this->firstName = $firstName;

		return $this;
	}

	/**
	 * Get firstName
	 *
	 * @return string
	 */
	public function getFirstName() {

		return $this->firstName;
	}

	/**
	 * Set lastName
	 *
	 * @param string $lastName
	 *
	 * @return Submission
	 */
	public function setLastName( $lastName ) {

		$this->lastName = $lastName;

		return $this;
	}

	/**
	 * Get lastName
	 *
	 * @return string
	 */
	public function getLastName() {

		return $this->lastName;
	}

	/**
	 * Gets the Race in a specific format.
	 * This should be used all the time for requesting the Race
	 */
	public function getRaceFormatted() {

		$race = $this->getRace()->getShortName();
	}

	/**
	 * Set birthday
	 *
	 * @param \DateTime $birthday
	 *
	 * @return Submission
	 */
	public function setBirthday( $birthday ) {

		$this->birthday = $birthday;

		return $this;
	}

	/**
	 * Get birthday
	 *
	 * @return \DateTime
	 */
	public function getBirthday() {

		return $this->birthday;
	}

	/**
	 * Set address
	 *
	 * @param string $address
	 *
	 * @return Submission
	 */
	public function setAddress( $address ) {

		$this->address = $address;

		return $this;
	}

	/**
	 * Get address
	 *
	 * @return string
	 */
	public function getAddress() {

		return $this->address;
	}

	/**
	 * Set city
	 *
	 * @param string $city
	 *
	 * @return Submission
	 */
	public function setCity( $city ) {

		$this->city = $city;

		return $this;
	}

	/**
	 * Get city
	 *
	 * @return string
	 */
	public function getCity() {

		return $this->city;
	}

	/**
	 * @return string
	 */
	public function getState() {

		return $this->state;
	}

	/**
	 * @param string $state
	 */
	public function setState( $state ) {

		$this->state = $state;
	}

	/**
	 * Set zip
	 *
	 * @param string $zip
	 *
	 * @return Submission
	 */
	public function setZip( $zip ) {

		$this->zip = $zip;

		return $this;
	}

	/**
	 * Get zip
	 *
	 * @return string
	 */
	public function getZip() {

		return $this->zip;
	}

	/**
	 * Set currentSchool
	 *
	 * @param string $currentSchool
	 *
	 * @return Submission
	 */
	public function setCurrentSchool( $currentSchool ) {

		$this->currentSchool = $currentSchool;

		return $this;
	}

	/**
	 * Get currentSchool
	 *
	 * @return string
	 */
	public function getCurrentSchool() {

		return $this->currentSchool;
	}

	/**
	 * Set grade
	 *
	 * @param integer $currentGrade
	 *
	 * @return Submission
	 */
	public function setCurrentGrade( $currentGrade ) {

		$this->currentGrade = $currentGrade;

		return $this;
	}

	/**
	 * Get grade
	 *
	 * @return integer
	 */
	public function getCurrentGrade() {

		return $this->currentGrade;
	}

	/**
	 * @return int
	 */
	public function getNonHSVStudent() {

		return $this->nonHSVStudent;
	}

	/**
	 * @param int $nonHSVStudent
	 */
	public function setNonHSVStudent( $nonHSVStudent ) {

		$this->nonHSVStudent = $nonHSVStudent;
	}

	/**
	 * @return int
	 */
	public function getSpecialAccommodations() {

		return $this->specialAccommodations;
	}

	/**
	 * @param int $specialAccommodations
	 */
	public function setSpecialAccommodations( $specialAccommodations ) {

		$this->specialAccommodations = $specialAccommodations;
	}

	/**
	 * Set nextGrade
	 *
	 * @param integer $nextGrade
	 *
	 * @return Submission
	 */
	public function setNextGrade( $nextGrade ) {

		$this->nextGrade = $nextGrade;

		return $this;
	}

	/**
	 * Get nextGrade
	 *
	 * @return integer
	 */
	public function getNextGrade() {

		return $this->nextGrade;
	}

	/**
	 * Set submissionStatus
	 *
	 * @param \IIAB\MagnetBundle\Entity\SubmissionStatus $submissionStatus
	 *
	 * @return Submission
	 */
	public function setSubmissionStatus( SubmissionStatus $submissionStatus = null ) {

		$this->submissionStatus = $submissionStatus;

		return $this;
	}

	/**
	 * Get submissionStatus
	 *
	 * @return \IIAB\MagnetBundle\Entity\SubmissionStatus
	 */
	public function getSubmissionStatus() {

		return $this->submissionStatus;
	}

	/**
	 * Set firstChoice
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $firstChoice
	 *
	 * @return Submission
	 */
	public function setFirstChoice( MagnetSchool $firstChoice = null ) {

		$this->firstChoice = $firstChoice;

		return $this;
	}

	/**
	 * Get firstChoice
	 *
	 * @return \IIAB\MagnetBundle\Entity\MagnetSchool
	 */
	public function getFirstChoice() {

		return $this->firstChoice;
	}

	/**
	 * Set secondChoice
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $secondChoice
	 *
	 * @return Submission
	 */
	public function setSecondChoice( MagnetSchool $secondChoice = null ) {

		$this->secondChoice = $secondChoice;

		return $this;
	}

	/**
	 * Get secondChoice
	 *
	 * @return \IIAB\MagnetBundle\Entity\MagnetSchool
	 */
	public function getSecondChoice() {

		return $this->secondChoice;
	}

	/**
	 * Set thirdChoice
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $thirdChoice
	 *
	 * @return Submission
	 */
	public function setThirdChoice( MagnetSchool $thirdChoice = null ) {

		$this->thirdChoice = $thirdChoice;

		return $this;
	}

	/**
	 * Get thirdChoice
	 *
	 * @return \IIAB\MagnetBundle\Entity\MagnetSchool
	 */
	public function getThirdChoice() {

		return $this->thirdChoice;
	}

	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return Submission
	 */
	public function setOpenEnrollment( OpenEnrollment $openEnrollment = null ) {

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
	 * Set race
	 *
	 * @param \IIAB\MagnetBundle\Entity\Race $race
	 *
	 * @return Submission
	 */
	public function setRace( Race $race = null ) {

		$this->race = $race;

		return $this;
	}

	/**
	 * Get race
	 *
	 * @return \IIAB\MagnetBundle\Entity\Race
	 */
	public function getRace() {

		return $this->race;
	}

	/**
	 * Add grades
	 *
	 * @param \IIAB\MagnetBundle\Entity\SubmissionGrade $grades
	 *
	 * @return Submission
	 */
	public function addGrade( SubmissionGrade $grades ) {

		$this->grades[] = $grades;
		$grades->setSubmission( $this );

		return $this;
	}

	/**
	 * Remove grades
	 *
	 * @param \IIAB\MagnetBundle\Entity\SubmissionGrade $grades
	 */
	public function removeGrade( SubmissionGrade $grades ) {

		$this->grades->removeElement( $grades );
		$grades->setSubmission( null );
	}

	/**
	 * Get grades
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getGrades() {

		return $this->grades;
	}

	/**
	 * @return string
	 */
	public function getParentEmail() {

		return $this->parentEmail;
	}

	/**
	 * @param string $parentEmail
	 */
	public function setParentEmail( $parentEmail ) {

		$this->parentEmail = $parentEmail;
	}

	/**
	 * Add additionalData
	 *
	 * @param \IIAB\MagnetBundle\Entity\SubmissionData $additionalData
	 *
	 * @return Submission
	 */
	public function addAdditionalDatum( SubmissionData $additionalData ) {

		$this->additionalData[] = $additionalData;
		$additionalData->setSubmission( $this );
		return $this;
	}

	/**
	 * Remove additionalData
	 *
	 * @param \IIAB\MagnetBundle\Entity\SubmissionData $additionalData
	 */
	public function removeAdditionalDatum( SubmissionData $additionalData ) {

		$this->additionalData->removeElement( $additionalData );
		$additionalData->setSubmission( null );
	}

    /**
     * Get additionalData
     *
     * @param string $meta_key
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAdditionalDataByKey( $meta_key = '' )
    {
        if( !empty( $meta_key ) ){

            $data = $this->additionalData;
            $return = false;
            foreach( $data as $index => $data_object ){

                if( $data_object->getMetaKey() == $meta_key ){

                	if( !$return || $return->getId() < $data_object->getId() ){
                		$return = $data_object;
                	}
                }
            }
            return ( $return ) ? $return : null;
        }

        return null;
    }

	/**
	 * Get additionalData
	 *
	 * @param boolean $getAll
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getAdditionalData( $getAll = false ) {

		if( $getAll == true ) {
			return $this->additionalData;
		}

		$reserved_keys = MYPICK_CONFIG['submission_data_reserved_keys'];

		$tempArrayCollection = new ArrayCollection();
		foreach( $this->additionalData as $data ) {
			if( !in_array( $data->getMetaKey(), $reserved_keys )
				&& strpos($data->getMetaKey(), '_teacher_') === false
			) {
				$tempArrayCollection->add( $data );
			}
		}
		return $tempArrayCollection;
	}

	public function getName() {

		return $this->getLastName() . ', ' . $this->getFirstName();
	}

	/**
	 * @return \DateTime
	 */
	public function getCreatedAt() {

		return $this->createdAt;
	}

	/**
	 * @param \DateTime $createdAt
	 */
	public function setCreatedAt( $createdAt ) {

		$this->createdAt = $createdAt;
	}

	public function __toString() {

		return 'SPECIAL-' . $this->getOpenEnrollment()->getConfirmationStyle() . '-' . $this->getId();
	}

	/**
	 * Returns the String Current Grade
	 * @return int|string
	 */
	public function getCurrentGradeString() {

		if( $this->getCurrentGrade() >= 96 && $this->getCurrentGrade() < 99 ) {
			return 'None';
		} elseif( $this->getCurrentGrade() == 99 ) {
			return 'PreK';
		} elseif( $this->getCurrentGrade() == 0 ) {
			return 'K';
		} else {
			return $this->getCurrentGrade();
		}
	}

	/**
	 * Returns the String Next Grade
	 * @return int|string
	 */
	public function getNextGradeString() {

		if( $this->getNextGrade() >= 96 && $this->getNextGrade() < 99 ) {
			return 'None';
		} elseif( $this->getNextGrade() == 99 ) {
			return 'PreK';
		} elseif( $this->getNextGrade() == 0 ) {
			return 'K';
		} else {
			return $this->getNextGrade();
		}
	}

	/**
	 * Return the String of nonHSVString
	 * @return string
	 */
	public function getNonHSVStudentString() {

		if( $this->getNonHSVStudent() ) {
			return 'New';
		} else {
			return 'Current';
		}
	}

	/**
	 * Return the String of specialAccommodationsString
	 * @return string
	 */
	public function getSpecialAccommodationsString() {

		if( $this->getSpecialAccommodations() ) {
			return 'Yes';
		} else {
			return 'No';
		}
	}

	/**
	 * @return int
	 */
	public function getLotteryNumber() {

		return $this->lotteryNumber;
	}

	/**
	 * @param int $lotteryNumber
	 */
	public function setLotteryNumber( $lotteryNumber ) {

		$this->lotteryNumber = $lotteryNumber;
	}

	/**
	 * @param bool $format
	 *
	 * @return string
	 */
	public function getPhoneNumber( $format = false ) {

		if( $format && !empty( $this->phoneNumber ) ) {
			$phoneNumber = array_filter( preg_split( '/(\d{3})(\d{3})(\d{4})/' , $this->phoneNumber , -1 , PREG_SPLIT_DELIM_CAPTURE ) );
			return '(' . $phoneNumber[1] . ') ' . $phoneNumber[2] . '-' . $phoneNumber[3];
		}
		return $this->phoneNumber;
	}

	/**
	 * @param string $phoneNumber
	 */
	public function setPhoneNumber( $phoneNumber ) {

		$this->phoneNumber = $phoneNumber;
	}

	/**
	 * @param bool $format
	 *
	 * @return string
	 */

	public function getAlternateNumber( $format = false ) {

		if( $format && !empty( $this->alternateNumber ) ) {
			$alternateNumber = array_filter( preg_split( '/(\d{3})(\d{3})(\d{4})/' , $this->alternateNumber , -1 , PREG_SPLIT_DELIM_CAPTURE ) );
			return '(' . $alternateNumber[1] . ') ' . $alternateNumber[2] . '-' . $alternateNumber[3];
		}
		return $this->alternateNumber;
	}

	/**
	 * @param string $alternateNumber
	 */
	public function setAlternateNumber( $alternateNumber ) {

		$this->alternateNumber = $alternateNumber;
	}

	/**
	 * Return the confirmationStyle for the Export System.
	 * @return string
	 */
	public function getConfirmationStyleID() {

		return $this->__toString();
	}

	/**
	 * Returns the Birthday as a specific Format.
	 * @return string
	 */
	public function getBirthdayFormatted() {

		return $this->getBirthday()->format( 'm/d/y' );
	}

	/**
	 * Returns the CreatedAt as a specific Format.
	 * @return string
	 */
	public function getCreatedAtFormatted() {

		return $this->getCreatedAt()->format( 'm/d/y H:i' );
	}

	/**
	 * Returns the First Sibling Values
	 * @return null|string
	 */
	public function getFirstSiblingValue() {

		return $this->parseSubmissionData( 'First Choice Sibling ID' );
	}

	/**
	 * Returns the Second Sibling Values
	 * @return null|string
	 */
	public function getSecondSiblingValue() {

		return $this->parseSubmissionData( 'Second Choice Sibling ID' );
	}

	/**
	 * Returns the Third Sibling Values
	 * @return null|string
	 */
	public function getThirdSiblingValue() {

		return $this->parseSubmissionData( 'Third Choice Sibling ID' );
	}


	/**
	 * First Choice Committee Review Score
	 *
	 * @param null $score
	 */
	public function setCommitteeReviewScoreFirstChoice( $score = null ) {

		if( $score == null ) {
			return;
		}

        $foundScore = false;
        $data = $this->getAdditionalData( true );
        foreach( $data as $submissionData ) {
            if( $submissionData->getMetaKey() == 'Committee Review Score - First Choice' ) {
                $submissionData->setMetaValue( $score );
                $foundScore = true;
            }
        }

		if( !$foundScore ) {

			$subData = new SubmissionData();
			$subData->setMetaKey( 'Committee Review Score - First Choice' );
			$subData->setMetaValue( $score );
			$this->addAdditionalDatum( $subData );
		}
	}

    /**
     * Get the Focus data fields
     *
     * @return null|string
     */
    public function getFocusDataByChoice( $choice = 'first' ) {

    	// return empty array if focus is not used
    	return [];

        if( $this->nextGrade < 6 ) {
            $school_choice_focus_data = [
                $choice . '_choice_first_choice_focus' => [
                    'choices' => [],
                    'selected' => null,
                    'extra' => []
                ],
                $choice . '_choice_second_choice_focus' => [
                    'choices' => [],
                    'selected' => null,
                    'extra' => []
                ],
            ];
        } else {
            $school_choice_focus_data = [
                $choice . '_choice_first_choice_focus' => [
                    'choices' => [],
                    'selected' => null,
                    'extra' => []
                ],
                $choice . '_choice_second_choice_focus' => [
                    'choices' => [],
                    'selected' => null,
                    'extra' => []
                ],
                $choice . '_choice_third_choice_focus' => [
                    'choices' => [],
                    'selected' => null,
                    'extra' => []
                ],
            ];
        }

        $school_selector = 'get'. ucfirst( strtolower( $choice) ) .'Choice';

        if( !empty( $this->{$school_selector}() ) ) {

            $foci = $this->{$school_selector}()->getAdditionalData('focus');
            if (count($foci) == 0) {
                $foci = $this->{$school_selector}()->getProgram()->getAdditionalData('focus');
            }
            $focus_choices = [];
            foreach ($foci as $focus) {
                $focus_choices[] = [
                    'choice' => $focus->getMetaValue(),
                    'extra_field_1' => $focus->getExtraData1(),
                    'extra_field_2' => $focus->getExtraData2(),
                    'extra_field_3' => $focus->getExtraData3(),
                ];
            }

            foreach ($school_choice_focus_data as $key => $data_array) {

                $school_choice_focus_data[$key] = [
                    'choices' => $focus_choices,
                    'selected' => $this->parseSubmissionData($key),
                ];

                $school_choice_focus_data[$key]['extra'][$key . '_score' ] = [
                    'choices' => [
                        '0' => '0 (missed audition)',
                        '1' => '1' ,
                        '2' => '2' ,
                        '3' => '3' ,
                        '4' => '4' ,
                    ],
                    'selected' =>  $this->parseSubmissionData($key . '_score')
                ];
            }
        } else {
            foreach ($school_choice_focus_data as $key => $data_array) {
            }
        }
        return $school_choice_focus_data;
    }

	/**
	 * Get the First Choice Committee Score
	 *
	 * @return null|string
	 */
	public function getCommitteeReviewScoreFirstChoice() {

		return $this->parseSubmissionData( 'Committee Review Score - First Choice' );
	}

	/**
	 * Second Choice Committee Review Score
	 *
	 * @param null $score
	 */
	public function setCommitteeReviewScoreSecondChoice( $score = null ) {

		if( $score == null ) {
			return;
		}

        $foundScore = false;
        $data = $this->getAdditionalData( true );
        foreach( $data as $submissionData ) {
            if( $submissionData->getMetaKey() == 'Committee Review Score - Second Choice' ) {
                $submissionData->setMetaValue( $score );
                $foundScore = true;
            }
        }

        if( !$foundScore ) {

            $subData = new SubmissionData();
            $subData->setMetaKey( 'Committee Review Score - Second Choice' );
            $subData->setMetaValue( $score );
            $this->addAdditionalDatum( $subData );
        }

	}

	/**
	 * Get the Second Choice Committee Score
	 *
	 * @return null|string
	 */
	public function getCommitteeReviewScoreSecondChoice() {

		return $this->parseSubmissionData( 'Committee Review Score - Second Choice' );
	}

	/**
	 * Third Choice Committee Review Score
	 *
	 * @param null $score
	 */
	public function setCommitteeReviewScoreThirdChoice( $score = null ) {

		if( $score == null ) {
			return;
		}

        $foundScore = false;
        $data = $this->getAdditionalData( true );
        foreach( $data as $submissionData ) {
            if( $submissionData->getMetaKey() == 'Committee Review Score - Third Choice' ) {
                $submissionData->setMetaValue( $score );
                $foundScore = true;
            }
        }

        if( !$foundScore ) {

            $subData = new SubmissionData();
            $subData->setMetaKey( 'Committee Review Score - Third Choice' );
            $subData->setMetaValue( $score );
            $this->addAdditionalDatum( $subData );
        }

	}

	/**
	 * Get the Third Choice Committee Score
	 *
	 * @return null|string
	 */
	public function getCommitteeReviewScoreThirdChoice() {

		return $this->parseSubmissionData( 'Committee Review Score - Third Choice' );
	}

	/**
	 * Looks over the SubmissionData and returns a specific Key
	 *
	 * @param string $key
	 *
	 * @return string|null
	 */
	private function parseSubmissionData( $key = '' ) {

		if( empty( $key ) ) {
			return null;
		}

		$data = $this->getAdditionalData( true );
		foreach( $data as $submissionData ) {
			if( $submissionData->getMetaKey() == $key ) {
				return $submissionData->getMetaValue();
			}
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getZonedSchool() {

		return $this->zonedSchool;
	}

	/**
	 * @param string $zonedSchool
	 */
	public function setZonedSchool( $zonedSchool ) {

		$this->zonedSchool = $zonedSchool;
	}


	/**
	 * Add waitList
	 *
	 * @param \IIAB\MagnetBundle\Entity\WaitList $waitList
	 *
	 * @return Submission
	 */
	public function addWaitList( \IIAB\MagnetBundle\Entity\WaitList $waitList ) {

		$this->waitList[] = $waitList;

		return $this;
	}

	/**
	 * Remove waitList
	 *
	 * @param \IIAB\MagnetBundle\Entity\WaitList $waitList
	 */
	public function removeWaitList( \IIAB\MagnetBundle\Entity\WaitList $waitList ) {

		$this->waitList->removeElement( $waitList );
	}

	/**
	 * Get waitList
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getWaitList() {

		return $this->waitList;
	}

	/**
	 * Set offered
	 *
	 * @param \IIAB\MagnetBundle\Entity\Offered $offered
	 *
	 * @return Submission
	 */
	public function setOffered( \IIAB\MagnetBundle\Entity\Offered $offered = null ) {

		$this->offered = $offered;

		return $this;
	}

	/**
	 * Get offered
	 *
	 * @return \IIAB\MagnetBundle\Entity\Offered
	 */
	public function getOffered() {

	    $offers = $this->allOffers;

	    if( empty( $offers ) ){
	        return $this->offered;
        }

        $current_offer = null;
        foreach( $offers as $offer ){
	        if( empty( $current_offer) || $current_offer->getOfferedDateTime() < $offer->getOfferedDateTime() ){
	            $current_offer = $offer;
            }
        }

		return $current_offer;
	}

	/**
	 * Only used for when change an submission into Offered via the Admin
	 */
	public function getOfferedCreation() {

		return $this->offeredCreation;
	}

	/**
	 * Only used for when change an submission into Offered via the Admin
	 *
	 * @param $data
	 *
	 * @return \IIAB\MagnetBundle\Entity\Offered
	 */
	public function setOfferedCreation( $data ) {

		$this->offeredCreation = $data;

		return $this;
	}

	/**
	 * Only used for when change an submission into Offered via the Admin
	 *
	 * @return \DateTime
	 */
	public function getOfferedCreationEndOnlineTime() {

		return $this->offeredCreationEndOnlineTime;
	}

	/**
	 * Only used for when change an submission into Offered via the Admin
	 *
	 * @param \DateTime $offeredCreationEndOnlineTime
	 */
	public function setOfferedCreationEndOnlineTime( $offeredCreationEndOnlineTime ) {

		$this->offeredCreationEndOnlineTime = $offeredCreationEndOnlineTime;
	}

	/**
	 * Only used for when change an submission into Offered via the Admin
	 *
	 * @return \DateTime
	 */
	public function getOfferedCreationEndOfflineTime() {

		return $this->offeredCreationEndOfflineTime;
	}

	/**
	 * Only used for when change an submission into Offered via the Admin
	 *
	 * @param \DateTime $offeredCreationEndOfflineTime
	 */
	public function setOfferedCreationEndOfflineTime( $offeredCreationEndOfflineTime ) {

		$this->offeredCreationEndOfflineTime = $offeredCreationEndOfflineTime;
	}

    /**
     * Add userComments
     *
     * @param \IIAB\MagnetBundle\Entity\SubmissionComment $userComments
     * @return Submission
     */
    public function addUserComment(\IIAB\MagnetBundle\Entity\SubmissionComment $userComments)
    {
        $this->userComments[] = $userComments;

        return $this;
    }

    /**
     * Remove userComments
     *
     * @param \IIAB\MagnetBundle\Entity\SubmissionComment $userComments
     */
    public function removeUserComment(\IIAB\MagnetBundle\Entity\SubmissionComment $userComments)
    {
        $this->userComments->removeElement($userComments);
    }

    /**
     * Get userComments
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUserComments()
    {
        return $this->userComments;
    }

	public function getIsLateSubmission()
	{
		return ( $this->getOpenEnrollment()->getEndingDate()->modify('+1 day') < $this->getCreatedAt() ) ? 'Late' : '';
	}

    /**
     * Set gender
     *
     * @param string $gender
     * @return Submission
     */
    public function setGender($gender)
    {
        $this->gender = $gender;

        return $this;
    }

    /**
     * Get gender
     *
     * @return string
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     * Set emergencyContact
     *
     * @param string $emergencyContact
     * @return Submission
     */
    public function setEmergencyContact($emergencyContact)
    {
        $this->emergencyContact = $emergencyContact;

        return $this;
    }

    /**
     * Get emergencyContact
     *
     * @return string
     */
    public function getEmergencyContact()
    {
        return $this->emergencyContact;
    }

    /**
     * Set emergencyContactPhone
     *
     * @param string $emergencyContactPhone
     * @return Submission
     */
    public function setEmergencyContactPhone($emergencyContactPhone)
    {
        $this->emergencyContactPhone = $emergencyContactPhone;

        return $this;
    }

    /**
     * Get emergencyContactPhone
     *
     * @return string
     */
    public function getEmergencyContactPhone()
    {
        return $this->emergencyContactPhone;
    }

    /**
     * Set emergencyContactRelationship
     *
     * @param string $emergencyContactRelationship
     * @return Submission
     */
    public function setEmergencyContactRelationship($emergencyContactRelationship)
    {
        $this->emergencyContactRelationship = $emergencyContactRelationship;

        return $this;
    }

    /**
     * Get emergencyContactRelationship
     *
     * @return string
     */
    public function getEmergencyContactRelationship()
    {
        return $this->emergencyContactRelationship;
    }

    /**
     * Set parentFirstName
     *
     * @param string $parentFirstName
     * @return Submission
     */
    public function setParentFirstName($parentFirstName)
    {
        $this->parentFirstName = $parentFirstName;

        return $this;
    }

    /**
     * Get parentFirstName
     *
     * @return string
     */
    public function getParentFirstName()
    {
        return $this->parentFirstName;
    }

    /**
     * Set parentLastName
     *
     * @param string $parentLastName
     * @return Submission
     */
    public function setParentLastName($parentLastName)
    {
        $this->parentLastName = $parentLastName;

        return $this;
    }

    /**
     * Get parentLastName
     *
     * @return string
     */
    public function getParentLastName()
    {
        return $this->parentLastName;
    }
//
//    public function getChoiceDataByFocus(){
//
//        if(empty( $this->getfirstChoice() ) || empty( $this->getFirstChoiceFirstChoiceFocus()  ) ){
//            return null;
//        }
//
//        $foci = $this->getFirstChoice()->getProgram()->getAdditionalData( 'focus' );
//
//        foreach( $foci as $focus ){
//            if( $focus->getMetaValue() == $this->getFirstChoiceFirstChoiceFocus() ) {
//                return $focus;
//        }
//    }

    public function getGPA(){
        return $this->parseSubmissionData( 'calculated_gpa' );
    }

    public function getFirstChoiceTestScore(){
        return $this->parseSubmissionData( 'first_choice_assessment_test_score' );
    }

    public function getFirstChoiceTestEligible(){
        return $this->parseSubmissionData( 'first_choice_assessment_test_eligible' );
    }

    public function getSecondChoiceTestScore(){
        return $this->parseSubmissionData( 'second_choice_assessment_test_score' );
    }

    public function getSecondChoiceTestEligible(){
        return $this->parseSubmissionData( 'second_choice_assessment_test_eligible' );
    }

    public function getThirdChoiceTestScore(){
        return $this->parseSubmissionData( 'third_choice_assessment_test_score' );
    }

    public function getThirdChoiceTestEligible(){
        return $this->parseSubmissionData( 'third_choice_assessment_test_eligible' );
    }

    public function getConductGPA(){
        return $this->parseSubmissionData( 'conduct_gpa' );
    }

    public function getConductEligible(){
        return $this->parseSubmissionData( 'conduct_eligible' );
    }

    public function getOrientation(){
        return $this->parseSubmissionData( 'orientation' );
    }


    public function getFirstChoiceFirstChoiceFocus(){
        return $this->parseSubmissionData( 'first_choice_first_choice_focus' );
    }

    public function getFirstChoiceFirstChoiceFocusExtra1(){
        return $this->parseSubmissionData( 'first_choice_first_choice_focus_extra_1' );
    }

    public function getFirstChoiceFirstChoiceFocusExtra2(){
        return $this->parseSubmissionData( 'first_choice_first_choice_focus_extra_2' );
    }

    public function getFirstChoiceFirstChoiceFocusExtra3(){
        return $this->parseSubmissionData( 'first_choice_first_choice_focus_extra_3' );
    }

    public function getFirstChoiceSecondChoiceFocus(){
        return $this->parseSubmissionData( 'first_choice_second_choice_focus' );
    }

    public function getFirstChoiceSecondChoiceFocusExtra1(){
        return $this->parseSubmissionData( 'first_choice_second_choice_focus_extra_1' );
    }

    public function getFirstChoiceSecondChoiceFocusExtra2(){
        return $this->parseSubmissionData( 'first_choice_second_choice_focus_extra_2' );
    }

    public function getFirstChoiceSecondChoiceFocusExtra3(){
        return $this->parseSubmissionData( 'first_choice_second_choice_focus_extra_3' );
    }

    public function getFirstChoiceThirdChoiceFocus(){
        return $this->parseSubmissionData( 'first_choice_third_choice_focus' );
    }

    public function getFirstChoiceThirdChoiceFocusExtra1(){
        return $this->parseSubmissionData( 'first_choice_third_choice_focus_extra_1' );
    }

    public function getFirstChoiceThirdChoiceFocusExtra2(){
        return $this->parseSubmissionData( 'first_choice_third_choice_focus_extra_2' );
    }

    public function getFirstChoiceThirdChoiceFocusExtra3(){
        return $this->parseSubmissionData( 'first_choice_third_choice_focus_extra_3' );
    }




    public function getSecondChoiceFirstChoiceFocus(){
        return $this->parseSubmissionData( 'second_choice_first_choice_focus' );
    }

    public function getSecondChoiceFirstChoiceFocusExtra1(){
        return $this->parseSubmissionData( 'second_choice_first_choice_focus_extra_1' );
    }

    public function getSecondChoiceFirstChoiceFocusExtra2(){
        return $this->parseSubmissionData( 'second_choice_first_choice_focus_extra_2' );
    }

    public function getSecondChoiceFirstChoiceFocusExtra3(){
        return $this->parseSubmissionData( 'second_choice_first_choice_focus_extra_3' );
    }

    public function getSecondChoiceSecondChoiceFocus(){
        return $this->parseSubmissionData( 'second_choice_second_choice_focus' );
    }

    public function getSecondChoiceSecondChoiceFocusExtra1(){
        return $this->parseSubmissionData( 'second_choice_second_choice_focus_extra_1' );
    }

    public function getSecondChoiceSecondChoiceFocusExtra2(){
        return $this->parseSubmissionData( 'second_choice_second_choice_focus_extra_2' );
    }

    public function getSecondChoiceSecondChoiceFocusExtra3(){
        return $this->parseSubmissionData( 'second_choice_second_choice_focus_extra_3' );
    }

    public function getSecondChoiceThirdChoiceFocus(){
        return $this->parseSubmissionData( 'second_choice_third_choice_focus' );
    }

    public function getSecondChoiceThirdChoiceFocusExtra1(){
        return $this->parseSubmissionData( 'second_choice_third_choice_focus_extra_1' );
    }

    public function getSecondChoiceThirdChoiceFocusExtra2(){
        return $this->parseSubmissionData( 'second_choice_third_choice_focus_extra_2' );
    }

    public function getSecondChoiceThirdChoiceFocusExtra3(){
        return $this->parseSubmissionData( 'second_choice_third_choice_focus_extra_3' );
    }


    public function getThirdChoiceFirstChoiceFocus(){
        return $this->parseSubmissionData( 'third_choice_first_choice_focus' );
    }

    public function getThirdChoiceFirstChoiceFocusExtra1(){
        return $this->parseSubmissionData( 'third_choice_first_choice_focus_extra_1' );
    }

    public function getThirdChoiceFirstChoiceFocusExtra2(){
        return $this->parseSubmissionData( 'third_choice_first_choice_focus_extra_2' );
    }

    public function getThirdChoiceFirstChoiceFocusExtra3(){
        return $this->parseSubmissionData( 'third_choice_first_choice_focus_extra_3' );
    }

    public function getThirdChoiceSecondChoiceFocus(){
        return $this->parseSubmissionData( 'third_choice_second_choice_focus' );
    }

    public function getThirdChoiceSecondChoiceFocusExtra1(){
        return $this->parseSubmissionData( 'third_choice_second_choice_focus_extra_1' );
    }

    public function getThirdChoiceSecondChoiceFocusExtra2(){
        return $this->parseSubmissionData( 'third_choice_second_choice_focus_extra_2' );
    }

    public function getThirdChoiceSecondChoiceFocusExtra3(){
        return $this->parseSubmissionData( 'third_choice_second_choice_focus_extra_3' );
    }

    public function getThirdChoiceThirdChoiceFocus(){
        return $this->parseSubmissionData( 'third_choice_third_choice_focus' );
    }

    public function getThirdChoiceThirdChoiceFocusExtra1(){
        return $this->parseSubmissionData( 'third_choice_third_choice_focus_extra_1' );
    }

    public function getThirdChoiceThirdChoiceFocusExtra2(){
        return $this->parseSubmissionData( 'third_choice_third_choice_focus_extra_2' );
    }

    public function getThirdChoiceThirdChoiceFocusExtra3(){
        return $this->parseSubmissionData( 'third_choice_third_choice_focus_extra_3' );
    }

    public function getFirstChoiceCombinedAuditionScore(){
        return $this->parseSubmissionData( 'first_choice_combined_audition_score' );
    }

    public function getSecondChoiceCombinedAuditionScore(){
        return $this->parseSubmissionData( 'second_choice_combined_audition_score' );
    }

    public function getThirdChoiceCombinedAuditionScore(){
        return $this->parseSubmissionData( 'third_choice_combined_audition_score' );
    }

    public function doesRequire( $key ){
        $first_choice = ( !empty( $this->getFirstChoice() ) ) ? $this->getFirstChoice()->doesRequire( $key ) : false;
        $second_choice = ( !empty( $this->getSecondChoice() ) ) ? $this->getSecondChoice()->doesRequire( $key ) : false;
        $third_choice = ( !empty( $this->getThirdChoice() ) ) ? $this->getThirdChoice()->doesRequire( $key ) : false;

        if( $first_choice || $second_choice || $third_choice ){
            return true;
        }
        return false;
    }

    public function getSchoolChoiceIndex( MagnetSchool $magnet_school ){

        $school_id = $magnet_school->getId();
        if( !empty( $this->getFirstChoice() ) && $this->getFirstChoice()->getId() == $school_id ){
            return 1;
        }

        if( !empty( $this->getSecondChoice() ) && $this->getSecondChoice()->getId() == $school_id ){
            return 2;
        }

        if( !empty( $this->getThirdChoice() ) && $this->getThirdChoice()->getId() == $school_id ){
            return 3;
        }
        return false;
    }

    public function getFocusChoiceIndex( MagnetSchool $magnet_school, $focus ){
        $school_index = $this->getSchoolChoiceIndex( $magnet_school );

        if( !$school_index ){
            return false;
        }

        $ordinal_Service = new OrdinalService();
        $choice = $ordinal_Service->getOrdinalText( $school_index );

        if( $this->parseSubmissionData( $choice .'_choice_first_choice_focus' ) == $focus ){
            return 1;
        }

        if( $this->parseSubmissionData( $choice .'_choice_second_choice_focus' ) == $focus ){
            return 2;
        }

        if( $this->parseSubmissionData( $choice .'_choice_third_choice_focus' ) == $focus ){
            return 3;
        }
        return false;
    }

    /**
     * Add allOffers
     *
     * @param \IIAB\MagnetBundle\Entity\Offered $allOffers
     * @return Submission
     */
    public function addAllOffer(\IIAB\MagnetBundle\Entity\Offered $allOffers)
    {
        $this->allOffers[] = $allOffers;

        return $this;
    }

    /**
     * Remove allOffers
     *
     * @param \IIAB\MagnetBundle\Entity\Offered $allOffers
     */
    public function removeAllOffer(\IIAB\MagnetBundle\Entity\Offered $allOffers)
    {
        $this->allOffers->removeElement($allOffers);
    }

    /**
     * Get allOffers
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAllOffers()
    {
        return $this->allOffers;
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

	public function getHomeZone(){
		return $this->parseSubmissionData( 'home_zone' );
	}

	public function getWritingSample(){
        return $this->parseSubmissionData( 'writing_sample' );
    }

    /**
	 * Get Parent Employment
	 *
	 * @return string
	 */
    public function getParentEmployment(){
        return $this->parseSubmissionData( 'parent_employment' );
    }

    public function getParentEmploymentFormatted(){
        return ( $this->parseSubmissionData( 'parent_employment' ) )
        	? 'Employee'
        	: '';
    }

    public function getChoiceZone(){
        return $this->parseSubmissionData( 'choice_zone' );
    }

    /**
	 * Writing Sample
	 *
	 * @param null $sample
	 */
	public function setWritingSample( $sample = null ) {

		if( $sample == null ) {
			return;
		}

        $foundSample = false;
        $data = $this->getAdditionalData( true );
        foreach( $data as $submissionData ) {
            if( $submissionData->getMetaKey() == 'writing_sample' ) {
                $submissionData->setMetaValue( $sample );
                $foundSample = true;
            }
        }

		if( !$foundSample ) {

			$subData = new SubmissionData();
			$subData->setMetaKey( 'writing_sample' );
			$subData->setMetaValue( $sample );
			$this->addAdditionalDatum( $subData );
		}
	}
}
