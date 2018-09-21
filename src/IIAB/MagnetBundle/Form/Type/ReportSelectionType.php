<?php

namespace IIAB\MagnetBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ReportSelectionType extends AbstractType {

    public function buildForm( FormBuilderInterface $builder , array $options ) {

        $user = $options['user'];
        $entity_manager = $options['entity_manager'];

        $schools = $user->getSchools();
        $all_label = ( empty( $schools ) ) ? 'District (all programs)' : 'Mangaged Programs (all programs you manage)';

        if( empty( $schools ) ){
            $unique_schools = $entity_manager->getRepository( 'IIABMagnetBundle:MagnetSchool' )->createQueryBuilder( 'm' )
                ->select( 'm.name' )
                ->distinct( true )
                ->orderBy( 'm.name' , 'ASC' )
                ->getQuery()
                ->getArrayResult();

            foreach( $unique_schools as $row ){
                $schools[] = $row['name'];
            }
        }

        $school_choices = [];
        foreach( $schools as $school ){
            $school_choices[ trim( $school ) ] = trim( $school );
        }

        $builder

            ->add( 'openenrollment' , 'entity' , array(
                'class' => 'IIABMagnetBundle:OpenEnrollment' ,
                'label' => 'Enrollment' ,
                'required' => true ,
                'attr' => array( 'style' => 'margin-bottom: 20px' , 'class' => 'update-magnetschool' ) ,
                'placeholder' => 'Choose an Enrollment Period' ,
                'query_builder' => function ( $er ) {

                    $query = $er->createQueryBuilder( 'enrollment' )
                        ->orderBy( 'enrollment.year' , 'ASC' );

                    return $query;
                } ,
                'data' => $options['open_enrollment'],
            ) )

            ->add( 'magnetschool' , 'choice' , array(
                    'label' => 'School' ,
                    'required' => true ,
                    'placeholder' => 'Choose an option' ,
                    'choices' => ( $schools ) ? array_merge( [$all_label=>'all'], $school_choices ) : null,
            ) )

            ->add( 'generate_report' , 'submit' , array( 'label' => 'Generate Program Report' , 'attr' => array( 'class' => 'btn btn-primary' , 'style' => 'margin-top:20px;' ) ) );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
        $resolver->setDefaults( array(
            'user' => null,
            'entity_manager' => null,
            'open_enrollment' => null,
        ) );

        $resolver->setAllowedTypes('user', 'IIAB\MagnetBundle\Entity\User');
        $resolver->setAllowedTypes('entity_manager', 'Doctrine\ORM\EntityManager');
        $resolver->setAllowedTypes('open_enrollment', 'IIAB\MagnetBundle\Entity\OpenEnrollment');
    }


    public function getName() {

        return 'adm_data';
    }

}