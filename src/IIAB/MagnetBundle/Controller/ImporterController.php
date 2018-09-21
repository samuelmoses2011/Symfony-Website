<?php
/**
 * Company: Image In A Box
 * Date: 9/28/15
 * Time: 9:46 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Controller;

use IIAB\MagnetBundle\Entity\AddressBoundSchool;
use IIAB\MagnetBundle\Form\Type\AddressBoundSchoolUpdateType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Class ImporterController
 * @package IIAB\MagnetBundle\Controller
 *
 * @Route("/admin/import", options={"i18n"=false})
 */
class ImporterController extends Controller {


	/**
	 * @Route("/address/", name="import_address")
	 * @Template("@IIABMagnet/Admin/Report/report.html.twig")
	 */
	public function importAddressFileAction() {

		ini_set( 'memory_limit' , '768M' );
		set_time_limit( 0 );

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$form = $this->createFormBuilder();

		$form->add( 'file' , 'file' , [ 'label' => 'Address File' , 'help' => 'Must be a .CSV file' ] );

		$form->add( 'submit' , 'submit' , [ 'label' => 'Import File' , 'attr' => [ 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ] ] );

		$request = $this->get('request_stack')->getCurrentRequest();

		$form = $form->getForm();

		$form->handleRequest( $request );

		if( $form->isValid() ) {
			set_time_limit( 0 );

			$data = $form->getData();

			/** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
			$file = $data['file'];

			if( !file_exists( 'uploads/excels/' ) ) {
				mkdir( 'uploads/excels/' , 0777 , true );
			}

			$file->move( 'uploads/excels/' , $file->getClientOriginalName() );
			$currentFile = 'uploads/excels/' . $file->getClientOriginalName();

			try {

				$columns = '';
				$handle = fopen( $currentFile , "r" );
				if( $handle ) {
					$columns = trim( fgets( $handle , 4096 ) );
				}
				fclose( $handle );

				if( !empty( $columns ) ) {
					$columns = preg_split( '/,/' , $columns );
				}

				$dbname = $this->getParameter( 'database_name' );
				$dbhost = $this->getParameter( 'database_host' );
				$dbport = $this->getParameter( 'database_port' );
				$dbuser = $this->getParameter( 'database_user' );
				$dbpass = $this->getParameter( 'database_password' );

				$dsn = 'mysql:dbname=' . $dbname . ';host=' . $dbhost . ';port=' . $dbport;

				$pdo = new \PDO( $dsn , $dbuser , $dbpass , array( \PDO::MYSQL_ATTR_LOCAL_INFILE => 1 ) );

				$truncateStatement = $pdo->prepare( 'TRUNCATE TABLE `AddressBound`;' );
				$truncateResponse = $truncateStatement->execute();
				if( !$truncateResponse ) {
					throw new \Exception( $truncateStatement->errorInfo() );
				}
				$truncateStatement->closeCursor();

				try {
					$studentStatement = $pdo->prepare( 'LOAD DATA LOCAL INFILE \'' . $currentFile . '\' INTO TABLE `AddressBound` FIELDS TERMINATED BY \',\' ENCLOSED BY \'"\' ESCAPED BY \'\\\\\' LINES TERMINATED BY \'\n\' IGNORE 1 LINES (`' . implode( '`, `' , $columns ) . '`);' );
					$responseStudent = $studentStatement->execute();
					if( !$responseStudent ) {
						throw new \Exception( $studentStatement->errorInfo() );
					}
					$studentStatement->closeCursor();
				} catch( \Exception $e ) {
					throw $e;
				}

				$this->container->get( 'filesystem' )->remove( [ $currentFile ] );

				//Now time to add any new schools

				$elems = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:AddressBound' )->createQueryBuilder( 'e' )->distinct( true )->select( 'e.ESBND' )->orderBy( 'e.ESBND' , 'ASC' )->getQuery()->getResult();
				$midds = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:AddressBound' )->createQueryBuilder( 'e' )->distinct( true )->select( 'e.MSBND' )->orderBy( 'e.MSBND' , 'ASC' )->getQuery()->getResult();
				$highs = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:AddressBound' )->createQueryBuilder( 'e' )->distinct( true )->select( 'e.HSBND' )->orderBy( 'e.HSBND' , 'ASC' )->getQuery()->getResult();

				$newSchools = [ ];
				foreach( [ $elems , $midds , $highs ] as $choices ) {
					foreach( $choices as $key => $choice ) {
						$newSchools[] = array_pop( $choice );
					}
				}

				sort( $newSchools );
				$newSchools = array_unique( $newSchools );
				foreach( $newSchools as $school ) {
					$foundSchool = $this->getDoctrine()->getRepository('IIABMagnetBundle:AddressBoundSchool')->findOneByName( $school );
					if( $foundSchool == null ) {
						$newSchool = new AddressBoundSchool();
						$newSchool->setName( $school );
						$this->getDoctrine()->getManager()->persist( $newSchool );
						$newSchool = null;
					}
					$foundSchool = null;
				}
				$this->getDoctrine()->getManager()->flush();

			} catch( \Exception $e ) {
				$this->container->get( 'filesystem' )->remove( [ $currentFile ] );
				throw new \Exception( 'Error during file upload. Please try again. Error message:' . $e->getMessage() );
			}

			return $this->redirect( $this->generateUrl( 'import_address_update' ) );
		}

		return [ 'admin_pool' => $admin_pool , 'form' => $form->createView() , 'title' => 'Importer' , 'subtitle' => 'Import Address File' , 'hideWarning' => true ];
	}

	/**
	 * @Route("/address/update/", name="import_address_update")
	 * @Template("@IIABMagnet/Admin/Report/address.html.twig")
	 */
	public function updateAddressBoundSchoolsAction() {

		$admin_pool = $this->get( 'sonata.admin.pool' );

		$request = $this->get('request_stack')->getCurrentRequest();

		$schools = $this->getDoctrine()->getRepository('IIABMagnetBundle:AddressBoundSchool')->findAll();

		$form = $this->createForm( new AddressBoundSchoolUpdateType() , [ 'schools' => $schools ] );

		if( $form->handleRequest( $request ) ) {

			$this->getDoctrine()->getManager()->flush();
		}

		return [ 'admin_pool' => $admin_pool , 'form' => $form->createView(), 'title' => 'Address Schools Updater' , 'subtitle' => 'Updated Schools' , 'hideWarning' => true ];

	}
}

