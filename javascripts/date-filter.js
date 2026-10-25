(function ($) {
    'use strict';

    var selector = '#ngk-report .ngk-date-input';

    function attachPicker(input) {
        var field = $(input);
        if (field.hasClass('hasDatepicker')) {
            return;
        }

        var options = piwik.getBaseDatePickerOptions();
        // Search Console history can predate the creation of the Matomo site.
        $.extend(options, {
            dateFormat: 'dd-mm-yy',
            minDate: null,
            maxDate: null,
            showOn: 'both',
            buttonText: '▦'
        });
        var maxDate = field.attr('data-max-date');
        if (maxDate) {
            options.maxDate = $.datepicker.parseDate('yy-mm-dd', maxDate);
        }
        field.datepicker(options);
        field.next('.ui-datepicker-trigger').attr({
            'aria-label': _pk_translate('NeorGoogleKeywords_ChooseDate'),
            title: _pk_translate('NeorGoogleKeywords_ChooseDate')
        });
    }

    $(function () {
        $(selector).each(function () { attachPicker(this); });
    });

    // Also support report content inserted after the initial page load.
    $(document).on('focusin', selector, function () {
        if (!$(this).hasClass('hasDatepicker')) {
            attachPicker(this);
            $(this).datepicker('show');
        }
    });
}(jQuery));
