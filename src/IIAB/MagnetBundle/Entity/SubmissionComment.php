<?php
/**
 * Created by PhpStorm.
 * User: justingivens
 * Date: 12/30/14
 * Time: 8:50 PM
 */

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SubmissionComment
 *
 * @ORM\Table(name="submissioncomment")
 * @ORM\Entity
 */
class SubmissionComment {

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Submission", inversedBy="userComments")
	 * @ORM\JoinColumn(name="submission_id", referencedColumnName="id")
	 */
	protected $submission;

	/**
	 * @var string
	 *
	 * @ORM\Column(type="text", nullable=false)
	 */
	private $comment;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="createdAt", type="datetime", nullable=true)
	 */
	private $createdAt;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\User")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false)
	 */
	protected $user;

	public function __construct() {

		$this->createdAt = new \DateTime();
    }


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
     * Set comment
     *
     * @param string $comment
     * @return SubmissionComment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Get comment
     *
     * @return string 
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return SubmissionComment
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime 
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set submission
     *
     * @param \IIAB\MagnetBundle\Entity\Submission $submission
     * @return SubmissionComment
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
     * Set user
     *
     * @param \IIAB\MagnetBundle\Entity\User $user
     * @return SubmissionComment
     */
    public function setUser(\IIAB\MagnetBundle\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \IIAB\MagnetBundle\Entity\User 
     */
    public function getUser()
    {
        return $this->user;
    }
}
