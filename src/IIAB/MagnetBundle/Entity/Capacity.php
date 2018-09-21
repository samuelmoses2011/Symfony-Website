<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Capacity
 *
 * @ORM\Table(name="capacity")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Repository\CapacityRepository")
 */
class Capacity
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
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
     * @ORM\JoinColumn(name="school", referencedColumnName="id")
     */
    protected $school;

    /**
     * @var string
     *
     * @ORM\Column(name="focusArea", type="string", nullable=true)
     */
    protected $focusArea;

    /**
     * @var int
     *
     * @ORM\Column(name="max", type="integer")
     */
    private $max;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set max
     *
     * @param integer $max
     *
     * @return Capacity
     */
    public function setMax($max)
    {
        $this->max = $max;

        return $this;
    }

    /**
     * Get max
     *
     * @return integer
     */
    public function getMax()
    {
        return $this->max;
    }

    /**
     * Set school
     *
     * @param \IIAB\MagnetBundle\Entity\MagnetSchool $school
     *
     * @return Population
     */
    public function setSchool(\IIAB\MagnetBundle\Entity\MagnetSchool $school = null)
    {
        $this->school = $school;

        return $this;
    }

    /**
     * Get school
     *
     * @return \IIAB\MagnetBundle\Entity\MagnetSchool
     */
    public function getSchool()
    {
        return $this->school;
    }

    /**
     * Set focusArea
     *
     * @param string $focusArea
     * @return LotteryOutcomeSubmission
     */
    public function setFocusArea($focusArea)
    {
        $this->focusArea = $focusArea;

        return $this;
    }

    /**
     * Get focusArea
     *
     * @return string
     */
    public function getFocusArea()
    {
        return $this->focusArea;
    }
}

