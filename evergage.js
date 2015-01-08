/**
 * Set up the Evergage namespace.
 */
var _aaq = window._aaq || (window._aaq = []);

/* $Id$ */
(function (window, document) {

    // Initialize the Evergage account information.
    var evergageAccount = EvergageSettings.settings['setAccount'];
    var evergageDataset = EvergageSettings.settings['setDataset'];

    var evergageHost = "cdn.evergage.com";
    if (evergageAccount == "localtest") {
        evergageHost = "localtest.evergage.com:" + ( document.location.protocol == "https:"  ? "8443" : "8080");
    }

    var evergageScriptURL =
        document.location.protocol + "//" + evergageHost + "/beacon/" +
            evergageAccount + "/" + evergageDataset + "/scripts/evergage.min.js";


    // Send the parameters over.
    for (var key in EvergageSettings.settings || {}) {
        var value = EvergageSettings.settings[key];
        window._aaq.push([key, value]);
    }
    window._aaq.push(['setUseSiteConfig', true]);

    for (var key in EvergageSettings.customVariables || {}) {
        var value = EvergageSettings.customVariables[key];
        window._aaq.push(['setCustomVariable', key, value]);
    }

    var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
    g.type = 'text/javascript';
    g.defer = true;
    g.async = true;
    g.src = evergageScriptURL;

    s.parentNode.insertBefore(g, s);

})( window, document);
