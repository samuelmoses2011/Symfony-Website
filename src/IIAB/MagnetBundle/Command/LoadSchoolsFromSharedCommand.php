<?php

namespace IIAB\MagnetBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use IIAB\MagnetBundle\Entity\AddressBoundSchool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadSchoolsFromSharedCommand extends ContainerAwareCommand {

    protected $magnet_entity_manager;
    protected $shared_entity_manager;

    /**
     * @inheritdoc
     */
    protected function configure() {

        $this
            ->setName( 'magnet:load:shared:schools' )
            ->setDescription( 'Loads in the school data from the shared database.' )
            ->setHelp( <<<EOF
The <info>%command.name%</info> command imports the school data from the shared database.

<info>php %command.full_name%</info>

EOF
            );
    }

    /**
     * @inheritdoc
     */
    protected function execute( InputInterface $input , OutputInterface $output ) {

        var_dump( 'Loading Schools from Shared Data' );

        $this->magnet_entity_manager = $this->getContainer()->get( 'doctrine' )->getManager();
        $this->shared_entity_manager = $this->getContainer()->get( 'doctrine' )->getManager('shared');

        $address_bound_schools = $this->magnet_entity_manager->getRepository('IIABMagnetBundle:AddressBoundSchool')->findAll();
        $address_bound_schools_hash = [];
        foreach( $address_bound_schools as $school ){
            $address_bound_schools_hash[ $school->getName() ] = $school;
        }

        $shared_schools = $this->shared_entity_manager->getRepository('lfSharedDataBundle:ProgramSchool')->findAll();
        $shared_schools_hash = [];
        $parents = [];
        foreach( $shared_schools as $school ){

            if( empty( $school->getParent() ) ){

                $parents[$school->getId()] = $school;

            } else {

                $grade_level = $school->getGradeLevel();

                if( $grade_level <= 12 || $grade_level == 99 ){

                    $parent_id = $school->getParent()->getId();
                    if( !isset( $shared_schools_hash[ $parent_id ] ) ){
                        $shared_schools_hash[ $parent_id ] = [
                            'min' => $grade_level,
                            'max' => $grade_level,
                        ];
                    } else {
                        $shared_schools_hash[ $parent_id ]['min'] = $this->getSmaller( $grade_level, $shared_schools_hash[ $parent_id ]['min']);
                        $shared_schools_hash[ $parent_id ]['max'] = $this->getBigger( $grade_level, $shared_schools_hash[ $parent_id ]['max']);
                    }
                }
            }
        }

        foreach( $parents as $parent ){

            $parent_id = $parent->getId();

            if( isset( $shared_schools_hash[ $parent_id ] ) ){

                $name = $parent->getName();
                if( isset( $address_bound_schools_hash[ $name ] ) ){
                    $school = $address_bound_schools_hash[ $name ];
                } else {
                    $school = new AddressBoundSchool();
                }

                $school
                    ->setName( $name )
                    ->setStartGrade( $shared_schools_hash[ $parent_id ]['min'] )
                    ->setEndGrade( $shared_schools_hash[ $parent_id ]['max'] );

                $this->magnet_entity_manager->persist( $school );
            }
        }
        $this->magnet_entity_manager->flush();
        var_dump( 'Finished Loading Schools from Shared Data' );
    }

    //Compares Grade Levels and returns the lower level
    private function getSmaller( $a, $b ){

        $offset_a = ( $a > 20 ) ? $a - 100 : $a;
        $offset_b = ( $b > 20 ) ? $b - 100 : $b;

        return ( $offset_a < $offset_b ) ? $a : $b;
    }

    //Compares Grade Levels and returns the higher level
    private function getBigger( $a, $b ){

        $offset_a = ( $a > 20 ) ? $a - 100 : $a;
        $offset_b = ( $b > 20 ) ? $b - 100 : $b;

        return ( $offset_a > $offset_b ) ? $a : $b;
    }
}