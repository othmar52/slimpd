# slimpd
PHP/JS/MySQL based MPD-web-client for large music collections
heavily inspired by http://ompd.pl


## Features (most of them are working pretty well)
* massive performance one large music collections - especially the database-updates after the initial update
* unifiying of misspelled or different notations of tag information
* extracting useable information from filesystem of music files with no tags
* non-destructive approach (your music-collection wont get touched at all)
* separate linking of each remixers and featured artists
* localization of the whole frontend (currently en/de)
* displaying waveforms of currently played track
* choose between controlling MPD and playing files directly in browser
* AJAX driven frontend with browser-history-support (browser played audio keeps playing during navigating)
* autocomplete widget in searchfield with filter-support (all,artist,album,label)
* typos in searchterm gets autocorrected (if possible)
* mpd.conf:max_playlist_length="100000" does not affect gui performance
* support to control [xwax-osc](https://github.com/oligau/xwax-1.5-osc) (Digital Vinyl System)
* mobile friendly (not implemented yet)


[Screenshots of current development status](https://github.com/othmar52/slimpd/wiki)

[requirements & installation instructions](https://github.com/othmar52/slimpd/wiki/Installation)
