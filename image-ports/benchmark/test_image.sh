#!/bin/bash

function runtime {
    # Return runtime of a process in seconds
    >&2 echo -- $@ 
    a=`mktemp`
    >&2 /usr/bin/time --quiet --format='%e' --output=$a $@ > /dev/null
    ret=$?
    num=`cat $a | tr -d '\n'`
    echo -n $num
}

size=`stat --printf="%s" "$1"`
dimensions=`identify -format "%w,%h" "$1"`
t1=`runtime php ../columnFormat.php "$1"`
t2=`runtime php ../rasterFormat.php "$1"`
t3=`runtime python3 ../columnFormat.py "$1"`
t4=`runtime python3 ../rasterFormat.py "$1"`
t5=`runtime php ../drivers/escpos-php.php "$1"`
t6=`runtime python3 ../drivers/python-escpos.py "$1"`

echo $1,$size,$dimensions,$t1,$t2,$t3,$t4,$t5,$t6 >> out.csv

