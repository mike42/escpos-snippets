#!/usr/bin/gnuplot -persist
set term pdf size 15cm,20cm
set output 'plot.pdf'

set key inside left top vertical Right noreverse enhanced autotitle box
set title "Time to generate output by language, format" 
set xlabel "PNG image bytes" 
set ylabel "Seconds" 

set datafile separator ","
set log x
set log y
plot for [i=5:4+n] 'out.csv' u 2:i title columnheader(i) ps 0.5

