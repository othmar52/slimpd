# Puppet MPD Module

Module for provisioning MPD (Music Playing Daemon)

Tested on Ubuntu 12.04, patches to support other operating systems are welcome.

## Installation

Clone this repo to your Puppet modules directory

    git clone git://github.com/ajjahn/puppet-mpd.git mpd

## Usage

Tweak and add the following to your site manifest:

    node 'server.example.com' {

      # Checkout 'manifests/server.pp' for more options.
      class {'mpd::server':
        music_directory => '/path/to/music',
        replaygain => "track",
        volume_normalization => "yes",
        auto_update => "yes",
      }

      class {'mpd::client':
        volume => '100',
        repeat => 'on',
        random => 'off',
        single => 'off',
        consume => 'off',
        crossfade => '10',
        force_play => true,
        force_update => true,
        remove_duplicates => true,
      }

    }

### Audio Outputs

You can add custom audio outputs via an array containing each element another nested, key/value pairs array definig the output's custom parameters. See [MPD Audio Outputs Configuration](http://mpd.wikia.com/wiki/Configuration#Audio_Outputs).

Example `audio_outputs` for local PulseAudio:

    class { 'mpd::server':
      ...
      audio_outputs => [ { 'name' => 'PulseAudio', 'type' => 'pulse', }, ],
      ...
    }

Warning: [ There are issues specific to local PulseAudio output in MPD: ]( https://wiki.archlinux.org/index.php/MPD/Tips_and_Tricks#MPD_.26_PulseAudio )


## Contributing

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Added some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request

## License

This module is released under the MIT license:

* [http://www.opensource.org/licenses/MIT](http://www.opensource.org/licenses/MIT)
