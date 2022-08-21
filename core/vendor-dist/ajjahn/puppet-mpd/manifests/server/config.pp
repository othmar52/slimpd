class mpd::server::config {
  file { "/etc/mpd.conf":
    ensure => present,
    owner => "root",
    group => "root",
    mode => 0644,
    notify => Class["mpd::server::service"],
    require => Class["mpd::server::install"],
    content => template("${module_name}/mpd.conf.erb")
  }
}