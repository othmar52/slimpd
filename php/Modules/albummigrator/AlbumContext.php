<?php
namespace Slimpd\Modules\albummigrator;
class AlbumContext extends \Slimpd\Models\Album {
	use \Slimpd\Modules\albummigrator\MigratorContext; // config
	protected $confKey = "album-tag-mapping-";
	
	public function getTagsFromTrack($rawTagArray, $config) {
		$this->rawTagRecord = $rawTagArray;
		$this->rawTagArray = unserialize($rawTagArray['tagData']);
		$this->config = $config;
		$this->configBasedSetters();
	}
	
	/**
	 * some rawTagData-fields are identical to album fields 
	 */
	public function copyBaseProperties($rawTagRecord) {
		$this->setRelPath($rawTagRecord['relDirPath'])
			->setRelPathHash($rawTagRecord['relDirPathHash'])
			->setFilemtime($rawTagRecord['directoryMtime'])
			//->setAdded($rawTagRecord['added'])
			//->setLastScan($rawTagRecord['lastDirScan'])
			;
	}
		
	public function migrate($trackContextItems, $jumbleJudge) {
		$album = new \Slimpd\Models\Album();

		$album->setRelPath($this->getRelPath())
			->setRelPathHash($this->getRelPathHash())
			->setFilemtime($this->getFilemtime())
			->setIsJumble($jumbleJudge->handleAsAlbum)
			/*->setArtistUid(join(",", \Slimpd\Models\Artist::getUidsByString($albumArtists)))
			->setGenreUid(join(",", \Slimpd\Models\Genre::getUidsByString($mergedFromTracks['genre'])))
			->setCatalogNr($this->mostScored['album']['catalogNr'])
			
			->setAdded($this->mostRecentAdded)
			->setTitle($this->mostScored['album']['title'])
			->setYear($this->mostScored['album']['year'])
			->setLabelUid(
				join(",", \Slimpd\Models\Label::getUidsByString(
					($album->getIsJumble() === 1)
						? $mergedFromTracks['label']			// all labels
						: $this->mostScored['album']['label']	// only 1 label
				))
			)*/
			->setTrackCount(count($trackContextItems))
			->update();

		$this->setUid($album->getUid());
	}
}
