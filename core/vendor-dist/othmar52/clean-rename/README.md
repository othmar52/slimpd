# clean-rename
recursive replacing special chars in UNIX-UTF8-filesystem with most similar alphanumerics in filenames and directorynames

	Špêçïål ©harš in filesÿsteμ/$ù¢κš.e×t -> Special_chars_in_filesystem/Sucks.ext

# demo	

create testdirectory and testfile

	~/othmar52/clean-rename $ mkdir -p "/tmp/clean-rename-demo/Špêçïål ©harš in filesÿsteμ"
	~/othmar52/clean-rename $ touch "/tmp/clean-rename-demo/Špêçïål ©harš in filesÿsteμ/$ù¢κš.e×t"
	
run clean-rename

	~/othmar52/clean-rename $ ./clean-rename /tmp/clean-rename-demo
	/tmp/clean-rename-demo/Špêçïål ©harš in filesÿsteμ/$ù¢κš.e×t ---> /tmp/clean-rename-demo/Špêçïål ©harš in filesÿsteμ/Sucks.ext
	/tmp/clean-rename-demo/Špêçïål ©harš in filesÿsteμ ---> /tmp/clean-rename-demo/Special_chars_in_filesystem
	0 errors
	
show result

	~/othmar52/clean-rename $ find /tmp/clean-rename-demo -type f
	/tmp/clean-rename-demo/Special_chars_in_filesystem/Sucks.ext

	
# troubleshooting

in case the result from the example above looks like this `Yapaeacaiaal_eharYa_in_filesaysteu/SauauYa.eat` you have to make sure your session is UTF-8. one way to achieve this is to add following lines to your `~/.bashrc`

	export LC_ALL=en_US.UTF-8
	export LANG=en_US.UTF-8
	export LANGUAGE=en_US.UTF-8

