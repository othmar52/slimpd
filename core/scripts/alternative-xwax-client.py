#! /usr/bin/env python
# -*- coding: utf-8 -*-

# Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
#
# This file is part of sliMpd - a php based mpd web client
#
# This program is free software: you can redistribute it and/or modify it under
# the terms of the GNU Affero General Public License as published by the Free
# Software Foundation, either version 3 of the License, or (at your option) any
# later version.
#
# This program is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
# FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
# details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

import argparse
import xmlrpclib
import os
import sys
import inspect

def shellquote(s):
	return "'" + s.replace("'", "'\\''") + "'"

def main():
	parser = argparse.ArgumentParser()
	parser.add_argument("ip", type=str, help="ip of server")
	parser.add_argument("cmd", type=str, help="command")
	parser.add_argument("deck", nargs='?', type=str, help="decknumber", default='100')
	parser.add_argument("opt1", nargs='?', type=str, help="pathname|cue-number|position", default='')
	parser.add_argument("opt2", nargs='?', type=str, help="artist|position", default='artist')
	parser.add_argument("opt3", nargs='?', type=str, help="title", default='title')
	args = parser.parse_args()
	xwax_client = os.path.dirname(os.path.abspath(inspect.getfile(inspect.currentframe())))+"/../vendor-dist/othmar52/xwax-1.5-osc/xwax-client"
	port = '9000'
	s = xmlrpclib.ServerProxy('http://' + args.ip + ':' + port)
	if args.cmd == 'launch':
		print s.launch()
		sys.exit()
	elif args.cmd == 'exit':
		print s.exit()
		sys.exit()
	elif args.cmd == 'load_track':
		print s.load_track(str(args.deck), str(args.opt1))
		sys.exit()
	else:
		cmd = "timeout 2 " + xwax_client + " " + " ".join(map(shellquote, sys.argv[1:]))
		os.system(cmd)

	sys.exit()


if __name__=='__main__':
	main()
