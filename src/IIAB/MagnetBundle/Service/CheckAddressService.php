<?php
/**
 * Created by PhpStorm.
 * User: michellegivens
 * Date: 12/26/14
 * Time: 1:29 PM
 */

namespace IIAB\MagnetBundle\Service;

use Doctrine\ORM\EntityManager;
use IIAB\MagnetBundle\Entity\AddressBound;
use IIAB\MagnetBundle\Service\ZoningAPIService;

class CheckAddressService {

	/** @var array */
	private $student;

	/** @var EntityManager */
	private $emLookup;

    /** @var  boolean */
    private $use_api = MYPICK_CONFIG['use_zoning_api'];

    /**
     * @var \IIAB\MagnetBundle\Service\ZoningAPIService
     */
    private $zoningAPI;

	/**
	 * Setup the services to be able to lookup any information needed.
	 *
	 * @param       $emLookup
	 */
	public function __construct( EntityManager $emLookup ) {

		$this->setEmLookup( $emLookup );

        if( $this->use_api ) {
            $this->zoningAPI = new ZoningAPIService($this->emLookup);
        }
	}

	/**
	 * Check the address of the current student
	 * to see if the student is in bound.
	 *
	 * @param array $student
	 *
	 * @return AddressBound|bool
	 */
	public function checkAddress( array $student ) {
		$this->student = $student;

		if( $this->student['student_status'] == 'current' ) {
			//Do not check current student against Zoning.
			return true;
		}

        $lookupAddress = strtoupper( trim( $this->student['address'] ) );
        $lookupAddress = explode( ' APT ', $lookupAddress)[0];
        $lookupAddress = explode( ' UNIT ', $lookupAddress)[0];

		$zip = preg_split( '/-/' , trim( $this->student['zip'] ) , 2 );
		$zip = trim( $zip[0] );
        if( strlen( $zip) > 5 ) {
            $zip = substr( $zip , 0 , 5 );
        }

        if( $this->use_api ) {
            $lookupResponse = $this->zoningAPI->getZonedSchools( $lookupAddress , $zip );
        } else {
            $lookupResponse = $this->checkAddressAgainstDatabase($lookupAddress, $zip);
        }

		if( $lookupResponse == false ) {
			//Change address and try again.
			$addressArray = explode( ' ' , $lookupAddress );

			//check the first two elements in address string against the HSV zoning site
			$secondTryAddressLookup = implode( ' ' , array_slice( $addressArray , 0 , 2 ) );

            if( $this->use_api ) {
                $lookupResponse2 = $this->zoningAPI->getZonedSchools( $secondTryAddressLookup , $zip );
            } else {
                $lookupResponse2 = $this->checkAddressAgainstDatabase($secondTryAddressLookup, $zip);
            }

			if( $lookupResponse2 == false ) {

				$thirdTryAddressLookup = implode( ' ' , array_slice( $addressArray , 0 , 2 ) ) . '%' . implode( ' ' , array_slice( $addressArray , -2 , 2 ) );

                if( $this->use_api ) {
                    $lookupResponse3 = $this->zoningAPI->getZonedSchools( $thirdTryAddressLookup , $zip );
                } else {
                    $lookupResponse3 = $this->checkAddressAgainstDatabase($thirdTryAddressLookup, $zip);
                }

				if( $lookupResponse3 == false ) {
					return false;
				} else {
					return ( isset($lookupResponse3['zoned']) ) ? $lookupResponse3['zoned'] : $lookupResponse3;
				}
			} else {
				return ( isset($lookupResponse2['zoned']) ) ? $lookupResponse2['zoned'] : $lookupResponse2;
			}
		}

        return ( isset($lookupResponse['zoned']) ) ? $lookupResponse['zoned'] : $lookupResponse;
	}

	/**
	 * Checks the address against the AddressBounds database.
	 *
	 * @param string $lookupAddress
	 * @param string $zip
	 *
	 * @return bool|AddressBound
	 */
    private function checkAddressAgainstDatabase( $lookupAddress = '' , $zip = '' ) {

        $addressFound = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:AddressBound' )->findAddressLike( $lookupAddress , $zip );

		$match_found = false;
        if( count( $addressFound ) > 1 ) {

			foreach( $addressFound as $possible_match){
				if( $lookupAddress == trim( preg_replace('/\s+/', ' ', $possible_match->getAddressFu() ) ) ){
					$match_found = true;
					$addressFound = [ $possible_match ];
					break;
				}
			}

			if(!$match_found) {
				$addressFound = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:AddressBound' )->findSpecificAddress( $lookupAddress, $zip );
			}
        }

        //IF the addressFound is null or there is more than one address (like apt, units, etc).
        //return false;
        if( $addressFound == null || count( $addressFound ) > 1 ) {
            return false;
        }

        return $addressFound[0];
    }

    /**
     * Checks the address against the AddressBounds database.
     *
     * @param string $lookupAddress
     * @param string $zip
     * @param integer $maxSuggestions
     *
     * @return bool|AddressBound
     */
    private function getAddressFromDatabase( $lookupAddress = '' , $zip = '' , $maxSuggestions = 0 ) {
        $limit = ( $maxSuggestions > 100 ) ? $maxSuggestions : 100;

        // check for exact matches
        $matches = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:AddressBound' )->findSpecificAddress( $lookupAddress , $zip, $limit );

        // if there are no exact matches then look for addresses like the lookupAddress
        if( !$matches ) {
            $matches = $this->getEmLookup()->getRepository( 'IIABMagnetBundle:AddressBound' )->findAddressLike( $lookupAddress , $zip, $limit );
        }

        // if we didn't find any or we found several and don't want any suggestions
        if( !$matches || ( !$maxSuggestions && count($matches) > 1 ) ) {
            return false;
        }

        // if there is only one match return it
        if( count($matches) == 1 ){
			return $matches[0];
        }

        // sort the matches by their similarity using the levenshtein algorithm
        $scoredList = [];
        foreach($matches as $match) {
            $scoredList[] = [
                'score' => levenshtein($lookupAddress, $match->getAddressFu()),
                'addressBound' => $match
            ];
        }
        usort($scoredList, function($a, $b) {
            return $a['score'] - $b['score'];
        });

        // return the best matches
        $scoredList = array_slice($scoredList, 0, $maxSuggestions);
        return array_column( $scoredList, 'addressBound' );

    }

	/**
	 * Check the address against the HSV zoning website.
	 *
	 * @param string $lookupAddress
	 *
	 * @return bool
	 */
	private function checkAddressAgainstHSVZoning( $lookupAddress = '' ) {

		//Make the address urlencoded so it is prepared for the URL.
		$lookupAddress = urlencode( "'" . $lookupAddress . "%'" );

		//TODO: Added in HSV Zoning Query from StudentTransfer Project

		return true;
	}

	/**
	 * Correct the address to match all the street names that the city/bound file uses.
	 * See function for more details on the changes.
	 *
	 * @param string $lookupAddress
	 *
	 * @return string
	 */
	private function correctAddress( $lookupAddress = '' ) {

		$lookupAddress = trim( $lookupAddress );
		$lookupAddress = preg_replace( '/(\bSuite\b)|(\bLot\b)|(\bApartment\b)|(\bApt\b)|(\bAddress\b)/i' , 'Unit' , $lookupAddress );
		$lookupAddress = preg_replace( "/(\.)|(,)|(')|(#)/" , '' , $lookupAddress );
		$lookupAddress = preg_replace( '/(\bDrive\b)/i' , 'DR' , $lookupAddress );
		$lookupAddress = preg_replace( '/(\bCr\b)|(\bCircle\b)/i' , 'CIR' , $lookupAddress );
		$lookupAddress = preg_replace( '/(\bmc)/i' , 'Mc' , $lookupAddress );
		$lookupAddress = preg_replace( '/(\bBlvd\b)/i' , 'BLV' , $lookupAddress );
		$lookupAddress = preg_replace( '/(\bAvenue\b)/i' , 'AVE' , $lookupAddress );
		$lookupAddress = preg_replace( '/(\bPlace\b)/i' , 'PL' , $lookupAddress );
		$lookupAddress = preg_replace( '/(\bLane\b)/i' , 'LN' , $lookupAddress );
		$lookupAddress = strtoupper( $lookupAddress ); //Bounds are 100% upper case.

		$addressArray = explode( ' ' , $lookupAddress );

		//Does the index:1 contain an number street. Example: 8th Street.
		if( isset( $addressArray[1] ) && preg_match( '/\d+/' , $addressArray[1] , $matches ) !== false ) {
			//Index:1 contains an number. Need to replace.
			//Add in switch statement to handle converting 1st - 17th to First - Seventeenth
			switch( strtoupper( $addressArray [1] ) ) {
				case '1ST':
					$addressArray[1] = 'FIRST';
					break;
				case '2ND':
					$addressArray[1] = 'SECOND';
					break;
				case '3RD':
					$addressArray[1] = 'THIRD';
					break;
				case '4TH':
					$addressArray[1] = 'FOURTH';
					break;
				case '5TH':
					$addressArray[1] = 'FIFTH';
					break;
				case '6TH':
					$addressArray[1] = 'SIXTH';
					break;
				case '7TH':
					$addressArray[1] = 'SEVENTH';
					break;
				case '8TH':
					$addressArray[1] = 'EIGHTH';
					break;
				case '9TH':
					$addressArray[1] = 'NINTH';
					break;
				case '10TH':
					$addressArray[1] = 'TENTH';
					break;
				case '11TH':
					$addressArray[1] = 'ELEVENTH';
					break;
				case '12TH':
					$addressArray[1] = 'TWELFTH';
					break;
				case '13TH':
					$addressArray[1] = 'THIRTEENTH';
					break;
				case '14TH':
					$addressArray[1] = 'FOURTEENTH';
					break;
				case '15TH':
					$addressArray[1] = 'FIFTEENTH';
					break;
				case '17TH':
					$addressArray[1] = 'SEVENTEENTH';
					break;
				default:
					break;
			}
		}
		$lookupAddress = implode( ' ' , $addressArray );

		return $lookupAddress;
	}

    /**
     * @return array
     */
    public function getSuggestions( array $student = null , $maxSuggestions = 5 ) {

        if( !MYPICK_CONFIG['enforce_address_bounds'] ) {
            return [];
        }
        $data = ( isset($student) ) ? $student : $this->student;

		$data['address'] = $this->correctAddress( $data['address'] );

        $addressParts = explode( ' ', trim( $data['address'] ) );
        $countParts = count($addressParts);

        $results = null;
        for( $useParts = $countParts; $useParts > 0; $useParts-- ){
            $searchAddress = implode( ' ', array_slice( $addressParts, 0, $useParts ) );

            if( $this->use_api ) {
                $suggestions = $this->zoningAPI->getAddressCandidates( $searchAddress , $data['zip'], $maxSuggestions );
            } else {
                $suggestions = $this->getAddressFromDatabase( $searchAddress, $data['zip'], $maxSuggestions );
            }

            if($suggestions) {
                return ( is_array($suggestions) ) ? $suggestions : [$suggestions];
            }
        }
        return [];
    }

	/**
	 * @return EntityManager
	 */
	public function getEmLookup() {

		return $this->emLookup;
	}

	/**
	 * @param EntityManager $emLookup
	 */
	public function setEmLookup( $emLookup ) {

		$this->emLookup = $emLookup;
	}

    public function getZonedSchoolFromAddressResponse( $addressResponse, $next_grade ){

        if( empty( $addressResponse ) ){
            return false;
        }

        switch ( $next_grade ) {

            case 99:
                $zonedSchool = $this->emLookup
                    ->getRepository('IIABMagnetBundle:AddressBoundSchool')
                    ->createQueryBuilder('a')
                    ->where('a.startGrade = 99')
                    ->andWhere('a.name = :school')
                    ->setParameter('school', $addressResponse->getESBND())
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getResult();
                break;

            default:
                $zonedSchool = $this->emLookup
                    ->getRepository('IIABMagnetBundle:AddressBoundSchool')
                    ->createQueryBuilder('a')
                    ->where('a.startGrade <= :grade OR a.startGrade = 99')
                    ->andWhere('a.endGrade >= :grade')
                    ->andWhere('a.name = :elemschool OR a.name = :middleschool OR a.name = :highschool')
                    ->setParameter('grade', number_format($next_grade, 0))
                    ->setParameter('elemschool', $addressResponse->getESBND())
                    ->setParameter('middleschool', $addressResponse->getMSBND())
                    ->setParameter('highschool', $addressResponse->getHSBND())
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getResult();
                break;
        }

        if( empty( $zonedSchool ) ){
            return false;
        }

        return $zonedSchool[0];
    }
}

/**
 * This file is part of the array_column library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) Ben Ramsey (http://benramsey.com)
 * @license http://opensource.org/licenses/MIT MIT
 */

if (!function_exists('array_column')) {
	/**
	 * Returns the values from a single column of the input array, identified by
	 * the $columnKey.
	 *
	 * Optionally, you may provide an $indexKey to index the values in the returned
	 * array by the values from the $indexKey column in the input array.
	 *
	 * @param array $input A multi-dimensional array (record set) from which to pull
	 *                     a column of values.
	 * @param mixed $columnKey The column of values to return. This value may be the
	 *                         integer key of the column you wish to retrieve, or it
	 *                         may be the string key name for an associative array.
	 * @param mixed $indexKey (Optional.) The column to use as the index/keys for
	 *                        the returned array. This value may be the integer key
	 *                        of the column, or it may be the string key name.
	 * @return array
	 */
	function array_column($input = null, $columnKey = null, $indexKey = null)
	{
		// Using func_get_args() in order to check for proper number of
		// parameters and trigger errors exactly as the built-in array_column()
		// does in PHP 5.5.
		$argc = func_num_args();
		$params = func_get_args();

		if ($argc < 2) {
			trigger_error("array_column() expects at least 2 parameters, {$argc} given", E_USER_WARNING);
			return null;
		}

		if (!is_array($params[0])) {
			trigger_error(
				'array_column() expects parameter 1 to be array, ' . gettype($params[0]) . ' given',
				E_USER_WARNING
			);
			return null;
		}

		if (!is_int($params[1])
			&& !is_float($params[1])
			&& !is_string($params[1])
			&& $params[1] !== null
			&& !(is_object($params[1]) && method_exists($params[1], '__toString'))
		) {
			trigger_error('array_column(): The column key should be either a string or an integer', E_USER_WARNING);
			return false;
		}

		if (isset($params[2])
			&& !is_int($params[2])
			&& !is_float($params[2])
			&& !is_string($params[2])
			&& !(is_object($params[2]) && method_exists($params[2], '__toString'))
		) {
			trigger_error('array_column(): The index key should be either a string or an integer', E_USER_WARNING);
			return false;
		}

		$paramsInput = $params[0];
		$paramsColumnKey = ($params[1] !== null) ? (string) $params[1] : null;

		$paramsIndexKey = null;
		if (isset($params[2])) {
			if (is_float($params[2]) || is_int($params[2])) {
				$paramsIndexKey = (int) $params[2];
			} else {
				$paramsIndexKey = (string) $params[2];
			}
		}

		$resultArray = array();

		foreach ($paramsInput as $row) {
			$key = $value = null;
			$keySet = $valueSet = false;

			if ($paramsIndexKey !== null && array_key_exists($paramsIndexKey, $row)) {
				$keySet = true;
				$key = (string) $row[$paramsIndexKey];
			}

			if ($paramsColumnKey === null) {
				$valueSet = true;
				$value = $row;
			} elseif (is_array($row) && array_key_exists($paramsColumnKey, $row)) {
				$valueSet = true;
				$value = $row[$paramsColumnKey];
			}

			if ($valueSet) {
				if ($keySet) {
					$resultArray[$key] = $value;
				} else {
					$resultArray[] = $value;
				}
			}

		}

		return $resultArray;
	}

}
