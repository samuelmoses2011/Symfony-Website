<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SubmissionGrade
 *
 * @ORM\Table(name="submissiongrade")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\SubmissionGradeRepository")
 */
class SubmissionGrade {

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Submission", inversedBy="grades")
	 * @ORM\JoinColumn(name="submission_id", referencedColumnName="id")
	 */
	protected $submission;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="academicYear", type="integer", nullable=true)
	 */
	private $academicYear;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="academicTerm", type="string", length=255, nullable=true)
	 */
	private $academicTerm;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="courseTypeID", type="string", length=255, nullable=true)
	 */
	private $courseTypeID;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="courseType", type="string", length=255, nullable=true)
	 */
	private $courseType;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="courseName", type="string", length=255, nullable=true)
	 */
	private $courseName;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="sectionNumber", type="string", length=255, nullable=true)
	 */
	private $sectionNumber;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="numericGrade", type="integer", nullable=true)
	 */
	private $numericGrade;

    /**
     * @var integer
     *
     * @ORM\Column(name="use_in_calculations", type="integer", nullable=true)
     */
	private $useInCalculations;

	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {

		return $this->id;
	}

	/**
	 * Set academicYear
	 *
	 * @param integer $academicYear
	 *
	 * @return SubmissionGrade
	 */
	public function setAcademicYear( $academicYear ) {

		$this->academicYear = $academicYear;

		return $this;
	}

	/**
	 * Get academicYear
	 *
	 * @return integer
	 */
	public function getAcademicYear() {

		return $this->academicYear;
	}

	/**
	 * Set academicTerm
	 *
	 * @param string $academicTerm
	 *
	 * @return SubmissionGrade
	 */
	public function setAcademicTerm( $academicTerm ) {

		$this->academicTerm = $academicTerm;

		return $this;
	}

	/**
	 * Get academicTerm
	 *
	 * @return string
	 */
	public function getAcademicTerm() {

		return $this->academicTerm;
	}

	/**
	 * Set courseType
	 *
	 * @param string $courseType
	 *
	 * @return SubmissionGrade
	 */
	public function setCourseType( $courseType ) {

		$this->courseType = $courseType;

		return $this;
	}

	/**
	 * Get courseType
	 *
	 * @return string
	 */
	public function getCourseType() {

		return $this->courseType;
	}

	/**
	 * Set courseName
	 *
	 * @param string $courseName
	 *
	 * @return SubmissionGrade
	 */
	public function setCourseName( $courseName ) {

		$this->courseName = $courseName;

		return $this;
	}

	/**
	 * Get courseName
	 *
	 * @return string
	 */
	public function getCourseName() {

		return $this->courseName;
	}

	/**
	 * Set sectionNumber
	 *
	 * @param string $sectionNumber
	 *
	 * @return SubmissionGrade
	 */
	public function setSectionNumber( $sectionNumber ) {

		$this->sectionNumber = $sectionNumber;

		return $this;
	}

	/**
	 * Get sectionNumber
	 *
	 * @return string
	 */
	public function getSectionNumber() {

		return $this->sectionNumber;
	}

	/**
	 * Set numericGrade
	 *
	 * @param integer $numericGrade
	 *
	 * @return SubmissionGrade
	 */
	public function setNumericGrade( $numericGrade ) {

		$this->numericGrade = $numericGrade;

		return $this;
	}

	/**
	 * Get numericGrade
	 *
	 * @return integer
	 */
	public function getNumericGrade() {

		return $this->numericGrade;
	}

	/**
	 * @return string
	 */
	public function getCourseTypeID() {

		return $this->courseTypeID;
	}

	/**
	 * @param string $courseTypeID
	 */
	public function setCourseTypeID( $courseTypeID ) {

		$this->courseTypeID = $courseTypeID;
	}

	/**
	 * Set submission
	 *
	 * @param \IIAB\MagnetBundle\Entity\Submission $submission
	 *
	 * @return SubmissionGrade
	 */
	public function setSubmission( Submission $submission = null ) {
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
     * Set useInCalculations
     *
     * @param integer $useInCalculations
     * @return SubmissionGrade
     */
    public function setUseInCalculations($useInCalculations)
    {
        $this->useInCalculations = $useInCalculations;

        return $this;
    }

    /**
     * Get useInCalculations
     *
     * @return integer 
     */
    public function getUseInCalculations()
    {
        return $this->useInCalculations;
    }
}
