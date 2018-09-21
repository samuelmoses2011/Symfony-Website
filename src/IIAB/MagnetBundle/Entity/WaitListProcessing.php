<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * WaitListProcessing
 *
 * @ORM\Table(name="waitlistprocessing")
 * @ORM\Entity
 */
class WaitListProcessing {

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="addedDateTimeGroup", type="datetime", nullable=false)
	 */
	private $addedDateTimeGroup;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
	 * @ORM\JoinColumn(referencedColumnName="id", name="magnetSchool")
	 */
	protected $magnetSchool;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(referencedColumnName="id", name="openEnrollment")
	 */
	protected $openEnrollment;

    /**
     * @var string
     *
     * @ORM\Column(name="focus_area", type="string", length=255, nullable=true)
     */
    protected $focusArea;

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
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {

		return $this->id;
	}

	/**
	 * @return \DateTime
	 */
	public function getAddedDateTimeGroup() {

		return $this->addedDateTimeGroup;
	}

	/**
	 * @param \DateTime $addedDateTimeGroup
	 */
	public function setAddedDateTimeGroup( $addedDateTimeGroup ) {

		$this->addedDateTimeGroup = $addedDateTimeGroup;
	}

	/**
	 * Set magnetSchool
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool
	 *
	 * @return WaitListProcessing
	 */
	public function setMagnetSchool( MagnetSchool $magnetSchool = null ) {

		$this->magnetSchool = $magnetSchool;

		return $this;
	}

	/**
	 * Get magnetSchool
	 *
	 * @return \IIAB\MagnetBundle\Entity\MagnetSchool
	 */
	public function getMagnetSchool() {

		return $this->magnetSchool;
	}

	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return WaitListProcessing
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
     * Set focusArea
     *
     * @param string $focusArea
     * @return WaitListProcessing
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
}
