class mpd::client($volume = false,
                  $repeat = false,
                  $random = false,
                  $single = false,
                  $consume = false,
                  $crossfade = false,
                  $force_play = false,
                  $force_update = false,
                  $remove_duplicates = false) {

  include mpd::client::install

  if $volume {
    exec { "set-volume":
      command => "mpc volume ${volume}",
      unless => "mpc | grep 'volume:${volume}'",
      require => Class['mpd::client::install']
    }
  }

  if $repeat {
    exec { "set-repeat":
      command => "mpc repeat",
      unless => "mpc | grep 'repeat: ${repeat}'",
      require => Class['mpd::client::install']
    }
  }

  if $random {
    exec { "set-random":
      command => "mpc random",
      unless => "mpc | grep 'random: ${random}'",
      require => Class['mpd::client::install']
    }
  }

  if $single {
    exec { "set-single":
      command => "mpc single",
      unless => "mpc | grep 'single: ${single}'",
      require => Class['mpd::client::install']
    }
  }

  if $consume {
    exec { "set-consume":
      command => "mpc consume",
      unless => "mpc | grep 'consume: ${consume}'",
      require => Class['mpd::client::install']
    }
  }

  if $crossfade {
    exec { "set-crossfade":
      command => "mpc crossfade ${crossfade}",
      unless => "mpc crossfade | grep 'crossfade: ${crossfade}'",
      require => Class['mpd::client::install']
    }
  }

  if $force_play {
    exec { "force-play":
      command => "mpc play",
      unless => 'mpc | grep "\[playing\]"',
      require => Class['mpd::client::install']
    }
  }

  if $force_update {
    exec { "force-update":
      command => "mpc update",
      require => Class['mpd::client::install']
    }
  }

  if $remove_duplicates {
    file { "/usr/local/bin/mpd-remove-duplicates":
      ensure => file,
      mode => 755,
      owner => 'root',
      group => 'root',
      source => "puppet:///modules/${module_name}/mpd-remove-duplicates.sh"
    }

    cron { "mpd-nodups":
      command => '/usr/local/bin/mpd-remove-duplicates',
      minute => '*/5',
      user => 'root',
      ensure => present,
      require => [Class['mpd::client::install'], File["/usr/local/bin/mpd-remove-duplicates"]]
    }
  }

}
