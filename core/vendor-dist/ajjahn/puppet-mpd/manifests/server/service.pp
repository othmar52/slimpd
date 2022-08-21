class mpd::server::service {
 service { "mpd":
    ensure => $mpd::server::service_ensure,
    hasstatus => true,
    hasrestart => true,
    enable => $mpd::server::service_enable,
    require => Class["mpd::server::config"]
  }
}
