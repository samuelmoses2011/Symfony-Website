<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SubmissionStatus
 *
 * @ORM\Table(name="submissionstatus")
 * @ORM\Entity
 */
class SubmissionStatus {
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
	 * @ORM\Column(name="status", type="string", length=255)
	 */
	private $status;


	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set status
	 *
	 * @param string $status
	 *
	 * @return SubmissionStatus
	 */
	public function setStatus( $status ) {
		$this->status = $status;

		return $this;
	}

	/**
	 * Get status
	 *
	 * @return string
	 */
	public function getStatus() {
		return ucwords( strtolower( $this->status ) );
	}

	public function __toString() {
		return $this->getStatus();
	}
}
