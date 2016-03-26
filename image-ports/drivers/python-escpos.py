#!/usr/bin/env python
import sys
sys.path.append('../../drivers/python-escpos')
from escpos import printer
device = printer.File("/dev/stdout")

if len(sys.argv) > 1:
    filename = sys.argv[1]
else:
    filename = u"../tulips.png"
device.image(filename)
