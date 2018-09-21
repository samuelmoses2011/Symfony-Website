jQuery(document).ready(function ($) {
    $(document).foundation();
    $('#application_exit_without_savings, #application_exit_without_savings, #application_info_incorrect').click(function () {
        var $form = $(this).parents('form');
        $form.attr('novalidate', 'novalidate');
    });
    $('select[name="application[current_grade]"]').on('change', function () {
        var thisSelectedIndex = $(this)[0].selectedIndex;
        $('select[name="application[next_grade]"] option').removeAttr('selected');
        console.log(thisSelectedIndex);
        if (thisSelectedIndex != 0) {
            var $option = $('select[name="application[next_grade]"] option:eq(' + thisSelectedIndex + ')');
            $option.prop('selected', 'selected');
        } else {
            var $option = $('select[name="application[next_grade]"] option').first();
            $option.prop('selected', 'selected');
        }
    });


    $('select[name="application[first_choice][school]"],select[name="application[second_choice][school]"],select[name="application[third_choice][school]"]').each(function () {
        var selectedOptionIndexes = [];
        $('select[name$="[school]"] option').removeAttr('disabled');
        $('select[name$="[school]"]').each(function () {
            var thisSelectedIndex = $(this)[0].selectedIndex;
            if (thisSelectedIndex != 0) {
                selectedOptionIndexes.push(thisSelectedIndex);
            }
        });
        $('select[name$="[school]"]').each(function () {
            var $select = $(this);
            var $options = $(this).find('option');
            $.each(selectedOptionIndexes, function (index, value) {
                var $option = $($options[value]);
                if ($select[0].selectedIndex == 0) {
                    $option.attr('disabled', 'disabled');
                }
                if ($select[0].selectedIndex != value) {
                    $option.attr('disabled', 'disabled');
                }
            });
        });
    });
    $('select[name="application[first_choice][school]"],select[name="application[second_choice][school]"],select[name="application[third_choice][school]"]').on('change', function () {
        var selectedOptionIndexes = [];
        $('select[name$="[school]"] option').removeAttr('disabled');
        $('select[name$="[school]"]').each(function () {
            var thisSelectedIndex = $(this)[0].selectedIndex;
            if (thisSelectedIndex != 0) {
                selectedOptionIndexes.push(thisSelectedIndex);
            }
        });
        $('select[name$="[school]"]').each(function () {
            var $select = $(this);
            var $options = $(this).find('option');
            $.each(selectedOptionIndexes, function (index, value) {
                var $option = $($options[value]);
                if ($select[0].selectedIndex == 0) {
                    $option.attr('disabled', 'disabled');
                }
                if ($select[0].selectedIndex != value) {
                    $option.attr('disabled', 'disabled');
                }
            });
        });
    });
    $('select[name="application[second_choice][school]"],select[name="application[third_choice][school]"]').each(function() {
        var $yesOption, $noOption;
        if( $(this).prop('id').indexOf('second') != -1 ) {
            $yesOption = $('#application_second_choice_sibling_0');
            $noOption = $('#application_second_choice_sibling_1');
        }
        if( $(this).prop('id').indexOf('third') != -1 ) {
            $yesOption = $('#application_third_choice_sibling_0');
            $noOption = $('#application_third_choice_sibling_1');
        }
        if( $(this).val() != '' ) {
            $yesOption.attr( 'required' , 'required' );
            $noOption.attr( 'required' , 'required' );
        } else {
            $yesOption.removeAttr( 'required' );
            $noOption.removeAttr( 'required' );
        }
    });
    $('select[name="application[second_choice][school]"],select[name="application[third_choice][school]"]').on('change', function() {
        var $yesOption, $noOption;
        if( $(this).prop('id').indexOf('second') != -1 ) {
            $yesOption = $('#application_second_choice_sibling_0');
            $noOption = $('#application_second_choice_sibling_1');
        }
        if( $(this).prop('id').indexOf('third') != -1 ) {
            $yesOption = $('#application_third_choice_sibling_0');
            $noOption = $('#application_third_choice_sibling_1');
        }
        if( $(this).val() != '' ) {
            $yesOption.attr( 'required' , 'required' );
            $noOption.attr( 'required' , 'required' );
        } else {
            $yesOption.removeAttr( 'required' );
            $noOption.removeAttr( 'required' );
        }
    });
    $('input[name="application[first_choice][sibling]"],input[name="application[second_choice][sibling]"],input[name="application[third_choice][sibling]"]').each(function () {
        if ($(this).prop('checked')) {
            var $parent = $(this).parent().parent().parent().parent().parent().parent().parent();
            var $inputFieldDiv = $parent.next().next();
            var $inputField = $inputFieldDiv.find('input');
            if ($(this).val() == 1) {
                //Need to show sibling ID field.
                $inputFieldDiv.removeClass('hide');
                $inputField.attr('required', 'required');
            } else {
                //Need to hide.
                $inputFieldDiv.addClass('hide');
                $inputField.removeAttr('required');
            }
        }
    });
    $('input[name="application[first_choice][sibling]"],input[name="application[second_choice][sibling]"],input[name="application[third_choice][sibling]"]').on('change', function () {
        var $parent = $(this).parent().parent().parent().parent().parent().parent().parent();
        var $inputFieldDiv = $parent.next().next();
        var $inputField = $inputFieldDiv.find('input');
        if ($(this).val() == 1) {
            //Need to show sibling ID field.
            $inputFieldDiv.removeClass('hide');
            $inputField.attr('required', 'required');
        } else {
            //Need to hide.
            $inputFieldDiv.addClass('hide');
            $inputField.removeAttr('required');
        }
    });
    $('select[name="start[student_status]"]').on( 'change' , function () {
        if( $(this).val() == 'new' ) {
            //$('#new-modal').foundation('reveal','open');
        }
    });
    $('a.helper').on('click', function() {
        $('#magnetHelp').foundation('reveal','open');
        return false;
    });

    $('.set-address').click( function() {
        $this = $(this);
        $this.closest('div').find('input').val($(this).html());
        $this.closest('small').hide();
        return false;
    });

    $('#application_parentEmployment').change( function(){

        if( $(this).val() > 0 ){
            $('#application_parentEmployeeName').removeClass('hide');
            $("label[for='application_parentEmployeeName']").removeClass('hide');
            $("#application_parentEmployeeName").attr('required', 'required');

            $('#application_parentEmployeeLocation').removeClass('hide');
            $("label[for='application_parentEmployeeLocation']").removeClass('hide');
            $("#application_parentEmployeeLocation").attr('required', 'required');
        } else {
            $('#application_parentEmployeeName').addClass('hide');
            $("label[for='application_parentEmployeeName']").addClass('hide');
            $("#application_parentEmployeeName").removeAttr('required');

            $('#application_parentEmployeeLocation').addClass('hide');
            $("label[for='application_parentEmployeeLocation']").addClass('hide');
            $("#application_parentEmployeeLocation").removeAttr('required');
        }
    });

});
