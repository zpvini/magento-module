jQuery.noConflict();

jQuery(document).ready(function ($) {

    $.ajaxSetup({
        method: 'POST',
        dataType: 'JSON',
        async: true
    });

    $.ajax({url: '/mundipagg/fcontrol/getConfig'}).done(function (data) {
        var fp = new FcontrolFingerprint();
        fp.send(data.key, data.sessionId);
    });

    $(document).ajaxComplete(function (event, request, settings) {
        if (typeof settings.url != 'undefined') {
            if (settings.url.match(/\/fcontrol\/fingerprint\//)) {
                var logRequest = settings.data;

                var logResponse = {
                    statusCode: request.status,
                    statusText: request.statusText
                };

                var responseText = request.responseText

                if (responseText.length > 0) {
                    logResponse.fcontrolResponse = request.responseText
                }

                logResponse = JSON.stringify(logResponse);

                $.ajaxSetup({
                    url: '/mundipagg/fcontrol/logFp',
                    method: 'POST',
                    dataType: 'JSON',
                    async: true
                });

                $.ajax({data: {event: 'Request', data: logRequest}})
                    .always(function () {
                        $.ajax({
                            data: {
                                event: 'Response',
                                data: logResponse
                            }
                        });
                    });
            }
        }

    });

    var reportError = function (message) {
        jQuery.ajax({
            method: 'POST',
            url: '/mundipagg/fcontrol/reportError',
            dataType: 'JSON',
            data: {message: message}
        });
    };

});