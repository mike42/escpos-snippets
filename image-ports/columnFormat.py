#!/usr/bin/env python
"""
This is a minimal ESC/POS printing script which uses the 'column format'
of image output.

The snippet is designed to efficiently delegate image processing to
PIL, rather than spend CPU cycles looping over pixels.

Do not attempt to use this snippet in production, get a copy of python-escpos instead!
"""

from PIL import Image, ImageOps
import six
import sys
import os

def _to_column_format(im, line_height):
    """
    Extract slices of an image as equal-sized blobs of column-format data.

    :param im: Image to extract from
    :param line_height: Printed line height in dots
    """
    width_pixels, height_pixels = im.size
    top = 0
    left = 0
    blobs = []
    while left < width_pixels:
        remaining_pixels = width_pixels - left
        box = (left, top, left + line_height, top + height_pixels)
        slice = im.transform((line_height, height_pixels), Image.EXTENT, box)
        bytes = slice.tobytes()
        blobs.append(bytes)
        left += line_height
    return blobs

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
    
    # Initial rotate. mirror, and extract blobs for each 8 or 24-pixel row
    # Convert to black & white via greyscale (so that bits can be inverted)
    im = im.convert("L")  # Invert: Only works on 'L' images
    im = ImageOps.invert(im) # Bits are sent with 0 = white, 1 = black in ESC/POS
    im = im.convert("1") # Pure black and white
    im = im.transpose(Image.ROTATE_270).transpose(Image.FLIP_LEFT_RIGHT)
    line_height = 3 if high_density_vertical else 1
    blobs = _to_column_format (im, line_height * 8);

    # Generate ESC/POS header and print image
    ESC = b"\x1b";
    # Height and width refer to output size here, image is rotated in memory so coordinates are swapped
    height_pixels, width_pixels = im.size
    density_byte = (1 if high_density_horizontal else 0) + (32 if high_density_vertical else 0);
    header = ESC + b"*" + six.int2byte(density_byte) + _int_low_high( width_pixels, 2 );
    
    with os.fdopen(sys.stdout.fileno(), 'wb') as fp:
        fp.write(ESC + b"3" + six.int2byte(16)); # Adjust line-feed size
        for blob in blobs:
            fp.write(header + blob + b"\n")
        fp.write(ESC + b"2"); # Reset line-feed size
