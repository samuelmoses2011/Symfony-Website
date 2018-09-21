<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LotteryOutcomePopulation
 *
 * @ORM\Table(name="lotteryoutcomepopulation")
 * @ORM\Entity
 */
class LotteryOutcomePopulation
{
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
     * @ORM\Column(name="maxCapacity", type="integer", options={"default":0})
     */
    private $maxCapacity = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="tracking_column", type="string", length=255)
     */
    private $trackingColumn;

    /**
     * @var string
     *
     * @ORM\Column(name="tracking_value", type="string", length=255)
     */
    private $trackingValue;

    /**
     * @var int
     *
     * @ORM\Column(name="count", type="integer")
     */
    private $count;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=255)
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
     * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
     */
    protected $openEnrollment;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Placement")
     * @ORM\JoinColumn(name="placement", referencedColumnName="id")
     */
    protected $placement;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
     * @ORM\JoinColumn(name="magnetSchool", referencedColumnName="id")
     */
    protected $magnetSchool;

    /**
     * @var string
     *
     * @ORM\Column(name="focus_area", type="string", length=255, nullable=true)
     */
    protected $focusArea;

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
     * Set maxCapacity
     *
     * @param integer $maxCapacity
     * @return LotteryOutcomePopulation
     */
    public function setMaxCapacity($maxCapacity)
    {
        $this->maxCapacity = $maxCapacity;

        return $this;
    }

    /**
     * Get maxCapacity
     *
     * @return integer
     */
    public function getMaxCapacity()
    {
        return $this->maxCapacity;
    }

    /**
     * Set trackingColumn
     *
     * @param string $trackingColumn
     *
     * @return Population
     */
    public function setTrackingColumn($trackingColumn)
    {
        $this->trackingColumn = $trackingColumn;

        return $this;
    }

    /**
     * Get trackingColumn
     *
     * @return string
     */
    public function getTrackingColumn()
    {
        return $this->trackingColumn;
    }

    /**
     * Set trackingValue
     *
     * @param string $trackingValue
     *
     * @return Population
     */
    public function setTrackingValue($trackingValue)
    {
        $this->trackingValue = $trackingValue;

        return $this;
    }

    /**
     * Get trackingValue
     *
     * @return string
     */
    public function getTrackingValue()
    {
        return $this->trackingValue;
    }

    /**
     * Set count
     *
     * @param integer $count
     *
     * @return Population
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
     * Set lastUpdatedDateTime
     *
     * @param \DateTime $lastUpdatedDateTime
     * @return LotteryOutcomePopulation
     */
    public function setLastUpdatedDateTime($lastUpdatedDateTime)
    {
        $this->lastUpdatedDateTime = $lastUpdatedDateTime;

        return $this;
    }

    /**
     * Get lastUpdatedDateTime
     *
     * @return \DateTime
     */
    public function getLastUpdatedDateTime()
    {
        return $this->lastUpdatedDateTime;
    }

    /**
     * Set openEnrollment
     *
     * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
     * @return LotteryOutcomePopulation
     */
    public function setOpenEnrollment(\IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment = null)
    {
        $this->openEnrollment = $openEnrollment;

        return $this;
    }

    /**
     * Get openEnrollment
     *
     * @return \IIAB\MagnetBundle\Entity\OpenEnrollment
     */
    public function getOpenEnrollment()
    {
        return $this->openEnrollment;
    }

    /**
     * Set placement
     *
     * @param \IIAB\MagnetBundle\Entity\Placement $placement
     * @return LotteryOutcomePopulation
     */
    public function setPlacement(\IIAB\MagnetBundle\Entity\Placement $placement = null)
    {
        $this->placement = $placement;

        return $this;
    }

    /**
     * Get placement
     *
     * @return \IIAB\MagnetBundle\Entity\Placement
     */
    public function getPlacement()
    {
        return $this->placement;
    }

    /**
     * Set magnetSchool
     *
     * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool
     * @return LotteryOutcomePopulation
     */
    public function setMagnetSchool(\IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool = null)
    {
        $this->magnetSchool = $magnetSchool;

        return $this;
    }

    /**
     * Get magnetSchool
     *
     * @return \IIAB\MagnetBundle\Entity\MagnetSchool
     */
    public function getMagnetSchool()
    {
        return $this->magnetSchool;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return LotteryOutcomePopulation
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

    /**
     * Set focusArea
     *
     * @param string $focusArea
     * @return LotteryOutcomePopulation
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
