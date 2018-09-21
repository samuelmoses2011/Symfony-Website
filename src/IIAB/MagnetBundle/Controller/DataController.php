<?php
/**
 * Company: Image In A Box
 * Date: 2/3/15
 * Time: 4:26 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DataController
 * @package IIAB\MagnetBundle\Controller
 * @Route("/admin/iiab/magnet/data", options={"i18n"=false})
 */
class DataController extends Controller {

	/**
	 * @Route( "/required-data", name="admin_required_data")
	 *
	 * @param Request $request
	 *
	 * @return \Symfony\Component\HttpFoundation\StreamedResponse
	 */
	public function downloadRequiredDataExcel( Request $request ) {

		// create an empty object
		$phpExcelObject = $this->createXSLObject();

		$phpExcelObject->getActiveSheet()->setTitle( 'Required Data' );
		// Set active sheet index to the first sheet, so Excel opens this as the first sheet
		$phpExcelObject->setActiveSheetIndex( 0 );

		$lastEnrollment = $this->getDoctrine()->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneBy( array() , array(
			'endingDate' => 'DESC'
		) );

		$magnetSchools = $this->getDoctrine()->getRepository('IIABMagnetBundle:MagnetSchool')->createQueryBuilder('magnetSchool')
			->distinct( true )
			->select( 'magnetSchool.name' )
			->where( 'magnetSchool.active = 1' )
			->orderBy( 'magnetSchool.name' , 'ASC' )
			->andWhere( 'magnetSchool.openEnrollment = :openEnrollment' )
			->setParameter( 'openEnrollment' , $lastEnrollment )
			->getQuery()
			->getResult()
		;

		$row = 1;

		foreach( $magnetSchools as $magnetSchool ) {

			$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:I{$row}" );
			$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , $magnetSchool['name'] );
			$row++;
			$schoolGrades = array();

			$grades = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'magnetGrades' )
				->where( 'magnetGrades.name = :school' )
				->andWhere( 'magnetGrades.grade != :PreK' )
				->andWhere( 'magnetGrades.active = 1' )
				->andWhere( 'magnetGrades.openEnrollment = :openEnrollment')
				->setParameter( 'openEnrollment' , $lastEnrollment )
				->setParameter( 'school' , $magnetSchool['name'] )
				->setParameter( 'PreK' , 99 )
				->orderBy( 'magnetGrades.grade' , 'ASC' )
				->getQuery()
				->getResult();

			$preK = $this->getDoctrine()->getRepository( 'IIABMagnetBundle:MagnetSchool' )->findOneBy( array(
				'name' => $magnetSchool['name'] ,
				'openEnrollment' => $lastEnrollment,
				'grade' => 99 ,
				'active' => 1
			) );

			if( $preK != null || count( $grades ) > 0 ) {
				//Means there are grades. Lets add the Header Columns
				$phpExcelObject->getActiveSheet()->setCellValue( "B{$row}" , 'Black' );
				$phpExcelObject->getActiveSheet()->setCellValue( "C{$row}" , 'White' );
				$phpExcelObject->getActiveSheet()->setCellValue( "D{$row}" , 'American Indian/Alaskan Native' );
				$phpExcelObject->getActiveSheet()->setCellValue( "E{$row}" , 'Asian' );
				$phpExcelObject->getActiveSheet()->setCellValue( "F{$row}" , 'Multi Race - Two or More Races' );
				$phpExcelObject->getActiveSheet()->setCellValue( "G{$row}" , 'Not Specified' );
				$phpExcelObject->getActiveSheet()->setCellValue( "H{$row}" , 'Other' );
				$phpExcelObject->getActiveSheet()->setCellValue( "I{$row}" , 'Pacific Islander' );
				$row++;
			}

			if( $preK != null ) {
				$grade = $preK->getGrade();
				if( $grade >= 96 ) {
					$grade = "PreK";
				} elseif( $grade == 0 ) {
					$grade = "K";
				} else {
					$grade = $grade;
				}
				$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , $grade );
				$phpExcelObject->getActiveSheet()->getStyle( "A{$row}" )->getAlignment()->setHorizontal( \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT );
				$row++;
				$schoolGrades[] = $grade;
			}

			if( count( $grades ) > 0 ) {
				foreach( $grades as $grade ) {
					$grade = $grade->getGrade();
					if( $grade >= 96 ) {
						$grade = "PreK";
					} elseif( $grade == 0 ) {
						$grade = "K";
					} else {
						$grade = $grade;
					}
					$schoolGrades[] = $grade;
					$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , $grade );
					$phpExcelObject->getActiveSheet()->getStyle( "A{$row}" )->getAlignment()->setHorizontal( \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT );
					$row++;
				}

			}

			$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:I{$row}" );
			$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , "" );
			$row++;
			$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:I{$row}" );
			$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , "" );
			$row++;

			if( count( $schoolGrades ) > 0 ) {

				$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:I{$row}" );
				$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , $lastEnrollment->getYear() . " Total Capacity (do not subtract current students)" );
				$row++;

				foreach( $schoolGrades as $grade ) {

					$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , $grade );
					$phpExcelObject->getActiveSheet()->getStyle( "A{$row}" )->getAlignment()->setHorizontal( \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT );
					$row++;
				}

				$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:I{$row}" );
				$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , "" );
				$row++;
				$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:I{$row}" );
				$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , "" );
				$row++;
			}
		}

		$row = 1;
		$phpExcelObject->createSheet();
		$phpExcelObject->setActiveSheetIndex( 1 )->setTitle( 'ADM Data' );

		$schoolsFound = array();

		$districtElementarySchools = $this->getDoctrine()->getRepository('IIABMagnetBundle:AddressBound')->createQueryBuilder('address')
			->distinct( true )
			->select('address.ESBND')
			->orderBy('address.ESBND' , 'ASC')
			->getQuery()
			->getResult()
		;
		foreach( $districtElementarySchools as $school ) {
			$schoolsFound[] = $school['ESBND'];
		}

		$districtMiddleSchools = $this->getDoctrine()->getRepository('IIABMagnetBundle:AddressBound')->createQueryBuilder('address')
			->distinct( true )
			->select('address.MSBND')
			->orderBy('address.MSBND' , 'ASC')
			->getQuery()
			->getResult()
		;
		foreach( $districtMiddleSchools as $school ) {
			$schoolsFound[] = $school['MSBND'];
		}

		$districtHighSchools = $this->getDoctrine()->getRepository('IIABMagnetBundle:AddressBound')->createQueryBuilder('address')
			->distinct( true )
			->select('address.HSBND')
			->orderBy('address.HSBND' , 'ASC')
			->getQuery()
			->getResult()
		;
		foreach( $districtHighSchools as $school ) {
			$schoolsFound[] = $school['HSBND'];
		}
		sort( $schoolsFound );

		if( count( $schoolsFound ) ) {

			$phpExcelObject->getActiveSheet()->mergeCells( "A{$row}:I{$row}" );
			$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , "ADM Data" );
			$row++;

			//Means there are grades. Lets add the Header Columns
			$phpExcelObject->getActiveSheet()->setCellValue( "B{$row}" , 'Black' );
			$phpExcelObject->getActiveSheet()->setCellValue( "C{$row}" , 'White' );
			$phpExcelObject->getActiveSheet()->setCellValue( "D{$row}" , 'American Indian/Alaskan Native' );
			$phpExcelObject->getActiveSheet()->setCellValue( "E{$row}" , 'Asian' );
			$phpExcelObject->getActiveSheet()->setCellValue( "F{$row}" , 'Multi Race - Two or More Races' );
			$phpExcelObject->getActiveSheet()->setCellValue( "G{$row}" , 'Not Specified' );
			$phpExcelObject->getActiveSheet()->setCellValue( "H{$row}" , 'Other' );
			$phpExcelObject->getActiveSheet()->setCellValue( "I{$row}" , 'Pacific Islander' );
			$row++;

			foreach( $schoolsFound as $school ) {

				$phpExcelObject->getActiveSheet()->setCellValue( "A{$row}" , $school );
				$row++;
			}
		}

		$phpExcelObject->setActiveSheetIndex( 0 );

		$writer = $this->get('phpexcel')->createWriter($phpExcelObject, 'Excel2007');
		// create the response
		$response = $this->get('phpexcel')->createStreamedResponse($writer);
		// adding headers
		$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
		$response->headers->set('Content-Disposition', 'attachment;filename=required-data.xlsx');
		$response->headers->set('Pragma', 'public');
		$response->headers->set('Cache-Control', 'maxage=1');
		return $response;
	}

	/**
	 *
	 * @return \PHPExcel
	 * @throws \PHPExcel_Exception
	 */
	private function createXSLObject() {

		$phpExcelObject = $this->get( 'phpexcel' )->createPHPExcelObject();
		$phpExcelObject->getProperties()->setCreator( "Image In A Box" )
			->setLastModifiedBy( "Image In A Box" )
			->setTitle( "Required Data for Magnet Program" )
			->setSubject( "Required Data" )
			->setDescription( "Document needs to be completed in order for the Magnet Program website to run correctly." )
			->setKeywords( "mymagnetapp" )
			->setCategory( "required data" )
		;
		return $phpExcelObject;
	}
}