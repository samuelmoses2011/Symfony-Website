<?php
/**
 * Company: Image In A Box
 * Date: 8/17/15
 * Time: 4:42 PM
 * Copyright: 2015
 */

namespace IIAB\AuditBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use SimpleThings\EntityAudit\Utils\ArrayDiff;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AuditController
 * @package IIAB\AuditBundle\Controller
 * @Route("/audit/", name="admin_audit", options={"i18n":false})
 */
class AuditController extends Controller {

	/** @var \SimpleThings\EntityAudit\AuditReader $reader */
	private $reader;

	public function setContainer( ContainerInterface $container = null ) {

		$this->container = $container;
		$this->reader = $this->container->get( "simplethings_entityaudit.reader" );
	}


	/**
	 *
	 * @Route("", name="audit_index")
	 * @Route("page/{page}/", name="audit_index_paged")
	 * @Template()
	 *
	 * @param int $page
	 *
	 * @return array
	 */
	public function indexAction( $page = 1 ) {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$formattedRevisions = [ ];
		$maxPerPage = 20;

		$specificID = null;

		$filters = $this->get('request_stack')->getCurrentRequest()->get('filter');
		if( $filters != null ) {

			$revisions = $this->reader->findRevisions( 'IIAB\MagnetBundle\Entity\Submission' , $filters['id']['value'] );
			$page = 1;
			$specificID = $filters['id']['value'];
			$maxPerPage = count( $revisions );
			$revisionsCount = count( $revisions );
		} else {

			$revisions = $this->reader->findRevisionHistory( $maxPerPage , $maxPerPage * ( $page - 1 ) );

			$revisionsCountSQL = $this->getDoctrine()->getManager()->getConnection()->prepare( "SELECT COUNT(`id`) as `total` FROM `revisions` WHERE 1");
			$revisionsCountSQL->execute();

			$revisionsCount = (int) $revisionsCountSQL->fetch()['total'];
		}

		$lastPage = (int) ceil( $revisionsCount / $maxPerPage );
		$data_pool = new \stdClass();
		$data_pool->page = $page;
		$data_pool->previouspage = ( $page - 1 );
		$data_pool->nextpage = min( $lastPage , ( $page + 1 ) );
		$data_pool->lastpage = $lastPage;
		$data_pool->pagingStart = max( 1 , ( $page - 4 ) );
		$data_pool->pagingEnd = max( 10 , $page + 4 );
		if( $data_pool->pagingEnd > $lastPage ) {
			$data_pool->pagingStart = max( 1 , ( $lastPage - 8 ) );
			$data_pool->pagingEnd = $lastPage;
		}
		$data_pool->pages = max( 1 , $lastPage );

		foreach( $revisions as $revision ) {

			$submissionID = null;
			$stateID = null;
			$message = [ ];
			$messageType = null;
			$typeName = null;
			$previousChange = null;
			$user = $revision->getUsername();

			$changeEntity = $this->reader->findEntitiesChangedAtRevision( $revision->getRev() );
			$lastSubmissionID = 0;

			foreach( $changeEntity as $changed ) {

				if( $specificID != null && $changed->getEntity()->getId() != $specificID ) {
					continue;
				}

				$name = $changed->getClassName();
				$nameSplit = preg_split( '/\\\/' , $name );

				if( $name == 'IIAB\MagnetBundle\Entity\Submission' ) {

					if( $lastSubmissionID != $changed->getEntity()->getStateID() ) {
						if( $lastSubmissionID != 0 ) {
							//Not the first loop.

							$formattedRevisions[] = [
								'id' => $revision->getRev() ,
								'timestamp' => $revision->getTimestamp() ,
								'user' => $user ,
								'submissionID' => $submissionID ,
								'stateID' => $stateID ,
								'type' => $typeName ,
								'audit' => implode( '<br />' , $message ) ,
							];
							$message = [ ];
							$typeName = null;
						}
					}

					$submissionID = sprintf( '<a href="%s">%d</a>' , $this->generateUrl( 'admin_submission_edit' , array( 'id' => $changed->getEntity()->getId() ) ) , $changed->getEntity()->getId() );
					$lastSubmissionID = $changed->getEntity()->getId();
					$stateID = $changed->getEntity()->getStateID();
				}
				if( $name == 'IIAB\MagnetBundle\Entity\User' ) {
					$user = $changed->getEntity()->getUsername();
				}
				$messageType = $changed->getRevisionType();

				$type = end( $nameSplit );

				$type = preg_split( '/(?=[A-Z])/' , $type );
				$type = array_filter( $type );
				$type = trim( implode( ' ' , $type ) );
				if( $typeName == null ) {
					$typeName = $type;
				}

				switch( $messageType ) {
					case 'UPD':
						$message[] = sprintf( 'Updated <strong>%s</strong>' , $type );
						break;

					case 'INS':
						if( $type == 'Submission' ) {
							$message[] = sprintf( 'New Submission Received' );
							$message[] = sprintf( '%s <span style="text-decoration: underline;">%s</span>' , '&nbsp;&nbsp;&nbsp;Submission Status: set to' , $changed->getEntity()->getSubmissionStatus() );
						} else {
							$message[] = sprintf( '&nbsp;&nbsp;&nbsp;Added new <strong>%s</strong> into System. ID: %d' , $type , $changed->getEntity()->getId()  );
						}
						break;

					case 'DEL':
						$message[] = sprintf( 'Deleted %s from database (Unique Database ID: %d)' , $type , $changed->getEntity()->getId() );
						break;
				}

				if( $messageType == 'UPD' && ( $type == 'Submission' || $type == 'User' || $type == 'Submission Data' || $type == 'Offered' ) ) {
					//Only on Updates does we find the previousEntity.
					$previousChanges = $this->reader->findRevisions( $name , $changed->getEntity()->getId() );
					$previousChanges = $this->findPreviousChange( $revision->getRev() , $previousChanges );

					if( count( $previousChanges ) == 2 ) {

						//Only if there are two entities, now get the differences.
						$diff = $this->getDifference( $name , $changed , $previousChanges );

						foreach( $diff as $column => $data ) {

							$column = preg_split( '/(?=[A-Z])/' , $column );
							$column = array_filter( $column );
							$column = trim( implode( ' ' , $column ) );

							if( $data['new'] != '' ) {
								if( $type == 'Submission' ) {
									if( is_object( $data['new'] ) ) {
										switch( get_class( $data['new'] ) ) {
											case 'DateTime':
												$message[] = sprintf( '&nbsp;&nbsp;&nbsp;<span style="text-decoration: underline;">%s</span> changed from %s to %s' , $column , $data['old']->format( 'Y-m-d' ) , $data['new']->format( 'Y-m-d' ) );
												break;

											default:
												$message[] = '&nbsp;&nbsp;&nbsp;' . get_class( $data['new'] );
												break;
										}
									} else {
										$message[] = '&nbsp;&nbsp;&nbsp;' . sprintf( '%s: changed from <span data-id="%d" style="text-decoration: underline;">%s</span> to <span style="text-decoration: underline;">%s</span>' , ucwords( $column ) , $changed->getEntity()->getId() , $data['old'] , $data['new'] );
									}
								} elseif( $type == 'Offered' ) {
									if( is_object( $data['new'] ) ) {
										switch( get_class( $data['new'] ) ) {
											case 'DateTime':
												$message[] = sprintf( '&nbsp;&nbsp;&nbsp;<span style="text-decoration: underline;">%s</span> changed to %s' , ucwords( $column ) , $data['new']->format( 'Y-m-d H:i' ) );
												break;

											default:
												$message[] = '&nbsp;&nbsp;&nbsp;' . get_class( $data['new'] );
												break;
										}
									} else {
										$message[] = sprintf( '&nbsp;&nbsp;&nbsp;<span style="text-decoration: underline;">%s</span> changed to %s' , ucwords( $column ) , $data['new'] );
										$message[] = '&nbsp;&nbsp;&nbsp;' . sprintf( '%s: changed from <span data-id="%d" style="text-decoration: underline;">%s</span> to <span style="text-decoration: underline;">%s</span>' , ucwords( $column ) , $changed->getEntity()->getId() , $data['old'] , $data['new'] );
									}
								} else {
									if( $column == 'last Login' ) {
										$message = [ 'User logged in' ];
									}
								}
							} else {
								if( $type == 'Submission Data' ) {
									if( $column == 'metaKey' ) {
										$message[] = '&nbsp;&nbsp;&nbsp;' . $data['same'];
									}
								}
							}
						}
					} else {
						continue;
					}
				}

				if( $user == 'anon.' ) {
					$user = 'Anonymous';
				}

				//$diff = $this->reader->diff( $name , $changed->getEntity()->getId());
			}

			$formattedRevisions[] = [
				'id' => $revision->getRev() ,
				'timestamp' => $revision->getTimestamp() ,
				'user' => $user ,
				'submissionID' => $submissionID ,
				'stateID' => $stateID ,
				'type' => $typeName ,
				'audit' => implode( '<br />' , $message ) ,
			];
		}

		return [ 'admin_pool' => $admin_pool , 'revisions' => $formattedRevisions , 'data_pool' => $data_pool ];
	}

	/**
	 *
	 * @param int $submissionID
	 *
	 * @Route("view/{submissionID}/", name="audit_individual")
	 *
	 */
	public function individualAuditAction( $submissionID = 0 ) {

		die( 'here' );

		$auditReader = $this->container->get( "simplethings_entityaudit.reader" );

		$submissionAudit = $auditReader->findRevisions( 'IIAB\MagnetBundle\Entity\Submission' , 500 );
		foreach( $submissionAudit as $revision ) {

			/** @var \SimpleThings\EntityAudit\ChangedEntity $changed */
			$changes = $auditReader->findEntitiesChangedAtRevision( $revision->getRev() );

			foreach( $changes as $changed ) {
				var_dump( $changed->getClassName() );
				var_dump( $changed->getId() );
				var_dump( $changed->getRevisionType() );
				var_dump( $changed->getEntity() );
			}
		}
	}

	/**
	 * Gets the difference between two entities.
	 *
	 *
	 * @param $name
	 * @param $changed
	 * @param $previousChanges
	 *
	 * @return array|ArrayDiff
	 * @throws \SimpleThings\EntityAudit\AuditException
	 */
	private function getDifference( $name , $changed , $previousChanges ) {

		$metadata = $this->getDoctrine()->getManager()->getClassMetadata( $name );
		$fields = $metadata->getFieldNames();
		$otherFields = $metadata->getAssociationNames();
		$fields = array_merge( $fields , $otherFields );

		$oldValues = [ ];
		$oldEntity = $this->reader->find( $name , $changed->getEntity()->getId() , $previousChanges[1]->getRev() );

		$newValues = [ ];
		$newEntity = $this->reader->find( $name , $changed->getEntity()->getId() , $previousChanges[0]->getRev() );

		foreach( $fields AS $fieldName ) {
			$oldValue = $metadata->getFieldValue( $oldEntity , $fieldName );
			$newValue = $metadata->getFieldValue( $newEntity , $fieldName );
			if( is_object( $oldValue ) && method_exists( $oldValue , '__toString' ) ) {
				if( get_class( $oldValue ) != 'Doctrine\Common\Collections\ArrayCollection' ) {
					$oldValue = $oldValue->__toString();
				} else {
					$oldValue = 'array';
				}
			}
			if( is_object( $newValue ) && method_exists( $newValue , '__toString' ) ) {
				if( get_class( $newValue ) != 'Doctrine\Common\Collections\ArrayCollection' ) {
					$newValue = $newValue->__toString();
				} else {
					$newValue = 'array';
				}
			}

			$oldValues[$fieldName] = $oldValue;
			$newValues[$fieldName] = $newValue;
		}
		$diff = new ArrayDiff();
		$diff = $diff->diff( $oldValues , $newValues );

		return $diff;
	}

	/**
	 * Finds the current rev change and the previous one.
	 *
	 * @param int   $rev
	 * @param array $previousChanges
	 *
	 * @return array
	 */
	private function findPreviousChange( $rev = 0 , $previousChanges = [ ] ) {

		if( $rev == 0 ) {
			return [ ];
		}

		/** @var \SimpleThings\EntityAudit\Revision $change */
		foreach( $previousChanges as $key => $change ) {
			if( $change->getRev() == $rev ) {
				$key++;
				if( isset( $previousChanges[$key] ) ) {
					return [ $change , $previousChanges[$key] ];
				} else {
					return [ $change ];
				}
				break;
			}
		}

		return [ ];
	}
}