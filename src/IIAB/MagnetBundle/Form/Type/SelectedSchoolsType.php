<?php
/**
 * Company: Image In A Box
 * Date: 3/10/15
 * Time: 11:37 AM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Type;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class SelectedSchoolsType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder->add( 'active' , 'choice' , [
			'choices' => array_flip( [ 1 => 'Import from Previous Open Enrollment', 0 => 'Do Not Import' ] ),
			'expanded' => false ,
			'multiple' => false ,
			'required' => true
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
        $resolver->setDefaults( [
			'data_class' => 'IIAB\MagnetBundle\Entity\MagnetSchool' ,
		] );

        $resolver->setAllowedTypes('data_class', 'string');
    }

	public function getName() {

		return 'placement';
	}
}