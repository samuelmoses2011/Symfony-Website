<?php
/**
 * Company: Image In A Box
 * Date: 2/16/15
 * Time: 5:11 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Validator\Constraints\NotBlank;

class OfferedType extends AbstractType {

    public function buildForm( FormBuilderInterface $builder , array $options ) {

        $offer = $builder->getData();

        $builder
            ->add( 'decline_offer' , 'submit' , array(
                'label' => 'Decline <br />Offer',
                'attr' => array(
                    'class' => 'left alert radius medium-4 small-12 noMarginBottom',
                    'style' => 'border: 12px solid #FFFFFF;'
                ),
            ) );

        if( count( $offer->getSubmission()->getWaitList() ) ) {

            $submission = $offer->getSubmission();
            $waitlist = $submission->getWaitList();

            $waiting_school_ids = [];
            if( !empty( $waitlist ) ){
                foreach( $waitlist as $waiting ){
                    $waiting_school_ids[] = $waiting->getChoiceSchool()->getId();
                }
            }

            $firstChoice = ( !empty( $submission->getFirstChoice() ) && in_array( $submission->getFirstChoice()->getId(), $waiting_school_ids ) ) ? $submission->getFirstChoice()->getName() : '';
            $secondChoice = ( !empty( $submission->getSecondChoice() ) && in_array( $submission->getSecondChoice()->getId(), $waiting_school_ids ) ) ? $submission->getSecondChoice()->getName() : '';

            $label = 'Decline Offer/Choose to be Waitlisted for '. $firstChoice;
            $label .= ( $firstChoice && $secondChoice ) ? ' and ' : '';
            $label .= $secondChoice;

            $builder
                ->add('decline_and_waitlist', 'submit', array(
                    'label' => $label,
                    'attr' => array(
                        'class' => 'right radius medium-4 small-12 noMarginBottom',
                        'style' => 'background-color: #ffbf00; border: 12px solid #FFFFFF;',
                    ),
                ));
        }

        $builder
            ->
            add('accept_offer', 'submit', array(
                'label' => 'Accept <br />Offer',
                'attr' => array(
                    'class' => 'right radius medium-4 small-12 noMarginBottom',
                    'style' => 'background-color: #009900; border: 12px solid #FFFFFF;',
                ),
            ));

    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions( OptionsResolverInterface $resolver ) {

        $resolver->setDefaults( [
            'data_class' => 'IIAB\MagnetBundle\Entity\Offered' ,
        ] );
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName() {

        return 'offered';
    }


}