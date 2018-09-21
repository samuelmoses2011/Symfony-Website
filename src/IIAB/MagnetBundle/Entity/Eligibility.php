<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Eligibility
 *
 * @ORM\Table(name="eligibility")
 * @ORM\Entity
 */
class Eligibility {

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
	 * @ORM\Column(name="criteriaType", type="string", length=255)
	 */
	private $criteriaType;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="courseTypeToAverage", type="string", length=255, nullable=true)
	 */
	private $courseTypeToAverage;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="numberofSemesters", type="integer", nullable=true)
	 */
	private $numberofSemesters;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="passingThreshold", type="string", length=255, nullable=true)
	 */
	private $passingThreshold;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="courseTypeToCheckTitle", type="string", length=255, nullable=true)
	 */
	private $courseTypeToCheckTitle;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="courseTitle", type="string", length=255, nullable=true)
	 */
	private $courseTitle;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="comparison", type="string", length=5, nullable=true)
	 */
	private $comparison;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool", inversedBy="eligibility")
     * @ORM\JoinColumn(name="magnetSchool", referencedColumnName="id", nullable=true)
	 */
	protected $magnetSchool;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Program", inversedBy="eligibility")
     * @ORM\JoinColumn(name="program", referencedColumnName="id", nullable=true)
     */
    protected $program;

	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {

		return $this->id;
	}

	/**
	 * Set criteriaType
	 *
	 * @param string $criteriaType
	 *
	 * @return Eligibility
	 */
	public function setCriteriaType( $criteriaType ) {

		$this->criteriaType = $criteriaType;

		return $this;
	}

	/**
	 * Get criteriaType
	 *
	 * @return string
	 */
	public function getCriteriaType() {

		return $this->criteriaType;
	}

	/**
	 * Set courseTypeToAverage
	 *
	 * @param string $courseTypeToAverage
	 *
	 * @return Eligibility
	 */
	public function setCourseTypeToAverage( $courseTypeToAverage ) {

		$this->courseTypeToAverage = $courseTypeToAverage;

		return $this;
	}

	/**
	 * Get courseTypeToAverage
	 *
	 * @return string
	 */
	public function getCourseTypeToAverage() {

		return $this->courseTypeToAverage;
	}

	/**
	 * Set numberofSemesters
	 *
	 * @param integer $numberofSemesters
	 *
	 * @return Eligibility
	 */
	public function setNumberofSemesters( $numberofSemesters ) {

		$this->numberofSemesters = $numberofSemesters;

		return $this;
	}

	/**
	 * Get numberofSemesters
	 *
	 * @return integer
	 */
	public function getNumberofSemesters() {

		return $this->numberofSemesters;
	}

	/**
	 * Set passingThreshold
	 *
	 * @param string $passingThreshold
	 *
	 * @return Eligibility
	 */
	public function setPassingThreshold( $passingThreshold ) {

		$this->passingThreshold = $passingThreshold;

		return $this;
	}

	/**
	 * Get passingThreshold
	 *
	 * @return string
	 */
	public function getPassingThreshold() {

		return $this->passingThreshold;
	}

	/**
	 * Set courseTypeToCheckTitle
	 *
	 * @param string $courseTypeToCheckTitle
	 *
	 * @return Eligibility
	 */
	public function setCourseTypeToCheckTitle( $courseTypeToCheckTitle ) {

		$this->courseTypeToCheckTitle = $courseTypeToCheckTitle;

		return $this;
	}

	/**
	 * Get courseTypeToCheckTitle
	 *
	 * @return string
	 */
	public function getCourseTypeToCheckTitle() {

		return $this->courseTypeToCheckTitle;
	}

	/**
	 * Set courseTitle
	 *
	 * @param string $courseTitle
	 *
	 * @return Eligibility
	 */
	public function setCourseTitle( $courseTitle ) {

		$this->courseTitle = $courseTitle;

		return $this;
	}

	/**
	 * Get courseTitle
	 *
	 * @return string
	 */
	public function getCourseTitle() {

		return $this->courseTitle;
	}

	/**
	 * Set magnetSchool
	 *
	 * @param MagnetSchool $magnetSchool
	 *
	 * @return Eligibility
	 */
	public function setMagnetSchool( MagnetSchool $magnetSchool = null ) {

		$this->magnetSchool = $magnetSchool;

		return $this;
	}

	/**
	 * Get magnetSchool
	 *
	 * @return MagnetSchool
	 */
	public function getMagnetSchool() {

		return $this->magnetSchool;
	}

	/**
	 * @return string
	 */
	public function getComparison() {

		return $this->comparison;
	}

	/**
	 * @param string $comparison
	 */
	public function setComparison( $comparison ) {

		$this->comparison = $comparison;
	}

    /**
     * Set program
     *
     * @param \IIAB\MagnetBundle\Entity\Program $program
     * @return Eligibility
     */
    public function setProgram(\IIAB\MagnetBundle\Entity\Program $program = null)
    {
        $this->program = $program;

        return $this;
    }

    /**
     * Get program
     *
     * @return \IIAB\MagnetBundle\Entity\Program 
     */
    public function getProgram()
    {
        return $this->program;
    }

    public function getFieldKeys(){

    }
}
