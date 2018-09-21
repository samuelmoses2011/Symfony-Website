<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * OpenEnrollment
 *
 * @ORM\Table(name="openenrollment")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\OpenEnrollmentRepository")
 */
class OpenEnrollment {

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
	 * @ORM\Column(name="year", type="string", length=255)
	 */
	private $year;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="beginningDate", type="datetime")
	 */
	private $beginningDate;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="endingDate", type="datetime")
	 */
	private $endingDate;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="confirmationStyle", type="string", length=255)
	 */
	private $confirmationStyle;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $latePlacementBeginningDate;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(type="datetime", nullable=true)
	 *
	 */
	private $latePlacementEndingDate;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="status", type="string", length=255, nullable=true)
	 */
	private $status;

	/**
	 * @var float
	 *
	 * @ORM\Column(type="float", options={"default":0})
	 */
	private $HRCWhite = 0;

	/**
	 * @var float
	 * @ORM\Column(type="float", options={"default":0})
	 */
	private $HRCBlack = 0;

	/**
	 * @var float
	 *
	 * @ORM\Column(type="float", options={"default":0})
	 */
	private $HRCOther = 0;

	/**
	 * @var float
	 *
	 * @ORM\Column(type="float", options={"default":0})
	 */
	private $maxPercentSwing = 0;

	/**
	 * @var boolean
	 *
	 * @ORM\Column(type="boolean", options={"default":0})
	 */
	private $active;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\Placement", mappedBy="openEnrollment", cascade={"all"})
     */
    private $placement;

	/**
	 * @return string
	 */
	public function getConfirmationStyle() {

		return $this->confirmationStyle;
	}

	/**
	 * @param string $confirmationStyle
	 */
	public function setConfirmationStyle( $confirmationStyle ) {

		$this->confirmationStyle = $confirmationStyle;
	}

	/**
	 * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\Program", mappedBy="openEnrollment")
	 */
	protected $programs;

	/**
	 * Construct for the OpenEnrollment Entity and setup up Programs
	 */
	public function __construct() {

		$this->programs = new ArrayCollection();
	}

	/**
	 * Add programs
	 *
	 * @param \IIAB\MagnetBundle\Entity\Program $program
	 *
	 * @return OpenEnrollment
	 */
	public function addProgram( \IIAB\MagnetBundle\Entity\Program $program ) {

		$this->programs->add( $program );
		$program->setOpenEnrollment( $this );

		return $this;
	}

	/**
	 * Remove programs
	 *
	 * @param \IIAB\MagnetBundle\Entity\Program $program
	 */
	public function removeProgram( \IIAB\MagnetBundle\Entity\Program $program ) {

		$program->setOpenEnrollment( null );
		$this->programs->removeElement( $program );
	}

	/**
	 * Get programs
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getPrograms() {

		return $this->programs;
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
	 * Get year
	 *
	 * @return string
	 */
	public function getYear() {

		return $this->year;
	}

	/**
	 * Set year
	 *
	 * @param string $year
	 *
	 * @return OpenEnrollment
	 */
	public function setYear( $year ) {

		$this->year = $year;

		return $this;
	}

	/**
	 * Get beginningDate
	 *
	 * @return \DateTime
	 */
	public function getBeginningDate() {

		return $this->beginningDate;
	}

	/**
	 * Set beginningDate
	 *
	 * @param \DateTime $beginningDate
	 *
	 * @return OpenEnrollment
	 */
	public function setBeginningDate( $beginningDate ) {

		$this->beginningDate = $beginningDate;

		return $this;
	}

	/**
	 * Get endingDate
	 *
	 * @return \DateTime
	 */
	public function getEndingDate() {

		return $this->endingDate;
	}

	/**
	 * Set endingDate
	 *
	 * @param \DateTime $endingDate
	 *
	 * @return OpenEnrollment
	 */
	public function setEndingDate( $endingDate ) {

		$this->endingDate = $endingDate;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getStatus() {

		return $this->status;
	}

	/**
	 * @param string $status
	 */
	public function setStatus( $status ) {

		$this->status = $status;
	}

	/**
	 * @return float
	 */
	public function getHRCWhite() {

		return $this->HRCWhite;
	}

	/**
	 * @param float $HRCWhite
	 */
	public function setHRCWhite( $HRCWhite ) {

		$this->HRCWhite = $HRCWhite;
	}

	/**
	 * @return float
	 */
	public function getHRCBlack() {

		return $this->HRCBlack;
	}

	/**
	 * @param float $HRCBlack
	 */
	public function setHRCBlack( $HRCBlack ) {

		$this->HRCBlack = $HRCBlack;
	}

	/**
	 * @return float
	 */
	public function getHRCOther() {

		return $this->HRCOther;
	}

	/**
	 * @param float $HRCOther
	 */
	public function setHRCOther( $HRCOther ) {

		$this->HRCOther = $HRCOther;
	}

	/**
	 *
	 * @return string
	 */
	public function __toString() {

		return $this->getYear() ?: 'Create';
	}

	/**
	 * Set maxPercentSwing
	 *
	 * @param float $maxPercentSwing
	 *
	 * @return OpenEnrollment
	 */
	public function setMaxPercentSwing( $maxPercentSwing ) {

		$this->maxPercentSwing = $maxPercentSwing;

		return $this;
	}

	/**
	 * Get maxHRCWhite
	 *
	 * @return float
	 */
	public function getMaxHRCWhite() {

		$maxHRCWhite = $this->HRCWhite + $this->maxPercentSwing;
		if( $maxHRCWhite > 100 ) {
			return 100;
		} else if( $maxHRCWhite < 0 ) {
			return 0;
		} else {
			return $maxHRCWhite;
		}

	}

	/**
	 * Get minHRCWhite
	 *
	 * @return float
	 */
	public function getMinHRCWhite() {

		$minHRCWhite = $this->HRCWhite - $this->maxPercentSwing;
		if( $minHRCWhite > 100 ) {
			return 100;
		} else if( $minHRCWhite < 0 ) {
			return 0;
		} else {
			return $minHRCWhite;
		}

	}

	/**
	 * Get maxHRCBlack
	 *
	 * @return float
	 */
	public function getMaxHRCBlack() {

		$maxHRCBlack = $this->HRCBlack + $this->maxPercentSwing;
		if( $maxHRCBlack > 100 ) {
			return 100;
		} else if( $maxHRCBlack < 0 ) {
			return 0;
		} else {
			return $maxHRCBlack;
		}

	}

	/**
	 * Get maxHRCBlack
	 *
	 * @return float
	 */
	public function getMinHRCBlack() {

		$minHRCBlack = $this->HRCBlack - $this->maxPercentSwing;
		if( $minHRCBlack > 100 ) {
			return 100;
		} else if( $minHRCBlack < 0 ) {
			return 0;
		} else {
			return $minHRCBlack;
		}

	}

	/**
	 * Get maxHRCOther
	 *
	 * @return float
	 */
	public function getMaxHRCOther() {

		$maxHRCOther = $this->HRCOther + $this->maxPercentSwing;
		if( $maxHRCOther > 100 ) {
			return 100;
		} else if( $maxHRCOther < 0 ) {
			return 0;
		} else {
			return $maxHRCOther;
		}

	}

	/**
	 * Get minHRCOther
	 *
	 * @return float
	 */
	public function getMinHRCOther() {

		$minHRCOther = $this->HRCOther - $this->maxPercentSwing;
		if( $minHRCOther > 100 ) {
			return 100;
		} else if( $minHRCOther < 0 ) {
			return 0;
		} else {
			return $minHRCOther;
		}

	}

	/**
	 * Get maxPercentSwing
	 *
	 * @return float
	 */
	public function getMaxPercentSwing() {

		return $this->maxPercentSwing;
	}

	/**
	 * @return boolean
	 */
	public function isActive() {

		return $this->active;
	}

	/**
	 * @param boolean $active
	 */
	public function setActive( $active ) {

		$this->active = $active;
	}

    /**
     * Get active
     *
     * @return boolean 
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set latePlacementBeginningDate
     *
     * @param \DateTime $latePlacementBeginningDate
     * @return OpenEnrollment
     */
    public function setLatePlacementBeginningDate($latePlacementBeginningDate)
    {
        $this->latePlacementBeginningDate = $latePlacementBeginningDate;

        return $this;
    }

    /**
     * Get latePlacementBeginningDate
     *
     * @return \DateTime 
     */
    public function getLatePlacementBeginningDate()
    {
        return $this->latePlacementBeginningDate;
    }

    /**
     * Set latePlacementEndingDate
     *
     * @param \DateTime $latePlacementEndingDate
     * @return OpenEnrollment
     */
    public function setLatePlacementEndingDate($latePlacementEndingDate)
    {
        $this->latePlacementEndingDate = $latePlacementEndingDate->setTime( 23, 59, 59 );

        return $this;
    }

    /**
     * Get latePlacementEndingDate
     *
     * @return \DateTime 
     */
    public function getLatePlacementEndingDate()
    {
        return ( $this->latePlacementEndingDate !== null ) ? $this->latePlacementEndingDate->setTime( 23, 59, 59 ) : null ;
    }

    /**
     * @param integer $offset
     * @return string
     */
    public function getOffsetYear( $offset ){
        $current_year = explode( '-', $this->getYear() );
        if( count( $current_year ) == 2 ){
            return ( $current_year[0] + ( $offset - 1 ) ) .'-'. ( $current_year[1] + ( $offset - 1 ) );
            $academicYears[ $index ] = $academic_year;
        }
    }

    /**
     * Add placement
     *
     * @param \IIAB\MagnetBundle\Entity\Placement $placement
     * @return OpenEnrollment
     */
    public function addPlacement(\IIAB\MagnetBundle\Entity\Placement $placement)
    {
        $this->placement[] = $placement;

        return $this;
    }

    /**
     * Remove placement
     *
     * @param \IIAB\MagnetBundle\Entity\Placement $placement
     */
    public function removePlacement(\IIAB\MagnetBundle\Entity\Placement $placement)
    {
        $this->placement->removeElement($placement);
    }

    /**
     * Get placement
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getPlacement()
    {
        return $this->placement;
    }
}
