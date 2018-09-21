<?php
/**
 * Company: Image In A Box
 * Date: 1/12/15
 * Time: 9:16 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;

use IIAB\MagnetBundle\Form\DataTransformer\PhoneNumberTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class PhoneNumberType extends AbstractType {

	public function buildForm( FormBuilderInterface $builder , array $options ) {

		if( !empty( $options['part_1']['attr']['tabindex'] ) ){

			$options['part_2']['attr']['tabindex'] = ($options['part_2']['attr']['tabindex']) ? $options['part_2']['attr']['tabindex'] : $options['part_1']['attr']['tabindex'];

			$options['part_3']['attr']['tabindex'] = ($options['part_3']['attr']['tabindex']) ? $options['part_3']['attr']['tabindex'] : $options['part_1']['attr']['tabindex'];
		}

		$builder
			->add( 'part_1' , 'text' , 
				!empty( $options['part_1'] ) ? $options['part_1'] : null
			)
			->add( 'part_2' , 'text' , 
				!empty( $options['part_2'] ) ? $options['part_2'] : null
			)
			->add( 'part_3' , 'text' , 
				!empty( $options['part_3'] ) ? $options['part_3'] : null
			);

		$builder->addModelTransformer( new PhoneNumberTransformer() );
	}

	public function finishView( FormView $view , FormInterface $form , array $options ) {

		$pattern = '{{ part_1 }}{{ part_2 }}{{ part_3 }}';

		$view->vars['phone_number_pattern'] = $pattern;
	}

	/**
     * {@inheritdoc}
     */
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {

		$resolver->setDefaults( array(
			'part_1' => array(
				'attr' => array()
			) ,
			'part_2' => array(
				'attr' => array()
			) ,
			'part_3' => array(
				'attr' => array()
			) ,
			'compound' => true ,
			'cascade_validation' => true,
		) );

        $resolver->setAllowedTypes('part_1', 'array');
        $resolver->setAllowedTypes('part_2', 'array');
        $resolver->setAllowedTypes('part_3', 'array');
    }



	public function getParent() {

		return 'text';
	}

	/**
	 * Returns the name of this type.
	 *
	 * @return string The name of this type
	 */
	public function getName() {

		return 'phone_number';
	}


}