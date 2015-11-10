<?php
namespace Slimpd\modules\localization;
class Localization
{
	private static $lang = array(
		'default' => array(
			'menu.library' => 'Library',
			'menu.playlist' => 'Now Playing',
			'menu.playlists' => 'Playlists',
			'menu.favorites' => 'Favorites',
			'menu.importer' => 'Importer',
			'menu.artists' => 'Artists',
			'menu.genres' => 'Genres',
			'menu.labels' => 'Labels',
			'menu.filebrowser' => '<i class="fa fa-folder-open fa-lg"></i>',
			
			'error.mpdconnect' => 'Can\'t connect to mpd. please check your settings!',
			'error.mpdwrite' => 'Can\'t write to mpd-socket. please check your settings!',
			'error.mpdgeneral' => 'MPD general error: %s',
			'error.mpdconnectionclosed' => '[b]Music Player Daemon error[/b][br]Connection unexpectedly closed',
			'error.mpd.dbfile' => 'ERROR: databasefile "%s" not readable',
			
			'database.connect' => 'Can\'t connect to database. please check your settings!',
			
			'importer.image.keep' => 'Keeping    %s',
			'importer.image.destroy' => 'Destroying %s',
			'importer.destroyimages.result' => 'Deleted %s images with a total size of %s',
			
			'importer.fixgenre.msg' => 'Updating album:%s with genres:%s',
			'importer.fixlabel.msg' => 'Updating album:%s with labels:%s',
			
			'general.artist' => 'Artist',
			'general.title' => 'Title',
			'general.label' => 'Label',
			'general.year' => 'Year',
			'general.id' => 'ID',
			'general.added' => 'Added',
			'general.genre' => 'Genre',
			'general.fileformat' => 'File format',
			'general.filetype' => 'File type',
		),
		'de' => array(
			'menu.library' => 'Sammlung',
			'menu.playlXist' => 'Now Playing',
			'menu.favorXites' => 'Favorites',
			
			'error.mpdgeneral' => 'MPD-Fehler: %s',
			
			'importer.image.keep' => 'Behalte %s',
			'importer.image.destroy' => 'Lösche  %s',
			'importer.destroyimages.result' => '%s Bilddateien gelöscht mit einer Gesamtgröße von %s',
		)
	);
	public static function str($itemkey, $vars = array()) {
		// TODO: get from separate langfiles
		foreach(array('de', 'default') as $langkey) {
			if(isset(self::$lang[$langkey][$itemkey])) {
				if(count($vars) === 0) {
					return self::$lang[$langkey][$itemkey];
				} else {
					return vsprintf(self::$lang[$langkey][$itemkey], $vars);
				}
			}
		}
		return 'TRANSLATE:' . $itemkey;
	}

	public static function getAllCommonTranslationItems($str = 'general') {
		$return = array();
		$str .= '.';
		foreach(self::$lang['default'] as $key => $value) {
			if(preg_match("/^".$str."/", $key)) {
				$return[substr($key,strlen($str))] = self::str($key);
			}
		}
		return $return;
	}
}
