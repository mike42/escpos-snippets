Escpos image benchmarks
-----------------------

Compare performance of image implementations over a test set.

Eg, [this collection of sample images](http://people.sc.fsu.edu/~jburkardt/data/png/png.html) can be tested like so:

    wget --recursive --level=1 --accept png --no-clobber --no-host-directories --no-directories http://people.sc.fsu.edu/~jburkardt/data/png/
    ./test_all.sh
 
The output is then saved to 'out.csv' and 'plot.pdf'.

