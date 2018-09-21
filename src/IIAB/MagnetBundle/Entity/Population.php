<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Population
 *
 * @ORM\Table(name="population")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\PopulationRepository")
 */
class Population {

    public static $population_types = [
        'starting',
        'adjustment',
        'offer',
        'decline',
        'withdrawal'
    ];

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
     * @ORM\JoinColumn(name="magnet_school", referencedColumnName="id", nullable=true)
     */
    protected $magnetSchool;

    /**
     * @var string
     *
     * @ORM\Column(name="focusArea", type="string", nullable=true)
     */
    protected $focusArea;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\AddressBoundSchool")
     * @ORM\JoinColumn(name="bound_school", referencedColumnName="id", nullable=true)
     */
    protected $addressBoundSchool;

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
     * @var string
     *
     * @ORM\Column(name="update_type", type="string", length=255)
     */
    private $updateType;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updateDateTime", type="datetime")
     */
    private $updateDateTime;

    /**
     * @var int
     *
     * @ORM\Column(name="count", type="integer")
     */
    private $count;


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
     * Set updateDateTime
     *
     * @param \DateTime $updateDateTime
     *
     * @return Population
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
     * Set updateType
     *
     * @param string $updateType
     *
     * @return Population
     */
    public function setUpdateType($updateType)
    {
        $this->updateType = $updateType;

        return $this;
    }

    /**
     * Get updateType
     *
     * @return string
     */
    public function getUpdateType()
    {
        return $this->updateType;
    }

    /**
     * Set magnetSchool
     *
     * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool
     *
     * @return Population
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
     * Set addressBoundSchool
     *
     * @param \IIAB\MagnetBundle\Entity\AddressBoundSchool $addressBoundSchool
     *
     * @return Population
     */
    public function setAddressBoundSchool(\IIAB\MagnetBundle\Entity\AddressBoundSchool $addressBoundSchool = null)
    {
        $this->addressBoundSchool = $addressBoundSchool;

        return $this;
    }

    /**
     * Get addressBoundSchool
     *
     * @return \IIAB\MagnetBundle\Entity\AddressBoundSchool
     */
    public function getAddressBoundSchool()
    {
        return $this->addressBoundSchool;
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

    public function __toString(){
        return strval( $this->getCount() );
    }

}
