<?php
/**
 * Created by PhpStorm.
 * User: michaeltremblay
 * Date: 02/08/17
 * Time: 7:55 AM
 */

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProgramSchoolData
 *
 * @ORM\Table(name="program_school_data")
 * @ORM\Entity
 */
class ProgramSchoolData {

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool", inversedBy="additionalData")
	 * @ORM\JoinColumn(name="magnetSchool_id", referencedColumnName="id", nullable=true)
	 */
	protected $magnetSchool;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Program", inversedBy="additionalData")
     * @ORM\JoinColumn(name="program_id", referencedColumnName="id", nullable=true)
     */
    protected $program;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="meta_key", type="string", length=255, nullable=true)
	 */
	private $metaKey;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="meta_value", type="string", length=255, nullable=true)
	 */
	private $metaValue;

    /**
     * @var string
     *
     * @ORM\Column(name="extra_data_1", type="string", length=255, nullable=true)
     */
    private $extraData_1;

    /**
     * @var string
     *
     * @ORM\Column(name="extra_data_2", type="string", length=255, nullable=true)
     */
    private $extraData_2;

    /**
     * @var string
     *
     * @ORM\Column(name="extra_data_3", type="string", length=255, nullable=true)
     */
    private $extraData_3;

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
     * Set metaKey
     *
     * @param string $metaKey
     * @return ProgramSchoolData
     */
    public function setMetaKey($metaKey)
    {
        $this->metaKey = $metaKey;

        return $this;
    }

    /**
     * Get metaKey
     *
     * @return string 
     */
    public function getMetaKey()
    {
        return $this->metaKey;
    }

    /**
     * Set metaValue
     *
     * @param string $metaValue
     * @return ProgramSchoolData
     */
    public function setMetaValue($metaValue)
    {
        $this->metaValue = $metaValue;

        return $this;
    }

    /**
     * Get metaValue
     *
     * @return string 
     */
    public function getMetaValue()
    {
        return $this->metaValue;
    }

    /**
     * Set magnetSchool
     *
     * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool
     * @return ProgramSchoolData
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
     * Set program
     *
     * @param \IIAB\MagnetBundle\Entity\Program $program
     * @return ProgramSchoolData
     */
    public function setProgram(\IIAB\MagnetBundle\Entity\Program $program = null)
    {
        $this->program = $program;

        return $this;
    }

    /**
     * Get program
     *
     * @return \IIAB\MagnetBundle\Entity\Program 
     */
    public function getProgram()
    {
        return $this->program;
    }

    /**
     * Set extraData_1
     *
     * @param string $extraData1
     * @return ProgramSchoolData
     */
    public function setExtraData1($extraData1)
    {
        $this->extraData_1 = $extraData1;

        return $this;
    }

    /**
     * Get extraData_1
     *
     * @return string 
     */
    public function getExtraData1()
    {
        return $this->extraData_1;
    }

    /**
     * Set extraData_2
     *
     * @param string $extraData2
     * @return ProgramSchoolData
     */
    public function setExtraData2($extraData2)
    {
        $this->extraData_2 = $extraData2;

        return $this;
    }

    /**
     * Get extraData_2
     *
     * @return string 
     */
    public function getExtraData2()
    {
        return $this->extraData_2;
    }

    /**
     * Set extraData_3
     *
     * @param string $extraData3
     * @return ProgramSchoolData
     */
    public function setExtraData3($extraData3)
    {
        $this->extraData_3 = $extraData3;

        return $this;
    }

    /**
     * Get extraData_3
     *
     * @return string 
     */
    public function getExtraData3()
    {
        return $this->extraData_3;
    }
}
