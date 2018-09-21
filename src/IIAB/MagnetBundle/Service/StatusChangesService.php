<?php
/**
 * Created by PhpStorm.
 * User: michaeltremblay
 * Date: 6/2/15
 * Time: 12:03 PM
 */

namespace IIAB\MagnetBundle\Service;

use Doctrine\ORM\EntityManager;

class StatusChangesService {

    /** @var EntityManager */
    private $emLookup;

    /**
     * Setting up all the defaults needed for the Class.
     *
     * @param EntityManager      $emLookup
     */
    function __construct( EntityManager $emLookup ) {

        $this->emLookup = $emLookup;
    }

    /**
     * Finds the Last Status Change Date for the submissionID
     *
     * @param integer $submissionID
     *
     * @return dateTime|date
     */
    public function getLastStatusDate( $submissionID = '' ) {

        $sql = "SELECT
                  revisions.timestamp
                  FROM revisions
                    LEFT JOIN submission_audit ON submission_audit.rev = revisions.id
                    LEFT JOIN submission ON submission_audit.id = submission.id
                  WHERE submission.id = :submissionID
                    AND submission_audit.submissionStatus = submission.submissionStatus
                  ORDER BY revisions.timestamp ASC
                  LIMIT 1";
        $params = array('submissionID'=>$submissionID);

        $sqlStatement = $this->emLookup->getConnection()->prepare($sql);
        $sqlStatement->execute($params);

        $date = $sqlStatement->fetch()['timestamp'];
        return isset($date) ? new \DateTime($date) : NULL ;
    }

    /**
     * Returns the LastStatusDate as a specific Format.
     *
     * @param integer $submissionID
     *
     * @return string
     */
    public function getLastStatusDateFormatted( $submissionID = '' ) {

        $date = $this->getLastStatusDate($submissionID);

        return isset($date) ? $date->format( 'm/d/y H:i' ) : null;
    }
}