<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * School
 *
 * @ORM\Table(name="school")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\SchoolRepository")
 */
class School {

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\AddressBoundSchool")
     * @ORM\JoinColumn(name="addressBoundSchool", referencedColumnName="id", nullable=true)
     */
    protected $addressBoundSchool;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
     * @ORM\JoinColumn(name="magnetSchool", referencedColumnName="id", nullable=true)
     */
    protected $magnetSchool;

    /**
     * @var string
     *
     * @ORM\Column(name="focus_area", type="string", length=255, nullable=true)
     */
    protected $focusArea;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
     * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
     */
    protected $openEnrollment;


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
     * Set focusArea
     *
     * @param string $focusArea
     *
     * @return School
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
     * Set addressBoundSchool
     *
     * @param \IIAB\MagnetBundle\Entity\AddressBoundSchool $addressBoundSchool
     *
     * @return School
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
     * Set magnetSchool
     *
     * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool
     *
     * @return School
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
     * Set openEnrollment
     *
     * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
     *
     * @return School
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
}
