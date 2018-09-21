<?php

namespace IIAB\MagnetBundle\Entity;

use Doctrine\ORM\EntityRepository;

class SubmissionRepository extends EntityRepository {

    /**
     * Get all unconfirmed submission
     * @param string $openEnrollment
     *
     * @return array
     */
    public function findAllUnconfirmed( $openEnrollment ) {

        return $this->createQueryBuilder( 's' )
            ->leftJoin( 'IIABMagnetBundle:SubmissionData', 'd', 'WITH',
                    's.id = d.submission '
                    .'AND d.metaKey IN ( \'Confirmation Sent\', \'Confirmation Attempted\' )'
                )
            ->where( 'd.id IS NULL' )
            ->andWhere( 's.parentEmail IS NOT NULL')
            ->andWhere( 's.openEnrollment = :openEnrollment' )
            ->setParameter( 'openEnrollment' , $openEnrollment )
            ->getQuery()
            ->getResult();
    }

    public function findAllResendRecommendationEmails( $openEnrollment ){
        $submissions = $this->createQueryBuilder( 's' )
            ->leftJoin( 'IIABMagnetBundle:SubmissionData', 'd', 'WITH',
                    's.id = d.submission '
                    .'AND d.metaValue = \'pending\' '
                    .'AND d.metaKey IN ( '
                        .'\'Math Recommendation Resend\', '
                        .'\'English Recommendation Resend\', '
                        .'\'Counselor Recommendation Resend\' '
                    .')'
                )
            ->where( 'd.id IS NOT NULL' )
            ->andWhere( 's.openEnrollment = :openEnrollment' )
            ->setParameter( 'openEnrollment' , $openEnrollment )
            ->getQuery()
            ->getResult();

        $return = [];
        foreach( $submissions as $submission ){
            if( empty( $return[ $submission->getId() ] ) ){
                $return[ $submission->getId() ] = $submission;
            }
        }
        return $return;
    }


    /**
     * Get all submissions who have not been sent recommendation emails
     * @param string $openEnrollment
     *
     * @return array
     */
    public function findAllNotSentRecommendationEmail( $openEnrollment ) {

        return $this->createQueryBuilder( 's' )
            ->leftJoin( 'IIABMagnetBundle:SubmissionData', 'd', 'WITH',
                    's.id = d.submission '
                    .'AND d.metaKey IN ( '
                        .'\'Math Recommendation Sent\', '
                        .'\'English Recommendation Sent\', '
                        .'\'Counselor Recommendation Sent\', '
                        .'\'Math Recommendation Attempted\', '
                        .'\'English Recommendation Attempted\', '
                        .'\'Counselor Recommendation Attempted\''
                    .')'
                )
            ->where( 'd.id IS NULL' )
            ->andWhere( 's.openEnrollment = :openEnrollment' )
            ->setParameter( 'openEnrollment' , $openEnrollment )
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all submissions who were not sent a writing prompt
     * @param string $openEnrollment
     *
     * @return array
     */
    public function findAllNotSentWritingPromptEmail( $openEnrollment ) {

        return $this->createQueryBuilder( 's' )
            ->leftJoin( 'IIABMagnetBundle:SubmissionData', 'd', 'WITH',
                    's.id = d.submission '
                    .'AND d.metaKey IN ( \'Writing Prompt Sent\', \'Writing Prompt Attempted\' )'
                )
            ->where( 'd.id IS NULL' )
            ->andWhere( 's.openEnrollment = :openEnrollment' )
            ->setParameter( 'openEnrollment' , $openEnrollment )
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all submissions who were not sent a learner screening device
     * @param string $openEnrollment
     *
     * @return array
     */
    public function findAllNotSentLearnerScreeningDeviceEmail( $openEnrollment ) {

        return $this->createQueryBuilder( 's' )
            ->leftJoin( 'IIABMagnetBundle:SubmissionData', 'd', 'WITH',
                    's.id = d.submission '
                    .'AND d.metaKey IN ( \'Learner Screening Device Sent\', \'Learner Screening Device Attempted\' )'
                )
            ->where( 'd.id IS NULL' )
            ->andWhere( 's.openEnrollment = :openEnrollment' )
            ->setParameter( 'openEnrollment' , $openEnrollment )
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all submissions who are missing a recommendation
     * @param array $options [openEnrollment,user,magnetSchool]
     *
     * @return array
     */
    public function findAllMissingRecommendationsBy( $options = [] ){

        if( empty( MYPICK_CONFIG['eligibility_fields']['recommendations'] ) ){
            return [];
        }

        $options = [
            'openEnrollment' => ( !empty( $options['openEnrollment'] ) )? $options['openEnrollment'] : null,
            'user' => ( !empty( $options['user'] ) ) ? $options['user'] : null,
            'magnetSchool' => ( !empty( $options['magnetSchool'] ) ) ? $options['magnetSchool'] : [],
            'recommendation_key' => ( !empty( $options['recommendation_key'] ) ) ? $options['recommendation_key'] : '',
        ];

        $magnet_schools = $options['magnetSchool'];

        if( empty( $magnet_schools ) && !empty( $options['user'] ) ){
            $magnet_schools = $options['user']->getMagnetSchoolsByOpenEnrollment( $options['openEnrollment'] );
        }

        if( empty( $magnet_schools) ){
            $programs = $options['openEnrollment']->getPrograms();

            foreach( $programs as $program ){
                foreach( $program->getMagnetSchools() as $magnet_school ){
                    $magnet_schools[] = $magnet_school;
                }
            }
        }

        $search_schools = [];
        foreach( $magnet_schools as $index => $magnet_school ){
            if( $magnet_school->doesRequire('recommendations') ){
                $search_schools[] = $magnet_school;
            }
        }

        if( !$search_schools ){
            return [];
        }

        $key = $options['recommendation_key'];
        $query = $this->createQueryBuilder( 's' )
            ->where( 's.openEnrollment = :openEnrollment' )
            ->setParameter( 'openEnrollment' , $options['openEnrollment'] )
            ->leftJoin( 'IIABMagnetBundle:SubmissionData', 'd', 'WITH',
                    's.id = d.submission '
                    .'AND d.metaKey LIKE \''.str_replace('_', '\\_', $key).'%\' '
                    .'AND d.metaKey != \''.$key.'_url\' '
                )
            ->andWhere('d.id IS NULL' )
            ->andWhere('s.submissionStatus IN (1,5)');

        if( !empty( $search_schools ) ){
            $query
                ->andWhere( '( s.firstChoice IN (:schools) OR s.secondChoice IN (:schools) OR s.thirdChoice IN (:schools) )' )
                ->setParameter( 'schools' , $search_schools );
        }

        return $query
            ->getQuery()
            ->getResult();
    }

	/**
	 * Get all submissions who are missing a learner screening report
	 * @param array $options [openEnrollment,user,magnetSchool]
	 *
	 * @return array
	 */
	public function findAllMissingLearnerScreeningDevice ( $options = [] ){

		if( empty( MYPICK_CONFIG['eligibility_fields']['learner_screening_device'] ) ){
			return [];
		}

		$options = [
			'openEnrollment' => ( !empty( $options['openEnrollment'] ) )? $options['openEnrollment'] : null,
			'user' => ( !empty( $options['user'] ) ) ? $options['user'] : null,
			'magnetSchool' => ( !empty( $options['magnetSchool'] ) ) ? $options['magnetSchool'] : [],
		];

		$magnet_schools = $options['magnetSchool'];

		if( empty( $magnet_schools ) && !empty( $options['user'] ) ){
			$magnet_schools = $options['user']->getMagnetSchoolsByOpenEnrollment( $options['openEnrollment'] );
		}

		if( empty( $magnet_schools) ){
			$programs = $options['openEnrollment']->getPrograms();

			foreach( $programs as $program ){
				foreach( $program->getMagnetSchools() as $magnet_school ){
					$magnet_schools[] = $magnet_school;
				}
			}
		}

		$search_schools = [];
		foreach( $magnet_schools as $index => $magnet_school ){
			if( $magnet_school->doesRequire('learner_screening_device') ){
				$search_schools[] = $magnet_school;
			}
		}

		if( !$search_schools ){
			return [];
		}

		$query = $this->createQueryBuilder( 's' )
		            ->where( 's.openEnrollment = :openEnrollment' )
		            ->setParameter( 'openEnrollment' , $options['openEnrollment'] )
        			->leftJoin( 'IIABMagnetBundle:SubmissionData', 'd', 'WITH',
		  			    's.id = d.submission '
                        .'AND d.metaKey LIKE \'learner\\_screening\\_device%\' '
                        .'AND d.metaKey != \'learner_screening_device_url\' '
				    )
                    ->andWhere( 'd.id IS NULL' )
                    ->andWhere('s.submissionStatus IN (1,5)');

		if( !empty( $search_schools ) ){
			$query
				->andWhere( '( s.firstChoice IN (:schools) OR s.secondChoice IN (:schools) OR s.thirdChoice IN (:schools) )' )
				->setParameter( 'schools' , $search_schools );
		}

		return $query
			->getQuery()
			->getResult();
    }

    /**
     * Get all submissions who are missing a auditions
     * @param array $options [openEnrollment,user,magnetSchool]
     *
     * @return array
     */
    public function findAllMissingAuditionsBy( $options ){
        if( empty( MYPICK_CONFIG['eligibility_fields']['audition'] )
            && empty( MYPICK_CONFIG['eligibility_fields']['audition_total'] )
        ){
            return [];
        }

        $options = [
            'openEnrollment' => ( !empty( $options['openEnrollment'] ) )? $options['openEnrollment'] : null,
            'user' => ( !empty( $options['user'] ) ) ? $options['user'] : null,
            'magnetSchool' => ( !empty( $options['magnetSchool'] ) ) ? $options['magnetSchool'] : [],
        ];

        $magnet_schools = $options['magnetSchool'];

        if( empty( $magnet_schools ) && !empty( $options['user'] ) ){
            $magnet_schools = $options['user']->getMagnetSchoolsByOpenEnrollment( $options['openEnrollment'] );
        }

        if( empty( $magnet_schools) ){
            $programs = $options['openEnrollment']->getPrograms();

            foreach( $programs as $program ){
                foreach( $program->getMagnetSchools() as $magnet_school ){
                    $magnet_schools[] = $magnet_school;
                }
            }
        }

        $search_schools = [];
        foreach( $magnet_schools as $index => $magnet_school ){
            if( $magnet_school->doesRequire('audition')
                || $magnet_school->doesRequire('audition_total')
            ){
                $search_schools[] = $magnet_school;
            }
        }

        if( !$search_schools ){
            return [];
        }

        $audition_keys = array_keys( MYPICK_CONFIG['eligibility_fields']['audition_total']['info_field'] );

        $query = $this->createQueryBuilder( 's' )
            ->where( 's.openEnrollment = :openEnrollment' )
            ->setParameter( 'openEnrollment' , $options['openEnrollment'] )
            ->leftJoin( 'IIABMagnetBundle:SubmissionData', 'sd', 'WITH',
                's.id = sd.submission '
                .'AND sd.metaKey = \'audition_total\' '
            )
            ->andWhere( 'sd.id IS NULL' )
            ->andWhere('s.submissionStatus IN (1,5)');

        // foreach( $audition_keys as $index => $key ){
        //     $alias = 'd'.$index;
        //     $query
        //         ->leftJoin( 'IIABMagnetBundle:SubmissionData', $alias, 'WITH',
        //             's.id = '.$alias.'.submission '
        //             .'AND '.$alias.'.metaKey = \''.$key.'\' '
        //         )
        //         ->andWhere( $alias.'.id IS NULL' );
        // }

        if( !empty( $search_schools ) ){
            $query
                ->andWhere( '( s.firstChoice IN (:schools) OR s.secondChoice IN (:schools) OR s.thirdChoice IN (:schools) )' )
                ->setParameter( 'schools' , $search_schools );
        }

        return $query
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all submissions who are missing a standardized test score
     * @param array $options [openEnrollment,user,magnetSchool]
     *
     * @return array
     */
    public function findAllMissingTestingBy( $options = [] ){

        if( empty( MYPICK_CONFIG['eligibility_fields']['standardized_testing'] ) ){
            return [];
        }

        $options = [
            'openEnrollment' => ( !empty( $options['openEnrollment'] ) )? $options['openEnrollment'] : null,
            'user' => ( !empty( $options['user'] ) ) ? $options['user'] : null,
            'magnetSchool' => ( !empty( $options['magnetSchool'] ) ) ? $options['magnetSchool'] : [],
            'test' => ( !empty( $options['test'] ) ) ? $options['test'] : null,
        ];

        $magnet_schools = $options['magnetSchool'];

        if( empty( $magnet_schools ) && !empty( $options['user'] ) ){
            $magnet_schools = $options['user']->getMagnetSchoolsByOpenEnrollment( $options['openEnrollment'] );
        }

        if( empty( $magnet_schools) ){
            $programs = $options['openEnrollment']->getPrograms();

            foreach( $programs as $program ){
                foreach( $program->getMagnetSchools() as $magnet_school ){
                    $magnet_schools[] = $magnet_school;
                }
            }
        }

        $search_schools = [];
        foreach( $magnet_schools as $index => $magnet_school ){
            if( $magnet_school->doesRequire('standardized_testing') ){
                $search_schools[] = $magnet_school;
            }
        }

        if( !$search_schools ){
            return [];
        }

        $key = $options['test'];
        $query = $this->createQueryBuilder( 's' )
            ->where( 's.openEnrollment = :openEnrollment' )
            ->setParameter( 'openEnrollment' , $options['openEnrollment'] )
            ->leftJoin( 'IIABMagnetBundle:SubmissionData', 'd', 'WITH',
                    's.id = d.submission '
                    .'AND d.metaKey = \''.$key.'\''
                )
            ->andWhere('d.id IS NULL' )
            ->andWhere('s.submissionStatus IN (1,5)');

        if( !empty( $search_schools ) ){
            $query
                ->andWhere( '( s.firstChoice IN (:schools) OR s.secondChoice IN (:schools) OR s.thirdChoice IN (:schools) )' )
                ->setParameter( 'schools' , $search_schools );
        }

        return $query
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all submissions who are missing a writing sample
     * @param array $options [openEnrollment,user,magnetSchool]
     *
     * @return array
     */
    public function findAllMissingWritingSamplesBy( $options = [] ){

        if( empty( MYPICK_CONFIG['eligibility_fields']['writing_prompt'] ) ){
            return [];
        }

        $options = [
            'openEnrollment' => ( !empty( $options['openEnrollment'] ) )? $options['openEnrollment'] : null,
            'user' => ( !empty( $options['user'] ) ) ? $options['user'] : null,
            'magnetSchool' => ( !empty( $options['magnetSchool'] ) ) ? $options['magnetSchool'] : [],
        ];

        $magnet_schools = $options['magnetSchool'];

        if( empty( $magnet_schools ) && !empty( $options['user'] ) ){
            $magnet_schools = $options['user']->getMagnetSchoolsByOpenEnrollment( $options['openEnrollment'] );
        }

        if( empty( $magnet_schools) ){
            $programs = $options['openEnrollment']->getPrograms();

            foreach( $programs as $program ){
                foreach( $program->getMagnetSchools() as $magnet_school ){
                    $magnet_schools[] = $magnet_school;
                }
            }
        }

        $search_schools = [];
        foreach( $magnet_schools as $index => $magnet_school ){
            if( $magnet_school->doesRequire('writing_prompt') ){
                $search_schools[] = $magnet_school;
            }
        }

        if( !$search_schools ){
            return [];
        }

        $query = $this->createQueryBuilder( 's' )
            ->where( 's.openEnrollment = :openEnrollment' )
            ->setParameter( 'openEnrollment' , $options['openEnrollment'] )
            ->leftJoin( 'IIABMagnetBundle:SubmissionData', 'd', 'WITH',
                    's.id = d.submission '
                    .'AND d.metaKey = \'writing_sample\''
                )
            ->andWhere('d.id IS NULL' )
            ->andWhere('s.submissionStatus IN (1,5)');

        if( !empty( $search_schools ) ){
            $query
                ->andWhere( '( s.firstChoice IN (:schools) OR s.secondChoice IN (:schools) OR s.thirdChoice IN (:schools) )' )
                ->setParameter( 'schools' , $search_schools );
        }

        return $query
            ->getQuery()
            ->getResult();
    }
}