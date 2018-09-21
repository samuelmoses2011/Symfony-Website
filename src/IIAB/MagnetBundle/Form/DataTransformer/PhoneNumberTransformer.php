<?php
/**
 * Company: Image In A Box
 * Date: 1/12/15
 * Time: 10:46 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class PhoneNumberTransformer implements DataTransformerInterface {

	/**
	 * Transforms a value from the original representation to a transformed representation.
	 *
	 * This method is called on two occasions inside a form field:
	 *
	 * 1. When the form field is initialized with the data attached from the datasource (object or array).
	 * 2. When data from a request is submitted using {@link Form::submit()} to transform the new input data
	 *    back into the renderable format. For example if you have a date field and submit '2009-10-10'
	 *    you might accept this value because its easily parsed, but the transformer still writes back
	 *    "2009/10/10" onto the form field (for further displaying or other purposes).
	 *
	 * This method must be able to deal with empty values. Usually this will
	 * be NULL, but depending on your implementation other empty values are
	 * possible as well (such as empty strings). The reasoning behind this is
	 * that value transformers must be chainable. If the transform() method
	 * of the first value transformer outputs NULL, the second value transformer
	 * must be able to process that value.
	 *
	 * By convention, transform() should return an empty string if NULL is
	 * passed.
	 *
	 * @param mixed $value The value in the original representation
	 *
	 * @return mixed The value in the transformed representation
	 *
	 * @throws TransformationFailedException When the transformation fails.
	 */
	public function transform( $value ) {

		$value = trim( $value );

		if( null === $value || empty( $value ) ) {
			return;
		}

		$returnArray = array(
			'part_1' => is_numeric( substr( $value , 0 , 3 ) ) ? substr( $value , 0 , 3 ) : '' ,
			'part_2' => is_numeric( substr( $value , 3 , 3 ) ) ? substr( $value , 3 , 3 ) : '' ,
			'part_3' => is_numeric( substr( $value , 6 , 4 ) ) ? substr( $value , 6 , 4 ) : '' ,
		);
		return $returnArray;
	}

	/**
	 * Transforms a value from the transformed representation to its original
	 * representation.
	 *
	 * This method is called when {@link Form::submit()} is called to transform the requests tainted data
	 * into an acceptable format for your data processing/model layer.
	 *
	 * This method must be able to deal with empty values. Usually this will
	 * be an empty string, but depending on your implementation other empty
	 * values are possible as well (such as empty strings). The reasoning behind
	 * this is that value transformers must be chainable. If the
	 * reverseTransform() method of the first value transformer outputs an
	 * empty string, the second value transformer must be able to process that
	 * value.
	 *
	 * By convention, reverseTransform() should return NULL if an empty string
	 * is passed.
	 *
	 * @param mixed $value The value in the transformed representation
	 *
	 * @return mixed The value in the original representation
	 *
	 * @throws TransformationFailedException When the transformation fails.
	 */
	public function reverseTransform( $value ) {

		if( null === $value ) {
			return "";
		}

		if( is_array( $value ) ) {
			$number = implode( '' , $value );
		} else {
			$number = '';
		}
		return $number;
	}
}