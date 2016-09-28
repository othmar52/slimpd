

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


--
-- table structure `album`
--

CREATE TABLE IF NOT EXISTS `album` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` text,
  `relPath` text,
  `relPathHash` varchar(11) NOT NULL DEFAULT '',
  `year` smallint(4) unsigned DEFAULT NULL,
  `month` tinyint(2) unsigned DEFAULT NULL,
  `artistUid` varchar(255) NOT NULL DEFAULT '',
  `genreUid` varchar(255) NOT NULL DEFAULT '',
  `labelUid` varchar(255) NOT NULL DEFAULT '',
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
  PRIMARY KEY (`uid`),
  KEY `artistUid` (`artistUid`),
  KEY `year` (`year`,`month`),
  KEY `labelUid` (`labelUid`),
  KEY `genreUid` (`genreUid`),
  KEY `added` (`added`),
  KEY `importStatus` (`importStatus`),
  KEY `isMixed` (`isMixed`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `album`
  ADD FULLTEXT KEY `relPath` (`relPath`),
  ADD FULLTEXT KEY `title` (`title`);
-- --------------------------------------------------------

--
-- table structure `bitmap`
--
	
CREATE TABLE IF NOT EXISTS `bitmap` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `relPath` text,
  `relPathHash` varchar(11) NOT NULL DEFAULT '',
  `relDirPathHash` varchar(11) NOT NULL DEFAULT '',
  `filemtime` int(11) unsigned NOT NULL DEFAULT '0',
  `filesize` int(11) unsigned NOT NULL DEFAULT '0',
  `mimeType` varchar(64) NOT NULL DEFAULT '',
  `width` int(7) unsigned DEFAULT NULL,
  `height` int(7) unsigned DEFAULT NULL,
  `bghex` varchar(7) NOT NULL DEFAULT '',
  `albumUid` int(11) unsigned DEFAULT NULL,
  `trackUid` int(11) unsigned DEFAULT NULL,
  `embedded` tinyint(4) unsigned DEFAULT NULL,
  `fileName` varchar(255) NOT NULL DEFAULT '',
  `pictureType` varchar(20) NOT NULL DEFAULT '',
  `sorting` int(6) unsigned DEFAULT NULL,
  `hidden` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `importStatus` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `error` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`),
  KEY `relPathHash` (`relPathHash`),
  KEY `relDirPathHash` (`relDirPathHash`),
  KEY `albumUid` (`albumUid`),
  KEY `trackUid` (`trackUid`),
  KEY `importStatus` (`importStatus`),
  KEY `embedded` (`embedded`),
  KEY `pictureType` (`pictureType`),
  KEY `sorting` (`sorting`),
  KEY `error` (`error`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `bitmap`
  ADD FULLTEXT KEY `relPath` (`relPath`);

-- --------------------------------------------------------

--
-- table structure `artist`
--

CREATE TABLE IF NOT EXISTS `artist` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `article` varchar(24) NOT NULL DEFAULT '',
  `az09` varchar(255) NOT NULL DEFAULT '0',
  `trackCount` int(11) unsigned  DEFAULT '0',
  `albumCount` int(11) unsigned  DEFAULT '0',
  `topGenreUids` varchar(20) NOT NULL  DEFAULT '',
  `topLabelUids` varchar(20) NOT NULL  DEFAULT '',
  `albumCount` int(11) unsigned  DEFAULT '0',
  PRIMARY KEY `uid` (`uid`),
  KEY `az09` (`az09`),
  KEY `trackCount` (`trackCount`),
  KEY `albumCount` (`albumCount`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `genre`
--

CREATE TABLE IF NOT EXISTS `genre` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `parent` int(4) unsigned NOT NULL DEFAULT '0',
  `az09` varchar(255) NOT NULL DEFAULT '0',
  `trackCount` int(11) unsigned  DEFAULT '0',
  `albumCount` int(11) unsigned  DEFAULT '0',
  `topArtistUids` varchar(20) NOT NULL  DEFAULT '',
  `topLabelUids` varchar(20) NOT NULL  DEFAULT '',
  PRIMARY KEY `uid` (`uid`),
  KEY `az09` (`az09`),
  KEY `trackCount` (`trackCount`),
  KEY `albumCount` (`albumCount`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `label`
--

CREATE TABLE IF NOT EXISTS `label` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `az09` varchar(255) NOT NULL DEFAULT '0',
  `trackCount` int(11) unsigned  DEFAULT '0',
  `albumCount` int(11) unsigned  DEFAULT '0',
  `topArtistUids` varchar(20) NOT NULL  DEFAULT '',
  `topGenreUids` varchar(20) NOT NULL  DEFAULT '',
  PRIMARY KEY `uid` (`uid`),
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
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `artistUid` varchar(255) NOT NULL DEFAULT '',
  `featuringUid` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `remixerUid` varchar(255) NOT NULL DEFAULT '',
  `relPath` text NOT NULL,
  `relPathHash` varchar(11) NOT NULL,
  `relDirPathHash` varchar(11) NOT NULL,
  
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
  `audioComprRatio` double unsigned NOT NULL DEFAULT '0',
  `audioDataformat` varchar(64) NOT NULL DEFAULT '',
  `audioEncoder` varchar(64) NOT NULL DEFAULT '',
  `audioProfile` varchar(64) NOT NULL DEFAULT '',
  
  `videoDataformat` varchar(64) NOT NULL DEFAULT '',
  `videoCodec` varchar(64) NOT NULL DEFAULT '',
  `videoResolutionX` int(10) unsigned NOT NULL DEFAULT '0',
  `videoResolutionY` int(10) unsigned NOT NULL DEFAULT '0',
  `videoFramerate` int(10) unsigned NOT NULL DEFAULT '0',
  
  `disc` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `trackNumber` varchar(8) DEFAULT NULL,
  `error` varchar(255) NOT NULL DEFAULT '',
  `albumUid` varchar(11) NOT NULL DEFAULT '',
  `transcoded` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `importStatus` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `lastScan` int(11) unsigned NOT NULL DEFAULT '0',
  `genreUid` varchar(255) NOT NULL DEFAULT '',
  `labelUid` varchar(255) NOT NULL DEFAULT '',
  `catalogNr` varchar(64) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `year` smallint(4) unsigned DEFAULT NULL,
  
  `isMixed` smallint(1) unsigned DEFAULT NULL,
  `discogsId` varchar(64) NOT NULL DEFAULT '',
  `rolldabeatsId` varchar(64) NOT NULL DEFAULT '',
  `beatportId` varchar(64) NOT NULL DEFAULT '',
  `junoId` varchar(64) NOT NULL DEFAULT '',
  
  `dynRange` tinyint(3) unsigned  DEFAULT NULL,
  PRIMARY KEY (`uid`),
  KEY `artistUid` (`artistUid`),
  KEY `featuringUid` (`featuringUid`),
  KEY `title` (`title`),
  KEY `remixerUid` (`remixerUid`),
  KEY `relPathHash` (`relPathHash`),
  KEY `fingerprint` (`fingerprint`),
  KEY `audioDataformat` (`audioDataformat`),
  KEY `videoDataformat` (`videoDataformat`),
  KEY `albumUid` (`albumUid`,`disc`),
  KEY `importStatus` (`importStatus`),
  KEY `genreUid` (`genreUid`),
  KEY `labelUid` (`labelUid`),
  KEY `error` (`error`),
  KEY `transcoded` (`transcoded`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `track`
  ADD FULLTEXT KEY `relPath` (`relPath`);
  
  
-- --------------------------------------------------------

--
-- table structure `trackindex`
--

CREATE TABLE IF NOT EXISTS `trackindex` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `artist` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `allchunks` mediumtext NOT NULL,
  PRIMARY KEY (`uid`),
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
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `artist` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `allchunks` mediumtext NOT NULL,
  PRIMARY KEY (`uid`),
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
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tstamp` int(11) unsigned NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT '',
  `extid` int(11) unsigned NOT NULL,
  `response` mediumtext NOT NULL,
  PRIMARY KEY (`uid`),
  KEY `type` (`type`),
  KEY `extid` (`extid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

  
-- --------------------------------------------------------

--
-- table structure `rawtagdata`
--

CREATE TABLE IF NOT EXISTS `rawtagdata` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `relPath` text NOT NULL,
  `added` int(11) unsigned NOT NULL DEFAULT '0',
  `filesize` bigint(20) unsigned NOT NULL DEFAULT '0',
  `filemtime` int(10) unsigned NOT NULL DEFAULT '0',
  `directoryMtime` int(10) unsigned NOT NULL DEFAULT '0',
  `lastScan` int(11) unsigned NOT NULL DEFAULT '0',
  `importStatus` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `lastDirScan` int(11) unsigned NOT NULL DEFAULT '0',
  `extension` varchar(64) NOT NULL DEFAULT '',
  `error` text,
  `relPathHash` varchar(11) NOT NULL,
  `relDirPath` text NOT NULL,
  `relDirPathHash` varchar(11) NOT NULL,
  `fingerprint` varchar(32) NOT NULL DEFAULT '',
  `tagData` LONGBLOB,

  PRIMARY KEY (`uid`),
  KEY `relPathHash` (`relPathHash`),
  KEY `relDirPathHash` (`relDirPathHash`),
  KEY `fingerprint` (`fingerprint`),
  KEY `filemtime` (`filemtime`),
  KEY `directoryMtime` (`directoryMtime`),
  KEY `importStatus` (`importStatus`),
  KEY `lastScan` (`lastScan`),
  KEY `lastDirScan` (`lastDirScan`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `rawtagdata`
  ADD FULLTEXT KEY `error` (`error`);
  

-- --------------------------------------------------------

--
-- table structure `playnext`
--

CREATE TABLE IF NOT EXISTS `playnext` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tstamp` int(11) unsigned NOT NULL DEFAULT 0,
  `prio` int(11) unsigned NOT NULL DEFAULT 0,
  `userId` int(11) unsigned NOT NULL DEFAULT 0,
  `trackUid` varchar(255) NOT NULL DEFAULT '',
  `relPath` text NOT NULL,
  `relPathHash` varchar(11) NOT NULL,
  `fingerprint` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`uid`),
  KEY `prio` (`prio`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- table structure `editorial`
--

CREATE TABLE IF NOT EXISTS `editorial` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `crdate` int(11) unsigned NOT NULL DEFAULT '0',
  `tstamp` int(11) unsigned NOT NULL DEFAULT '0',
  `itemType` varchar(20) NOT NULL DEFAULT '',
  `itemUid` int(11) NOT NULL DEFAULT '0',
  `relPath` text NOT NULL,
  `relPathHash` varchar(11) NOT NULL,
  `fingerprint` varchar(32) NOT NULL DEFAULT '',
  `column` varchar(32) NOT NULL DEFAULT '',
  `value` text,
  PRIMARY KEY (`uid`),
  KEY `itemUid` (`itemUid`),
  KEY `relPathHash` (`relPathHash`),
  KEY `fingerprint` (`fingerprint`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `importer`
--

CREATE TABLE IF NOT EXISTS `importer` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `batchUid` int(11) unsigned NOT NULL DEFAULT '0',
  `jobPhase` int(11) NOT NULL DEFAULT '0',
  `jobStart` DOUBLE NOT NULL,
  `jobLastUpdate` DOUBLE NOT NULL,
  `jobEnd` DOUBLE NOT NULL,
  `jobStatistics` longtext NOT NULL,
  `relPath` text NOT NULL,
  PRIMARY KEY (`uid`),
  KEY `jobStart` (`jobStart`),
  KEY `jobEnd` (`jobEnd`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- table structure `suggest`
--

CREATE TABLE IF NOT EXISTS `suggest` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) NOT NULL,
  `trigrams` varchar(255) NOT NULL,
  `freq` int(11) unsigned NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- table structure `pollcache`
--

CREATE TABLE IF NOT EXISTS `pollcache` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `microtstamp` varchar(255) NOT NULL,
  `type` varchar(11) NOT NULL,
  `deckindex` tinyint(4) unsigned DEFAULT '0',
  `success` tinyint(2) unsigned DEFAULT '0',
  `ipAddress` varchar(15) NOT NULL,
  `port` tinyint(5) unsigned DEFAULT '0',
  `response` mediumtext NOT NULL,
  PRIMARY KEY (`uid`)
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

INSERT INTO `artist` VALUES (NULL, 'Unknown Artist', '', 'unknownartist', 0, 0, '', '');
INSERT INTO `artist` VALUES (NULL, 'Various Artists', '', 'variousartists', 0, 0, '', '');

-- --------------------------------------------------------

-- 
-- Default genre
--

INSERT INTO `genre` VALUES (NULL, 'Unknown', '0', 'unknown', 0, 0, '', '');

-- --------------------------------------------------------

-- 
-- Default label
--

INSERT INTO `label` VALUES (NULL, 'Unknown Label', 'unknownlabel', 0, 0, '', '');
