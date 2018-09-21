<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Withdrawals
 *
 * @ORM\Table(name="withdrawals")
 * @ORM\Entity
 */
class Withdrawals
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
     * @var integer
     *
     * @ORM\Column(name="CPWhite", type="integer", options={"default":0})
     */
    private $CPWhite = 0;

    /**
     * @var integer
     *
     * @ORM\Column(name="CPBlack", type="integer", options={"default":0})
     */
    private $CPBlack = 0;

    /**
     * @var integer
     *
     * @ORM\Column(name="CPOther", type="integer", options={"default":0})
     */
    private $CPOther = 0;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="withdrawalDateTime", type="datetime")
     */
    private $withdrawalDateTime;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
     * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
     */
    protected $openEnrollment;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
     * @ORM\JoinColumn(name="magnetSchool", referencedColumnName="id")
     */
    protected $magnetSchool;

    /**
     * @var string
     * @ORM\Column(name="focusArea", type="string", length=255, nullable=true)
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
     * Set CPWhite
     *
     * @param integer $cPWhite
     * @return LotteryOutcomePopulation
     */
    public function setCPWhite($cPWhite)
    {
        $this->CPWhite = $cPWhite;

        return $this;
    }

    /**
     * Get CPWhite
     *
     * @return integer
     */
    public function getCPWhite()
    {
        return $this->CPWhite;
    }

    /**
     * Set CPBlack
     *
     * @param integer $cPBlack
     * @return LotteryOutcomePopulation
     */
    public function setCPBlack($cPBlack)
    {
        $this->CPBlack = $cPBlack;

        return $this;
    }

    /**
     * Get CPBlack
     *
     * @return integer
     */
    public function getCPBlack()
    {
        return $this->CPBlack;
    }

    /**
     * Set CPOther
     *
     * @param integer $cPOther
     * @return LotteryOutcomePopulation
     */
    public function setCPOther($cPOther)
    {
        $this->CPOther = $cPOther;

        return $this;
    }

    /**
     * Get CPOther
     *
     * @return integer
     */
    public function getCPOther()
    {
        return $this->CPOther;
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
     * @return \DateTime
     */
    public function getWithdrawalDateTime() {

        return $this->withdrawalDateTime;
    }

    /**
     * @param \DateTime $withdrawalDateTime
     */
    public function setWithdrawalDateTime( $withdrawalDateTime ) {

        $this->withdrawalDateTime = $withdrawalDateTime;
    }

    /**
     * Set focusArea
     *
     * @param string $focusArea
     * @return Withdrawals
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
