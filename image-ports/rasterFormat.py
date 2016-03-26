#!/usr/bin/env python
"""
This is a minimal ESC/POS printing script which uses the 'raster format'
of image output.

The snippet is designed to efficiently delegate image processing to
PIL, rather than spend CPU cycles looping over pixels.

Do not attempt to use this snippet in production, get a copy of python-escpos instead!
"""

from PIL import Image, ImageOps
import six
import sys
import os

def _to_raster_format(im):
    """ Convert image to raster-format binary
    
    : param im: Input image
    """
    # Convert down to greyscale
    im = im.convert("L") 
    # Invert: Only works on 'L' images
    im = ImageOps.invert(im)
    # Pure black and white
    im = im.convert("1")
    return im.tobytes()

def _int_low_high(inp_number, out_bytes):
    """ Generate multiple bytes for a number: In lower and higher parts, or more parts as needed.
    
    :param inp_number: Input number
    :param out_bytes: The number of bytes to output (1 - 4).
    """
    max_input = (256 << (out_bytes * 8) - 1);
    if not 1 <= out_bytes <= 4:
        raise ValueError("Can only output 1-4 byes")
    if not 0 <= inp_number <= max_input:
        raise ValueError("Number too large. Can only output up to {0} in {1} byes".format(max_input, out_bytes))
    outp = b'';
    for _ in range(0, out_bytes):
        outp += six.int2byte(inp_number % 256)
        inp_number = inp_number // 256
    return outp

if __name__ == "__main__":
    # Configure
    high_density_horizontal = True
    high_density_vertical = True
    if len(sys.argv) > 1:
        filename = sys.argv[1]
    else:
        filename = u"tulips.png"
    
    # Load Image
    im = Image.open(filename)
    
    # Print
    GS = b"\x1d";
    width, height_pixels = im.size
    width_bytes = (int)((width + 7) / 8);
    density_byte = (0 if high_density_vertical else 1) + (0 if high_density_horizontal else 2);
    header = GS + b"v0" + six.int2byte(density_byte) + _int_low_high(width_bytes, 2) + _int_low_high(height_pixels, 2);
    
    with os.fdopen(sys.stdout.fileno(), 'wb') as fp:
        fp.write(header + _to_raster_format(im));
