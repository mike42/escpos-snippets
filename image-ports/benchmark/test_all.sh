#!/bin/bash
# Header
echo "file,bytes,width,height,PHP columnFormat,PHP rasterFormat,Python columnFormat,Python rasterFormat,escpos-php,python-escpos" > out.csv

# nCPUs minus 1 so that we aren't here all week
CONCURRENCY=$((`getconf _NPROCESSORS_ONLN`-1)) 

# Execute w/ progress bar
find . -name '*.png' -print0 | parallel --no-notice --bar -0 -P 7 --no-run-if-empty --max-args=1 ./test_image.sh

# Plot everything
cols=6
gnuplot -e "n=$cols" plot.gnuplot
