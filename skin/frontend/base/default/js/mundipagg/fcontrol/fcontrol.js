var includeFile = function (filename, onload) {

    if ((typeof jQuery === 'undefined') && !window.jQuery) {
        var body = document.getElementsByTagName('body')[0];
        var script = document.createElement('script');
        script.src = filename;
        script.type = 'text/javascript';

        script.onload = script.onreadystatechange = function () {
            if (script.readyState) {
                if (script.readyState === 'complete' || script.readyState === 'loaded') {
                    script.onreadystatechange = null;
                    onload();
                }

            } else {
                onload();
            }
        };
        body.appendChild(script);

    } else {
        onload();
    }

};

var sendSessionId = function () {
    $.noConflict();

    jQuery(document).ready(function ($) {
        var fcontrolScript = "https://static.fcontrol.com.br/fingerprint/fcontrol.min-ed.js";

        $.ajax({
            url: fcontrolScript,
            dataType: "script"
        })
            .error(function () {
                alert('success');
                var fp = new FcontrolFingerprint();
                fp.send("chaveUsuario", "SessionId");
            })
            .success(function () {
                alert('Error!');
            });

    });
};

var jQueryScript = "https://code.jquery.com/jquery-1.11.3.min.js";

includeFile(jQueryScript, sendSessionId);