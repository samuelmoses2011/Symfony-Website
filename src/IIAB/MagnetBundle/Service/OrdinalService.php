<?php
/**
 * Company: Lean Frog
 * Date: 5/26/2017
 * Copyright: 2017
 */

namespace IIAB\MagnetBundle\Service;

class OrdinalService{

    private $ordinal = [
        1 => '1st',
        2 => '2nd',
        3 => '3rd',
        4 => '4th',
        5 => '5th',
        6 => '6th',
        7 => '7th',
        8 => '8th',
        9 => '9th'
    ];

    private $ordinal_text = [
        1 => 'first',
        2 => 'second',
        3 => 'third',
        4 => 'fourth',
        5 => 'fifth',
        6 => 'sixth',
        7 => 'seventh',
        8 => 'eighth',
        9 => 'ninth'
    ];

    public function getIndex( $input ){

        if( is_integer( $input ) ){
            if( $input < 1 || $input > 9 ){
                return false;
            }
            return $input;
        }

        if( in_array( $input, $this->ordinal ) ){
            return array_search( $input, $this->ordinal );
        }

        if( in_array( $input, $this->ordinal_text ) ){
            return array_search( $input, $this->ordinal_text );
        }

        return false;
    }


    public function getOrdinal( $input ){

        $index = $this->getIndex( $input );
        return ( $index != false ) ? $this->ordinal[ $index ] : false;
    }

    public function getOrdinalText( $input ){

        $index = $this->getIndex( $input );
        return ( $index != false ) ? $this->ordinal_text[ $index ] : false;
    }

}