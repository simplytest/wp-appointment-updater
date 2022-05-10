(function ( $ ) {
    // Strict mode: catch coding bloopers, throwing exception
    // Prevents or throws error for unsafe actions, i.e. gaining access to the global object
    // -> "secure" JS
    "use strict";
    $( document ).ready( function() {
        if (typeof admin_ajaxObj === 'undefined') {
            $('#button-refetch-data-id').hide();
            $('#button-refetch-data-id').click(e => e.preventDefault());
            return '';
        }
        var data = {
            action: admin_ajaxObj.ajax_callback,
            security: admin_ajaxObj.ajax_nonce,
            id: 1
        }
        $('#button-refetch-data-id').click(function (e) {
            e.preventDefault();
            $.post(
                admin_ajaxObj.ajax_url,
                data,
                function (response) {
                    // ERROR HANDLING
                    if (!response.success) {
                        if (!response.data)
                            $("#popup-id-on-clear").html('AJAX Error: no response');
                        else 
                            $("#popup-id-on-clear").html(response.data.error);
                    } else {
                        $("#popup-id-on-clear").html("Data will be refetched...");
                        $("#popup-id-on-clear").show();
                        setTimeout(function() {
                            $("#popup-id-on-clear").hide();
                        }, 5000);
                    }
                }
            )
        }) 
      
    });
})( jQuery );