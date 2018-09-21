<?php

namespace IIAB\MagnetBundle\Form\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ValidSibling extends Constraint
{
    public $message = 'This sibling "%string%" must be currently enrolled in the magnet program.  This does not include siblings who are applying for placement in this program.';

    public function validatedBy() {
        return 'validate_sibling';
    }
}