<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AddressBoundSchool
 *
 * @ORM\Table(name="addressboundschool")
 * @ORM\Entity(repositoryClass="IIAB\MagnetBundle\Entity\AddressBoundSchoolRepository")
 */
class AddressBoundSchool
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
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var integer
     *
     * @ORM\Column(name="startGrade", type="integer", nullable=true)
     */
    private $startGrade;

    /**
     * @var integer
     *
     * @ORM\Column(name="endGrade", type="integer", nullable=true)
     */
    private $endGrade;

    /**
     * @var string
     *
     * @ORM\Column(name="alias", type="string", length=255)
     */
    private $alias;

    /**
     * @var bool
     * @ORM\Column(name="active", type="boolean")
     */
    private $active = false;


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
     * Set name
     *
     * @param string $name
     * @return AddressBoundSchool
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set alias
     *
     * @param string $name
     * @return AddressBoundSchool
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Get alias
     *
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Set startGrade
     *
     * @param integer $startGrade
     * @return AddressBoundSchool
     */
    public function setStartGrade($startGrade)
    {
        $this->startGrade = $startGrade;

        return $this;
    }

    /**
     * Get startGrade
     *
     * @return integer
     */
    public function getStartGrade()
    {
        return $this->startGrade;
    }

    /**
     * Set endGrade
     *
     * @param integer $endGrade
     * @return AddressBoundSchool
     */
    public function setEndGrade($endGrade)
    {
        $this->endGrade = $endGrade;

        return $this;
    }

    /**
     * Get endGrade
     *
     * @return integer
     */
    public function getEndGrade()
    {
        return $this->endGrade;
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
}
