<?php

namespace IIAB\MagnetBundle\Admin;

use IIAB\MagnetBundle\Entity\Program;
use IIAB\MagnetBundle\Entity\ProgramSchoolData;
use IIAB\MagnetBundle\Entity\Eligibility;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use IIAB\MagnetBundle\Service\EligibilityRequirementsService;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

class ProgramAdmin extends AbstractAdmin {

	/**
	 * @var string
	 */
	protected $baseRouteName = 'admin_programs';

	/**
	 * @var string
	 */
	protected $baseRoutePattern = 'programs';

	/**
	 * @var array
	 */
	protected $datagridValues = array(
		'_page' => 1 ,            // display the first page (default = 1)
		'_sort_order' => 'ASC' , // reverse order (default = 'ASC')
		'_sort_by' => 'openEnrollment'  // name of the ordered field
		// (default = the model's id field, if any)

		// the '_sort_by' key can be of the form 'mySubModel.mySubSubModel.myField'.
	);

	/**
	 * @param FormMapper $form
	 */
	protected function configureFormFields( FormMapper $form ) {

		$subject = $this->getSubject();

		$disabled = ( $subject->getId() ? true : false );

		$focus_description = $subject->getAdditionalData('focus_description');
        $focus_description = ( isset($focus_description[0]) ) ? $focus_description[0]->getMetaValue() : null;

        $capacity_determined_by = $subject->getAdditionalData('capacity_by');
        $capacity_determined_by = ( isset($capacity_determined_by[0]) ) ? $capacity_determined_by[0]->getMetaValue() : 'grade';

        $focus_placement = $subject->getAdditionalData('focus_placement');
        $focus_placement = ( isset($focus_placement[0]) ) ? $focus_placement[0]->getMetaValue() : null;

        $slotting_method = $subject->getAdditionalData('slotting_method');
        $slotting_method = ( isset($slotting_method[0]) ) ? $slotting_method[0]->getMetaValue() : 'grade';

        $available_slotting_methods = [];
        foreach( MYPICK_CONFIG['lottery']['types'] as $lottery_type => $lottery_settings ){
            if( $lottery_settings['enabled'] ){
                $available_slotting_methods[ $lottery_settings['label'] ] = $lottery_type;
            }
        }
        $form
        ->tab('Program')
        ->end()
        ->tab('Eligibility')
        ->end()

        ->tab('SubChoices/Exclusions')
        ->end();

        $onchange = "

                var id = this.id;
                var selection = this.value;
                var base_id = id.split('capacityBy')[0];

                var placement_container =  document.getElementById( 'sonata-ba-field-container-'+ base_id +'focusPlacement');
                var placement_field =  document.getElementById( 's2id_'+ base_id +'focusPlacement');


                var display = 'none';
                if( this.value == 'focus' ){
                    var display = '';
                }

                placement_field.style.display = display;
                var nodes = Array.prototype.slice.call(placement_container.childNodes);

                for( var i=0; i < nodes.length; i++ ){
                    if( typeof( nodes[i] )== 'object' && typeof(nodes[i].tagName) != 'undefined'){
                        nodes[i].style.display = display;
                    }
                }

            ";
        $onchange = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $onchange));



        $form
            ->tab('Program')
                ->add( 'name' , null , array(
                    'required' => true ,
                ) )

                ->add( 'openEnrollment' , null , array(
                    'label' => 'Open Enrollment' ,
                    'required' => false ,
                    'disabled' => $disabled ,
                    'query_builder' => function ( $er ) {
                        $qb = $er->createQueryBuilder( 'oe' )
                            ->orderBy( 'oe.year' , 'DESC' );
                        return $qb;
                    }
                ) )

                ->add('slottingMethod', 'choice', array(
                    'label' => 'Available Seats Awarded by',
                    'placeholder' => 'Choose an option',
                    'choices' => $available_slotting_methods,
                    'required' => false,
                    'mapped' => false,
                    'data' => $slotting_method,
                ))

                ->add('capacityBy', 'choice', array(
                    'label' => 'Capacity Determined by',
                    'placeholder' => 'Choose an option',
                    'choices' => array_flip( [
                        'grade' => 'Grade',
                        'focus' => 'Grade and SubChoice',
                    ]),
                    'required' => false,
                    'mapped' => false,
                    'data' => $capacity_determined_by,
                    'attr' => [
                        'onchange' => $onchange
                    ]
                ))

                ->add('focusPlacement', 'choice', array(
                    'label' => 'SubChoice Placement',
                    'label_attr' =>  ( $capacity_determined_by == 'grade' ) ? [ 'style' => 'display:none;'] : [],
                    'placeholder' => 'Choose an option',
                    'choices' => array_flip( [
                        'all' => 'Place All SubChoices',
                        'first' => 'Place First SubChoice Only',
                        'second' => 'Place First and Second SubChoices',
                    ] ),
                    'required' => false,
                    'mapped' => false,
                    'data' => $focus_placement,
                    'attr' => [
                        'style' => ( $capacity_determined_by == 'grade' ) ? 'display:none;' : null,
                    ]
                ) )

                ->add( 'iNowNames' , 'sonata_type_collection' , array(
                    'type_options' => array(
                        'delete' => true ,
                    )
                ) , array(
                    'edit' => 'inline' ,
                    'inline' => 'table' ,
                ) )

            ->end()->end();

        $form
            ->tab('Eligibility');

            $eligibility_service = new EligibilityRequirementsService(
                $this->getConfigurationPool()->getContainer()->get( 'doctrine' )->getManager()
            );
            $eligibility_fields = $eligibility_service->getEligibilityFieldIDs();

            $schools = $subject->getMagnetSchools();
            $school_choices = [];
            foreach( $schools as $school ){
                $school_choices[ $school->getId() ] = $school->__toString();
            }

            foreach( $eligibility_fields as $key => $data ){

                $onchange = "

                var id = this.id;
                var selection = this.value;
                var base_id = id.split('". $key ."')[0];

                var schools =  document.getElementById( base_id +'".$key."_schools');

                if( this.value == 2 || this.value == 4 ){
                   schools.style.display = '';

                } else {

                    schools.style.display = 'none';
                }
            ";
                $onchange = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $onchange));

                $program_eligibility = $this->getConfigurationPool()->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Eligibility' )
                    ->findOneBy([
                        'program' => $subject,
                        'criteriaType' => $key
                    ]);

                $school_eligibility = $this->getConfigurationPool()->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Eligibility' )
                    ->findBy([
                        'magnetSchool' => $subject->getMagnetSchools()->toArray(),
                        'criteriaType' => $key
                    ]);

                $required_data = 0;
                if( count( $school_eligibility) ){
                    $required_data = 2;
                    if( $school_eligibility[0]->getCourseTitle() == 'combined' ){
                        $required_data = 4;
                    }
                }
                if( !empty( $program_eligibility) ){
                    $required_data = 1;

                    if( $program_eligibility->getCourseTitle() == 'combined' ){
                        $required_data = 3;
                    }
                }

                $choices = ( isset( $data['admin_options'] ) ) ? $data['admin_options'] : [
                    0 => 'Not Required',
                    1 => 'Required',
                    2 => 'Required by Grade'
                ];


                $form
                ->add($key, 'choice', array(
                    'label' => $data['label'],
                    'placeholder' => false,
                    'choices' => array_flip( $choices ),
                    'required' => false,
                    'mapped' => false,
                    'data' => $required_data,
                    'attr' => [
                        'onchange' => $onchange,
                    ]
                ));

                $found_data = [];

                foreach( $school_eligibility as $school ){
                    $found_data[] = $school->getMagnetSchool()->getId();
                }

                $form
                ->add($key.'_schools', 'choice', array(
                    'placeholder' => false,
                    'choices' => array_flip( $school_choices ),
                    'required' => false,
                    'mapped' => false,
                    'data' => $found_data,
                    'expanded' => true,
                    'multiple' => true,
                    'attr' => [ 'style' => ($required_data == 2) ? '' : 'display: none;' ],
                    'label_attr' => [ 'style' => 'display: none;' ]
                ));
            }
        $form
            ->end()->end();

        $form
            ->tab('SubChoices/Exclusions')

                ->add('focusDescription', 'choice', array(
                    'label' => 'SubChoices Titles',
                    'placeholder' => 'Choose an option',
                    'choices' => [
                        'focus' => 'SubChoice',
                        'academy' => 'Academy',
                        'audition' => 'Audition',
                        'specialty' => 'Specialty',
                        'class' => 'Class',
                        'program' => 'Program'
                    ],
                    'required' => false,
                    'mapped' => false,
                    'data' => $focus_description,
                ))
                ->add('additionalData', 'sonata_type_collection', array(
                    'label' => 'SubChoices/Exclusions',
                    'type_options' => array(
                        'delete' => true,
                    ),
                ), array(
                    'edit' => 'inline',
                    'inline' => 'table',
                ))

            ->end()->end();

            $file = false;

            $directory = 'uploads/program/'. $subject->getId() .'/pdfs/';
            if( !is_dir( $directory ) ){
                mkdir( $directory, 0755, true);
            }

            $finder = new Finder();
            $finder->files()->in($directory);

            foreach( $finder as $found ){
                $file = $directory . $found->getFileName();
            }

        $form
            ->tab('Files');

        if( $file ){

            $form->add('currentFile', 'text', array(
                'label' => 'Current File',
                'disabled' => true,
                'required' => false,
                'mapped' => false,
                'data' => $found->getFileName(),
                'help' => '<a  target="_blank" href="https://'.$_SERVER['HTTP_HOST'].$this->getRequest()->getBasePath().'/'.$file.'">View File</a>',
            ));
        }

        $form
            ->add( 'file', 'file', array(
                'required' => false ,
                'mapped' => false,
                'label' => 'Add new File to use' ,
                'help' => 'Make sure the file name is unique. Add new file here for them to show up in the list. If you option is on "No file", then the text will not show on the front facing pages.'
            ) );

        if( $file ){

            $fileDisplayAfterSubmission = $subject->getAdditionalData('file_display_after_submission');
            $fileDisplayAfterSubmission = ( count( $fileDisplayAfterSubmission ) ) ? $fileDisplayAfterSubmission[0]->getMetaValue() : null;

            $fileDisplayAfterSubmissionLabel = $subject->getAdditionalData('file_display_after_submission_label');
            $fileDisplayAfterSubmissionLabel = ( count( $fileDisplayAfterSubmissionLabel ) ) ? $fileDisplayAfterSubmissionLabel[0]->getMetaValue() : null;

            $form
            ->add('fileDisplayAfterSubmission', 'choice', array(
                    'label' => 'Display link to file after submission',
                    'placeholder' => 'Choose an option',
                    'choices' => [
                        'Yes' => 1,
                        'No' => 0,
                    ],
                    'required' => false,
                    'mapped' => false,
                    'data' => $fileDisplayAfterSubmission,
                ))

            ->add('fileDisplayAfterSubmissionLabel', 'text', array(
                    'label' => 'File Label for Display',
                    'required' => false,
                    'mapped' => false,
                    'data' => $fileDisplayAfterSubmissionLabel,
                ));
        }

        $form->end()->end();

	}

	/**
	 * @param ListMapper $list
	 */
	protected function configureListFields( ListMapper $list ) {

		$list
			->addIdentifier( 'name' )
			->add( 'openEnrollment' );
	}

	/**
	 * @param DatagridMapper $filter
	 */
	protected function configureDatagridFilters( DatagridMapper $filter ) {

		$filter
			->add( 'name' )
			->add( 'openEnrollment' );
	}

	/**
	 * @param RouteCollection $collection
	 */
	protected function configureRoutes( RouteCollection $collection ) {

		$collection->remove( 'batch' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $object ) {

		if( $this->tryToDelete( $object ) == false ) {
			throw new ModelManagerException( 'You cannot delete a Program that has Magnet Schools assign to it that already have submissions.' , 2001 );
		} else {
			parent::delete( $object );
		}
	}

	/**
	 * @param Program $program
	 *
	 * @return bool
	 */
	private function tryToDelete( Program $program ) {

		$schools = $program->getMagnetSchools();
		$em = $this->getConfigurationPool()->getContainer()->get( 'doctrine' )->getManager();
		/** @var \IIAB\MagnetBundle\Entity\MagnetSchool $school */
		foreach( $schools as $school ) {
			$submissions = $em->getRepository( 'IIABMagnetBundle:Submission' )->createQueryBuilder( 's' )
				->where( 's.firstChoice = :school' )
				->orWhere( 's.secondChoice = :school' )
				->orWhere( 's.thirdChoice = :school' )
				->setParameter( 'school' , $school )
				->getQuery()
				->getResult();

			//If there are submission tried to this Schools, we cannot allow deletion.
			if( count( $submissions ) > 0 ) {
				return false;
				break;
			}

			$submissions = null;
		}
		return true;
	}

	/**
	 * Sets the Submission data, comments and grades for the SubmissionID.
	 *
	 * @param \IIAB\MagnetBundle\Entity\Submission $object
	 *
	 * @return void
	 */
	public function preUpdate( $object ) {

        $uniqid = $this->getRequest()->query->get( 'uniqid' );
        $formData = $this->getRequest()->request->get( $uniqid );

		$em = $this->getConfigurationPool()->getContainer()->get('doctrine')->getManager();
		foreach ($object->getINowNames() as $name) {
			$name->setProgram($object);
			$em->persist( $name );
		}

		if( isset($formData['additionalData'])) {
            foreach ($formData['additionalData'] as $row) {
                if (isset($row['_delete']) && $row['_delete']) {

                    $foundData = $em->getRepository('IIABMagnetBundle:ProgramSchoolData')->findBy([
                        'metaKey' => $row['metaKey'],
                        'metaValue' => $row['metaValue'],
                        'program' => $object,
                    ]);

                    foreach ($foundData as $datum) {
                        $object->removeAdditionalDatum($datum);
                        $em->persist($object);
                        $em->remove($datum);
                    }
                }
            }
        }

        foreach( $object->getAdditionalData() as $data ) {
            $data->setProgram( $object );
        }

        //ProgramSchoolData Fields
        $check_for_fields = [
            'focus_description' => 'focusDescription',
            'capacity_by' => 'capacityBy',
            'focus_placement' => 'focusPlacement',
            'slotting_method' => 'slottingMethod',
        ];

        foreach( $check_for_fields as $key => $field_name ){
            $foundData = $object->getAdditionalData($key);
            $foundData = ( isset( $foundData[0]) ) ? $foundData[0] : null;

            if ($foundData == null) {
                if ( isset( $formData[ $field_name ] ) ) {
                    $subData = new ProgramSchoolData();
                    $subData->setMetaKey($key);
                    $subData->setMetaValue($formData[$field_name]);
                    $subData->setProgram($object);
                    $em->persist($subData);
                }
            } else {
                if ( isset( $formData[ $field_name ] ) ) {
                    $foundData->setMetaValue($formData[$field_name]);
                    $em->persist($foundData);
                } else {
                    $object->removeAdditionalDatum($foundData);
                }
            }
        }

        $eligibility_service = new EligibilityRequirementsService(
            $this->getConfigurationPool()->getContainer()->get( 'doctrine' )->getManager()
        );
        $eligibility_fields = $eligibility_service->getEligibilityFieldIDs();

        $magnet_school_hash = [];
        foreach( $object->getMagnetSchools() as $magnetSchool){
            $magnet_school_hash[ $magnetSchool->getId() ] = $magnetSchool;
        }

        foreach( $eligibility_fields as $key => $field_name ){

            $program_eligibility = $this->getConfigurationPool()->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Eligibility' )
                ->findOneBy([
                    'program' => $object,
                    'criteriaType' => $key
                ]);

            $school_eligibility = $this->getConfigurationPool()->getContainer()->get( 'doctrine' )->getRepository( 'IIABMagnetBundle:Eligibility' )
                ->findBy([
                    'magnetSchool' => $object->getMagnetSchools()->toArray(),
                    'criteriaType' => $key
                ]);

            switch ( $formData[$key] ) {
                case 4:
                case 2:

                    if (!empty($program_eligibility)) {
                        $em->remove($program_eligibility);
                    }

                    $new_school_ids = (isset($formData[$key . '_schools'])) ? $formData[$key . '_schools'] : [];

                    foreach ($school_eligibility as $school) {
                        if (in_array($school->getId(), $new_school_ids)) {
                            unset($new_school_ids[array_search($school->getId(), $new_school_ids)]);
                        } else {
                            $em->remove($school);
                        }
                    }

                    foreach ($new_school_ids as $school_id) {
                        $eligibility_record = new Eligibility();
                        $eligibility_record->setMagnetSchool($magnet_school_hash[$school_id]);
                        $eligibility_record->setCriteriaType($key);
                        $eligibility_record->setCourseTitle( ( $formData[$key] == 3 || $formData[$key] == 4 ) ? 'combined' : null );
                        $em->persist($eligibility_record);
                    }
                    break;

                case 3:
                case 1:

                    if (empty($program_eligibility)) {
                        $program_eligibility = new Eligibility();
                        $program_eligibility->setProgram($object);
                        $program_eligibility->setCriteriaType($key);
                    }

                    $program_eligibility->setCourseTitle( ( $formData[$key] == 3 || $formData[$key] == 4 ) ? 'combined' : null );
                    $em->persist( $program_eligibility );

                    foreach ($school_eligibility as $school) {
                        $em->remove($school);
                    }
                    break;

                case 0:
                default:

                    if (!empty($program_eligibility)) {
                        $em->remove($program_eligibility);
                    }

                    foreach ($school_eligibility as $school) {
                        $em->remove($school);
                    }


                    break;
            }
        }

        $uploadedFile = false;
        $files = $this->getRequest()->files;

        foreach( $files->keys() as $key ){
            $key_files = $files->get($key);
            if( isset( $key_files['file'] ) ){
                $uploadedFile = $key_files['file'];
            }
        }

        if( $uploadedFile ){

            $old_files = glob( 'uploads/program/'. $object->getId() .'/pdfs/' . '*', GLOB_MARK);

            foreach ($old_files as $old_file) {
                if (!is_dir($old_file)) {
                    unlink($old_file);
                }
            }

            $uploadedFile->move(
                'uploads/program/'. $object->getId() .'/pdfs/',
                $uploadedFile->getClientOriginalName()
            );
        }


        $fileFields = [
            'file_display_after_submission' => 'fileDisplayAfterSubmission',
            'file_display_after_submission_label' => 'fileDisplayAfterSubmissionLabel'
        ];

        foreach( $fileFields as $key => $field ){

            $foundData = $object->getAdditionalData( $key );

            if(
                ( isset( $formData[ $field ] ) && $formData[ $field ] === '0' )
                || !empty( $formData[ $field ] )
            ) {

                if( count( $foundData ) ) {

                    $programData = $foundData[0];
                } else {
                    $programData = new ProgramSchoolData();
                    $programData->setMetaKey($key);
                    $programData->setProgram($object);
                }
                $programData->setMetaValue($formData[$field]);
                $em->persist( $programData );

            } else {
                if( count( $foundData ) ) {
                    foreach( $foundData as $data ){
                        $object->removeAdditionalDatum( $data );
                    }
                }
            }
        }

        $em->flush();
    }

	/**
	 * @param mixed $object
	 *
	 * @return void
	 */
	public function prePersist( $object )
	{
		$em = $this->getConfigurationPool()->getContainer()->get('doctrine')->getManager();
		foreach ($object->getINowNames() as $name) {
			$name->setProgram($object);
			$em->persist( $name );
		}

        foreach( $object->getAdditionalData() as $data ) {
            $data->setProgram( $object );
        }
	}
}