# chrome-escpos-receipt

Proof of concept snippet to print a "Hello world" receipt on a thermal receipt or impact printer, as a Chrome app.

The loaded USB vendor and product ID's are for:

- Epson TM-T20
- PL2305 Parallel Port
- Winbond Virtual Com Port.

## Install

- Under `chrome://extensions`, tick developer mode, and 'load unpacked extension', and locate this folder.
- Launch the extension, select your printer
 - If not listed, find the USB product, vendor ID, add it to `manifest.md`, and restart.
- Click print
 - If it doesn't print, check the error log and see the notes within the app for the basic things to check.
 - In particular, unload the `usblp` kernel module if applicable as it will claim the USB interface to the printer.

# Screenshot
![screenshot](https://github.com/mike42/escpos-snippets/raw/master/chrome-escpos-receipt/assets/screenshot.png)

