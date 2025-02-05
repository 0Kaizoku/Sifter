<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Pdf
 */

namespace ZendPdf\Cmap;

use ZendPdf as Pdf;
use ZendPdf\Exception;

/**
 * Implements the "segment mapping to delta values" character map (type 4).
 *
 * This is the Microsoft standard mapping table type for OpenType fonts. It
 * provides the ability to cover multiple contiguous ranges of the Unicode
 * character set, with the exception of Unicode Surrogates (U+D800 - U+DFFF).
 *
 * @package    Zend_PDF
 * @subpackage Zend_PDF_Font
 */
class SegmentToDelta extends AbstractCmap
{
    /**** Instance Variables ****/


    /**
     * The number of segments in the table.
     * @var integer
     */
    protected $_segmentCount = 0;

    /**
     * The size of the binary search range for segments.
     * @var integer
     */
    protected $_searchRange = 0;

    /**
     * The number of binary search steps required to cover the entire search
     * range.
     * @var integer
     */
    protected $_searchIterations = 0;

    /**
     * Array of ending character codes for each segment.
     * @var array
     */
    protected $_segmentTableEndCodes = array();

    /**
     * The ending character code for the segment at the end of the low search
     * range.
     * @var integer
     */
    protected $_searchRangeEndCode = 0;

    /**
     * Array of starting character codes for each segment.
     * @var array
     */
    protected $_segmentTableStartCodes = array();

    /**
     * Array of character code to glyph delta values for each segment.
     * @var array
     */
    protected $_segmentTableIdDeltas = array();

    /**
     * Array of offsets into the glyph index array for each segment.
     * @var array
     */
    protected $_segmentTableIdRangeOffsets = array();

    /**
     * Glyph index array. Stores glyph numbers, used with range offset.
     * @var array
     */
    protected $_glyphIndexArray = array();



    /**** Public Interface ****/


    /* Concrete Class Implementation */

    /**
     * Returns an array of glyph numbers corresponding to the Unicode characters.
     *
     * If a particular character doesn't exist in this font, the special 'missing
     * character glyph' will be substituted.
     *
     * See also {@link glyphNumberForCharacter()}.
     *
     * @param array $characterCodes Array of Unicode character codes (code points).
     * @return array Array of glyph numbers.
     */
    public function glyphNumbersForCharacters($characterCodes)
    {
        $glyphNumbers = array();
        foreach ($characterCodes as $key => $characterCode) {

            /* These tables only cover the 16-bit character range.
             */
            if ($characterCode > 0xffff) {
                $glyphNumbers[$key] = AbstractCmap::MISSING_CHARACTER_GLYPH;
                continue;
            }

            /* Determine where to start the binary search. The segments are
             * ordered from lowest-to-highest. We are looking for the first
             * segment whose end code is greater than or equal to our character
             * code.
             *
             * If the end code at the top of the search range is larger, then
             * our target is probably below it.
             *
             * If it is smaller, our target is probably above it, so move the
             * search range to the end of the segment list.
             */
            if ($this->_searchRangeEndCode >= $characterCode) {
                $searchIndex = $this->_searchRange;
            } else {
                $searchIndex = $this->_segmentCount;
            }

            /* Now do a binary search to find the first segment whose end code
             * is greater or equal to our character code. No matter the number
             * of segments (there may be hundreds in a large font), we will only
             * need to perform $this->_searchIterations.
             */
            for ($i = 1; $i <= $this->_searchIterations; $i++) {
                if ($this->_segmentTableEndCodes[$searchIndex] >= $characterCode) {
                    $subtableIndex = $searchIndex;
                    $searchIndex -= $this->_searchRange >> $i;
                } else {
                    $searchIndex += $this->_searchRange >> $i;
                }
            }

            /* If the segment's start code is greater than our character code,
             * that character is not represented in this font. Move on.
             */
            if ($this->_segmentTableStartCodes[$subtableIndex] > $characterCode) {
                $glyphNumbers[$key] = AbstractCmap::MISSING_CHARACTER_GLYPH;
                continue;
            }

            if ($this->_segmentTableIdRangeOffsets[$subtableIndex] == 0) {
                /* This segment uses a simple mapping from character code to
                 * glyph number.
                 */
                $glyphNumbers[$key] = ($characterCode + $this->_segmentTableIdDeltas[$subtableIndex]) % 65536;

            } else {
                /* This segment relies on the glyph index array to determine the
                 * glyph number. The calculation below determines the correct
                 * index into that array. It's a little odd because the range
                 * offset in the font file is designed to quickly provide an
                 * address of the index in the raw binary data instead of the
                 * index itself. Since we've parsed the data into arrays, we
                 * must process it a bit differently.
                 */
                $glyphIndex = ($characterCode - $this->_segmentTableStartCodes[$subtableIndex] +
                               $this->_segmentTableIdRangeOffsets[$subtableIndex] - $this->_segmentCount +
                               $subtableIndex - 1);
                $glyphNumbers[$key] = $this->_glyphIndexArray[$glyphIndex];

            }

        }
        return $glyphNumbers;
    }

    /**
     * Returns the glyph number corresponding to the Unicode character.
     *
     * If a particular character doesn't exist in this font, the special 'missing
     * character glyph' will be substituted.
     *
     * See also {@link glyphNumbersForCharacters()} which is optimized for bulk
     * operations.
     *
     * @param integer $characterCode Unicode character code (code point).
     * @return integer Glyph number.
     */
    public function glyphNumberForCharacter($characterCode)
    {
        /* This code is pretty much a copy of glyphNumbersForCharacters().
         * See that method for inline documentation.
         */

        if ($characterCode > 0xffff) {
            return AbstractCmap::MISSING_CHARACTER_GLYPH;
        }

        if ($this->_searchRangeEndCode >= $characterCode) {
            $searchIndex = $this->_searchRange;
        } else {
            $searchIndex = $this->_segmentCount;
        }

        for ($i = 1; $i <= $this->_searchIterations; $i++) {
            if ($this->_segmentTableEndCodes[$searchIndex] >= $characterCode) {
                $subtableIndex = $searchIndex;
                $searchIndex -= $this->_searchRange >> $i;
            } else {
                $searchIndex += $this->_searchRange >> $i;
            }
        }

        if ($this->_segmentTableStartCodes[$subtableIndex] > $characterCode) {
            return AbstractCmap::MISSING_CHARACTER_GLYPH;
        }

        if ($this->_segmentTableIdR                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            