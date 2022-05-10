(function ( $ ) {
    // Strict mode: catch coding bloopers, throwing exception
    // Prevents or throws error for unsafe actions, i.e. gaining access to the global object
    // -> "secure" JS
    "use strict";
    
    var variations = [];
    if (typeof SCRIPT_DATA.VARIATIONS === 'undefined') {
        SCRIPT_DATA.VARIATIONS = {};
    }
    if (typeof SCRIPT_DATA.SHOW_ALL === 'undefined') {
        SCRIPT_DATA.SHOW_ALL = false;
    }

    for (const [productId, productVariations] of Object.entries(SCRIPT_DATA.VARIATIONS)) {
        variations.push(clean_product_variations(productVariations, productId));
    }
    
    variations = variations.filter(arrays => arrays.length != 0).flat();
    variations = variations.sort((a, b) => a.reversed_sku_date.localeCompare(b.reversed_sku_date));
    variations = removeDuplicateKey(variations, 'productId');
    $( document ).ready( function() {
        $("div#next-appointments").data("vars",variations);
        $("div#next-appointments").prepend(createAppointmentNodes);
      
    });

    function createAppointmentNodes(elementPosition, oldHTML) {
        // Lightweight version of the actual document. Changes maded to this framgent don't affect the document
        // or incur any performance impact when changes are made.
        let documentFragment = document.createDocumentFragment();
        let variations = $("div#next-appointments").data("vars");
        let totalAppointments = variations.length;
        let numAppointments = SCRIPT_DATA.SHOW_ALL ? totalAppointments : Math.min(3, totalAppointments);
        for (var i = 0; i < numAppointments; ++i) {
            let p = document.createElement( 'p' );
                let a = document.createElement('a');
                a.setAttribute('href', variations[i].permalink);
                a.innerHTML = "<b>" + variations[i].sku_date + "</b><br/>";
                a.innerHTML += variations[i].productName;  
            p.appendChild(a);
            documentFragment.appendChild(p);
        }
        return documentFragment;
    }

    function clean_product_variations(productVariations, productKey) {
        let clearedProdVariations = []; // Array of JavaScript objects
        let [productId, productName] = productKey.split("_x_");
        JSON.parse(productVariations.body).forEach(function(variation) {
            if (variation.status === "publish" && !variation.sku.toLowerCase().includes('individuell')) {
                let dates = normalize_date(variation.sku);
                clearedProdVariations.push(
                    {
                        "productId" : productId,
                        "productName" : productName,
                        "sku_date" : dates[0],
                        "reversed_sku_date" : dates[1], 
                        "permalink" : variation.permalink,
                    }
                );
            }
        });
        return clearedProdVariations;
    }

    function normalize_date(myDate) {
        let normalizedDates = [];
        let reversedNormalizeDates = [];
        let endIndex = myDate.indexOf('(');
        myDate = endIndex == -1 ? myDate.substring(0) : myDate.substring(0, endIndex);
        myDate = myDate.replace('–', '-').split('-');
        myDate.forEach( date => {
            date = date.trim();
            date = date.split('.');
            if (date[2].length == 2)
                date[2] = "20".concat(date[2]);
            
            normalizedDates.push(date.join('.'));
            reversedNormalizeDates.push(date.reverse().join('.'));
        });
        return [normalizedDates.join(' - '), reversedNormalizeDates[0]];
    }

    function removeDuplicateKey(_array, _key) {
        return _array.filter((arrayValue, arrayIndex, arrayParent) =>
            arrayIndex === arrayParent.findIndex((otherArrayVal) => (otherArrayVal[_key] === arrayValue[_key]))
        );
    }
})( jQuery );