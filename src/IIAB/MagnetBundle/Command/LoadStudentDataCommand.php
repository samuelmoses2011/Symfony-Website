<?php
/**
 * Company: Image In A Box
 * Date: 12/30/14
 * Time: 12:45 PM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use PDO;

class LoadStudentDataCommand extends ContainerAwareCommand {

	private $file;

	/** @var PDO */
	private $pdo;

	/**
	 * @inheritdoc
	 */
	protected function configure() {

		$this
			->setName( 'magnet:load:student' )
			->setDescription( 'Loads in the student data file.' )
			->addOption( 'file' , null , InputOption::VALUE_REQUIRED , 'File you want to import' )
			->setHelp( <<<EOF
The <info>%command.name%</info> command imports the student file that is build every hour.

<info>php %command.full_name% --file=file</info>

EOF
			);
	}

	/**
	 * @inheritdoc
	 */
	protected function execute( InputInterface $input , OutputInterface $output ) {

		$file = $input->getOption( 'file' );
		if( empty( $file ) ) {
			throw new \Exception( 'File option is required. Please provide a file to import.' );
		}

		if( !file_exists( $file ) ) {
			throw new \Exception( 'File could not be found. Please provide a file to import. Make sure to use the full path of the file.' );
		}

		ini_set( 'memory_limit' , '1G' );
		set_time_limit( 0 );
		$output->writeln( '<fg=green>Starting loading @ ' . date( 'Y-m-d g:i:s' ) . '</fg=green>' );
		$output->writeln( '<fg=green>Reading: ' . $file . '</fg=green>' );

		$this->file = $file;

		$dbname = $this->getContainer()->getParameter('database_name');
		$dbhost = $this->getContainer()->getParameter('database_host');
		$dbport = $this->getContainer()->getParameter('database_port');
		$dbuser = $this->getContainer()->getParameter('database_user');
		$dbpass = $this->getContainer()->getParameter('database_password');

		$dsn = 'mysql:dbname=' . $dbname . ';host=' . $dbhost . ';port=' . $dbport;

		$this->pdo = new \PDO( $dsn , $dbuser , $dbpass , array( \PDO::MYSQL_ATTR_LOCAL_INFILE => 1 ) );

		$dataSetofStudent = array();
		$dataSetofGrades = array();
		try {
			$fp = fopen( $file , 'r' );

			// Headrow
			$head = fgetcsv( $fp , 4096 , ',' , '"' , '\\' );

			$studentColumns = array_merge( array_slice( $head , 0 , 13 ) , array_slice( $head , -1 ) );
			$gradeColumns = array_merge( array_slice( $head , 0 , 1 ) , array_slice( $head , -8 , 7 ) );
		} catch( \Exception $e ) {
			throw $e;
		}
		if( filesize( $file ) < 1049000 ) {
			throw new \Exception( 'File is way too small. Stop and check everything' );
		}

		$truncateStatement = $this->pdo->prepare( 'TRUNCATE TABLE `Student`; TRUNCATE TABLE `StudentGrade`;' );
		$truncateResponse = $truncateStatement->execute();
		if( !$truncateResponse ) {
			var_dump( $truncateStatement->errorInfo() );
			die('check truncate' );
		}
		$truncateStatement->closeCursor();

		$studentIDList = array();
		$changeToMultiRace = array();
		$changeToMultiRaceHispanic = array();
		$multiRace = 'Multi Race - Two or More Races';
		$multiRaceHispanic = 'Multi Race - Two or More Races- Hispanic';
		$total = 1;
		$counter = 0;
		$reset = 0;
		// Rows
		while( $column = fgetcsv( $fp , 4096 , ',' , '"' , '\\' ) ) {
			// This is a great trick, to get an associative row by combining the headrow with the content-rows.

			//Ignoring --- those and last rows that include count.
			if( preg_match( '/----|(\(.+rows affected\))/' , $column[0] ) || $column[0] == '' ) {
				continue;
			}

			$student = array_merge( array_slice( $column , 0 , 13 ) , array_slice( $column , -1 ) );
			$grade = array_merge( array_slice( $column , 0 , 1 ) , array_slice( $column , -8 , 7 ) );

			try {
				$student = array_combine( $studentColumns , $student );

				$student['IsHispanic'] = (int) $student['IsHispanic'];

				//Fixed Address to be into one column.
				$address = array( $student['StreetNumber'] , $student['AddressLine1'] , $student['AddressLine2'] );
				$address = array_filter( $address );
				$address = trim( implode( ' ' , $address ) );
				$address = preg_replace( '/,/' , '' , $address );

				//remove un need columns
				unset( $student['StudentNumber'] , $student['StreetNumber'] , $student['AddressLine1'] , $student['AddressLine2'] );

				$student['Address'] = $address;

				if( $student['IsHispanic'] == 1 ) {
					$student['Race'] = $student['Race'] . '- Hispanic';
				}

				$address = null;

				//$grade = array_combine( $gradeColumns , $grade );

				//Only add unique StateID, we only need one copy of them in the database.
				if( !isset( $studentIDList[$column[0]] ) ) {
					$studentIDList[$column[0]] = 1;
					$dataSetofStudent[$column[0]] = $student;
				} else {
					//Need to convert Student over to Multi Race because there is more than one Race entity for this student.
					if( isset( $dataSetofStudent[$student['StateIDNumber']] ) ) {
						//If the races are different, then they are now a Multi Race Student;
						//Multi Race - Two or More Races
						if( $student['Race'] != $dataSetofStudent[$student['StateIDNumber']]['Race'] ) {
							if( $student['IsHispanic'] == 1 ) {
								$dataSetofStudent[$student['StateIDNumber']]['Race'] = $multiRaceHispanic;
							} else {
								$dataSetofStudent[$student['StateIDNumber']]['Race'] = $multiRace;
							}
						}
					} else {
						//They have already been imported. So we just need to update their race after completing the full import.
						if( $student['IsHispanic'] == 1 ) {
							$changeToMultiRaceHispanic[] = $student['StateIDNumber'];
						} else {
							$changeToMultiRace[] = $student['StateIDNumber'];
						}
					}
				}

				//Add every grade as we need to keep it.
				if( !empty( $grade ) ) {
					$dataSetofGrades[] = $grade;
				}
				$student = null;
				$grade = null;

			} catch( \Exception $e ) {

				$output->writeln( 'Error 1001 : <pre>' . print_r( $column , true ) . '</pre>' );
				$output->writeln( $e->getMessage() );
				break;
			}

			$counter++;
			$total++;
			/*if( $counter == 25000 ) {
				$importStatus = $this->importData( $dataSetofStudent , $dataSetofGrades );
				if( $importStatus ) {
					$output->writeln( '<fg=green>Successfully partial import at ' . $total . ' @ ' . date( 'Y-m-d g:i' ) . '</fg=green>' );
					$dataSetofStudent = array();
					$dataSetofGrades = array();
					$counter = 0;
					$reset++;
				} else {
					$output->writeln( '<fg=red>Failed partial import at ' . $total . ' @ ' . date( 'Y-m-d g:i' ) . '</fg=red>' );
					throw new \Exception( 'Import failed due to error.' );
				}
			}*/
		}
		if( count( $dataSetofStudent ) > 0 || count( $dataSetofGrades ) > 0 ) {
			$importStatus = $this->importData( $dataSetofStudent , $dataSetofGrades );
			if( $importStatus ) {
				$output->writeln( '<fg=green>Successfully partial import at ' . $total . ' @ ' . date( 'Y-m-d g:i' ) . '</fg=green>' );
			} else {
				$output->writeln( '<fg=red>Failed partial import at ' . $total . ' @ ' . date( 'Y-m-d g:i' ) . '</fg=red>' );
				throw new \Exception( 'Import failed due to error.' );
			}
		}

		$dataSetofStudent = null;
		$dataSetofGrades = null;

		if( count( $changeToMultiRace ) > 0 ) {
			//Need to Update some Students to MultiRace;
			$changeToMultiRace = array_unique( $changeToMultiRace );
			$output->writeln( '<fg=yellow>Update Race. Updating ' . count( $changeToMultiRace ) . ' rows.</fg=yellow>' );
			$studentRaceStatement = $this->pdo->prepare( 'UPDATE `Student` SET `race` = "' . $multiRace . '" WHERE `stateID` IN (\'' . implode( '\', \'' , $changeToMultiRace ) . '\')' );
			$responseStudentRace = $studentRaceStatement->execute();
			if( !$responseStudentRace ) {
				var_dump( $studentRaceStatement->errorInfo() );
			}
		}
		if( count( $changeToMultiRaceHispanic ) > 0 ) {
			//Need to Update some Students to MultiRace;
			$changeToMultiRaceHispanic = array_unique( $changeToMultiRaceHispanic );
			$output->writeln( '<fg=yellow>Update Race. Updating Hispanic ' . count( $changeToMultiRaceHispanic ) . ' rows.</fg=yellow>' );
			$studentRaceStatementHispanic = $this->pdo->prepare( 'UPDATE `Student` SET `race` = "' . $multiRaceHispanic . '" WHERE `stateID` IN (\'' . implode( '\', \'' , $changeToMultiRaceHispanic ) . '\')' );
			$responseStudentRaceHispanic = $studentRaceStatementHispanic->execute();
			if( !$responseStudentRaceHispanic ) {
				var_dump( $studentRaceStatementHispanic->errorInfo() );
			}
		}

		$output->writeln( '<fg=green>Import Complete. Loaded ' . $total . ' rows. Looped a total of ' . $reset . '</fg=green>' );
		$output->writeln( '<fg=green>Finished loading @ ' . date( 'Y-m-d g:i:s' ) . '</fg=green>' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function interact( InputInterface $input , OutputInterface $output ) {

		$dialog = $this->getHelper( 'dialog' );
		foreach( $input->getOptions() as $option => $value ) {
			if( $value === null ) {
				$input->setOption( $option , $dialog->ask( $output ,
					sprintf( '<question>%s</question>: ' , ucfirst( $option ) )
				) );
			}
		}
	}

	/**
	 * Does the mySQL LOAD DATA call to import the temporary arrays.
	 * Split arrays to keep memory usage low.
	 *
	 * @param array $student
	 * @param array $grades
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function importData( array $student , array $grades ) {

		$fileSystem = new Filesystem();

		$path = str_replace( '\\' , "/" , dirname( $this->file ) );
		$tempStudent = $path . '/temp-student-file.csv';
		$tempGrade = $path . '/temp-grade-file.csv';

		$fileSystem->touch( array( $tempStudent , $tempGrade ) );

		if( !$fileSystem->exists( array( $tempStudent , $tempGrade ) ) ) {
			throw new \Exception('Temporary files were not able to be created. Please check folder permissions.');
		}

		$fpStudent = fopen( $tempStudent , 'w' );

		foreach( $student as $fields ) {
			fputcsv( $fpStudent , $fields , ',' , '"' );
		}

		fclose( $fpStudent );

		try {
			$studentStatement = $this->pdo->prepare( 'LOAD DATA LOCAL INFILE \'' . $tempStudent . '\' INTO TABLE `Student` FIELDS TERMINATED BY \',\' ENCLOSED BY \'"\' ESCAPED BY \'\\\\\' LINES TERMINATED BY \'\n\' (`stateID`, `lastName`, `firstName`, `birthday`, `currentSchool`, `grade`, `race`, `city`, `zip`, `IsHispanic`, `address`);' );
			$responseStudent = $studentStatement->execute();
			if( !$responseStudent ) {
				var_dump( $studentStatement->errorInfo() );
			}
			$studentStatement->closeCursor();
		} catch( \Exception $e ) {
			throw $e;
		}

		$fpStudent = null;

		$fpGrade = fopen( $tempGrade , 'w' );

		foreach( $grades as $fields ) {
			fputcsv( $fpGrade , $fields , ',' , '"' );
		}

		fclose( $fpGrade );

		try {
			$gradeStatement = $this->pdo->prepare( 'LOAD DATA LOCAL INFILE \'' . $tempGrade . '\' INTO TABLE `StudentGrade` FIELDS TERMINATED BY \',\' ENCLOSED BY \'"\' ESCAPED BY \'\\\\\' LINES TERMINATED BY \'\n\' (`stateID`, `academicYear`, `academicTerm`, `courseTypeID`, `courseType`, `courseName`, `sectionNumber`, `numericGrade`);' );
			$responseGrade = $gradeStatement->execute();
			if( !$responseGrade ) {
				var_dump( $gradeStatement->errorInfo() );
			}
			$gradeStatement->closeCursor();
		} catch( \Exception $e ) {
			throw $e;
		}

		$fpGrade = null;

		$fileSystem->remove( array( $tempStudent , $tempGrade ) );

		$studentStatement = null;
		$gradeStatement = null;
		$student = null;
		$grades = null;

		if( $responseStudent && $responseGrade ) {
			return true;
		}
		return false;
	}
}