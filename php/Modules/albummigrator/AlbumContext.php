<?php
namespace Slimpd\Modules\albummigrator;
class AlbumContext extends \Slimpd\Models\Album {
	use \Slimpd\Modules\albummigrator\MigratorContext; // config
	protected $confKey = "album-tag-mapping-";
	
	public function getTagsFromTrack($rawTagArray, $config) {
		$this->rawTagRecord = $rawTagArray;
		$this->config = $config;
		$this->configBasedSetters();
	}
}
