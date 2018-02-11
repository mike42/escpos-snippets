<?php
/**
 * Interface passed to Escpos class for receiving print data. Print connectors
 * are responsible for transporting this to the actual printer.
 */
interface PrintConnector
{
    /**
     * Print connectors should cause a NOTICE if they are deconstructed
     * when they have not been finalized.
     */
    public function __destruct();
    
    /**
     * Finish using this print connector (close file, socket, send
     * accumulated output, etc).
     */
    public function finalize();
    
    /**
     * Read data from the printer.
     *
     * @param string $len Length of data to read.
     * @return Data read from the printer, or false where reading is not possible.
     */
    public function read($len);
    
    /**
     * Write data to the print connector.
     *
     * @param string $data The data to write
     */
    public function write($data);
}

/**
 * PrintConnector for passing print data to a file.
 */
class FilePrintConnector implements PrintConnector
{
    /**
     * @var resource $fp
     *  The file pointer to send data to.
     */
    protected $fp;
    
    /**
     * Construct new connector, given a filename
     *
     * @param string $filename
     */
    public function __construct($filename)
    {
        $this -> fp = fopen($filename, "wb+");
        if ($this -> fp === false) {
            throw new Exception("Cannot initialise FilePrintConnector.");
        }
    }
    
    public function __destruct()
    {
        if ($this -> fp !== false) {
            trigger_error("Print connector was not finalized. Did you forget to close the printer?", E_USER_NOTICE);
        }
    }
    
    /**
     * Close file pointer
     */
    public function finalize()
    {
        fclose($this -> fp);
        $this -> fp = false;
    }
    
    /* (non-PHPdoc)
     * @see PrintConnector::read()
     */
    public function read($len)
    {
        return fread($this -> fp, $len);
    }
    
    /**
     * Write data to the file
     *
     * @param string $data
     */
    public function write($data)
    {
        fwrite($this -> fp, $data);
    }
}

interface PrintBuffer
{
    /**
     * Cause the buffer to send any partial input and wait on a newline.
     * If the printer is already on a new line, this does nothing.
     */
    public function flush();
    
    /**
     * Used by Escpos to check if a printer is set.
     */
    public function getPrinter();
    
    /**
     * Used by Escpos to hook up one-to-one link between buffers and printers.
     *
     * @param Escpos $printer New printer
     */
    public function setPrinter(Printer $printer = null);
    
    /**
     * Accept UTF-8 text for printing.
     *
     * @param string $text Text to print
     */
    public function writeText($text);
    
    /**
     * Accept 8-bit text in the current encoding and add it to the buffer.
     *
     * @param string $text Text to print, already the target encoding.
     */
    public function writeTextRaw($text);
}


class Printer {
    /**
     * ASCII escape control character
     */
    const ESC = "\x1b";
    
    /**
     * Make a full cut, when used with Printer::cut
     */
    const CUT_FULL = 65;
    
    /**
     * ASCII group separator control character
     */
    const GS = "\x1d";
    
    /**
     * Use Font A, when used with Printer::setFont
     */
    const FONT_A = 0;
    
    /**
     * Use Font B, when used with Printer::setFont
     */
    const FONT_B = 1;
    
    /**
     * Use Font C, when used with Printer::setFont
     */
    const FONT_C = 2;
    
    
    /**
     * Use Font A, when used with Printer::selectPrintMode
     */
    const MODE_FONT_A = 0;
    
    /**
     * Use Font B, when used with Printer::selectPrintMode
     */
    const MODE_FONT_B = 1;
    
    /**
     * Use text emphasis, when used with Printer::selectPrintMode
     */
    const MODE_EMPHASIZED = 8;
    
    /**
     * Use double height text, when used with Printer::selectPrintMode
     */
    const MODE_DOUBLE_HEIGHT = 16;
    
    /**
     * Use double width text, when used with Printer::selectPrintMode
     */
    const MODE_DOUBLE_WIDTH = 32;
    
    /**
     * Underline text, when used with Printer::selectPrintMode
     */
    const MODE_UNDERLINE = 128;
    
    /**
     * @var PrintBuffer $buffer
     *  The printer's output buffer.
     */
    protected $buffer;
    
    /**
     * @var int $characterTable
     *  Current character code table
     */
    protected $characterTable;
    
    
    public function __construct(PrintConnector $connector) {
        /* Set connector */
        $this -> connector = $connector;
        /* Set buffer */
        $buffer = new UnicodePrintBuffer();
        $this -> buffer = null;
        $this -> setPrintBuffer($buffer);
        $this -> initialize();
    }
    
    /**
     * Close the underlying buffer. With some connectors, the
     * job will not actually be sent to the printer until this is called.
     */
    public function close()
    {
        $this -> connector -> finalize();
    }
    
    /**
     * Cut the paper.
     *
     * @param int $mode Cut mode, either Printer::CUT_FULL or Printer::CUT_PARTIAL. If not specified, `Printer::CUT_FULL` will be used.
     * @param int $lines Number of lines to feed
     */
    public function cut($mode = Printer::CUT_FULL, $lines = 3)
    {
        // TODO validation on cut() inputs
        $this -> connector -> write(self::GS . "V" . chr($mode) . chr($lines));
    }
    
    /**
     * @return number
     */
    public function getCharacterTable()
    {
        return $this -> characterTable;
    }
    
    /**
     * @return PrintBuffer
     */
    public function getPrintBuffer()
    {
        return $this -> buffer;
    }
    
    /**
     * @return PrintConnector
     */
    public function getPrintConnector()
    {
        return $this -> connector;
    }
    
    /**
     * Add text to the buffer.
     *
     * Text should either be followed by a line-break, or feed() should be called
     * after this to clear the print buffer.
     *
     * @param string $str Text to print
     */
    public function text($str = "")
    {
        $this -> buffer -> writeText((string)$str);
    }
    
    /**
     * Attach a different print buffer to the printer. Buffers are responsible for handling text output to the printer.
     *
     * @param PrintBuffer $buffer The buffer to use.
     * @throws InvalidArgumentException Where the buffer is already attached to a different printer.
     */
    public function setPrintBuffer(PrintBuffer $buffer)
    {
        if ($buffer === $this -> buffer) {
            return;
        }
        if ($buffer -> getPrinter() != null) {
            throw new InvalidArgumentException("This buffer is already attached to a printer.");
        }
        if ($this -> buffer !== null) {
            $this -> buffer -> setPrinter(null);
        }
        $this -> buffer = $buffer;
        $this -> buffer -> setPrinter($this);
    }
    
    /**
     * Initialize printer. This resets formatting back to the defaults.
     */
    public function initialize()
    {
        $this -> connector -> write(self::ESC . "@");
        $this -> characterTable = 0;
    }
    
    public function selectUserDefinedCharacterSet($on = true)
    {
        $this -> connector -> write(self::ESC . "%". ($on ? chr(1) : chr(0)));
    }
    
}

class ColumnFormatGlyph {
    public $width;
    public $data;
    
    function segment(int $maxWidth) {
        if($this -> width <= $maxWidth) {
            return [$this];
        }
        $dataChunks = str_split($this -> data, $maxWidth * 3);
        $ret = [];
        foreach($dataChunks as $chunk) {
            $g = new ColumnFormatGlyph();
            $g -> data = $chunk;
            $g -> width = strlen($chunk) / 3;
            $ret[] = $g;
        }
        return $ret;
    }
}

interface ColumnFormatGlyphFactory {
    function getGlyph($codePoint);
}

class UnifontCodeSource implements ColumnFormatGlyphFactory {
    protected $unifontFile;

    public static function colFormat16(array $in) {
        // Map 16 x 16 bit unifont (32 bytes) to 16 x 24 ESC/POS column format image (48 bytes).
        return UnifontCodeSource::colFormat8($in, 2, 1) . UnifontCodeSource::colFormat8($in, 2, 2);
    }
    
    public static function colFormat8(array $in, $chars = 1, $idx = 1) {
        // Map 8 x 16 bit unifont (32 bytes) to 8 x 24 ESC/POS column format image (24 bytes).
        return implode([
            chr(
                (($in[0 * $chars + $idx] & 0x80)) |
                (($in[1 * $chars + $idx] & 0x80) >> 1) |
                (($in[2 * $chars + $idx] & 0x80) >> 2) |
                (($in[3 * $chars + $idx] & 0x80) >> 3) |
                (($in[4 * $chars + $idx] & 0x80) >> 4) |
                (($in[5 * $chars + $idx] & 0x80) >> 5) |
                (($in[6 * $chars + $idx] & 0x80) >> 6) |
                (($in[7 * $chars + $idx] & 0x80) >> 7)
                ),
            chr(
                (($in[8 * $chars + $idx] & 0x80)) |
                (($in[9 * $chars + $idx] & 0x80) >> 1) |
                (($in[10 * $chars + $idx] & 0x80) >> 2) |
                (($in[11 * $chars + $idx] & 0x80) >> 3) |
                (($in[12 * $chars + $idx] & 0x80) >> 4) |
                (($in[13 * $chars + $idx] & 0x80) >> 5) |
                (($in[14 * $chars + $idx] & 0x80) >> 6) |
                (($in[15 * $chars + $idx] & 0x80) >> 7)
                ),
            chr(0),
            chr(
                (($in[0 * $chars + $idx] & 0x40) << 1) |
                (($in[1 * $chars + $idx] & 0x40)) |
                (($in[2 * $chars + $idx] & 0x40) >> 1) |
                (($in[3 * $chars + $idx] & 0x40) >> 2) |
                (($in[4 * $chars + $idx] & 0x40) >> 3) |
                (($in[5 * $chars + $idx] & 0x40) >> 4) |
                (($in[6 * $chars + $idx] & 0x40) >> 5) |
                (($in[7 * $chars + $idx] & 0x40) >> 6)
                ),
            chr(
                (($in[8 * $chars + $idx] & 0x40) << 1) |
                (($in[9 * $chars + $idx] & 0x40) >> 0) |
                (($in[10 * $chars + $idx] & 0x40) >> 1) |
                (($in[11 * $chars + $idx] & 0x40) >> 2) |
                (($in[12 * $chars + $idx] & 0x40) >> 3) |
                (($in[13 * $chars + $idx] & 0x40) >> 4) |
                (($in[14 * $chars + $idx] & 0x40) >> 5) |
                (($in[15 * $chars + $idx] & 0x40) >> 6)
                ),
            chr(0),
            chr(
                (($in[0 * $chars + $idx] & 0x20) << 2) |
                (($in[1 * $chars + $idx] & 0x20) << 1) |
                (($in[2 * $chars + $idx] & 0x20)) |
                (($in[3 * $chars + $idx] & 0x20) >> 1) |
                (($in[4 * $chars + $idx] & 0x20) >> 2) |
                (($in[5 * $chars + $idx] & 0x20) >> 3) |
                (($in[6 * $chars + $idx] & 0x20) >> 4) |
                (($in[7 * $chars + $idx] & 0x20) >> 5)
                ),
            chr(
                (($in[8 * $chars + $idx] & 0x20) << 2) |
                (($in[9 * $chars + $idx] & 0x20) << 1) |
                (($in[10 * $chars + $idx] & 0x20)) |
                (($in[11 * $chars + $idx] & 0x20) >> 1) |
                (($in[12 * $chars + $idx] & 0x20) >> 2) |
                (($in[13 * $chars + $idx] & 0x20) >> 3) |
                (($in[14 * $chars + $idx] & 0x20) >> 4) |
                (($in[15 * $chars + $idx] & 0x20) >> 5)
                ),
            chr(0),
            chr(
                (($in[0 * $chars + $idx] & 0x10) << 3) |
                (($in[1 * $chars + $idx] & 0x10) << 2) |
                (($in[2 * $chars + $idx] & 0x10) << 1) |
                (($in[3 * $chars + $idx] & 0x10)) |
                (($in[4 * $chars + $idx] & 0x10) >> 1) |
                (($in[5 * $chars + $idx] & 0x10) >> 2) |
                (($in[6 * $chars + $idx] & 0x10) >> 3) |
                (($in[7 * $chars + $idx] & 0x10) >> 4)
                ),
            chr(
                (($in[8 * $chars + $idx] & 0x10) << 3) |
                (($in[9 * $chars + $idx] & 0x10) << 2) |
                (($in[10 * $chars + $idx] & 0x10) << 1) |
                (($in[11 * $chars + $idx] & 0x10)) |
                (($in[12 * $chars + $idx] & 0x10) >> 1) |
                (($in[13 * $chars + $idx] & 0x10) >> 2) |
                (($in[14 * $chars + $idx] & 0x10) >> 3) |
                (($in[15 * $chars + $idx] & 0x10) >> 4)
                ),
            chr(0),
            chr(
                (($in[0 * $chars + $idx] & 0x08) << 4) |
                (($in[1 * $chars + $idx] & 0x08) << 3) |
                (($in[2 * $chars + $idx] & 0x08) << 2) |
                (($in[3 * $chars + $idx] & 0x08) << 1) |
                (($in[4 * $chars + $idx] & 0x08)) |
                (($in[5 * $chars + $idx] & 0x08) >> 1) |
                (($in[6 * $chars + $idx] & 0x08) >> 2) |
                (($in[7 * $chars + $idx] & 0x08) >> 3)
                ),
            chr(
                (($in[8 * $chars + $idx] & 0x08) << 4) |
                (($in[9 * $chars + $idx] & 0x08) << 3) |
                (($in[10 * $chars + $idx] & 0x08) << 2) |
                (($in[11 * $chars + $idx] & 0x08) << 1) |
                (($in[12 * $chars + $idx] & 0x08)) |
                (($in[13 * $chars + $idx] & 0x08) >> 1) |
                (($in[14 * $chars + $idx] & 0x08) >> 2) |
                (($in[15 * $chars + $idx] & 0x08) >> 3)
                ),
            chr(0),
            chr(
                (($in[0 * $chars + $idx] & 0x04) << 5) |
                (($in[1 * $chars + $idx] & 0x04) << 4) |
                (($in[2 * $chars + $idx] & 0x04) << 3) |
                (($in[3 * $chars + $idx] & 0x04) << 2) |
                (($in[4 * $chars + $idx] & 0x04) << 1) |
                (($in[5 * $chars + $idx] & 0x04)) |
                (($in[6 * $chars + $idx] & 0x04) >> 1) |
                (($in[7 * $chars + $idx] & 0x04) >> 2)
                ),
            chr(
                (($in[8 * $chars + $idx] & 0x04) << 5) |
                (($in[9 * $chars + $idx] & 0x04) << 4) |
                (($in[10 * $chars + $idx] & 0x04) << 3) |
                (($in[11 * $chars + $idx] & 0x04) << 2) |
                (($in[12 * $chars + $idx] & 0x04) << 1) |
                (($in[13 * $chars + $idx] & 0x04)) |
                (($in[14 * $chars + $idx] & 0x04) >> 1) |
                (($in[15 * $chars + $idx] & 0x04) >> 2)
                ),
            chr(0),
            chr(
                (($in[0 * $chars + $idx] & 0x02) << 6) |
                (($in[1 * $chars + $idx] & 0x02) << 5) |
                (($in[2 * $chars + $idx] & 0x02) << 4) |
                (($in[3 * $chars + $idx] & 0x02) << 3) |
                (($in[4 * $chars + $idx] & 0x02) << 2) |
                (($in[5 * $chars + $idx] & 0x02) << 1) |
                (($in[6 * $chars + $idx] & 0x02)) |
                (($in[7 * $chars + $idx] & 0x02) >> 1)
                ),
            chr(
                (($in[8 * $chars + $idx] & 0x02) << 6) |
                (($in[9 * $chars + $idx] & 0x02) << 5) |
                (($in[10 * $chars + $idx] & 0x02) << 4) |
                (($in[11 * $chars + $idx] & 0x02) << 3) |
                (($in[12 * $chars + $idx] & 0x02) << 2) |
                (($in[13 * $chars + $idx] & 0x02) << 1) |
                (($in[14 * $chars + $idx] & 0x02)) |
                (($in[15 * $chars + $idx] & 0x02) >> 1)
                ),
            chr(0),
            chr(
                (($in[0 * $chars + $idx] & 0x01) << 7) |
                (($in[1 * $chars + $idx] & 0x01) << 6) |
                (($in[2 * $chars + $idx] & 0x01) << 5) |
                (($in[3 * $chars + $idx] & 0x01) << 4) |
                (($in[4 * $chars + $idx] & 0x01) << 3) |
                (($in[5 * $chars + $idx] & 0x01) << 2) |
                (($in[6 * $chars + $idx] & 0x01) << 1) |
                (($in[7 * $chars + $idx] & 0x01))
                ),
            chr(
                (($in[8 * $chars + $idx] & 0x01) << 7) |
                (($in[9 * $chars + $idx] & 0x01) << 6) |
                (($in[10 * $chars + $idx] & 0x01) << 5) |
                (($in[11 * $chars + $idx] & 0x01) << 4) |
                (($in[12 * $chars + $idx] & 0x01) << 3) |
                (($in[13 * $chars + $idx] & 0x01) << 2) |
                (($in[14 * $chars + $idx] & 0x01) >> 1) |
                (($in[15 * $chars + $idx] & 0x01))
                ),
            chr(0)
        ]);
    }

    public function __construct() {
        $unifont = "/usr/share/unifont/unifont.hex";
        $this -> unifontFile = explode("\n", file_get_contents($unifont));
    }

    public function getGlyph($codePoint) {
        // Binary search for correct line.
        $min = 0;
        $max = count($this -> unifontFile) - 1;
        $foundId = 0;
        $m = 255; // Bias toward low side.
        while($min <= $max) {
            $thisCodePoint = hexdec(substr($this -> unifontFile[$m], 0, 4));
            if($codePoint === $thisCodePoint) {
                $foundId = $m;
                break;
            } else if($codePoint < $thisCodePoint) {
                $max = $m - 1;
            } else {
                $min = $m + 1;
            }
            $m = floor(($min + $max) / 2);
        }
        $unifontLine = $this -> unifontFile[$foundId];

        // Convert to column format
        $binStr = unpack("C*", pack("H*", substr($unifontLine, 5)));
        $bytes = count($binStr);
        if($bytes == 32) {
            $width = 16;
            $colFormat = UnifontCodeSource::colFormat16($binStr);
        } else if($bytes == 16) {
            $width = 8;
            $colFormat = UnifontCodeSource::colFormat8($binStr);
        }
        // Write to obj
        $glyph = new ColumnFormatGlyph();
        $glyph -> width = $width;
        $glyph -> data = $colFormat;
        return $glyph;
    }

}

class FontMap {
    protected $printer;

    const MIN = 0x20;
    const MAX = 0x7E;
    const FONT_A_WIDTH = 12;
    const FONT_B_WIDTH = 9;
    
    // Map memory locations to code points
    protected $memory;
    
    // Map unicode code points to bytes
    protected $chars;
    
    // next available slot
    protected $next = 0;
     
    public function __construct(ColumnFormatGlyphFactory $glyphFactory, Printer $printer) {
        $this -> printer = $printer;
        $this -> glyphFactory = $glyphFactory;
        $this -> reset();
    }
    
    public function cacheChars(array $codePoints) {
        // TODO flush existing cache to fill with these chars.
    }
    
    public function writeChar(int $codePoint) {
        if(!$this -> addChar($codePoint, true)) {
            throw new InvalidArgumentException("Code point $codePoint not available");
        }
        $data = implode($this -> chars[$codePoint]);
        $this -> printer -> getPrintConnector() -> write($data);
    }
    
    public function reset() {
        $this -> chars = [];
        $this -> memory = array_fill(0, (FontMap::MAX - FontMap::MIN) + 1, -1);
    }
    
    public function occupied($id) {
        return $this -> memory[$id] !== -1;
    }
    
    public function evict($id) {
        if(!$this -> occupied($id)) {
            return true;
        }
        unset($this -> chars[$this -> memory[$id]]);
        $this -> memory[$id] = -1;
        return true;
    }
    
    public function addChar(int $codePoint, $evict = true) {
        if(isset($this -> chars[$codePoint])) {
            // Char already available
            return true;
        }
        // Get glyph
        $glyph = $this -> glyphFactory -> getGlyph($codePoint);
        $glyphParts = $glyph -> segment(self::FONT_B_WIDTH);
        //print_r($glyphParts);
        //
        // Clear count($glyphParts) of space from $start
        $start = $this -> next;
        $chars = [];
        $submit = [];
        for($i = 0; $i < count($glyphParts); $i++) {
            $id = ($this -> next + $i) % count($this -> memory);
            if($this -> occupied($id)) {
                if($evict){
                    $this -> evict($id);
                } else {
                    return false;
                }
            }
            $thisChar = $id + self::MIN;
            $chars[] = chr($thisChar);
            $submit[$thisChar] = $glyphParts[$i];
        }
        
        // Success in locating memory space, move along counters
        $this -> next = ($this -> next + count($glyphParts)) % count($this -> memory);
        $this -> submitCharsToPrinterFont($submit);
        $this -> memory[$start] = $codePoint;
        $this -> chars[$codePoint] = $chars;
        
        return true;
    }
    
    public function submitCharsToPrinterFont(array $chars) {
        ksort($chars);
        // TODO We can sort into batches of contiguous characters here.
        foreach($chars as $char => $glyph) {
            $verticalBytes = 3;
            $data = Printer::ESC . "&" . chr($verticalBytes) . chr($char) . chr($char) . chr($glyph -> width) . $glyph -> data;
            $this -> printer -> getPrintConnector() -> write($data);
        }
    }
}

class UnicodePrintBuffer implements PrintBuffer {
    protected $printer;
    protected $fontMap;
    protected $started = false;
    public function __construct() {
        $this -> unifont = new UnifontCodeSource();
    }
    
    public function writeChar($codePoint) {
        if($codePoint == 10) {
            $this -> write("\n");
        } else if($codePoint == 13) {
            // Ignore CR char
        } else {
            // Straight column-format prints
            $this -> fontMap -> writeChar($codePoint);
        }
    }
    
    public function writeText($text)
    {
        if(!$this -> started) {
            $mode = Printer::MODE_FONT_B | Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH;
            $this -> printer -> getPrintConnector() -> write(Printer::ESC . "!" . chr($mode));
            $this -> printer -> selectUserDefinedCharacterSet(true);
        }
        $chrArray = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $codePoints = array_map("IntlChar::ord", $chrArray);
        foreach($codePoints as $char) {
            $this -> writeChar($char);
        }
    }
    
    public function flush()
    {
        
    }
    
    public function setPrinter(Printer $printer = null)
    {
        $this -> printer = $printer;
        $this -> fontMap = new FontMap($this -> unifont, $this -> printer);
    }
    
    public function writeTextRaw($text)
    {}
    
    public function getPrinter()
    {
        return $this -> printer;
    }
    
    /**
     * Write data to the underlying connector.
     *
     * @param string $data
     */
    private function write($data)
    {
        $this -> printer -> getPrintConnector() -> write($data);
    }
}

$text = file_get_contents("php://stdin");
$connector = new FilePrintConnector("php://stdout");
$printer = new Printer($connector);
$printer -> text($text);
$printer -> cut();
$printer -> close();

?>