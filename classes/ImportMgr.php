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
* ImportMgr.php
*
* @category Modules
* @package Invoice
* @author Clay Hinson <clay.hinson@garick.com>
* @copyright 2009 Garick
* @license http://opensource.org/licenses/bsd-license.php BSD License
* @version SVN: $Id: ImportMgr.php 57 2009-11-30 21:06:53Z clay.hinson@garick.com $
* @link http://garick.springloops.com/source/sgl_module_invoice/
*/

/*show errors */
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors',1);

// include dependencies
require_once 'InvoiceDAO.php';
require_once 'ImportBatch.php';

/**
 * This imports documents by evaluating OCR'ed XML files
 * this manager is designed to be run from CLI
 *
 * @package Invoice
 * @author  Clay Hinson <clay.hinson@garick.com>
 */
class ImportMgr extends SGL_Manager
{
    public function __construct()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        parent::SGL_Manager();

        $this->_aActionsMapping =  array(
            'list'          => array('list', 'cliResult'),
        );

        $this->da = InvoiceDAO::singleton();
    }

    public function validate(SGL_Request $req, SGL_Registry $input)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $this->validated    = true;
        $input->error       = array();
        $input->pageTitle   = $this->pageTitle;
        $input->masterTemplate = $this->masterTemplate;
        $input->template    = $this->template;
        $input->action      = ($req->get('action')) ? $req->get('action') : 'list';
        $input->simulate    = ($req->get('simulate')) ? $req->get('simulate') : 0;
        $input->tty      = "\n";
    }

    /*
    * This imports documents by evaluating OCR'ed XML files
     * The procedure is:
     * - read the folder containing the pdf & XML files
     * - get a list of XML files by name
     * - iterate through the list
     * - process the contents, searching for and parsing XML tags containing invoice/vendor numbers
     * - if an invoice number and vendor number pair is detected, check it for existence against the list of existing invoice/vendor number pairs
     * - if it exists, add the PDF to the mediamgr, create the document content, assign its type if possible, and link the existing invoice to the document content
     * - if the order does not exist, create the document content,
     * - then create the garick invoice content, set its properties based on SBT & document text, and link to the document content
     * - look up the entering user's email address based on the USERID via LDAP, and send a confirmation email\

     * notes: filenames end with _1, _2, etc. for multiple documents that were extracted from a single file.
     * to create a batch, this needs to be stripped, and the base filename should be stored and compared against the next filename.
     * if a match is not found, the current batch is done and an email should be sent to the user associated with the batch.
     * the email should contain a list of invoices entered in the current batch, with date, entered by, amount, etc
     * the email should be sent to all users found in the current batch
     *
     * @param SGL_Registry $input
     * @param SGL_Output $output
     */
    public function _cmd_list(SGL_Registry $input, SGL_Output $output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        // read the temp file folder
        $filePath = dirname(__FILE__) . $this->conf['InvoiceMgr']['importPath'];
        $pdfFileList = $this->da->getFileList($filePath);
        // loop through and parse each text file
        foreach($pdfFileList AS $id => $fileName) {
                SGL::logMessage($fileName, PEAR_LOG_DEBUG);
                if(!ereg("invoice", $fileName)) {
		           // skip non-invoices
                    continue;
		        }

                // get the root filename without the extension
                $baseFileName = substr($fileName, 0, strlen($fileName)-4);

                // get batchFileName
                if(ereg("^(invoice_[0-9]{8}-[0-9]{2}_.*)_([0-9]{1,4})$", $baseFileName, $match)) {
                    // get the root filename without the extension or the _1, _2, etc
                    $batchFileName = $match[1];
                    // get the page number
                    $batchPageNum = $match[2];
                } else {
                    // if this file contained no barcodes
                    $batchFileName = $baseFileName;
                    $batchPageNum = 1;
                }

                // set up a batch for handling emails, etc
                if(!isset($batch)) {
                    // get a batch singleton
                    $batch = & ImportBatch::singleton($input->simulate);
                    // add the base fileName to the batch array
                    $batch->batchFileName =  $batchFileName;
                    $batch->baseFileName = $baseFileName;
                    // baseFileName doesn't match, create a new batch and email the last batch
                } else if($batchFileName != $batch->batchFileName) {

                    // finalize the batch (send error notices and reset)
                    $batch->finalize();

                    // start over
                    // add the base fileName to the batch array
                    $batch->batchFileName = $batchFileName;
                    $batch->baseFileName = $baseFileName;
                }

                // read in the XML file, also checks to see if the file is named with the invoice info, skips barcode if so
                $aScannedInvoiceData = $this->da->parseScannedInvoiceXML($filePath, $baseFileName);

                // look for errors. if the first 2 elements here are false, there was a problem
                if(!$aScannedInvoiceData[0]) {
                    SGL::logMessage('Error accessing XML data or filename for invoice. Adding to DB as unlinked and sending error notice.', PEAR_LOG_DEBUG);

                    // add the document as a invoiceDoc, unlinked, with the filename as the media description
                    $docId = $this->da->createInvoiceDocContent($filePath, $fileName, $baseFileName,
                                                                $batchFileName, $fileName, $aScannedInvoiceData[2]);

                    // if creating the document didn't work then bail out.
                    if(PEAR::isError($docId)) {
                        continue;
                    } else {
                        // add the record to the batch in the errors array
                        $batch->addError(array('batchPageNumber' => $batchPageNum,
                                                 'fileName' => $fileName,
                                                 'documentId' => $docId));

                        // erase the files since they've been added to the system.
                        if(!PEAR::isError($docId) && !$input->simulate) {
                                // remove the original file
                                $this->da->deleteProcessedPDF($filePath, $fileName);
                                $this->da->deleteProcessedXML($filePath, $baseFileName);
                        }

                        // skip adding the invoice since there wasn't enough information
                        continue;
                    }
                }

                // continue to set up the invoice if there wasn't an parsing error above

                // set up the invoice variables
                list($invoiceNum, $invoiceVendorNum, $invoiceRawXML) = $aScannedInvoiceData;

                // this is used as the invoice name in the db
                $invoiceDescription = $invoiceVendorNum.'-'.$invoiceNum;

                // get the info about the invoice from SBT
                $SBTData = $this->da->getSBTInvoice($invoiceNum, $invoiceVendorNum);

                if(!$SBTData) {
                    // there was a problem.
                    SGL::logMessage('Invalid Data Returned from SBT/Web Connection', PEAR_LOG_DEBUG);

                    // TODO: add error notice to admin here, add error to batch

                    continue;
                }

                // add the invoice & vendor number to the array in the batch plus the other info
                $invoiceVendorName = $SBTData['company'];
                $invoiceAmount = $SBTData['puramt'];
                $invoiceComment = $SBTData['comment'];
                $invoiceUserId = $SBTData['enteredby'];

                if(empty($invoiceUserId)) {
                    $invoiceUserId = $this->conf['InvoiceMgr']['altInvoiceUserId'];
                }

                $batch->addInvoice(array('invoiceNum' => $invoiceNum,
                                           'vendorNum'  => $invoiceVendorNum,
                                           'vendorName' => $invoiceVendorName,
                                           'invoiceAmount' => $invoiceAmount,
                                           'invoiceComment' => $invoiceComment,
                                           'invoiceUserId' => $invoiceUserId,
                                           'batchPageNumber' => $batchPageNum));

                // lookup the user in the current batch or cache
                $userInfo = $batch->getUser($invoiceUserId);

                // add the user info to the batch if not present
                if(!$userInfo) {
                    // get the user info from the invoice info
                    // get userid from car report, and get user info from LDAP
                    $userInfo = $this->da->garickUserLookup($invoiceUserId);
                    list($invoiceUserName, $invoiceUserEmail) = $userInfo;

                    // add the user to the current batch
                    $batch->addUser($invoiceUserId, $invoiceUserName, $invoiceUserEmail);

                } else {
                    // the user was found, expand their details
                    $invoiceUserName = $userInfo['invoiceUserName'];
                    $invoiceUserEmail = $userInfo['invoiceUserEmail'];
                }

                /*
                * - if an invoice # & vendor is detected, check it for existence against the list of existing orders
                * - if it exists, add the PDF to the mediamgr, create the document content, assign its type if possible, and link
                *   the existing invoice to the document content
                * - if the invoice does not exist, create the document content,
                * - then create the garick invoice content, set its properties based on SBT & document text,
                *   and link to the document content
                *
                */

                // first ensure that the media & content does not already exist
                $docId = $this->da->searchByName($fileName);

                if(is_null($docId)) {
                    $newInvoiceDocument = true;
                    $docId = $this->da->createInvoiceDocContent($filePath, $fileName, $baseFileName, $batchFileName, $invoiceDescription, $invoiceRawXML);
                } else {
                    // docId was not null above
                    $newInvoiceDocument = false;
                }

                // the invoice document has been created or already exists, so now to add the invoice.
                // first, check to see if the invoice content already exists
                $invoiceId = $this->da->searchByName($invoiceDescription);
                if(is_null($invoiceId)) {
                    $invoiceId = $this->da->createInvoiceContent($invoiceDescription, $invoiceNum, $invoiceVendorNum,
                                                                $invoiceVendorName, $invoiceUserId, $invoiceUserName,
                                                                $invoiceUserEmail, $invoiceAmount,$batchFileName);
                }

                // link the invoice to the document for both new and existing invoices, but only for new invoice documents
                if($newInvoiceDocument) {
                    // link the contents
                    $this->da->addDocumentLinks($docId, $invoiceDescription);
                    // mark the document as linked
                    $this->da->markDocumentLinked($docId);

                    if(!PEAR::isError($docId) && !$input->simulate) {
                        // remove the original file
                        $this->da->deleteProcessedPDF($filePath, $fileName);
                        $this->da->deleteProcessedXML($filePath, $baseFileName);
                    }
                }


        } // end file loop

        // finalize the batch one last time if the last file has been processed.
        if(isset($batch)) {
            $batch->finalize();
        }
    }

    /**
     * Action, which outputs CLI result.
     *
     * @param SGL_Registry $input
     * @param SGL_Output $output
     */
    public function _cmd_cliResult(SGL_Registry $input, SGL_Output $output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $input->tty .= "\nDone.\n";
        $this->_flush($input->tty, $stopScript = true);
    }

    /**
     * Send data to terminal.
     *
     * @param string $string
     * @param boolean $stopScript
     */
    private function _flush(&$string, $stopScript = false)
    {
        echo $string;
        flush();
        $string = '';
        if ($stopScript) {
            exit;
        }
    }

}
?>
