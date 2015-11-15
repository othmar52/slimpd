

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


--
-- table structure `album`
--

CREATE TABLE IF NOT EXISTS `album` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` text,
  `relativePath` text,
  `relativePathHash` varchar(11) NOT NULL DEFAULT '',
  `year` smallint(4) unsigned DEFAULT NULL,
  `month` tinyint(2) unsigned DEFAULT NULL,
  `artistId` varchar(255) NOT NULL DEFAULT '',
  `genreId` varchar(255) NOT NULL DEFAULT '',
  `labelId` varchar(255) NOT NULL DEFAULT '',
  `added` int(10) unsigned NOT NULL DEFAULT '0',
  `filemtime` int(10) unsigned NOT NULL DEFAULT '0',
  `discs` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `importStatus` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `lastScan` int(11) unsigned NOT NULL DEFAULT '0',
  `albumDr` tinyint(3) unsigned  DEFAULT NULL,
  `trackCount` smallint(5) unsigned  DEFAULT '0',
  `isMixed` smallint(1) unsigned DEFAULT NULL,
  `isJumble` smallint(1) unsigned DEFAULT NULL,
  `isLive` smallint(1) unsigned DEFAULT NULL,
  `discogsId` varchar(64) NOT NULL DEFAULT '',
  `rolldabeatsId` varchar(64) NOT NULL DEFAULT '',
  `beatportId` varchar(64) NOT NULL DEFAULT '',
  `junoId` varchar(64) NOT NULL DEFAULT '',
  `catalogNr` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `artistId` (`artistId`),
  KEY `year` (`year`,`month`),
  KEY `labelId` (`labelId`),
  KEY `genreId` (`genreId`),
  KEY `added` (`added`),
  KEY `importStatus` (`importStatus`),
  KEY `isMixed` (`isMixed`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `album`
  ADD FULLTEXT KEY `relativePath` (`relativePath`),
  ADD FULLTEXT KEY `title` (`title`);
-- --------------------------------------------------------

--
-- table structure `bitmap`
--
	
CREATE TABLE IF NOT EXISTS `bitmap` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `relativePath` text,
  `relativePathHash` varchar(11) NOT NULL DEFAULT '',
  `filemtime` int(11) unsigned NOT NULL DEFAULT '0',
  `filesize` int(11) unsigned NOT NULL DEFAULT '0',
  `mimeType` varchar(64) NOT NULL DEFAULT '',
  `width` int(7) unsigned DEFAULT NULL,
  `height` int(7) unsigned DEFAULT NULL,
  `albumId` int(11) unsigned DEFAULT NULL,
  `trackId` int(11) unsigned DEFAULT NULL,
  `rawTagDataId` int(11) unsigned DEFAULT NULL,
  `embedded` tinyint(4) unsigned DEFAULT NULL,
  `importStatus` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `error` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `relativePathHash` (`relativePathHash`),
  KEY `albumId` (`albumId`),
  KEY `trackId` (`trackId`),
  KEY `rawTagDataId` (`rawTagDataId`),
  KEY `importStatus` (`importStatus`),
  KEY `embedded` (`embedded`),
  KEY `error` (`error`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `bitmap`
  ADD FULLTEXT KEY `relativePath` (`relativePath`);
-- --------------------------------------------------------

--
-- table structure `cache`
--

CREATE TABLE IF NOT EXISTS `cache` (
  `id` varchar(20) NOT NULL DEFAULT '',
  `profile` int(11) NOT NULL DEFAULT '0',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0',
  `idle_time` int(10) unsigned NOT NULL DEFAULT '0',
  `filesize` bigint(20) unsigned NOT NULL DEFAULT '0',
  `filemtime` int(10) unsigned NOT NULL DEFAULT '0',
  `tag_hash` varchar(32) NOT NULL DEFAULT '',
  `zip_hash` varchar(32) NOT NULL DEFAULT '',
  `relative_file` varchar(255) NOT NULL DEFAULT '',
  `importStatus` tinyint(1) unsigned NOT NULL DEFAULT '0',
  KEY `id` (`id`),
  KEY `profile` (`profile`),
  KEY `idle_time` (`idle_time`),
  KEY `relative_file` (`relative_file`),
  KEY `importStatus` (`importStatus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `counter`
--

CREATE TABLE IF NOT EXISTS `counter` (
  `sid` varchar(40) NOT NULL DEFAULT '',
  `album_id` varchar(11) NOT NULL DEFAULT '',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `flag` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `time` int(10) unsigned NOT NULL DEFAULT '0',
  KEY `sid` (`sid`),
  KEY `album_id` (`album_id`),
  KEY `user_id` (`user_id`),
  KEY `time` (`time`),
  KEY `flag` (`flag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `country`
--

CREATE TABLE IF NOT EXISTS `country` (
  `iso` char(2) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `code` smallint(6) unsigned NOT NULL DEFAULT '0',
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `favorite`
--

CREATE TABLE IF NOT EXISTS `favorite` (
  `name` varchar(255) NOT NULL DEFAULT '',
  `comment` varchar(255) NOT NULL DEFAULT '',
  `stream` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `favorite_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`favorite_id`),
  KEY `comment` (`comment`),
  KEY `name` (`name`),
  KEY `stream` (`stream`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=16 ;

-- --------------------------------------------------------

--
-- table structure `favoriteitem`
--

CREATE TABLE IF NOT EXISTS `favoriteitem` (
  `track_id` varchar(20) NOT NULL DEFAULT '',
  `stream_url` varchar(255) NOT NULL DEFAULT '',
  `position` smallint(5) unsigned NOT NULL DEFAULT '0',
  `favorite_id` int(10) unsigned NOT NULL DEFAULT '0',
  KEY `favorite_id` (`favorite_id`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `artist`
--

CREATE TABLE IF NOT EXISTS `artist` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `article` varchar(24) NOT NULL DEFAULT '',
  `az09` varchar(255) NOT NULL DEFAULT '0',
  `trackCount` int(11) unsigned  DEFAULT '0',
  `albumCount` int(11) unsigned  DEFAULT '0',
  PRIMARY KEY `id` (`id`),
  KEY `az09` (`az09`),
  KEY `trackCount` (`trackCount`),
  KEY `albumCount` (`albumCount`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `genre`
--

CREATE TABLE IF NOT EXISTS `genre` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `parent` int(4) unsigned NOT NULL DEFAULT '0',
  `az09` varchar(255) NOT NULL DEFAULT '0',
  `trackCount` int(11) unsigned  DEFAULT '0',
  `albumCount` int(11) unsigned  DEFAULT '0',
  PRIMARY KEY `id` (`id`),
  KEY `az09` (`az09`),
  KEY `trackCount` (`trackCount`),
  KEY `albumCount` (`albumCount`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `label`
--

CREATE TABLE IF NOT EXISTS `label` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `az09` varchar(255) NOT NULL DEFAULT '0',
  `trackCount` int(11) unsigned  DEFAULT '0',
  `albumCount` int(11) unsigned  DEFAULT '0',
  PRIMARY KEY `id` (`id`),
  KEY `az09` (`az09`),
  KEY `trackCount` (`trackCount`),
  KEY `albumCount` (`albumCount`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `player`
--

CREATE TABLE IF NOT EXISTS `player` (
  `player_name` varchar(255) NOT NULL DEFAULT '',
  `player_type` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `player_host` varchar(255) NOT NULL DEFAULT '',
  `player_port` smallint(5) unsigned NOT NULL DEFAULT '0',
  `player_pass` varchar(255) NOT NULL DEFAULT '',
  `media_share` varchar(255) NOT NULL DEFAULT '',
  `mute_volume` smallint(5) unsigned NOT NULL DEFAULT '0',
  `player_id` int(10) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`player_id`),
  KEY `httpq_name` (`player_name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=19 ;

-- --------------------------------------------------------

--
-- table structure `random`
--

CREATE TABLE IF NOT EXISTS `random` (
  `sid` varchar(40) NOT NULL DEFAULT '',
  `track_id` varchar(20) NOT NULL DEFAULT '',
  `position` smallint(5) unsigned NOT NULL DEFAULT '0',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0',
  KEY `sid` (`sid`),
  KEY `track_id` (`track_id`),
  KEY `position` (`position`),
  KEY `create_time` (`create_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `server`
--

CREATE TABLE IF NOT EXISTS `server` (
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `session`
--

CREATE TABLE IF NOT EXISTS `session` (
  `logged_in` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `hit_counter` int(10) unsigned NOT NULL DEFAULT '0',
  `visit_counter` int(10) unsigned NOT NULL DEFAULT '0',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0',
  `pre_login_time` bigint(20) unsigned NOT NULL DEFAULT '0',
  `login_time` int(10) unsigned NOT NULL DEFAULT '0',
  `idle_time` int(10) unsigned NOT NULL DEFAULT '0',
  `ip` varchar(255) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `sid` varchar(40) NOT NULL DEFAULT '',
  `sign` varchar(40) NOT NULL DEFAULT '',
  `seed` varchar(40) NOT NULL DEFAULT '',
  `skin` varchar(255) NOT NULL DEFAULT 'ompd_default',
  `random_blacklist` varchar(255) NOT NULL DEFAULT '',
  `thumbnail` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `thumbnail_size` tinyint(3) unsigned NOT NULL DEFAULT '100',
  `stream_id` int(10) NOT NULL DEFAULT '-1',
  `download_id` int(10) NOT NULL DEFAULT '-1',
  `player_id` int(10) NOT NULL DEFAULT '1',
  KEY `user_id` (`user_id`),
  KEY `idle_time` (`idle_time`),
  KEY `sid` (`sid`),
  KEY `ip` (`ip`,`pre_login_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `share_download`
--

CREATE TABLE IF NOT EXISTS `share_download` (
  `ip` varchar(255) NOT NULL DEFAULT '',
  `sid` varchar(40) NOT NULL DEFAULT '',
  `album_id` varchar(11) NOT NULL DEFAULT '',
  `download_id` tinyint(4) NOT NULL DEFAULT '0',
  `expire_time` int(10) unsigned NOT NULL DEFAULT '0',
  KEY `album_id` (`album_id`),
  KEY `expire_time` (`expire_time`),
  KEY `sid` (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `share_stream`
--

CREATE TABLE IF NOT EXISTS `share_stream` (
  `ip` varchar(255) NOT NULL DEFAULT '',
  `sid` varchar(255) NOT NULL DEFAULT '',
  `album_id` varchar(11) NOT NULL DEFAULT '',
  `stream_id` tinyint(4) NOT NULL DEFAULT '0',
  `expire_time` int(10) unsigned NOT NULL DEFAULT '0',
  KEY `sid` (`sid`),
  KEY `album_id` (`album_id`),
  KEY `expire_time` (`expire_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `track`
--

CREATE TABLE IF NOT EXISTS `track` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `artistId` varchar(255) NOT NULL DEFAULT '',
  `featuringId` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `remixerId` varchar(255) NOT NULL DEFAULT '',
  `relativePath` text NOT NULL,
  `relativePathHash` varchar(11) NOT NULL,
  `directoryPathHash` varchar(11) NOT NULL,
  
  `fingerprint` varchar(32) NOT NULL DEFAULT '',
  `mimeType` varchar(64) NOT NULL DEFAULT '',
  `filesize` bigint(20) unsigned NOT NULL DEFAULT '0',
  `filemtime` int(10) unsigned NOT NULL DEFAULT '0',
  
  `miliseconds` int(10) unsigned NOT NULL DEFAULT '0',
  `audioBitrate` int(10) unsigned NOT NULL DEFAULT '0',
  `audioBitsPerSample` int(10) unsigned NOT NULL DEFAULT '0',
  `audioSampleRate` int(10) unsigned NOT NULL DEFAULT '0',
  `audioChannels` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `audioLossless` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `audioCompressionRatio` double unsigned NOT NULL DEFAULT '0',
  `audioDataformat` varchar(64) NOT NULL DEFAULT '',
  `audioEncoder` varchar(64) NOT NULL DEFAULT '',
  `audioProfile` varchar(64) NOT NULL DEFAULT '',
  
  `videoDataformat` varchar(64) NOT NULL DEFAULT '',
  `videoCodec` varchar(64) NOT NULL DEFAULT '',
  `videoResolutionX` int(10) unsigned NOT NULL DEFAULT '0',
  `videoResolutionY` int(10) unsigned NOT NULL DEFAULT '0',
  `videoFramerate` int(10) unsigned NOT NULL DEFAULT '0',
  
  `disc` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `number` varchar(8) DEFAULT NULL,
  `error` varchar(255) NOT NULL DEFAULT '',
  `albumId` varchar(11) NOT NULL DEFAULT '',
  `transcoded` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `importStatus` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `lastScan` int(11) unsigned NOT NULL DEFAULT '0',
  `genreId` varchar(255) NOT NULL DEFAULT '',
  `labelId` varchar(255) NOT NULL DEFAULT '',
  `catalogNr` varchar(64) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `year` smallint(4) unsigned DEFAULT NULL,
  
  `isMixed` smallint(1) unsigned DEFAULT NULL,
  `discogsId` varchar(64) NOT NULL DEFAULT '',
  `rolldabeatsId` varchar(64) NOT NULL DEFAULT '',
  `beatportId` varchar(64) NOT NULL DEFAULT '',
  `junoId` varchar(64) NOT NULL DEFAULT '',
  
  `dr` tinyint(3) unsigned  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `artistId` (`artistId`),
  KEY `featuringId` (`featuringId`),
  KEY `title` (`title`),
  KEY `remixerId` (`remixerId`),
  KEY `relativePathHash` (`relativePathHash`),
  KEY `fingerprint` (`fingerprint`),
  KEY `audioDataformat` (`audioDataformat`),
  KEY `videoDataformat` (`videoDataformat`),
  KEY `albumId` (`albumId`,`disc`),
  KEY `importStatus` (`importStatus`),
  KEY `genreId` (`genreId`),
  KEY `labelId` (`labelId`),
  KEY `error` (`error`),
  KEY `transcoded` (`transcoded`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `track`
  ADD FULLTEXT KEY `relativePath` (`relativePath`);
  
  
  
-- --------------------------------------------------------

--
-- table structure `rawtagdata`
--

CREATE TABLE IF NOT EXISTS `rawtagdata` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `artist` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `album` varchar(255) NOT NULL DEFAULT '',
  `genre` varchar(255) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `year` varchar(255) NOT NULL DEFAULT '',
  `date` varchar(255) NOT NULL DEFAULT '',
  `publisher` varchar(255) NOT NULL DEFAULT '',
  `trackNumber` varchar(255) NOT NULL DEFAULT '',
  `totalTracks` varchar(255) NOT NULL DEFAULT '',
  `albumArtist` varchar(255) NOT NULL DEFAULT '',
  `remixer` varchar(255) NOT NULL DEFAULT '',
  `language` varchar(255) NOT NULL DEFAULT '',
  `country` varchar(255) NOT NULL DEFAULT '',
  
  `relativePath` text NOT NULL,
  `relativePathHash` varchar(11) NOT NULL,
  `relativeDirectoryPath` text NOT NULL,
  `relativeDirectoryPathHash` varchar(11) NOT NULL,
  `directoryMtime` int(10) unsigned NOT NULL DEFAULT '0',
  
  `initialKey` varchar(255) NOT NULL DEFAULT '',
  `textBpm` varchar(255) NOT NULL DEFAULT '',
  `textBpmQuality` varchar(255) NOT NULL DEFAULT '',
  `textPeakDb` varchar(255) NOT NULL DEFAULT '',
  `textPerceivedDb` varchar(255) NOT NULL DEFAULT '',
  `textRating` varchar(255) NOT NULL DEFAULT '',
  `textCatalogNumber` varchar(255) NOT NULL DEFAULT '',
  `textDiscogsReleaseId` varchar(255) NOT NULL DEFAULT '',
  `textUrlUser` varchar(255) NOT NULL DEFAULT '',
  `textSource` varchar(255) NOT NULL DEFAULT '',
  
  `fingerprint` varchar(32) NOT NULL DEFAULT '',
  `mimeType` varchar(64) NOT NULL DEFAULT '',
  `filesize` bigint(20) unsigned NOT NULL DEFAULT '0',
  `filemtime` int(10) unsigned NOT NULL DEFAULT '0',
  `miliseconds` varchar(24) NOT NULL DEFAULT '',
  `dynamicRange` varchar(255) NOT NULL DEFAULT '',
  
  `audioBitrate` varchar(24) NOT NULL DEFAULT '',
  `audioBitrateMode` varchar(24) NOT NULL DEFAULT '',
  `audioBitsPerSample` varchar(24) NOT NULL DEFAULT '0',
  `audioSampleRate` varchar(24) NOT NULL DEFAULT '0',
  `audioChannels` varchar(24) NOT NULL DEFAULT '0',
  `audioLossless` varchar(24) NOT NULL DEFAULT '0',
  `audioCompressionRatio` varchar(24) NOT NULL DEFAULT '0',
  `audioDataformat` varchar(64) NOT NULL DEFAULT '',
  `audioEncoder` varchar(64) NOT NULL DEFAULT '',
  `audioProfile` varchar(64) NOT NULL DEFAULT '',
  
  `videoDataformat` varchar(64) NOT NULL DEFAULT '',
  `videoCodec` varchar(64) NOT NULL DEFAULT '',
  `videoResolutionX` varchar(24) NOT NULL DEFAULT '0',
  `videoResolutionY` varchar(24) NOT NULL DEFAULT '0',
  `videoFramerate` varchar(24) NOT NULL DEFAULT '0',
  
  `lastScan` int(11) unsigned NOT NULL DEFAULT '0',
  `importStatus` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `error` text,

  PRIMARY KEY (`id`),
  KEY `relativePathHash` (`relativePathHash`),
  KEY `relativeDirectoryPathHash` (`relativeDirectoryPathHash`),
  KEY `fingerprint` (`fingerprint`),
  KEY `filemtime` (`filemtime`),
  KEY `directoryMtime` (`directoryMtime`),
  KEY `importStatus` (`importStatus`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `rawtagdata`
  ADD FULLTEXT KEY `error` (`error`);
  

-- --------------------------------------------------------

--
-- table structure `playnext`
--

CREATE TABLE IF NOT EXISTS `playnext` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tstamp` int(11) unsigned NOT NULL DEFAULT 0,
  `prio` int(11) unsigned NOT NULL DEFAULT 0,
  `userId` int(11) unsigned NOT NULL DEFAULT 0,
  `trackId` varchar(255) NOT NULL DEFAULT '',
  `relativePath` text NOT NULL,
  `relativePathHash` varchar(11) NOT NULL,
  `fingerprint` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `prio` (`prio`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

  
  
-- --------------------------------------------------------

--
-- table structure `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `username` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(40) NOT NULL DEFAULT '',
  `seed` varchar(40) NOT NULL DEFAULT '',
  `version` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_media` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_popular` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_favorite` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_playlist` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_play` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_add` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_stream` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_download` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_cover` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_record` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_statistics` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `access_admin` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`user_id`),
  KEY `username` (`username`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- table structure `editorial`
--

CREATE TABLE IF NOT EXISTS `editorial` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `crdate` int(11) unsigned NOT NULL DEFAULT '0',
  `tstamp` int(11) unsigned NOT NULL DEFAULT '0',
  `itemType` varchar(20) NOT NULL DEFAULT '',
  `itemId` int(11) NOT NULL DEFAULT '0',
  `relativePath` text NOT NULL,
  `relativePathHash` varchar(11) NOT NULL,
  `fingerprint` varchar(32) NOT NULL DEFAULT '',
  `column` varchar(32) NOT NULL DEFAULT '',
  `value` text,
  PRIMARY KEY (`id`),
  KEY `itemId` (`itemId`),
  KEY `relativePathHash` (`relativePathHash`),
  KEY `fingerprint` (`fingerprint`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `importer`
--

CREATE TABLE IF NOT EXISTS `importer` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `jobPhase` int(11) NOT NULL DEFAULT '0',
  `jobStart` DOUBLE NOT NULL,
  `jobLastUpdate` DOUBLE NOT NULL,
  `jobEnd` DOUBLE NOT NULL,
  `jobStatistics` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobStart` (`jobStart`),
  KEY `jobEnd` (`jobEnd`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Country code
-- 

INSERT INTO `country` VALUES ('af', 'Afghanistan', 4);
INSERT INTO `country` VALUES ('ax', 'Aland Islands', 248);
INSERT INTO `country` VALUES ('al', 'Albania', 8);
INSERT INTO `country` VALUES ('dz', 'Algeria', 12);
INSERT INTO `country` VALUES ('as', 'American Samoa', 16);
INSERT INTO `country` VALUES ('ad', 'Andorra', 20);
INSERT INTO `country` VALUES ('ao', 'Angola', 24);
INSERT INTO `country` VALUES ('ai', 'Anguilla', 660);
INSERT INTO `country` VALUES ('aq', 'Antarctica', 10);
INSERT INTO `country` VALUES ('ag', 'Antigua and Barbuda', 28);
INSERT INTO `country` VALUES ('ar', 'Argentina', 32);
INSERT INTO `country` VALUES ('am', 'Armenia', 51);
INSERT INTO `country` VALUES ('aw', 'Aruba', 533);
INSERT INTO `country` VALUES ('au', 'Australia', 36);
INSERT INTO `country` VALUES ('at', 'Austria', 40);
INSERT INTO `country` VALUES ('az', 'Azerbaijan', 31);
INSERT INTO `country` VALUES ('bs', 'Bahamas', 44);
INSERT INTO `country` VALUES ('bh', 'Bahrain', 48);
INSERT INTO `country` VALUES ('bd', 'Bangladesh', 50);
INSERT INTO `country` VALUES ('bb', 'Barbados', 52);
INSERT INTO `country` VALUES ('by', 'Belarus', 112);
INSERT INTO `country` VALUES ('be', 'Belgium', 56);
INSERT INTO `country` VALUES ('bz', 'Belize', 84);
INSERT INTO `country` VALUES ('bj', 'Benin', 204);
INSERT INTO `country` VALUES ('bm', 'Bermuda', 60);
INSERT INTO `country` VALUES ('bt', 'Bhutan', 64);
INSERT INTO `country` VALUES ('bo', 'Bolivia', 68);
INSERT INTO `country` VALUES ('ba', 'Bosnia and Herzegovina', 70);
INSERT INTO `country` VALUES ('bw', 'Botswana', 72);
INSERT INTO `country` VALUES ('bv', 'Bouvet Island', 74);
INSERT INTO `country` VALUES ('br', 'Brazil', 76);
INSERT INTO `country` VALUES ('io', 'British Indian Ocean Territory', 86);
INSERT INTO `country` VALUES ('bn', 'Brunei Darussalam', 96);
INSERT INTO `country` VALUES ('bg', 'Bulgaria', 100);
INSERT INTO `country` VALUES ('bf', 'Burkina Faso', 854);
INSERT INTO `country` VALUES ('bi', 'Burundi', 108);
INSERT INTO `country` VALUES ('kh', 'Cambodia', 116);
INSERT INTO `country` VALUES ('cm', 'Cameroon', 120);
INSERT INTO `country` VALUES ('ca', 'Canada', 124);
INSERT INTO `country` VALUES ('cv', 'Cape Verde', 132);
INSERT INTO `country` VALUES ('ky', 'Cayman Islands', 136);
INSERT INTO `country` VALUES ('cf', 'Central African Republic', 140);
INSERT INTO `country` VALUES ('td', 'Chad', 148);
INSERT INTO `country` VALUES ('cl', 'Chile', 152);
INSERT INTO `country` VALUES ('cn', 'China', 156);
INSERT INTO `country` VALUES ('cx', 'Christmas Island', 162);
INSERT INTO `country` VALUES ('cc', 'Cocos (Keeling) Islands', 166);
INSERT INTO `country` VALUES ('co', 'Colombia', 170);
INSERT INTO `country` VALUES ('km', 'Comoros', 174);
INSERT INTO `country` VALUES ('cg', 'Congo', 178);
INSERT INTO `country` VALUES ('cd', 'Congo, the Democratic Republic of the', 180);
INSERT INTO `country` VALUES ('ck', 'Cook Islands', 184);
INSERT INTO `country` VALUES ('cr', 'Costa Rica', 188);
INSERT INTO `country` VALUES ('ci', 'Cote D''Ivoire', 384);
INSERT INTO `country` VALUES ('hr', 'Croatia', 191);
INSERT INTO `country` VALUES ('cu', 'Cuba', 192);
INSERT INTO `country` VALUES ('cy', 'Cyprus', 196);
INSERT INTO `country` VALUES ('cz', 'Czech Republic', 203);
INSERT INTO `country` VALUES ('dk', 'Denmark', 208);
INSERT INTO `country` VALUES ('dj', 'Djibouti', 262);
INSERT INTO `country` VALUES ('dm', 'Dominica', 212);
INSERT INTO `country` VALUES ('do', 'Dominican Republic', 214);
INSERT INTO `country` VALUES ('ec', 'Ecuador', 218);
INSERT INTO `country` VALUES ('eg', 'Egypt', 818);
INSERT INTO `country` VALUES ('sv', 'El Salvador', 222);
INSERT INTO `country` VALUES ('gq', 'Equatorial Guinea', 226);
INSERT INTO `country` VALUES ('er', 'Eritrea', 232);
INSERT INTO `country` VALUES ('ee', 'Estonia', 233);
INSERT INTO `country` VALUES ('et', 'Ethiopia', 231);
INSERT INTO `country` VALUES ('fk', 'Falkland Islands (Malvinas)', 238);
INSERT INTO `country` VALUES ('fo', 'Faroe Islands', 234);
INSERT INTO `country` VALUES ('fj', 'Fiji', 242);
INSERT INTO `country` VALUES ('fi', 'Finland', 246);
INSERT INTO `country` VALUES ('fr', 'France', 250);
INSERT INTO `country` VALUES ('gf', 'French Guiana', 254);
INSERT INTO `country` VALUES ('pf', 'French Polynesia', 258);
INSERT INTO `country` VALUES ('tf', 'French Southern Territories', 260);
INSERT INTO `country` VALUES ('ga', 'Gabon', 266);
INSERT INTO `country` VALUES ('gm', 'Gambia', 270);
INSERT INTO `country` VALUES ('ge', 'Georgia', 268);
INSERT INTO `country` VALUES ('de', 'Germany', 276);
INSERT INTO `country` VALUES ('gh', 'Ghana', 288);
INSERT INTO `country` VALUES ('gi', 'Gibraltar', 292);
INSERT INTO `country` VALUES ('gr', 'Greece', 300);
INSERT INTO `country` VALUES ('gl', 'Greenland', 304);
INSERT INTO `country` VALUES ('gd', 'Grenada', 308);
INSERT INTO `country` VALUES ('gp', 'Guadeloupe', 312);
INSERT INTO `country` VALUES ('gu', 'Guam', 316);
INSERT INTO `country` VALUES ('gt', 'Guatemala', 320);
INSERT INTO `country` VALUES ('gg', 'Guernsey', 831);
INSERT INTO `country` VALUES ('gn', 'Guinea', 324);
INSERT INTO `country` VALUES ('gw', 'Guinea-Bissau', 624);
INSERT INTO `country` VALUES ('gy', 'Guyana', 328);
INSERT INTO `country` VALUES ('ht', 'Haiti', 332);
INSERT INTO `country` VALUES ('hm', 'Heard Island and Mcdonald Islands', 334);
INSERT INTO `country` VALUES ('va', 'Holy See (Vatican City State)', 336);
INSERT INTO `country` VALUES ('hn', 'Honduras', 340);
INSERT INTO `country` VALUES ('hk', 'Hong Kong', 344);
INSERT INTO `country` VALUES ('hu', 'Hungary', 348);
INSERT INTO `country` VALUES ('is', 'Iceland', 352);
INSERT INTO `country` VALUES ('in', 'India', 356);
INSERT INTO `country` VALUES ('id', 'Indonesia', 360);
INSERT INTO `country` VALUES ('ir', 'Iran, Islamic Republic of', 364);
INSERT INTO `country` VALUES ('iq', 'Iraq', 368);
INSERT INTO `country` VALUES ('ie', 'Ireland', 372);
INSERT INTO `country` VALUES ('il', 'Israel', 376);
INSERT INTO `country` VALUES ('it', 'Italy', 380);
INSERT INTO `country` VALUES ('jm', 'Jamaica', 388);
INSERT INTO `country` VALUES ('jp', 'Japan', 392);
INSERT INTO `country` VALUES ('je', 'Jersey', 832);
INSERT INTO `country` VALUES ('jo', 'Jordan', 400);
INSERT INTO `country` VALUES ('kz', 'Kazakhstan', 398);
INSERT INTO `country` VALUES ('ke', 'Kenya', 404);
INSERT INTO `country` VALUES ('ki', 'Kiribati', 296);
INSERT INTO `country` VALUES ('kp', 'Korea, Democratic People''s Republic of', 408);
INSERT INTO `country` VALUES ('kr', 'Korea, Republic of', 410);
INSERT INTO `country` VALUES ('kw', 'Kuwait', 414);
INSERT INTO `country` VALUES ('kg', 'Kyrgyzstan', 417);
INSERT INTO `country` VALUES ('la', 'Lao People''s Democratic Republic', 418);
INSERT INTO `country` VALUES ('lv', 'Latvia', 428);
INSERT INTO `country` VALUES ('lb', 'Lebanon', 422);
INSERT INTO `country` VALUES ('ls', 'Lesotho', 426);
INSERT INTO `country` VALUES ('lr', 'Liberia', 430);
INSERT INTO `country` VALUES ('ly', 'Libyan Arab Jamahiriya', 434);
INSERT INTO `country` VALUES ('li', 'Liechtenstein', 438);
INSERT INTO `country` VALUES ('lt', 'Lithuania', 440);
INSERT INTO `country` VALUES ('lu', 'Luxembourg', 442);
INSERT INTO `country` VALUES ('mo', 'Macao', 446);
INSERT INTO `country` VALUES ('mk', 'Macedonia, the Former Yugoslav Republic of', 807);
INSERT INTO `country` VALUES ('mg', 'Madagascar', 450);
INSERT INTO `country` VALUES ('mw', 'Malawi', 454);
INSERT INTO `country` VALUES ('my', 'Malaysia', 458);
INSERT INTO `country` VALUES ('mv', 'Maldives', 462);
INSERT INTO `country` VALUES ('ml', 'Mali', 466);
INSERT INTO `country` VALUES ('mt', 'Malta', 470);
INSERT INTO `country` VALUES ('mh', 'Marshall Islands', 584);
INSERT INTO `country` VALUES ('mq', 'Martinique', 474);
INSERT INTO `country` VALUES ('mr', 'Mauritania', 478);
INSERT INTO `country` VALUES ('mu', 'Mauritius', 480);
INSERT INTO `country` VALUES ('yt', 'Mayotte', 175);
INSERT INTO `country` VALUES ('mx', 'Mexico', 484);
INSERT INTO `country` VALUES ('fm', 'Micronesia, Federated States of', 583);
INSERT INTO `country` VALUES ('md', 'Moldova, Republic of', 498);
INSERT INTO `country` VALUES ('mc', 'Monaco', 492);
INSERT INTO `country` VALUES ('mn', 'Mongolia', 496);
INSERT INTO `country` VALUES ('me', 'Montenegro', 499);
INSERT INTO `country` VALUES ('me', 'Montenegro', 499);
INSERT INTO `country` VALUES ('ms', 'Montserrat', 500);
INSERT INTO `country` VALUES ('ma', 'Morocco', 504);
INSERT INTO `country` VALUES ('mz', 'Mozambique', 508);
INSERT INTO `country` VALUES ('mm', 'Myanmar', 104);
INSERT INTO `country` VALUES ('na', 'Namibia', 516);
INSERT INTO `country` VALUES ('nr', 'Nauru', 520);
INSERT INTO `country` VALUES ('np', 'Nepal', 524);
INSERT INTO `country` VALUES ('nl', 'Netherlands', 528);
INSERT INTO `country` VALUES ('an', 'Netherlands Antilles', 530);
INSERT INTO `country` VALUES ('nc', 'New Caledonia', 540);
INSERT INTO `country` VALUES ('nz', 'New Zealand', 554);
INSERT INTO `country` VALUES ('ni', 'Nicaragua', 558);
INSERT INTO `country` VALUES ('ne', 'Niger', 562);
INSERT INTO `country` VALUES ('ng', 'Nigeria', 566);
INSERT INTO `country` VALUES ('nu', 'Niue', 570);
INSERT INTO `country` VALUES ('nf', 'Norfolk Island', 574);
INSERT INTO `country` VALUES ('mp', 'Northern Mariana Islands', 580);
INSERT INTO `country` VALUES ('no', 'Norway', 578);
INSERT INTO `country` VALUES ('om', 'Oman', 512);
INSERT INTO `country` VALUES ('pk', 'Pakistan', 586);
INSERT INTO `country` VALUES ('pw', 'Palau', 585);
INSERT INTO `country` VALUES ('ps', 'Palestinian Territory, Occupied', 275);
INSERT INTO `country` VALUES ('pa', 'Panama', 591);
INSERT INTO `country` VALUES ('pg', 'Papua New Guinea', 598);
INSERT INTO `country` VALUES ('py', 'Paraguay', 600);
INSERT INTO `country` VALUES ('pe', 'Peru', 604);
INSERT INTO `country` VALUES ('ph', 'Philippines', 608);
INSERT INTO `country` VALUES ('pn', 'Pitcairn', 612);
INSERT INTO `country` VALUES ('pl', 'Poland', 616);
INSERT INTO `country` VALUES ('pt', 'Portugal', 620);
INSERT INTO `country` VALUES ('pr', 'Puerto Rico', 630);
INSERT INTO `country` VALUES ('qa', 'Qatar', 634);
INSERT INTO `country` VALUES ('re', 'Reunion', 638);
INSERT INTO `country` VALUES ('ro', 'Romania', 642);
INSERT INTO `country` VALUES ('ru', 'Russian Federation', 643);
INSERT INTO `country` VALUES ('rw', 'Rwanda', 646);
INSERT INTO `country` VALUES ('sh', 'Saint Helena', 654);
INSERT INTO `country` VALUES ('kn', 'Saint Kitts and Nevis', 659);
INSERT INTO `country` VALUES ('lc', 'Saint Lucia', 662);
INSERT INTO `country` VALUES ('pm', 'Saint Pierre and Miquelon', 666);
INSERT INTO `country` VALUES ('vc', 'Saint Vincent and the Grenadines', 670);
INSERT INTO `country` VALUES ('ws', 'Samoa', 882);
INSERT INTO `country` VALUES ('sm', 'San Marino', 674);
INSERT INTO `country` VALUES ('st', 'Sao Tome and Principe', 678);
INSERT INTO `country` VALUES ('sa', 'Saudi Arabia', 682);
INSERT INTO `country` VALUES ('sn', 'Senegal', 686);
INSERT INTO `country` VALUES ('rs', 'Serbia', 688);
INSERT INTO `country` VALUES ('rs', 'Serbia', 688);
INSERT INTO `country` VALUES ('sc', 'Seychelles', 690);
INSERT INTO `country` VALUES ('sl', 'Sierra Leone', 694);
INSERT INTO `country` VALUES ('sg', 'Singapore', 702);
INSERT INTO `country` VALUES ('sk', 'Slovakia', 703);
INSERT INTO `country` VALUES ('si', 'Slovenia', 705);
INSERT INTO `country` VALUES ('sb', 'Solomon Islands', 90);
INSERT INTO `country` VALUES ('so', 'Somalia', 706);
INSERT INTO `country` VALUES ('za', 'South Africa', 710);
INSERT INTO `country` VALUES ('gs', 'South Georgia and the South Sandwich Islands', 239);
INSERT INTO `country` VALUES ('es', 'Spain', 724);
INSERT INTO `country` VALUES ('lk', 'Sri Lanka', 144);
INSERT INTO `country` VALUES ('sd', 'Sudan', 736);
INSERT INTO `country` VALUES ('sr', 'Suriname', 740);
INSERT INTO `country` VALUES ('sj', 'Svalbard and Jan Mayen', 744);
INSERT INTO `country` VALUES ('sz', 'Swaziland', 748);
INSERT INTO `country` VALUES ('se', 'Sweden', 752);
INSERT INTO `country` VALUES ('ch', 'Switzerland', 756);
INSERT INTO `country` VALUES ('sy', 'Syrian Arab Republic', 760);
INSERT INTO `country` VALUES ('tw', 'Taiwan, Province of China', 158);
INSERT INTO `country` VALUES ('tj', 'Tajikistan', 762);
INSERT INTO `country` VALUES ('tz', 'Tanzania, United Republic of', 834);
INSERT INTO `country` VALUES ('th', 'Thailand', 764);
INSERT INTO `country` VALUES ('tl', 'Timor-Leste', 626);
INSERT INTO `country` VALUES ('tg', 'Togo', 768);
INSERT INTO `country` VALUES ('tk', 'Tokelau', 772);
INSERT INTO `country` VALUES ('to', 'Tonga', 776);
INSERT INTO `country` VALUES ('tt', 'Trinidad and Tobago', 780);
INSERT INTO `country` VALUES ('tn', 'Tunisia', 788);
INSERT INTO `country` VALUES ('tr', 'Turkey', 792);
INSERT INTO `country` VALUES ('tm', 'Turkmenistan', 795);
INSERT INTO `country` VALUES ('tc', 'Turks and Caicos Islands', 796);
INSERT INTO `country` VALUES ('tv', 'Tuvalu', 798);
INSERT INTO `country` VALUES ('ug', 'Uganda', 800);
INSERT INTO `country` VALUES ('ua', 'Ukraine', 804);
INSERT INTO `country` VALUES ('ae', 'United Arab Emirates', 784);
INSERT INTO `country` VALUES ('gb', 'United Kingdom', 826);
INSERT INTO `country` VALUES ('us', 'United States', 840);
INSERT INTO `country` VALUES ('um', 'United States Minor Outlying Islands', 581);
INSERT INTO `country` VALUES ('uy', 'Uruguay', 858);
INSERT INTO `country` VALUES ('uz', 'Uzbekistan', 860);
INSERT INTO `country` VALUES ('vu', 'Vanuatu', 548);
INSERT INTO `country` VALUES ('ve', 'Venezuela', 862);
INSERT INTO `country` VALUES ('vn', 'Viet Nam', 704);
INSERT INTO `country` VALUES ('vg', 'Virgin Islands, British', 92);
INSERT INTO `country` VALUES ('vi', 'Virgin Islands, U.s.', 850);
INSERT INTO `country` VALUES ('wf', 'Wallis and Futuna', 876);
INSERT INTO `country` VALUES ('eh', 'Western Sahara', 732);
INSERT INTO `country` VALUES ('ye', 'Yemen', 887);
INSERT INTO `country` VALUES ('zm', 'Zambia', 894);
INSERT INTO `country` VALUES ('zw', 'Zimbabwe', 716);


-- --------------------------------------------------------

-- 
-- Default users
--

INSERT INTO `user` VALUES ('admin', '4008750ce237101f5e39ec63c8ae46f134a40a65', 'xrR1KfV9FfLAwj2YMfeK1cttaMRHafauezAmbg51', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, NULL);
INSERT INTO `user` VALUES ('anonymous', 'adf8efe68157cf37503f86d602bec6d593750c33', 'I33sJY_HNVMlbGL1nBzY0VdXebb4oSkJIGcnZzLZ', 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);

-- --------------------------------------------------------

-- 
-- Default server
--

INSERT INTO `server` VALUES ('database_version', '42');
INSERT INTO `server` VALUES ('escape_char_hash', 'd41d8cd98f00b204e9800998ecf8427e');
INSERT INTO `server` VALUES ('getid3_hash', 'd41d8cd98f00b204e9800998ecf8427e');
INSERT INTO `server` VALUES ('image_quality', '0');
INSERT INTO `server` VALUES ('image_size', '0');
INSERT INTO `server` VALUES ('latest_version', '');
INSERT INTO `server` VALUES ('latest_version_idle_time', '0');


-- --------------------------------------------------------

-- 
-- Default artist
--

INSERT INTO `artist` VALUES ('1', 'Unknown Artist', 'unknownartist', '', 0,0);
INSERT INTO `artist` VALUES ('2', 'Various Artists', 'variousartists', '', 0,0);

-- --------------------------------------------------------

-- 
-- Default genre
--

INSERT INTO `genre` VALUES ('1', 'Unknown', '0', 'unknown',0,0);

-- --------------------------------------------------------

-- 
-- Default label
--

INSERT INTO `label` VALUES ('1', 'Unknown Label', 'unknownlabel',0,0);

  