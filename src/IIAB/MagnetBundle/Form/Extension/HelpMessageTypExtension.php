<?php
/**
 * Company: Image In A Box
 * Date: 1/5/15
 * Time: 2:12 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class HelpMessageTypExtension extends AbstractTypeExtension {

	public function buildForm( FormBuilderInterface $builder , array $options ) {

		$builder->setAttribute( 'help' , $options['help'] );
	}

	public function buildView( FormView $view , FormInterface $form , array $options ) {

		$view->vars['help'] = $options['help'];
	}

	/**
     * {@inheritdoc}
     */
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'help' => '',
        ));

        $resolver->setAllowedTypes('help', 'string');
    }
 
	public function setDefaultOptions( OptionsResolverInterface $resolver ) {

		$resolver->setDefaults( array(
			'help' => null ,
		) );
	}

	public function getExtendedType() {

		return 'form';
	}

}