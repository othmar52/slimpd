

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
  `embeddedName` varchar(255) NOT NULL DEFAULT '',
  `pictureType` varchar(20) NOT NULL DEFAULT '',
  `sorting` int(6) unsigned DEFAULT NULL,
  `importStatus` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `error` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `relativePathHash` (`relativePathHash`),
  KEY `albumId` (`albumId`),
  KEY `trackId` (`trackId`),
  KEY `rawTagDataId` (`rawTagDataId`),
  KEY `importStatus` (`importStatus`),
  KEY `embedded` (`embedded`),
  KEY `pictureType` (`pictureType`),
  KEY `sorting` (`sorting`),
  KEY `error` (`error`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `bitmap`
  ADD FULLTEXT KEY `relativePath` (`relativePath`);

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
-- table structure `trackindex`
--

CREATE TABLE IF NOT EXISTS `trackindex` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `artist` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `allchunks` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `artist` (`artist`),
  KEY `title` (`title`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `trackindex`
  ADD FULLTEXT KEY `allchunks` (`allchunks`);
  
  
-- --------------------------------------------------------

--
-- table structure `albumindex`
--

CREATE TABLE IF NOT EXISTS `albumindex` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `artist` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `allchunks` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `artist` (`artist`),
  KEY `title` (`title`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `albumindex`
  ADD FULLTEXT KEY `allchunks` (`allchunks`);
  
  
  
-- --------------------------------------------------------

--
-- table structure `discogsapicache`
--

CREATE TABLE IF NOT EXISTS `discogsapicache` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tstamp` int(11) unsigned NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT '',
  `extid` int(11) unsigned NOT NULL,
  `response` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `extid` (`extid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

  
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
  `added` int(11) unsigned NOT NULL DEFAULT '0',

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
  `relativePath` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobStart` (`jobStart`),
  KEY `jobEnd` (`jobEnd`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- table structure `suggest`
--

CREATE TABLE IF NOT EXISTS `suggest` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) NOT NULL,
  `trigrams` varchar(255) NOT NULL,
  `freq` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `pollcache`
--

CREATE TABLE IF NOT EXISTS `pollcache` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `microtstamp` varchar(255) NOT NULL,
  `type` varchar(11) NOT NULL,
  `deckindex` tinyint(4) unsigned DEFAULT '0',
  `success` tinyint(2) unsigned DEFAULT '0',
  `ip` varchar(11) NOT NULL,
  `port` tinyint(5) unsigned DEFAULT '0',
  `response` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- inrease starting value of uid for not bloating the sphinx index (min_word_length=2)
--

ALTER TABLE `artist` AUTO_INCREMENT = 10;
ALTER TABLE `genre` AUTO_INCREMENT = 10;
ALTER TABLE `label` AUTO_INCREMENT = 10;
ALTER TABLE `album` AUTO_INCREMENT = 10;
ALTER TABLE `albumindex` AUTO_INCREMENT = 10;
ALTER TABLE `track` AUTO_INCREMENT = 10;
ALTER TABLE `trackindex` AUTO_INCREMENT = 10;


-- --------------------------------------------------------

-- 
-- Default artist
--

INSERT INTO `artist` VALUES (NULL, 'Unknown Artist', '', 'unknownartist', 0,0);
INSERT INTO `artist` VALUES (NULL, 'Various Artists', '', 'variousartists', 0,0);

-- --------------------------------------------------------

-- 
-- Default genre
--

INSERT INTO `genre` VALUES (NULL, 'Unknown', '0', 'unknown',0,0);

-- --------------------------------------------------------

-- 
-- Default label
--

INSERT INTO `label` VALUES (NULL, 'Unknown Label', 'unknownlabel',0,0);

  