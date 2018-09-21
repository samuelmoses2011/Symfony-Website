<?php

namespace IIAB\MagnetBundle\Service;

use Doctrine\ORM\EntityManager;
use IIAB\MagnetBundle\Entity\Submission;
use IIAB\MagnetBundle\Entity\SubmissionData;

class RecommendationService {

    private $key_prefix = 'recommendation_';
    private $recommendation_fields = [];
    private $entityManager;

    public function __construct( EntityManager $entityManager )
    {
        $this->entityManager = $entityManager;

        foreach( MYPICK_CONFIG['eligibility_fields']['recommendations']['info_field'] as $key => $field ){
            $this->recommendation_fields[] = $key;
        }
    }

    public function getRecommendationKey( $possible_key, $type = null ){

        if( strpos($possible_key, $this->key_prefix) !== 0){
            $possible_key = $this->key_prefix . $possible_key;
        }

        if( !in_array( $possible_key, $this->recommendation_fields ) ){
            return null;
        }

        if( empty( $type ) ){
            return $possible_key;
        }

        return $possible_key .'_'. $type;
    }

    public function getRecommendationURL( Submission $submission, $recommendation, $generateMissing = false ){

        $recommendation = $this->getRecommendationKey( $recommendation );
        if( empty( $recommendation ) ){ return null; }
        $data_key = $this->getRecommendationKey( $recommendation, 'url' );

        $submissionData = $submission->getAdditionalDataByKey( $data_key );

        if( empty( $submissionData)
            && $generateMissing
            && $submission->doesRequire( $recommendation )
        ){
            return $this->generateRecommendationURL( $submission, $recommendation );
        }

        return ( !empty( $submissionData ) )? $submissionData->getMetaValue() : null;
    }

    public function getAllRecommendationURLs( Submission $submission, $generateMissing = false ){

        $urls = [];

        foreach( $this->recommendation_fields as $recommendation ){

            if( $submission->doesRequire( $recommendation ) ){

                $url = $this->getRecommendationURL( $submission, $recommendation );

                if( empty( $url ) && $generateMissing ){
                    $url = $this->generateRecommendationURL( $submission, $recommendation );
                }

                $urls[ $recommendation.'_url' ] = $url;
            }
        }
        return $urls;
    }

    public function generateRecommendationURL( Submission $submission, $recommendation ){

        $recommendation = $this->getRecommendationKey( $recommendation );
        if( empty( $recommendation ) ){ return null; }

        return implode('.', [
            str_replace( $this->key_prefix, '', $recommendation),
            $submission->getId(),
            rand ( 100, 999 )
        ]);
    }

    public function setRecommendationURL( Submission $submission, $recommendation, $url = null ){

        $recommendation = $this->getRecommendationKey( $recommendation );
        if( empty( $recommendation ) ){ return null; }

        if( !$submission->doesRequire( $recommendation ) ){return null;}

        $data_key = $recommendation .'_url';
        $submissionData = $submission->getAdditionalDataByKey( $data_key );

        if( empty( $submissionData ) ){
            $submissionData = new SubmissionData();
            $submissionData->setSubmission( $submission );
            $submissionData->setMetaKey( $data_key );
            $submission->addAdditionalDatum( $submissionData );
        }

        $url = ( !is_null( $url ) ) ? $url : $this->generateRecommendationURL( $submission, $recommendation );

        $submissionData->setMetaValue( $url );
        $this->entityManager->persist( $submissionData );
        $this->entityManager->flush();
    }

    public function setAllRecommendationURLS( $submission, $overwrite_existing = false ){

        $urls = array_fill_keys($this->recommendation_fields, null);

        if( !$overwrite_existing ){
        $urls = $this->getAllRecommendationURLs( $submission, 'generate_missing' );
        }

        foreach( $urls as $recommendation => $url ){
            $this->setRecommendationURL( $submission, $recommendation, $url );
        }
    }
}