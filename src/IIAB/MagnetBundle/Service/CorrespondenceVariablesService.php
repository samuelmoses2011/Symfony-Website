<?php
/**
 * Company: Image In A Box
 * Date: 2/2/15
 * Time: 12:00 PM
 * Copyright: 2015
 */

namespace IIAB\MagnetBundle\Service;

class CorrespondenceVariablesService {

	function __construct() {
	}

	/**
	 * Returns an array of twig entities for use in templates.
	 *
	 * @return array
	 */
	static function getDynamicVariables() {

        return [
            "Parent's Email" => '{{ submission.parentEmail }}',
            'Student First Name' => '{{ submission.firstName }}',
            'Student Last Name'  => '{{ submission.lastName }}',
            'Submission ID' => '{{ submission }}',
            'Submission Year' => '{{ submission.openEnrollment }}',
            'Submission Address' => '{{ submission.address }}',
            'Submission City' => '{{ submission.city }}',
            'Submission State' => '{{ submission.state|upper }}',
            'Submission Zip' => '{{ submission.zip }}',

            'Enrollment Year' => '{{ enrollment }}',
            'New Student Registration Date' => "{{ registrationNew|date('F j, Y') }}",
            'Current Student Registration Date' => "{{ registrationCurrent|date('F j, Y') }}",
            'Awarded School' => '{{ awardedSchool }}',
            'Acceptance Link' => '{{ acceptanceURL }}',
            'Online Acceptance Deadline Date' => '{{ acceptOnlineDate }}',
            'Online Acceptance Deadline Time' => '{{ acceptOnlineTime }}',
            'Offline Acceptance Deadline Date' => '{{ acceptOfflineDate }}',
            'Offline Acceptance Deadline Time' => '{{ acceptOfflineTime }}',
            'Confirmation Number' => '{{ confirmation }}',
            'Next Year' => '{{ nextYear }}',
            'Next School Year' => '{{ nextSchoolsYear }}',

            'First Choice School' => '{{ firstChoice }}',
            'First Choice Special Requirement' => '{{ firstChoiceMessage.specialRequirement }}',

            'Second Choice School' => '{{ secondChoice }}',
            'Second Choice Special Requirement' => '{{ secondChoiceMessage.specialRequirement }}',

            'Third Choice School' => '{{ thirdChoice }}',
            'Third Choice Special Requirement' => '{{ thirdChoiceMessage.specialRequirement }}',

            'Awarded School List' => '{{ awardedSchools|raw }}',

            'If New Student' => '{% if studentStatus == "new" %} New Student Message {% else %} Returning Student Message {% endif %}',

            'If First Choice' => '{% if firstChoice is defined and firstChoice is not empty %} First Choice Content {% else %} Alternate Content {% endif %}',
            'If First Choice Message' => '{% if firstChoiceMessage %} First Choice Message Content {% else %} Alternate Content {% endif %}',

            'If Second Choice' => '{% if secondChoice is defined and secondChoice is not empty %} Second Choice Content {% else %} Alternate Content {% endif %}',
            'If Second Choice Message' => '{% if secondChoiceMessage %} Second Choice Message Content {% else %} Alternate Content {% endif %}',

            'If Third Choice' => '{% if thirdChoice is defined and thirdChoice is not empty %} Third Choice Content {% else %} Alternate Content {% endif %}',
            'If Third Choice Message' => '{% if thirdChoiceMessage %} Third Choice Message Content {% else %} Alternate Content {% endif %}',

            'Placement Admin Email' => '{{ placement.emailAddress }}',
        ];
	}

    /**
     * Returns an array of twig templates
     *
     * @param string twig template
     *
     * @return array
     */
    static function divideEmailBlocks($template) {
        $emailTemplates = [];
        $matches = [];
        $regex = [
            'subject'   => '/{% block subject %}(.*){% endblock subject %}/s',
            'body_text' => '/{% block body_text %}(.*){% endblock body_text %}/s',
            'body_html' => '/{% block body_html %}(.*){% endblock body_html %}/s',
        ];

        foreach($regex as $key => $expression){
            preg_match($expression, $template, $matches);

            $emailTemplates[$key] = ($key == 'body_text') ? nl2br( $matches[1] ) : $matches[1];
        }
        return $emailTemplates;
    }

    /**
     * Returns an twig template
     *
     * @param array( subject, body_text, body_html ) twig templates
     *
     * @return string twig template
     */
    static function combineEmailBlocks($templates) {
        $templates['subject'] = html_entity_decode( $templates['subject'], ENT_QUOTES );

        $matches = [];
        $bodyText = ( isset( $templates['body_text'] ) ) ? $templates['body_text'] : $templates['body_html'];
        $bodyText = ( preg_match( '/<!-- BODY -->(.*)<!-- \/BODY -->/s', $bodyText, $matches ) ) ? $matches[1] : $bodyText;
        $bodyText = str_replace( ["<i>", "</i>"] , ["_", "_"], $bodyText );
        $bodyText = str_replace( "<a>", "|a", $bodyText );
        $bodyText = str_replace( "<li", " - <li", $bodyText);
        $bodyText = str_replace( "|raw ", "|raw|striptags ", $bodyText);
        $bodyText = strip_tags( $bodyText );
        $bodyText = html_entity_decode( $bodyText, ENT_QUOTES );
        $bodyText = trim( $bodyText );
        $bodyText = preg_replace( '/\n\s+/', PHP_EOL.PHP_EOL, $bodyText );

        return  '{% block subject %}'.
                strip_tags($templates['subject']) .
                '{% endblock subject %}{% block body_text %}'.
                //strip_tags( str_replace("<br />", PHP_EOL, $templates['body_html'] ) ) .
                $bodyText.
                '{% endblock body_text %}{% block body_html %}'.
                $templates['body_html'].
                '{% endblock body_html %}';
    }
}