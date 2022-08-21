class mpd::server(
      $ensure                           = installed,
      $service_enable                   = true,
      $service_ensure                   = running,
      $music_directory                  = '/var/lib/mpd/music',
      $playlist_directory               = "/var/lib/mpd/playlists",
      $db_file                          = "/var/lib/mpd/tag_cache",
      $log_file                         = "/var/log/mpd/mpd.log",
      $pid_file                         = "/var/run/mpd/pid",
      $state_file                       = "/var/lib/mpd/state",
      $sticker_file                     = "/var/lib/mpd/sticker.sql",
      $user                             = "mpd",
      $group                            = false,
      $bind_to_address                  = "localhost",
      $port                             = false,
      $log_level                        = false,
      $gapless_mp3_playback             = false,
      $save_absolute_paths_in_playlists = false,
      $metadata_to_use                  = false,
      $auto_update                      = false,
      $auto_update_depth                = false,
      $follow_outside_symlinks          = false,
      $follow_inside_symlinks           = false,
      $zeroconf_enabled                 = false,
      $zeroconf_name                    = false,
      $password                         = false,
      $default_permissions              = false,
      $mixer_type                       = false,
      $replaygain                       = false,
      $replaygain_preamp                = false,
      $volume_normalization             = false,
      $audio_buffer_size                = false,
      $buffer_before_play               = false,
      $audio_outputs                    = []) {
  include mpd::server::install
  if ( $ensure != purged ) {
    include mpd::server::config
    include mpd::server::service
  }
}
