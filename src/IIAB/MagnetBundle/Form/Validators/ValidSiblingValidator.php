<?php

namespace IIAB\MagnetBundle\Form\Validators;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManager;
use IIAB\MagnetBundle\Service\ValidateSiblingService;

class ValidSiblingValidator extends ConstraintValidator {

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

    public function validate($value, Constraint $constraint) {

        // Get submitted form data
        $data = $this->context->getRoot()->getData();

        /* Need to determine the parent element's name */
        // step 1: Break apart the property path into an array of parent child elements
        $propertyPath = explode('.', $this->context->getPropertyPath() );

        // step 2: find the key for any siblingID elements and select the last occurrence
        $key = array_keys( preg_grep( '/.*siblingID.*/', $propertyPath ) );
        $key = array_pop( $key );

        // step 3: select the element before the sibling id this is the parent element
        $parentPath = $propertyPath[ $key - 1 ];

        // step 4: extract parent name from [ brackets ] if needed
        preg_match( '/\[(.*)\]$/', $parentPath, $matches );
        $parentName = ( $matches ) ? $matches[ 1 ] : $parentPath;

        // step 5:
        $siblingIdRequired = $data[ $parentName ][ 'sibling' ];

        if($siblingIdRequired) {
            $validateSiblingService = new ValidateSiblingService( $this->emLookup );
            if( !$validateSiblingService->validateSiblingAttendsSchool( $value, $data[ $parentName ][ 'school' ] ) ) {
                $this->context->buildViolation( $constraint->message )
                    ->setParameter( '%string%', $value )
                    ->addViolation();
            }
        }
    }
}