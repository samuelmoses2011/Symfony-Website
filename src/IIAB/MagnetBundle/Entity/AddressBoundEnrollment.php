<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AddressBoundEnrollment
 *
 * @ORM\Table(name="address_bound_enrollment")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Repository\AddressBoundEnrollmentRepository")
 */
class AddressBoundEnrollment
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\AddressBoundSchool")
     * @ORM\JoinColumn(name="school", referencedColumnName="id")
     */
    protected $school;

    /**
     * @var int
     *
     * @ORM\Column(name="grade", type="integer")
     */
    protected $grade;

    /**
     * @var int
     *
     * @ORM\Column(name="count", type="integer")
     */
    private $count;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="update_date_time", type="datetime")
     */
    private $updateDateTime;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\User")
     * @ORM\JoinColumn(name="user", referencedColumnName="id", nullable=true)
     */
    private $user;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set grade
     *
     * @param integer $grade
     *
     * @return AddressBoundEnrollment
     */
    public function setGrade($grade)
    {
        $this->grade = $grade;

        return $this;
    }

    /**
     * Get grade
     *
     * @return integer
     */
    public function getGrade()
    {
        return $this->grade;
    }

    /**
     * Set count
     *
     * @param integer $count
     *
     * @return AddressBoundEnrollment
     */
    public function setCount($count)
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Get count
     *
     * @return integer
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Set updateDateTime
     *
     * @param \DateTime $updateDateTime
     *
     * @return AddressBoundEnrollment
     */
    public function setUpdateDateTime($updateDateTime)
    {
        $this->updateDateTime = $updateDateTime;

        return $this;
    }

    /**
     * Get updateDateTime
     *
     * @return \DateTime
     */
    public function getUpdateDateTime()
    {
        return $this->updateDateTime;
    }

    /**
     * Set school
     *
     * @param \IIAB\MagnetBundle\Entity\AddressBoundSchool $school
     *
     * @return AddressBoundEnrollment
     */
    public function setSchool(\IIAB\MagnetBundle\Entity\AddressBoundSchool $school = null)
    {
        $this->school = $school;

        return $this;
    }

    /**
     * Get school
     *
     * @return \IIAB\MagnetBundle\Entity\AddressBoundSchool
     */
    public function getSchool()
    {
        return $this->school;
    }

    /**
     * Set user
     *
     * @param \IIAB\MagnetBundle\Entity\User $user
     *
     * @return AddressBoundEnrollment
     */
    public function setUser(\IIAB\MagnetBundle\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \IIAB\MagnetBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }
}
