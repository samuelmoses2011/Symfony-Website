<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LotteryOutcomeSubmission
 *
 * @ORM\Table(name="lotteryoutcomesubmission")
 * @ORM\Entity
 */
class LotteryOutcomeSubmission
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
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Submission")
     * @ORM\JoinColumn(referencedColumnName="id", name="submission")
     */
    protected $submission;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=255)
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\MagnetSchool")
     * @ORM\JoinColumn(name="magnetSchool", referencedColumnName="id", nullable=true)
     */
    protected $magnetSchool;

    /**
     * @var integer
     *
     * @ORM\Column(name="choiceNumber", type="integer", nullable=true)
     */
    protected $choiceNumber;

    /**
     * @var string
     *
     * @ORM\Column(name="focusArea", type="string", nullable=true)
     */
    protected $focusArea;

    /**
     * @var integer
     *
     * @ORM\Column(name="lotteryNumber", type="bigint", nullable=true)
     */
    protected $lotteryNumber;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\OpenEnrollment")
     * @ORM\JoinColumn(name="openEnrollment", referencedColumnName="id")
     */
    protected $openEnrollment;

    /**
     * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Placement")
     * @ORM\JoinColumn(name="placement", referencedColumnName="id")
     */
    protected $placement;

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
     * Set submission
     *
     * @param \IIAB\MagnetBundle\Entity\Submission $submission
     * @return LotteryOutcomeSubmission
     */
    public function setSubmission(\IIAB\MagnetBundle\Entity\Submission $submission = null)
    {
        $this->submission = $submission;

        return $this;
    }

    /**
     * Get submission
     *
     * @return \IIAB\MagnetBundle\Entity\Submission
     */
    public function getSubmission()
    {
        return $this->submission;
    }

    /**
     * Set openEnrollment
     *
     * @param \IIAB\MagnetBundle\Entity\OpenEnrollment $openEnrollment
     * @return LotteryOutcomeSubmission
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
     * Set placement
     *
     * @param \IIAB\MagnetBundle\Entity\Placement $placement
     * @return LotteryOutcomeSubmission
     */
    public function setPlacement(\IIAB\MagnetBundle\Entity\Placement $placement = null)
    {
        $this->placement = $placement;

        return $this;
    }

    /**
     * Get placement
     *
     * @return \IIAB\MagnetBundle\Entity\Placement
     */
    public function getPlacement()
    {
        return $this->placement;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return LotteryOutcomeSubmission
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set magnetSchool
     *
     * @param \IIAB\MagnetBundle\Entity\MagnetSchool $magnetSchool
     * @return LotteryOutcomeSubmission
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
     * Set choiceNumber
     *
     * @param integer $choiceNumber
     * @return LotteryOutcomeSubmission
     */
    public function setChoiceNumber($choiceNumber)
    {
        $this->choiceNumber = $choiceNumber;

        return $this;
    }

    /**
     * Get choiceNumber
     *
     * @return integer
     */
    public function getChoiceNumber()
    {
        return $this->choiceNumber;
    }

    /**
     * Set lotteryNumber
     *
     * @param integer $lotteryNumber
     * @return LotteryOutcomeSubmission
     */
    public function setLotteryNumber($lotteryNumber)
    {
        $this->lotteryNumber = $lotteryNumber;

        return $this;
    }

    /**
     * Get lotteryNumber
     *
     * @return integer
     */
    public function getLotteryNumber()
    {
        return $this->lotteryNumber;
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
}
