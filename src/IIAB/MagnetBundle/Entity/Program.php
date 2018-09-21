<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Program
 *
 * @ORM\Table(name="program")
 * @ORM\Entity
 */
class Program {

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
	 * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\ProgramInowName", mappedBy="program", orphanRemoval=true)
	 */
	private $iNowNames;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment", inversedBy="programs")
	 * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
	 */
	protected $openEnrollment;


	/**
	 * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool", mappedBy="program")
	 */
	protected $magnetSchools;

	/** @var ArrayCollection */
	protected $placementMessaging;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\ProgramSchoolData", mappedBy="program", cascade={"all"})
     */
    private $additionalData;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="IIAB\MagnetBundle\Entity\Eligibility", mappedBy="program", cascade={"all"})
     */
    private $eligibility;

	/**
	 * Construct for the Program Entity and setup up MagnetSchools
	 */
	public function __construct() {

		$this->magnetSchools = new ArrayCollection();
		$this->placementMessaging = new ArrayCollection();
		$this->iNowNames = new ArrayCollection();
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
	 * Set name
	 *
	 * @param string $name
	 *
	 * @return Program
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
	 * Add magnetSchools
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool
	 *
	 * @return Program
	 */
	public function addMagnetSchool( \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool ) {

		$this->magnetSchools[] = $magnetSchool;
		$magnetSchool->setProgram( $this );

		return $this;
	}

	/**
	 * Remove magnetSchools
	 *
	 * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchools
	 */
	public function removeMagnetSchool( \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchools ) {

		$magnetSchools->setProgram( null );
		$this->magnetSchools->removeElement( $magnetSchools );
	}

	/**
	 * Get magnetSchools
	 *
	 * @return \Doctrine\Common\Collections\Collection
	 */
	public function getMagnetSchools() {

		return $this->magnetSchools;
	}

	/**
	 * @return ArrayCollection
	 */
	public function getPlacementMessaging() {

		if( $this->placementMessaging == null ) {
			$this->placementMessaging = new ArrayCollection();
		}

		return $this->placementMessaging;
	}

	function __toString() {

		if( $this->id ) {
			return $this->getName();
		} else {
			return 'New Program';
		}
	}


	/**
	 * Set openEnrollment
	 *
	 * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
	 *
	 * @return Program
	 */
	public function setOpenEnrollment( \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment = null ) {

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
     * Add iNowNames
     *
     * @param \IIAB\MagnetBundle\Entity\ProgramInowName $iNowName
     * @return Program
     */
    public function addINowName(\IIAB\MagnetBundle\Entity\ProgramInowName $iNowName)
    {
        $this->iNowNames[] = $iNowName;
		$iNowName->setProgram( $this );
        return $this;
    }

    /**
     * Remove iNowNames
     *
     * @param \IIAB\MagnetBundle\Entity\ProgramInowName $iNowName
     */
    public function removeINowName(\IIAB\MagnetBundle\Entity\ProgramInowName $iNowName)
    {
		$iNowName->setProgram(null);
        $this->iNowNames->removeElement($iNowName);
    }

    /**
     * Get iNowNames
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getINowNames()
    {
        return $this->iNowNames;
    }

    /**
     * Add additionalData
     *
     * @param \IIAB\MagnetBundle\Entity\ProgramSchoolData $additionalData
     * @return Program
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
        $reserved_keys = [
            'focus_description',
            'capacity_by',
            'conduct_eligible',
            'eligibility_field',
            'calculated_gpa',
            'assessment_test_eligible',
            'course_eligibility_met',
            'orientation',
            'focus_placement',
            'file_display_after_submission',
            'file_display_after_submission_label',
            'slotting_method'
        ];

        $tempArrayCollection = new ArrayCollection();

        if( $this->additionalData ){

            foreach( $this->additionalData as $data ) {

                if( $meta_key ){
                    if( $data->getMetaKey() == $meta_key ){
                        $tempArrayCollection->add( $data );
                    }
                } else if( !in_array( $data->getMetaKey(), $reserved_keys ) ) {
                    $tempArrayCollection->add( $data );
                }
            }
        }
        return $tempArrayCollection;
    }

    /**
     * @param $key
     * @return bool
     */
    public function doesRequire( $key ){

        if( strpos($key, 'combined_') !== false) {

            $key = str_replace('combined_', '', $key);
            $parent_key = \IIAB\MagnetBundle\Service\EligibilityRequirementsService::getParentKey( $key );
            foreach ($this->eligibility as $eligibility) {

                if ( in_array($eligibility->getCriteriaType(), [ $key, $parent_key ] )
                    && $eligibility->getCourseTitle() == 'combined'
                ) {
                    return true;
                }
            }
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
     * Add eligibility
     *
     * @param \IIAB\MagnetBundle\Entity\Eligibility $eligibility
     * @return Program
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
}
