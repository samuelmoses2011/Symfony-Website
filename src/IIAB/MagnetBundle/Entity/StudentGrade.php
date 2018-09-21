<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StudentGrade
 *
 * @ORM\Table(name="studentgrade")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\StudentGradeRepository")
 */
class StudentGrade {

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
	 * @return StudentGrade
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
	 * @return StudentGrade
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
	 * @return StudentGrade
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
	 * @return StudentGrade
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
	 * @return StudentGrade
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
	 * @return StudentGrade
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
	 * @return int
	 */
	public function getStateID() {

		return $this->stateID;
	}

	/**
	 * @param int $stateID
	 */
	public function setStateID( $stateID ) {

		$this->stateID = $stateID;
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
}
