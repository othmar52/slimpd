#!/usr/bin/python

"""md5dir -- Return md5 checksum for a file. Uses the tag-skipping algorithm
    for .mp3 files

Usage: md5dir [options] [directories]
Copyright: 2007 Graham Poulter
"""

__copyright__ = "2007 Graham Poulter"
__author__ = "Graham Poulter"
__license__ = """This program is free software: you can redistribute it and/or
modify it under the terms of the GNU General Public License as published by the
Free Software Foundation, either version 3 of the License, or (at your option)
any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <http://www.gnu.org/licenses/>."""

import getopt
import hashlib
import os
import os.path as op
import struct
import sys

### WARNING: ORIGINAL FUNCTION IS IN MP3MD5.PY - MODIFY THERE
def calculateUID(filepath):
    """Calculate MD5 for an MP3 excluding ID3v1 and ID3v2 tags if
    present. See www.id3.org for tag format specifications."""
    f = open(filepath, "rb")
    # Detect ID3v1 tag if present
    finish = os.stat(filepath).st_size;
    f.seek(-128, 2)
    if f.read(3) == "TAG":
        finish -= 128
    # ID3 at the start marks ID3v2 tag (0-2)
    f.seek(0)
    start = f.tell()
    if f.read(3) == "ID3":
        # Bytes w major/minor version (3-4)
        f.read(2)
        # Flags byte (5)
        flags = struct.unpack("B", f.read(1))[0]
        # Flat bit 4 means footer is present (10 bytes)
        footer = flags & (1<<4)
        # Size of tag body synchsafe integer (6-9)
        bs = struct.unpack("BBBB", f.read(4))
        bodysize = (bs[0]<<21) + (bs[1]<<14) + (bs[2]<<7) + bs[3]
        # Seek to end of ID3v2 tag
        f.seek(bodysize, 1)
        if footer:
            f.seek(10, 1)
        # Start of rest of the file
        start = f.tell()
    # Calculate MD5 using stuff between tags
    f.seek(start)
    h = hashlib.new("md5")
    h.update(f.read(finish-start))
    f.close()
    return h.hexdigest()


def calcsum(filepath, mp3mode):
    """Return md5 checksum for a file. Uses the tag-skipping algorithm
    for .mp3 files if in mp3mode."""
    if mp3mode and filepath.endswith(".mp3"):
        return calculateUID(filepath)
    h = hashlib.new("md5")
    f = open(filepath, "rb")
    s = f.read(1048576)
    while s != "":
        h.update(s)
        s = f.read(1048576)
    f.close()
    return h.hexdigest()


def main(argv):
    try:
        opts, dummy_args = getopt.getopt(argv, "h3:", ["mp3=", "help"])
    except getopt.GetoptError:
        print "error du fut... tips richti ju gaxbaer!!!"
        sys.exit(2)
    for opt, arg in opts:
        if opt in ("-3", "--mp3"):
            if not op.isfile(arg):
                print "ERROR: Argument %s is not a file" % (arg)
                sys.exit(2)
            hans = calcsum(arg,True)
            print hans
        elif opt in ("-h", "--help"):
            print "USEAGE: -3 fullpathToFile OR --mp3=fullpathToFile"


if __name__ == "__main__":
    main(sys.argv[1:])
