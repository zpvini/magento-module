jQuery.noConflict();

jQuery(document).ready(function ($) {
    var toggleRecurrencyFields = function (show) {
        var container = $('#product_info_tabs_group_18_content');
        var recurrencesInput = $('input[id=mundipagg_recurrences]');
        var fields = $(container).find('tr');

        $(fields).each(function (i, v) {
            if (i > 0) {
                if (show) {
                    $(v).show();
                    recurrencesInput.addClass('required-entry');

                } else {
                    $(v).hide();
                    recurrencesInput.removeClass('required-entry');
                }
            }
        });
    };

    var recurrencySelector = '#mundipagg_recurrent';

    if ($(recurrencySelector).val() == '1') {
        toggleRecurrencyFields(true);

    } else {
        toggleRecurrencyFields(false);
    }

    $(recurrencySelector).change(function () {
        if ($(this).val() == '1') {
            toggleRecurrencyFields(true);
        } else {
            toggleRecurrencyFields(false);
        }
    });
});