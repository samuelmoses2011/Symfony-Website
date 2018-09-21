<?php


namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Student
 *
 * @ORM\Table(name="student")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\StudentRepository")
 */
class Student {

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
     * @ORM\Column(name="gender", type="string", length=255)
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
	 * @ORM\Column(name="grade", type="integer")
	 */
	private $grade;

	/**
	 * @var int
	 *
	 * @ORM\Column(name="IsHispanic", type="integer", length=1, options={"default":0})
	 */
	private $IsHispanic = 0;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="nonHSVStudent", type="integer", length=1, options={"default":0})
	 */
	private $nonHSVStudent = 0;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="email", type="string", length=255, nullable=true)
	 */
	private $email;


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
	 * @return Student
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
	 * @return Student
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
	 * @return Student
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
	 * Set birthday
	 *
	 * @param \DateTime $birthday
	 *
	 * @return Student
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
	 * @return Student
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
	 * @return Student
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
	 * Set state
	 *
	 * @param string $state
	 *
	 * @return Student
	 */
	public function setState( $state ) {

		$this->state = $state;

		return $this;
	}

	/**
	 * Get state
	 *
	 * @return string
	 */
	public function getState() {

		return $this->state;
	}

	/**
	 * Set zip
	 *
	 * @param string $zip
	 *
	 * @return Student
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
	 * @return Student
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
	 * @param integer $grade
	 *
	 * @return Student
	 */
	public function setGrade( $grade ) {

		$this->grade = $grade;

		return $this;
	}

	/**
	 * Get grade
	 *
	 * @return integer
	 */
	public function getGrade() {

		return $this->grade;
	}

	/**
	 * @return int
	 */
	public function getIsHispanic() {

		return $this->IsHispanic;
	}

	/**
	 * @param int $IsHispanic
	 */
	public function setIsHispanic( $IsHispanic ) {

		$this->IsHispanic = $IsHispanic;
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
     * Set gender
     *
     * @param string $gender
     * @return Student
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
     * Set email
     *
     * @param string $email
     *
     * @return Student
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }
}
