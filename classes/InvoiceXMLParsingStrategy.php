<?php
/**
* Seagull PHP Framework
*
* LICENSE
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions
* are met:
*
*
* o Redistributions of source code must retain the above copyright
* notice, this list of conditions and the following disclaimer.
* o Redistributions in binary form must reproduce the above copyright
* notice, this list of conditions and the following disclaimer in the
* documentation and/or other materials provided with the distribution.
* o The names of the authors may not be used to endorse or promote
* products derived from this software without specific prior written
* permission.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
* "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
* LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
* A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
* OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
* SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
* LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
* DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
* THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
* (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
* OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*
* PHP version 5
* Seagull 0.6.5
* InvoiceXMLParsingStrategy.php
*
* @category Modules
* @package Invoice
* @author Clay Hinson <clay.hinson@garick.com>
* @copyright 2009 Garick
* @license http://opensource.org/licenses/bsd-license.php BSD License
* @version SVN: $Id: InvoiceXMLParsingStrategy.php 47 2009-11-17 20:07:45Z clay $
* @link $URL: https://clay.hinson%40garick.com@venture2.projectlocker.com/Garick/sgl_module_invoice/svn/tags/stable/classes/InvoiceXMLParsingStrategy.php $
*/

// require the xml parser lib if not already included
require_once SGL_LIB_DIR . '/other/XMLParsingStrategy.php';


/**
 * class InvoiceXMLParsingStrategy
 * parses invoice XML using inherited functionality from the XMLParsingStrategy library
 * uses the Decorator & strategy patterns (?)
 *
 * @package Invoice
 * @author  Clay Hinson <clay.hinson@garick.com>
 * @version $Rev: 47 $
 */
class InvoiceXMLParsingStrategy
{
    public $xmlfilePath;
    public static $vendorNumRegEx = '^([A-Z]{3}[0-9]{2,3})\.';
    public static $invoiceNumRegEx = '^([A-Z0-9]{1,10})(\-[0-9])?\+$';
    /**
     *
     */
    function __construct($filePath, $baseFileName)
    {
        $this->xmlFilePath = $filePath . $baseFileName . '.xml';
    }

    /**
     *
     */
    function parse()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $aInvoiceData = &XMLParsingStrategy::factory('ScannedInvoice',  $this->xmlFilePath, 'file', 'simple');

        // grab the raw file contents for storage
        $rawXML = file_get_contents($this->xmlFilePath);


        // extract the useful bits
        // vendor number should end in a period.
        // otherwise default to vendor number being first.

        // short circuit in case invoice data couldn't be fully found
        if(count($aInvoiceData) < 2) {
            // compress the text using zlib
            $gzXML = gzencode($rawXML, 9);
            $ret = array(false, false, $gzXML);
            return $ret;
        }



        // the barcodes in the XML file may not always be ours; have to search for the right strings.
        foreach($aInvoiceData AS $key => $xmlTags) {
            if(ereg(self::$vendorNumRegEx, $xmlTags['STRING'], $match)) {
                $vendorNum = $match[1];
            } else if(ereg(self::$invoiceNumRegEx, $xmlTags['STRING'], $match)) {
                $invoiceNum = $match[1];
            }
        }

        // old method. not reliable because other barcode strings appear in the xml.
        /*if(ereg($vendorNumRegEx, $aInvoiceData[0]['STRING'], $match)) {
            $vendorNum = $match[1];
            $invoiceNum = $aInvoiceData[1]['STRING'];
        } elseif(ereg($vendorNumRegEx, $aInvoiceData[1]['STRING'], $match)) {
            $vendorNum = $match[1];
            $invoiceNum = $aInvoiceData[0]['STRING'];
        } else {
            $vendorNum = $aInvoiceData[0]['STRING'];
            $invoiceNum = $aInvoiceData[1]['STRING'];
        }*/


    // compress the text using zlib
    //$gzXML = gzencode($rawXML, 9);

    // return an array with the invoice number, vendor number, and gzipped XML
    $ret = array($invoiceNum, $vendorNum, $rawXML);
    return $ret;
    }

}
?>
