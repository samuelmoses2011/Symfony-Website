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
 * SubmissionData
 *
 * @ORM\Table(name="submissiondata")
 * @ORM\Entity
 */
class SubmissionData {

	/**
	 * @var integer
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 */
	private $id;

	/**
	 * @ORM\ManyToOne(targetEntity="IIAB\MagnetBundle\Entity\Submission", inversedBy="additionalData")
	 * @ORM\JoinColumn(name="submission_id", referencedColumnName="id")
	 */
	protected $submission;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="meta_key", type="string", length=255, nullable=true)
	 */
	private $metaKey;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="meta_value", type="text", nullable=true)
	 */
	private $metaValue;

	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {

		return $this->id;
	}

	/**
	 * Get metaKey
	 *
	 * @return string
	 */
	public function getMetaKey() {

		return $this->metaKey;
	}

	/**
	 * Set metaKey
	 *
	 * @param string $metaKey
	 *
	 * @return SubmissionData
	 */
	public function setMetaKey( $metaKey ) {

		$this->metaKey = $metaKey;

		return $this;
	}

	/**
	 * Get metaValue
	 *
	 * @return string
	 */
	public function getMetaValue() {

		return $this->metaValue;
	}

	/**
	 * Set metaValue
	 *
	 * @param string $metaValue
	 *
	 * @return SubmissionData
	 */
	public function setMetaValue( $metaValue ) {

		$this->metaValue = $metaValue;

		return $this;
	}

	/**
	 * @return \IIAB\MagnetBundle\Entity\Submission
	 */
	public function getSubmission() {

		return $this->submission;
	}

	/**
	 * @param \IIAB\MagnetBundle\Entity\Submission $submission
	 */
	public function setSubmission( $submission ) {

		$this->submission = $submission;
	}
}
