<?php

namespace IIAB\MagnetBundle\Form\Validators;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManager;
use IIAB\MagnetBundle\Service\ValidateSiblingService;

class ValidAgeValidator extends ConstraintValidator {

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

        $openEnrollment = $this->emLookup->getRepository( 'IIABMagnetBundle:OpenEnrollment' )->findByDate( new \DateTime );
        $openEnrollment = ($openEnrollment ) ? $openEnrollment[0] : $this->emLookup->getRepository('IIABMagnetBundle:OpenEnrollment')->findOneBy( [] , ['endingDate' => 'DESC'] );

        $placement = $this->emLookup->getRepository( 'IIABMagnetBundle:Placement' )->findOneBy( [ 'openEnrollment' => $openEnrollment ] );

        switch( $data['next_grade'] ){
            case '99':
                $grade = 'pre-k';
                $cutOff = $placement->getPreKDateCutOff();
                break;
            case '00':
                $grade = 'kindergarten';
                $cutOff = $placement->getKindergartenDateCutOff();
                break;
            case '01':
                $grade = '1st grade';
                $cutOff = $placement->getfirstGradeDateCutOff();
                break;
            default :
                $cutOff = null;
        }

        if( isset($cutOff) && $data['dob'] > $cutOff ){
            $this->context->buildViolation( $constraint->message )
                ->setParameter( '%grade%', $grade )
                ->setParameter( '%date%', $cutOff->format( 'F d, Y' ) )
                ->setParameter( '%first_name%', $data['first_name'])
                ->setParameter( '%last_name%', $data['last_name'])
                ->addViolation();
        }
    }
}