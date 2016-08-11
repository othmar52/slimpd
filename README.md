<p align="center">
  <a name="top" href="engine@gas-werk.org"><img 
  src="https://github.com/othmar52/slimpd/raw/master/skin/default/img/slimpd_logo_moustache_v2.png"></a>
</p>
<p align="center"><sup>PHP/JS/MySQL based MPD-web-client for large music collections
heavily inspired by <a href="http://ompd.pl"><strong>O!MPD</strong></a></sup></p>
***

## Features (most of them are working pretty well)
* massive performance one large music collections
 * check if music-collection had been updated runs with  120K Tracks per minute
* choose between controlling MPD and playing files directly in browser
* AJAX driven frontend
 * browser-history-support
 * browser played audio keeps playing during navigating
 * animated favicon which shows progress of currently played track
* integrated filebrowser
 * access files in realtime without the need+benefit of doing an import first
 * having thousands of files or directories within a directory does not affect GUI-performance
* unifiying of misspelled or different notations of tag information
* extracting useable information from filesystem of music files with no tags
* non-destructive approach (your music-collection wont get touched at all)
* separate linking of each remixers and featured artists
* localization of the whole frontend
 * english
 * german
* displaying waveforms of currently played track
* toggle between displaying tags and displaying filepath
* autocomplete widget in searchfield with filter-support (all,artist,album,label)
* typos in searchterm gets autocorrected (if possible)
* mpd.conf:max_playlist_length="100000" does not affect gui performance
* support to control [xwax-osc](https://github.com/oligau/xwax-1.5-osc) (Digital Vinyl System)
* highly configurable in many ways
* mobile friendly (not implemented yet)


[Screenshots of current development status](https://github.com/othmar52/slimpd/wiki/Gallery)

[requirements & installation instructions](https://github.com/othmar52/slimpd/wiki/Installation)
