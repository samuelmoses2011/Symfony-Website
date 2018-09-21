<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MagnetSchool
 *
 * @ORM\Table(name="magnetschool")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\MagnetSchoolRepository")
 */
class MagnetSchool {

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
	 * @ORM\Column(name="name", type="string", length=255)
	 */
	private $name;

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="grade", type="integer")
	 */
	private $grade;

	/**
	 * @var bool
	 * @ORM\Column(name="active", type="boolean")
	 */
	private $active = false;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="address", type="string", length=500)
	 */
	private $address;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Program", inversedBy="magnetSchools")
	 * @ORM\JoinColumn(name="program", referencedColumnName="id")
	 */
	protected $program;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
	 * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
	 */
	protected $openEnrollment;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\ProgramSchoolData", mappedBy="magnetSchool", cascade={"all"})
     */
    private $additionalData;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\Eligibility", mappedBy="magnetSchool", cascade={"all"})
     */
    private $eligibility;

	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {

		return $this->id;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 *
	 * @return MagnetSchool
	 */
	public function setName( $name ) {

		$this->name = $name;

		return $this;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName() {

		return $this->name;
	}

	/**
	 * Set grade
	 *
	 * @param integer $grade
	 *
	 * @return MagnetSchool
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
	 * Get grade
	 *
	 * @return string
	 */
	public function getGradeString() {

		if( $this->grade >= 96 ) {
			return "PreK";
		} elseif( $this->grade == 0 ) {
			return "K";
		} else {
			return "{$this->grade}";
		}
	}

	/**
	 * To string function
	 * @return string
	 */
	public function __toString() {

		if( $this->grade >= 96 ) {
			return "{$this->name} - Grade PreK";
		} elseif( $this->grade == 0 ) {
			return "{$this->name} - Grade K";
		} else {
			return "{$this->name} - Grade {$this->grade}";
		}
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
	public function getActive() {

		return $this->active;
	}

	/**
	 * @return string
	 */
	public function getAddress() {

		return $this->address;
	}

	/**
	 * @param string $address
	 */
	public function setAddress( $address ) {

		$this->address = $address;
	}

	/**
	 * Set program
	 *
	 * @param \IIAB\MagnetBundle\Entity\Program $program
	 *
	 * @return MagnetSchool
	 */
	public function setProgram( \IIAB\MagnetBundle\Entity\Program $program = null ) {

		$this->program = $program;

		return $this;
	}

	/**
	 * Get program
	 *
	 * @return \IIAB\MagnetBundle\Entity\Program
	 */
	public function getProgram() {

		return $this->program;
	}

    /**
     * Set openEnrollment
     *
     * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
     * @return MagnetSchool
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
     * Constructor
     */
    public function __construct()
    {
        $this->additionalData = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add additionalData
     *
     * @param \IIAB\MagnetBundle\Entity\ProgramSchoolData $additionalData
     * @return MagnetSchool
     */
    public function addAdditionalDatum(\IIAB\MagnetBundle\Entity\ProgramSchoolData $additionalData)
    {
        $this->additionalData[] = $additionalData;

        return $this;
    }

    /**
     * Remove additionalData
     *
     * @param \IIAB\MagnetBundle\Entity\ProgramSchoolData $additionalData
     */
    public function removeAdditionalDatum(\IIAB\MagnetBundle\Entity\ProgramSchoolData $additionalData)
    {
        $this->additionalData->removeElement($additionalData);
    }

    /**
     * Get additionalData
     *
     * @param string $meta_key
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAdditionalData( $meta_key = '' )
    {
        $data = $this->additionalData;

        if( empty( $data ) ){
            return $data;
        }

        if( $meta_key ){

            foreach( $data as $index => $data_object ){
                if( $data_object->getMetaKey() !== $meta_key ){
                    unset( $data[$index] );
                }
            }
        }
        return $data;
    }

    /**
     * Add eligibility
     *
     * @param \IIAB\MagnetBundle\Entity\Eligibility $eligibility
     * @return MagnetSchool
     */
    public function addEligibility(\IIAB\MagnetBundle\Entity\Eligibility $eligibility)
    {
        $this->eligibility[] = $eligibility;

        return $this;
    }

    /**
     * Remove eligibility
     *
     * @param \IIAB\MagnetBundle\Entity\Eligibility $eligibility
     */
    public function removeEligibility(\IIAB\MagnetBundle\Entity\Eligibility $eligibility)
    {
        $this->eligibility->removeElement($eligibility);
    }

    /**
     * Get eligibility
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getEligibility()
    {
        return $this->eligibility;
    }

    /**
     * @param $key
     * @return bool
     */
    public function doesRequire( $key ){

        if( $this->getProgram()->doesRequire( $key ) ){
            return true;
        }

        $parent_key = \IIAB\MagnetBundle\Service\EligibilityRequirementsService::getParentKey( $key );

        foreach( $this->eligibility as $eligibility ){

            if( $eligibility->getCriteriaType() == $key
                || $eligibility->getCriteriaType() == $parent_key
            ){
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isCapacityByFocus(){

        if( $this->getGrade() < 1 || $this->getGrade() > 90 ){
            return false;
        }

        $capacity_by_focus = $this->getProgram()->getAdditionalData('capacity_by');
        return ( isset( $capacity_by_focus[0] ) && $capacity_by_focus[0]->getMetaValue() == 'focus' );
    }
}
