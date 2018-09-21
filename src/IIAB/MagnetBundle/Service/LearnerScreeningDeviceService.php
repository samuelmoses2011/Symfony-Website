<?php

namespace IIAB\MagnetBundle\Service;

use Doctrine\ORM\EntityManager;
use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionData;

class LearnerScreeningDeviceService {

    private $key_prefix = 'recommendation_';
    private $recommendation_fields = [];
    private $entityManager;

    private static $questions = [
        'visual_arts' => 'Ability in the Visual Arts',
        'performing_arts' => 'Ability in the Performance Arts',
        'leadership' => 'Leadership Qualities-Organizing-Decision Making',
        'psychomotor' => 'Psychomotor Skills and Abilities',
        'citizenship' => 'Citizenship and/or Behavior',
        'creative_thinking' => 'Creative or Productive Thinking',
        'abstract_thinking' => 'Use of Spatial and Abstract Thinking ',
        'general_intellect' => 'General Intellectual Ability ',
        'cultural' => 'Talent Associated with Cultural Heritage ',
    ];

    private static $rating_descriptions_by_value = [
        7 => 'In this category, the child is among the very highest in frequency, intensity, and/or quality of the behavior in comparison to the reference group',
        6 => 'Behavior is significantly more frequent, etc.',
        5 => 'Behavior is somewhat more frequent, etc.',
        4 => 'Behavior is typical or commonly observed in the reference group',
        3 => 'Behavior is somewhat less frequent, etc.',
        2 => 'Behavior is significantly less frequent, etc.',
        1 => 'In this category the child is among the very lowest in frequency, intensity and/or quality of the behavior in comparison to the reference group',
    ];

    public function __construct( EntityManager $entityManager ){
        $this->entityManager = $entityManager;

        // foreach( MYPICK_CONFIG['eligibility_fields'] as $key => $field ){

        //     if( strpos($key, $this->key_prefix) === 0 ){
        //         $this->recommendation_fields[] = $key;
        //     }
        // }
    }

    public static function getQuestions(){

        return self::$questions;
    }

    public static function getRatingDescriptions(){

        return self::$rating_descriptions_by_value;
    }

    public static function getRatingFormChoices(){

        $return_array = [];

        foreach( self::$rating_descriptions_by_value as $value => $description ){
            $return_array[ $value .': '. $description ] = $value;
        }
        return $return_array;
    }

    public function getLearnerScreeningDeviceURL( Submission $submission, $generateMissing = false ){

        $submissionData = $submission->getAdditionalDataByKey( 'learner_screening_device_url' );

        if( empty( $submissionData)
            && $generateMissing
            && $submission->doesRequire( 'learner_screening_device' )
        ){
            return $this->generateLearnerScreeningDeviceURL( $submission );
        }

        return ( !empty( $submissionData ) )? $submissionData->getMetaValue() : null;
    }

    public function generateLearnerScreeningDeviceURL( Submission $submission ){

        return implode('.',[
            $submission->getId(),
            rand ( 100, 999 )
        ]);
    }
}