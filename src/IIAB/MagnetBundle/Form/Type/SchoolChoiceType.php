<?php
/**
 * Company: Image In A Box
 * Date: 12/23/14
 * Time: 7:29 PM
 * Copyright: 2014
 */

namespace IIAB\MagnetBundle\Form\Type;

use IIAB\MagnetBundle\Form\Constraints\ValidSibling;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class SchoolChoiceType extends AbstractType {

	/** @var array */
	private $schools;

    /** @var array */
    private $foci;

    /** @var array */
    private $foci_experience;

    /** @var array */
    private $exclusions;

    /** @var array */
    private $focus_labels;

    /** @var integer */
    private $next_grade;

	public function buildForm( FormBuilderInterface $builder , array $options ) {

        if( !isset( $options['data']['schools'] ) ) {
            throw new \Exception( 'No schools were provided, this is required to show the next available schools.' , 500 );
        } else {
            $this->schools = array_flip( $options['data']['schools'] ); 
        }

        $this->next_grade = $options['data']['next_grade'];
        $this->foci = json_decode( $options['data']['foci'] );
        $this->exclusions = json_decode( $options['data']['exclusions'] );
        $this->focus_extras =  json_decode( $options['data']['focus_extras'] );
        $this->focus_labels =  json_decode( $options['data']['focus_labels'] );


		$submission = ( isset( $options['data']['submission'] ) ) ? $submission = $options['data']['submission'] : false;

        $builder
            ->add( 'school' , 'choice' , array(
                'choices' => $this->schools ,
                'placeholder' => 'form.option.choose' ,
                'label' => 'form.field.program' ,
                'required' =>  $builder->getRequired() ,
                'constraints' => ( $builder->getRequired() ) ? new NotBlank() : null,
                'attr' => [
                    'data-foci' => json_encode( $this->foci ),
                    'data-foci-experience' => json_encode( $this->foci_experience ),
                    'data-exclusions' => json_encode( $this->exclusions ),
                    'data-focus-extras' => json_encode( $this->focus_extras ),
                    'data-focus-labels' => json_encode( $this->focus_labels ),
                    'onchange' => 'check_exclusions(this); update_foci(this);',
                    'onfocus' => 'double_check_exclusions(this);'
                ],
            ) );

        if( count( $this->foci ) ) {

            if( $this->next_grade > 1 ) {
                $choices = ($this->next_grade > 8) ? ['first', 'second', 'third'] : ['first', 'second'];

                foreach ($choices as $choice_number) {
                    $builder
                        ->add($choice_number . '_choice_focus', 'choice', array(
                            'choices' => [],
                            'placeholder' => 'form.option.choose',
                            'label' => 'form.field.program.' . $choice_number . '.focus',
                            'required' => false,
                            'attr' => [
                                'onchange' => 'check_focus_conflicts( this );'
                            ]
                        ));
                    $builder->get($choice_number . '_choice_focus')->resetViewTransformers();

                    if (count($this->focus_extras)) {

                        for ($i = 1; $i <= 3; $i++) {
                            $builder
                                ->add($choice_number . '_choice_focus_extra_' . $i, 'text', array(
                                    'label' => ' ',
                                    'required' => false,
                                    'attr' => [
                                        'row_style' => 'display:none;'
                                    ],
                                ));
                            $builder->get($choice_number . '_choice_focus_extra_' . $i)->resetViewTransformers();
                        }
                    }
                }
            }
        }
	}

	public function getName() {

		return 'school_choices';
	}
}