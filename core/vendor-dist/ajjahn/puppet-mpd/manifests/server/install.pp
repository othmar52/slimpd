class mpd::server::install {
  package { "mpd":
    ensure => $mpd::server::ensure,
  }
}
