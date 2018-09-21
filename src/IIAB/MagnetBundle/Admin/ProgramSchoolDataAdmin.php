<?php
/**
 * Company: Image In A Box
 * Date: 1/6/15
 * Time: 2:41 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Form\FormMapper;

class ProgramSchoolDataAdmin extends AbstractAdmin {

	protected function configureFormFields( FormMapper $form ) {

	    $parent = false;
        if($this->hasParentFieldDescription()) {
            $parent = $this->getParentFieldDescription()->getAdmin()->getSubject();
        }

        $object = $this->getSubject();

        $onchange = "
            var id = this.id;

            var base_id = id.split('metaKey')[0];

            var value_field = document.getElementById( base_id + 'metaValue' );
            var school_field = document.getElementById( base_id + 'excludeSchool' );

            var extras = new Array(
                document.getElementById( base_id + 'extraData_1' ),
                document.getElementById( base_id + 'extraData_2' )
//                document.getElementById( base_id + 'extraData_3' )
            );

            var selection = this.value;

            if(selection == 'exclude'){
                value_field.style.display = 'none';
                value_field.value = '';

                extras.forEach( function( extra_field ){
                    extra_field.value = '';

                    var nodes = Array.prototype.slice.call(extra_field.parentElement.childNodes);
                    nodes.forEach( function( extra_node ){
                        if( typeof(extra_node) == 'object' && typeof(extra_node.tagName) != 'undefined'){
                            extra_node.style.display = 'none';
                        }
                    });
                });

                school_field.style.display = 'block';
                var schools = Array.prototype.slice.call(school_field.parentElement.childNodes);
                schools.forEach( function( school_node ){
                    if( typeof(school_node) == 'object' && typeof(school_node.tagName) != 'undefined'){
                        school_node.style.display = '';
                    }
                });
            } else {

                extras.forEach( function( extra_field ){
                    extra_field.value = '';

                    var nodes = Array.prototype.slice.call(extra_field.parentElement.childNodes);
                    nodes.forEach( function( extra_node ){
                        if( typeof(extra_node) == 'object' && typeof(extra_node.tagName) != 'undefined'){
                            extra_node.style.display = ( selection == 'focus' ) ? '' : 'none';
                        }
                    });
                });

                if ( (value_field.value * 1 ) == value_field.value ){
                    value_field.value = '';
                }
                var schools = Array.prototype.slice.call(school_field.parentElement.childNodes);
                schools.forEach( function( school_node ){
                    if( typeof(school_node) == 'object' && typeof(school_node.tagName) != 'undefined'){
                        school_node.style.display = 'none';
                    }
                });
            }
        ";
        $onchange = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $onchange));

        $form
            ->add('metaKey', 'choice', array(
                'label' => 'Key',
                'placeholder' => 'Choose an option',
                'choices' => array_flip([
                    'focus' => 'SubChoice',
                    'exclude' => 'Exclude School'
                ]),
                'attr' => ['onchange' => $onchange],
            ))
            ->add('metaValue', null, array(
                'label' => 'Value',
                'attr' => [
                    'style' => ($object && $object->getMetaKey() == 'exclude') ? 'display: none;' : null,
                ]
            ));


        if( $parent ){

            switch( get_class($parent ) ){
                case 'IIAB\MagnetBundle\Entity\Program':
                    $choice_objects = $parent->getOpenEnrollment()->getPrograms();
                break;

                case '':
                    $choice_objects = $parent->getMagnetSchools();
                break;
            };

            $choices = [];
            if( isset( $choice_objects ) ){
                foreach( $choice_objects as $choice ){
                    if( $choice->getId() != $parent->getId() ) {
                        $choices[$choice->getId()] = $choice->getName();
                    }
                }
            }

            $onchange = "

                var id = this.id;

                var base_id = id.split('excludeSchool')[0];

                var value_field = document.getElementById( base_id + 'metaValue' );

                value_field.value = this.value;
            ";
            $onchange = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $onchange));


            $form
                ->add('excludeSchool', 'choice', array(
                    'placeholder' => 'Choose School to Exclude',
                    'choices' => array_flip( $choices ),
                    'required' => false,
                    'mapped' => false,
                    'data' => ($object) ? $object->getMetaValue() : null,

                    'attr' => [
                        'onchange' => $onchange,
                        'style' => ($object && $object->getMetaKey() != 'exclude') ? 'display : none' : null,
                    ]
                ));

            $onchange = "
                var id = this.id;
                var selection = this.value;
                var base_id = id.split('extraData')[0];

                var extras = new Array(
                    document.getElementById( base_id + 'extraData_1' ),
                    document.getElementById( base_id + 'extraData_2' ),
                    document.getElementById( base_id + 'extraData_3' )
                );

                extras.forEach( function( extra_field ){
                    if( extra_field.id != id ){

                        extra_field.childNodes.forEach(function (option) {
                            option.disabled = (option.value && option.value == selection) ? true : false;
                        });
                    }
                });
            ";
            $onchange = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $onchange));


            $form
                ->add('extraData_1', 'choice', array(
                    'label' => 'Instrument',
                    'placeholder' => 'Not Required',
                    'choices' => array_flip([
                        'instrument' => 'Instrument'
                    ]),
                    'required' => false,
                    'data' => ($object) ? $object->getExtraData1() : null,
                    'attr' => [
                        'style' => ($object && $object->getMetaKey() != 'focus') ? 'display : none' : null,
                        'onchange' => $onchange,
                    ]
                ))

                ->add('extraData_2', 'choice', array(
                    'label' => 'Experience',
                    'placeholder' => 'Not Required',
                    'choices' => array_flip([
                        'experience' => 'Experience'
                    ]),
                    'required' => false,
                    'data' => ($object) ? $object->getExtraData2() : null,
                    'attr' => [
                        'style' => ($object && $object->getMetaKey() != 'focus') ? 'display : none' : null,
                        'onchange' => $onchange,
                    ]
                ));

//                ->add('extraData_3', 'choice', array(
//                    'placeholder' => 'No Extra Data Required',
//                    'choices' => [
//                        'instrument' => 'Instrument',
//                        'experience' => 'Experience'
//                    ],
//                    'required' => false,
//                    'data' => ($object) ? $object->getExtraData3() : null,
//                    'attr' => [
//                        'style' => ($object && $object->getMetaKey() != 'focus') ? 'display : none' : null,
//                        'onchange' => $onchange,
//                    ]
//                ));
            }
	}
}