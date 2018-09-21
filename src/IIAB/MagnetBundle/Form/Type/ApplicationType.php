<?php

namespace IIAB\MagnetBundle\Form\Type;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use IIAB\MagnetBundle\Form\Constraints\ValidAddress;
use IIAB\MagnetBundle\Form\Constraints\ValidAge;

class ApplicationType extends AbstractType {

	/**
	 * @param FormBuilderInterface $builder
	 * @param array                $options
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

        $data = $options['data'];
		switch( $data['step'] ) {

			//If current student, show the small form.
			//If new student, show full form to capture all data.
			case 1:
				$phoneError = new NotBlank();
				$phoneError->message = 'form.error.phone.blank'; //'Phone number cannot be blank';

				if( $data['student_status'] == 'current' ) {

					$builder
						->add( 'stateID' , 'number' , array(
							'label' => 'form.field.stateID' , //'State ID Number (10 digit)',
							'required' => true,
							'constraints' => array(
								new NotBlank()
							),
							'attr' => array(
								'tabindex' => 2,
							),
							'help' => 'form.field.stateid.help' //'If you do not know your 10-digit state identification number, log into the I-Now Parent Portal to obtain this information. If you need assistance with I-Now access, contact your school office.',
						) )
						->add( 'dob' , 'birthday' , array(
							'years' => range( date( 'Y' , strtotime( '-20 years' ) ) , date( 'Y' ) ),
							'label' => 'form.field.birthday' , //'Date of Birth',
							'required' => true,
							'format' => 'MMMM d yyyy',
							'attr' => array(
							)
						) )

                        ->add( 'parentFirstName' , 'text' , array(
                            'label' => "form.field.parentFirstName",
                            'required' => true,
                            'constraints' => array(
                                new NotBlank()
                            ),
                            'attr' => array(
                                'tabindex' => 8,
                            )
                        ) )

                        ->add( 'parentLastName' , 'text' , array(
                            'label' => "form.field.parentLastName",
                            'required' => true,
                            'constraints' => array(
                                new NotBlank()
                            ),
                            'attr' => array(
                                'tabindex' => 9,
                            )
                        ) )

						->add( 'phoneNumber' , PhoneNumberType::class , array(
							'label' => 'form.field.phoneNumber' , //'Best contact phone number',
							'required' => true,
							'error_bubbling' => false,
							'constraints' => array(
								$phoneError,
								new Length( array( 'min' => 10 , 'max' => 10 ) )
							),
							'part_1' => array(
								'attr' => array(
									'tabindex' => 10,
								),
							),
							'part_2' => array(
								'attr' => array(
									'tabindex' => 11,
								)
							),
							'part_3' => array(
								'attr' => array(
									'tabindex' => 12,
								)
							),
						) )
						->add( 'alternateNumber' , PhoneNumberType::class , array(
							'label' => 'form.field.alternateNumber',
							'required' => false,
							'part_1' => array(
								'attr' => array(
									'tabindex' => 13,
								)
							),
							'part_2' => array(
								'attr' => array(
									'tabindex' => 14,
								)
							),
							'part_3' => array(
								'attr' => array(
									'tabindex' => 15,
								)
							),
						) )
						->add( 'parentEmail' , 'repeated' , array(
							'type' => 'email',
							'required' => false,
							'invalid_message' => 'form.field.parentEmail.error',
							'first_options' => array(
								'label' => "form.field.parentEmail",
								'help' => 'form.field.parentEmail.help',
								'attr' => array(
									'tabindex' => 16,
								)
							),
							'second_options' => array(
								'label' => "form.field.parentEmail.second",
								'attr' => array(
									'tabindex' => 17,
								)
							),
						) )

                        ->add( 'parentEmployment' , 'choice' , array(
                            'label' => 'form.field.parentEmployment',
                            'placeholder' => 'form.option.choose',
                            'choice_translation_domain' => true,
                            'choices' => [
                                'No' => 0,
                                'Yes' => 1
                            ],
                            'required' => true,
                            'attr' => array(
                                'tabindex' => 18,
                            )
                        ) )

                        ->add( 'parentEmployeeName' , 'text' , array(
                            'label' => "form.field.employeeName",
                            'required' => false,
                            'label_attr' => [ 'class' => ( empty( $data['parentEmployeeName'] ) ) ? 'hide' : null, ],
                            'attr' => array(
                                'tabindex' => 19,
                                'class' => ( empty( $data['parentEmployeeName'] ) ) ? 'hide' : null,
                            )
                        ) )

                        ->add( 'parentEmployeeLocation' , 'text' , array(
                            'label' => "form.field.employeeLocation",
                            'required' => false,
                            'label_attr' => [ 'class' => ( empty( $data['employeeLocation'] ) ) ? 'hide' : null, ],
                            'attr' => array(
                                'tabindex' => 20,
                                'class' => ( empty( $data['employeeLocation'] ) ) ? 'hide' : null,
                            )
                        ) )

                        // ->add( 'emergencyContact' , 'text' , array(
                        //     'label' => "form.field.emergencyContact",
                        //     'required' => true,
                        //     'attr' => array(
                        //         'tabindex' => 16,
                        //     )
                        // ) )

                        // ->add( 'emergencyContactRelationship' , 'text' , array(
                        //     'label' => "form.field.emergencyContactRelationship",
                        //     'required' => true,
                        //     'attr' => array(
                        //         'tabindex' => 17,
                        //     )
                        // ) )

                        // ->add( 'emergencyContactPhone' , PhoneNumberType::class , array(
                        //     'label' => 'form.field.emergencyContactPhone',
                        //     'required' => true,
                        //     'part_1' => array(
                        //         'attr' => array(
                        //             'tabindex' => 18,
                        //         )
                        //     ),
                        //     'part_2' => array(
                        //         'attr' => array(
                        //             'tabindex' => 19,
                        //         )
                        //     ),
                        //     'part_3' => array(
                        //         'attr' => array(
                        //             'tabindex' => 20,
                        //         )
                        //     ),
                        // ) )

						->add( 'step' , 'hidden' )
						->add( 'look_up_student' , 'submit' , array(
							'label' => 'form.submit',
							'attr' => array(
								'class' => 'right radius noMarginBottom',
								'tabindex' => 21,
							),
						) );
					;
				} else {
                    $grades = array( 'K' => '00' );
					foreach( range( 1 , 12 , 1) as $grade ) {
						$grades[ $grade ] = sprintf( '%1$02d' , $grade );
					}
					$currentGrades = $grades;
					array_pop( $currentGrades );

                    $grades = array();
					foreach( range( 1 , 12 , 1) as $grade ) {
						$grades[ $grade ] = sprintf( '%1$02d' , $grade );
					}

					$builder
						->add( 'first_name' , 'text' , array(
							'label' => "form.field.first_name",
							'required' => true,
							'constraints' => array(
								new NotBlank()
							),
							'attr' => array(
								'tabindex' => 1,
							)
						) )
						->add( 'last_name' , 'text' , array(
							'label' => 'form.field.last_name',
							'required' => true,
							'constraints' => array(
								new NotBlank()
							),
							'attr' => array(
								'tabindex' => 2,
							)
						) )
						->add( 'dob' , 'birthday' , array(
							'years' => range( date( 'Y' , strtotime( '-20 years' ) ) , date( 'Y' ) ),
							'label' => 'form.field.birthday',
							'required' => true,
							'format' => 'MMMM d yyyy',
							'constraints' => array(
								new ValidAge()
							),
							'attr' => array(
							)
						) )

						->add( 'race' , 'entity' , array(
							'class' => 'IIABMagnetBundle:Race',
							'choice_translation_domain' => true,
							'label' => 'form.field.race',
							'query_builder' => function ( EntityRepository $er ) {
								return $er->createQueryBuilder( 'r' )
                                    ->addSelect('(CASE WHEN r.reportAsNoAnswer = 1 THEN 1 ELSE 0 END) AS HIDDEN special_order ')
									->orderBy( 'special_order, r.race' , 'ASC' );
							},

							'placeholder' => 'form.option.choose',
							'constraints' => array(
								new NotBlank()
							),
							'required' => true,
							'attr' => array(
								'tabindex' => 9,
							)
						) )

                        ->add( 'gender' , 'choice' , array(
                            'label' => 'form.field.gender',
                            'placeholder' => 'form.option.choose',
                            'choice_translation_domain' => true,
                            'choices' => [
                                'Male' => 'Male',
                                'Female' => 'Female',
                                'Choose Not to Answer' => 'Choose Not to Answer'
                            ],
                            'required' => true,
                            'constraints' => array(
                                new NotBlank()
                            ),
                            'attr' => array(
                                'tabindex' => 10,
                            )
                        ) )


						->add( 'current_school' , 'text' , array(
							'label' => 'form.field.current_school',
							'required' => true,
							'constraints' => array(
								new NotBlank()
							),
							'attr' => array(
								'tabindex' => 11,
							)
						) )
						->add( 'current_grade' , 'choice' , array(
							'label' => 'form.field.current_grade',
							'placeholder' => 'form.option.choose',
							'choices' => $currentGrades,
							'required' => true,
							'constraints' => array(
								new NotBlank()
							),
							'attr' => array(
								'tabindex' => 12,
							)
						) )
						->add( 'next_grade' , 'choice' , array(
							'label' => 'form.field.next_grade',
							'placeholder' => 'form.option.choose',
							'choices' => $grades,
							'constraints' => array(
								new NotBlank()
							),
							'required' => true,
							'attr' => array(
								'tabindex' => 13,
							)
						) )
						->add( 'address' , 'text' , array(
							'label' => 'form.field.address',
							'constraints' => array(
								new NotBlank(),
								new ValidAddress()
							),
							'attr' => array(
								'placeholder' => 'form.field.address.placeholder',
								'tabindex' => 14,
							),
							'required' => true,
						) )
						->add( 'city' , 'text' , array(
							'label' => 'form.field.city',
							'constraints' => array(
								new NotBlank()
							),
							'attr' => array(
								'placeholder' => 'form.field.city',
								'tabindex' => 15,
							),
							'required' => true,
						) )
						->add( 'state' , 'choice' , array(
						    'label' => 'form.field.state',
                            'data' => ( isset(  $data['state'] ) ) ?  $data['state'] : 'AL',
                            'choices' => array_flip( MYPICK_CONFIG['state_provinces'] ),
                            'attr' => array(
                                    'tabindex' => 16,
                                )
						) )

						->add( 'zip' , 'number' , array(
							'label' => 'form.field.zip',
							'constraints' => array(
								new NotBlank()
							),
							'attr' => array(
								'placeholder' => 'form.field.zip',
								'maxlength' => 5,
								'tabindex' => 17,
							),
							'required' => true,
						) )

                        ->add( 'phoneNumber' , PhoneNumberType::class , array(
                            'compound' => true,
							'label' => 'form.field.phoneNumber',
							'required' => true,
							'error_bubbling' => false,
							'constraints' => array(
								$phoneError,
								new Length( array( 'min' => 10 , 'max' => 10 ) )
							),
                            'part_1' => array(
                                'attr' => array(
                                    'tabindex' => 18,
                                ),
                            ),
                            'part_2' => array(
                                'attr' => array(
                                    'tabindex' => 19,
                                )
                            ),
                            'part_3' => array(
                                'attr' => array(
                                    'tabindex' => 20,
                                )
                            ),
						) )
						->add( 'alternateNumber' , PhoneNumberType::class , array(
                            'compound' => true,
							'label' => 'form.field.alternateNumber',
							'required' => false,
							'part_1' => array(
                                'attr' => array(
                                    'tabindex' => 21,
                                ),
                            ),
                            'part_2' => array(
                                'attr' => array(
                                    'tabindex' => 22,
                                )
                            ),
                            'part_3' => array(
                                'attr' => array(
                                    'tabindex' => 23,
                                )
                            ),
						) )

                        ->add( 'parentFirstName' , 'text' , array(
                            'label' => "form.field.parentFirstName",
                            'required' => true,
                            'attr' => array(
                                'tabindex' => 24,
                            )
                        ) )

                        ->add( 'parentLastName' , 'text' , array(
                            'label' => "form.field.parentLastName",
                            'required' => true,
                            'attr' => array(
                                'tabindex' => 25,
                            )
                        ) )

						->add( 'parentEmail' , 'repeated' , array(
							'type' => 'email',
							'invalid_message' => 'form.field.parentEmail.error',
							'first_options' => array(
                                'help' => 'form.field.parentEmail.help',
								'label' => "form.field.parentEmail",
								'attr' => array(
									'tabindex' => 26,
								)
							),
							'second_options' => array(
								'label' => "form.field.parentEmail.second",
								'attr' => array(
									'tabindex' => 27,
								)
							),
							'required' => false,
						) )
                        // ->add( 'emergencyContact' , 'text' , array(
                        //     'label' => "form.field.emergencyContact",
                        //     'required' => true,
                        //     'attr' => array(
                        //         'tabindex' => 24,
                        //     )
                        // ) )

                        // ->add( 'emergencyContactRelationship' , 'text' , array(
                        //     'label' => "form.field.emergencyContactRelationship",
                        //     'required' => true,
                        //     'attr' => array(
                        //         'tabindex' => 25,
                        //     )
                        // ) )

                        // ->add( 'emergencyContactPhone', PhoneNumberType::class , array(
                        //     'label' => 'form.field.emergencyContactPhone',
                        //     'required' => true,
                        //     'part_1' => array(
                        //         'attr' => array(
                        //             'tabindex' => 26,
                        //         )
                        //     ),
                        //     'part_2' => array(
                        //         'attr' => array(
                        //             'tabindex' => 27,
                        //         )
                        //     ),
                        //     'part_3' => array(
                        //         'attr' => array(
                        //             'tabindex' => 28,
                        //         )
                        //     ),
                        // ) )

                        ->add( 'parentEmployment' , 'choice' , array(
                            'label' => 'form.field.parentEmployment',
                            'placeholder' => 'form.option.choose',
                            'choice_translation_domain' => true,
                            'choices' => [
                                'No' => 0,
                                'Yes' => 1
                            ],
                            'required' => true,
                            'attr' => array(
                                'tabindex' => 28,
                            )
                        ) )

                        ->add( 'parentEmployeeName' , 'text' , array(
                            'label' => "form.field.employeeName",
                            'required' => false,
                            'label_attr' => [ 'class' => ( empty( $data['parentEmployment'] ) ) ? 'hide' : null, ],
                            'attr' => array(
                                'tabindex' => 29,
                                'class' => ( empty( $data['parentEmployment'] ) ) ? 'hide' : null,
                            )
                        ) )

                        ->add( 'parentEmployeeLocation' , 'text' , array(
                            'label' => "form.field.employeeLocation",
                            'required' => false,
                            'label_attr' => [ 'class' => ( empty( $data['parentLocation'] ) ) ? 'hide' : null, ],
                            'attr' => array(
                                'tabindex' => 30,
                                'class' => ( empty( $data['parentLocation'] ) ) ? 'hide' : null,
                            )
                        ) )


                        ->add( 'confirm_status' , 'checkbox' , array(
                            'label' => 'form.field.confirm_status',
                            'required' => true,
                            'constraints' => array(
                                new NotBlank()
                            ),
                            'attr' => array(
                                    'tabindex' => 31,
                                )
                        ) )

						->add( 'step' , 'hidden' )
						->add( 'submit' , 'submit' , array(
							'label' => 'form.submit',
							'attr' => array(
								'class' => 'right radius noMarginBottom',
								'tabindex' => 32
							)
						) );
					;
				}

				break;

			//Confirmation screen to ensure all the data is correct.
			case 2:

				$submission = ( isset( $data['submission'] ) ) ? $submission = $data['submission'] : false;

				if(
                    ( isset( $data['open_enrollment_selector'] ) && $data['open_enrollment_selector'] ) ||
                    ( isset( $data['isAdmin'] ) && $data['isAdmin'] == true )
                ) {
					$builder->add( 'openEnrollment' , 'entity' , array(
						'label' => 'Enrollment Period' ,
						'required' => true ,
						'placeholder' => 'Choose an Enrollment' ,
						'class' => 'IIABMagnetBundle:OpenEnrollment'
					) );
				}

                if( intval( $data['next_grade'] ) >= 9
                    && empty( $data['studentEmail'] )
                    && $data['student_status'] == 'current'
                ){
                    $builder
                        ->add( 'studentEmail' , 'repeated' , array(
                            'type' => 'email',
                            'invalid_message' => 'form.field.parentEmail.error',
                            'first_options' => array(
                                'label' => "form.field.studentEmail",
                                'help' => 'form.field.studentEmail.help',
                            ),
                            'second_options' => array(
                                'label' => "form.field.studentEmail.second",
                            ),
                        ) );
                }


				// $builder
				// 	->add( 'special_accommodations' , 'choice' , array(
				// 		'label' => 'form.field.special_accommodations',
				// 		'placeholder' => 'form.option.choose',
				// 		'choices' => array_flip( array(
				// 			1 => 'form.field.sibling.choice.yes' ,
				// 			0 => 'form.field.sibling.choice.no'
				// 		)) ,
				// 		'empty_data' => 0 ,
				// 		'required' => false
				// 	) );

                    if( $data['student_status'] == 'current' ) {
                        $builder
                            ->add( 'confirm_parent' , 'checkbox' , array(
                                'label' => 'form.field.confirm_parent',
                                'constraints' => array(
                                    new NotBlank()
                                )
                            ) );
                    } else {
                        $builder
                            ->add( 'confirm_parent' , 'checkbox' , array(
                                'label' => 'form.field.confirm_parent',
                                'constraints' => array(
                                    new NotBlank()
                                )
                            ) );
                    }


                $builder
					->add( 'confirm_correct' , 'checkbox' , array(
						'label' => 'form.field.confirm_correct',
						'constraints' => array(
							new NotBlank()
						)
					) )
					->add( 'info_correct' , 'submit' , array(
						'label' => 'form.field.info_correct',
						'attr' => array(
							'class' => 'right radius noMarginBottom',
						),
					) )
					->add( 'step' , 'hidden' )
					->add( 'info_incorrect' , 'submit' , array(
						'label' => 'form.field.info_incorrect',
						'validation_groups' => false,
						'attr' => array(
							'class' => 'left alert radius noMarginBottom',
						),
					) )
				;
				break;

			//Selecting the specifics schools they want to try and get into.
			case 3:

				$submission = ( isset( $data['submission'] ) ) ? $submission = $data['submission'] : false;

								$schoolChoiceData = $options['data'];
								$schoolChoiceData['schools'] = $data['schools'];

				//Only show school options if there is choice.
				if( count( $data['schools'] ) > 0 ) {
					$builder->add( 'first_choice' ,
                        SchoolChoiceType::class ,
                        array(
						  'label' => 'form.field.first_choice',
						  'required' => true,
                          'data' => $schoolChoiceData,
					) );
				}

				//Only show school options if there are two choices.
				if( count( $data['schools'] ) > 1 ) {
					$builder->add( 'second_choice' , SchoolChoiceType::class,
                        array(
						  'label' => 'form.field.second_choice',
						  'required' => false,
                          'data' => $schoolChoiceData,
					) );
				}

                //Only show school options if there are three choices.
                if( $data['next_grade'] >= 6) {
                    if (count($data['schools']) > 2) {
                        $builder->add('third_choice', SchoolChoiceType::class, array(
                            'label' => 'form.field.third_choice',
                            'required' => false,
                            'data' => $schoolChoiceData,
                        ));
                    }
                }

				$builder->add( 'confirm_selections' , 'checkbox' , array(
					'label' => 'form.field.confirm_selections',
					'required' => true,
					'constraints' => array(
						new NotBlank()
					),
				) )
					->add( 'proceed_with_choices' , 'submit' , array(
						'label' => 'form.field.proceed_with_choices',
						'attr' => array(
							'class' => 'right radius medium-4 small-12 noMarginBottom',
						),
					) )
					->add( 'exit_without_savings' , 'button' , array(
						'label' => 'form.field.exit_without_savings',
						'attr' => array(
							'class' => 'left alert radius medium-4 small-12 noMarginBottom',
							'onclick' => 'window.location.href="../exit-application/";'
						),
					) )
				;
				break;
		}

	}

    /**
     * {@inheritdoc}
     */
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
        $resolver->setDefaults( array(
            'emLookup' => null,
        ) );

        $resolver->setAllowedTypes('emLookup', 'Doctrine\ORM\EntityManager');
    }

	/**
	 * @return string
	 */
	public function getName() {
		return 'application';
	}
}
