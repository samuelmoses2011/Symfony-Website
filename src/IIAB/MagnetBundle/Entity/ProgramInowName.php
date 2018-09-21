<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProgramInowName
 *
 * @ORM\Table(name="program_inow_name")
 * @ORM\Entity
 */
class ProgramInowName
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
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Program", inversedBy="iNowNames")
     * @ORM\JoinColumn(name="program", referencedColumnName="id")
     */
    private $program;

    /**
     * @var string
     *
     * @ORM\Column(name="iNowName", type="string", length=255)
     */
    private $iNowName;


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
     * Set program
     *
     * @param \IIAB\MagnetBundle\Entity\Program $program
     *
     * @return MagnetSchool
     */
    public function setProgram( \IIAB\MagnetBundle\Entity\Program $program = null )
    {
        $this->program = $program;

        return $this;
    }

    /**
     * Get program
     *
     * @return integer 
     */
    public function getProgram()
    {
        return $this->program;
    }

    /**
     * Set iNowName
     *
     * @param string $iNowName
     * @return ProgramInowName
     */
    public function setINowName($iNowName)
    {
        $this->iNowName = $iNowName;

        return $this;
    }

    /**
     * Get iNowName
     *
     * @return string 
     */
    public function getINowName()
    {
        return $this->iNowName;
    }

    /**
     * Get the Inow Name as a String
     *
     * @return string
     */
    public function __toString()
    {
        if( $this->id ) {
            return $this->getINowName();
        } else {
            return 'New Inow Name';
        }
    }
}
