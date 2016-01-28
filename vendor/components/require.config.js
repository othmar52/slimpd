var components = {
    "packages": [
        {
            "name": "backbone",
            "main": "backbone-built.js"
        },
        {
            "name": "greensock-js",
            "main": "greensock-js-built.js"
        },
        {
            "name": "jquery",
            "main": "jquery-built.js"
        },
        {
            "name": "jquery-cookie",
            "main": "jquery-cookie-built.js"
        },
        {
            "name": "jquery-ui",
            "main": "jquery-ui-built.js"
        },
        {
            "name": "underscore",
            "main": "underscore-built.js"
        },
        {
            "name": "jplayer",
            "main": "jplayer-built.js"
        },
        {
            "name": "faviconx",
            "main": "faviconx-built.js"
        }
    ],
    "shim": {
        "backbone": {
            "deps": [
                "underscore"
            ],
            "exports": "Backbone"
        },
        "jquery-ui": {
            "deps": [
                "jquery"
            ],
            "exports": "jQuery"
        },
        "underscore": {
            "exports": "_"
        },
        "jplayer": {
            "deps": [
                "jquery"
            ]
        }
    },
    "baseUrl": "components"
};
if (typeof require !== "undefined" && require.config) {
    require.config(components);
} else {
    var require = components;
}
if (typeof exports !== "undefined" && typeof module !== "undefined") {
    module.exports = components;
}