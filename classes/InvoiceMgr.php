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
* InvoiceMgr.php
*
* @category Modules
* @package Invoice
* @author Clay Hinson <clay.hinson@garick.com>
* @copyright 2009 Garick
* @license http://opensource.org/licenses/bsd-license.php BSD License
* @version SVN: $Id: InvoiceMgr.php 47 2009-11-17 20:07:45Z clay $
* @link $URL: https://clay.hinson%40garick.com@venture2.projectlocker.com/Garick/sgl_module_invoice/svn/tags/stable/classes/InvoiceMgr.php $
*/

/* Include the module's DAO */
require_once 'InvoiceDAO.php';


/**
 * class InvoiceMgr
 * User interface to the Invoice module.
 * Provides a search function and PDF display capability
 *
 * @package Invoice
 * @author  Clay Hinson <clay.hinson@garick.com>
 * @version $Rev: 47 $
 */
class InvoiceMgr extends SGL_Manager
{
    function __construct()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        parent::SGL_Manager();

        $this->pageTitle    = 'Invoice Manager';
        $this->template     = 'invoiceList.html';

        $this->_aActionsMapping =  array(
            'list'      => array('list'),
            'view'      => array('viewPdf'),
            'search'    => array('search'),

        );

        $this->da = InvoiceDAO::singleton();
    }

    /**
     * processes submitted input before continuing
     *
     * @param SGL_Request $req
     * @param SGL_Registry $input
     */
    function validate(SGL_Request $req, SGL_Registry $input)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $this->validated    = true;
        $input->error       = array();
        $input->pageTitle   = $this->pageTitle;
        $input->masterTemplate = $this->masterTemplate;
        $input->template    = $this->template;
        $input->action      = ($req->get('action')) ? $req->get('action') : 'search';
        $input->submitted   = $req->get('submitted');

        // module-specific variables coming from the form
        $input->invoice = (object)$req->get('invoice');
        $input->invoiceId = $req->get('frmInvoiceId');
        $input->vendorId = $req->get('frmVendorId');
        $input->invoiceName = $input->vendorId .'-'.$input->invoiceId;
        $input->documentId = $req->get('frmDocumentId');

        // used for the pager
        $input->page = ($req->get('page')) ? $req->get('page') : 1;

        // determine searchmode based on which button was pressed
        if ($req->get('searchinvoice')) {
            $input->searchMode = 'invoice';
            if(!ereg(InvoiceXMLParsingStrategy::$invoiceNumRegEx, ($input->invoiceId . '+'))) {
            $aErrors['invoice'] = 'Invoice number invalid. If searching by invoice, please enter an invoice number to search for.';
            }
        }
        if ($req->get('searchvendor')) {
            $input->searchMode = 'vendor';
            if(!ereg(InvoiceXMLParsingStrategy::$vendorNumRegEx, ($input->vendorId . '.') )) {
            $aErrors['vendor'] = 'Vendor number invalid. Please enter a vendor number to search for in the form AAA99.';
            }
        }

        //  if errors have occurred, display them
        if (isset($aErrors) && count($aErrors)) {
            SGL::raiseMsg('Please fill in the indicated fields');
            $input->error = $aErrors;
            $this->validated = false;
        }
    }
    /**
     * sets output variables & handles any actions needed by all displayed pages
     *
     * @param SGL_Output $output
     */
    function display(SGL_Output $output)
    {
        if ($this->conf['InvoiceMgr']['showUntranslated'] == false) {
            $c = &SGL_Config::singleton();
            $c->set('debug', array('showUntranslated' => false));
        }
         $output->masterLayout = 'layout-navtop-1col.css';

         $output->STATUS_IN_PROGRESS = INVOICE_STATUS_IN_PROGRESS;
         $output->STATUS_APPROVED = INVOICE_STATUS_APPROVED;
         $output->STATUS_REVIEW = INVOICE_STATUS_REVIEW;
    }


    /**
     * typically default action when module is loaded
     * for invoice module, this displays a search form, but the default action is 'search'
     *
     * @param SGL_Registry $input
     * @param SGL_Output $output
     */
    function _cmd_list(SGL_Registry $input, SGL_Output $output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $output->masterTemplate = "masterNoCols.html";
        $output->template  = 'invoiceSearch.html';
        $output->pageTitle = 'Search Invoices';

    }

    /**
     * generates a PDF from media module which is displayed locally
     *
     * @param SGL_Registry $input
     * @param SGL_Output $output
     */
    function _cmd_viewPdf(SGL_Registry $input, SGL_Output $output)
    {
        // get a media object based on documentId
        $media = $this->da->getMediaById($input->documentId);
        // get the full path
        $filePath = SGL_UPLOAD_DIR . '/' . $media->file_name;
        // set the mime type to PDF manually
        $mimeType = 'application/pdf';

        // generate a file stream
        return $this->_generateFileStream($filePath, $mimeType, $media->description.'.pdf');
     }

    /**
     * private function which reads the requested file contents and sends back an inline file stream to the browser
     *
     * @access protected
     * @param SGL_Registry $input
     * @param SGL_Output $output*/
    private function _generateFileStream($filePath, $mimeType, $displayName)
    {
        // raise an error if no file is found
        if (!@is_file($filePath)) {
            SGL::raiseError('The specified file does not appear to exist',
            SGL_ERROR_NOFILE);
            return false;
        }
        // read in the file rather than output it directly
        // this allows the filename in the stream to be customized
        $fh = file_get_contents($filePath);

        // use an SGL_Download object
        $dl = &new SGL_Download();
        $dl->setData($fh);
        $dl->setContentType($mimeType);
        $dl->setContentDisposition(HTTP_DOWNLOAD_INLINE, $displayName);
        $dl->setAcceptRanges('none');
        $error = $dl->send();

        if (PEAR::isError($error)) {
            SGL::raiseError('There was an error displaying the file',
            SGL_ERROR_NOFILE);
        }
        // exit after generating the stream
        exit;
    }

    /**
     * default action for the invoice module
     * displays a search form, and searches by either invoice # or vendor #.
     *
     * @param SGL_Registry $input
     * @param SGL_Output $output
     */
    function _cmd_search(SGL_Registry $input, SGL_Output $output) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $output->masterTemplate = "masterNoCols.html";
        $output->template  = 'invoiceSearch.html';
        $output->pageTitle = 'Search Invoices';

        // perform the search
        if($input->submitted) {

            // set the template and page title
            $output->template  = 'invoiceList.html';
            $output->pageTitle = 'Search Results';

            // determine which kind of search is running
            switch($input->searchMode) {
                // search by invoice
                case 'invoice':
                    // filtered search of CMS database
                    $oContent = SGL_Finder::factory('content')
                        ->addFilter('typeName', 'Garick Invoice')
                        ->addFilter('attribute', array(
                                    'name'     => 'invoiceNum',
                                    'operator' => '=',
                                    'value'    => $input->invoiceId))
                        ->addFilter('sortBy', 'name')
                        ->addFilter('sortOrder', 'DESC')
                        ->retrieve();
                    // if no error, and the content is found
                    if(!PEAR::isError($oContent) && count($oContent) > 0) {
                        $aRet = $oContent;
                        // get the linked documents for the content object
                        // this always returns in an array, so jump to the first element
                        $docList = $aRet[0]->getLinkedContents();

                        // the template is expecting an array of document lists
                        $output->docList[] = $docList;
                    } else {
                        // the search returns false
                        $aRet = false;
                    }
                    break;
               // search by vendor
               case 'vendor':
                    // filtered search of CMS database
                    $aRet = SGL_Finder::factory('content')
                        ->addFilter('typeName', 'Garick Invoice')
                        ->addFilter('attribute', array(
                                    'name'     => 'vendorNum',
                                    'operator' => '=',
                                    'value'    => $input->vendorId))
                        ->addFilter('sortBy', 'name')
                        ->addFilter('sortOrder', 'DESC')
                        ->retrieve();

                    // if no error, and the content is found
                    if(!PEAR::isError($aRet) && count($aRet) > 0) {

                        // loop through the found content objects array
                        foreach($aRet AS $key => &$oContent) {
                            // get the linked documents for each
                            $dl = $oContent->getLinkedContents();
                            // send it back in array with matching keys,
                            // so the documents can be arranged properly on the page
                            $docList[$key] = $dl;
                        }
                        // send the array to output
                        $output->docList = $docList;

                        // clear the last $oContent
                        unset($oContent);

                    } else {
                        // search returns false
                        $aRet = false;
                    }

                    /**
                     * @todo add pager for large resultsets
                     */
                    break;
                default:
                    // by default return false as if no search results
                    $aRet = false;
                    break;
            }


            // send the result to the template
            if($aRet === FALSE) {
                    $output->template = 'invoiceSearch.html';

                    SGL::raiseMsg('There were no invoices that matched your search.');

            } else {
                 $output->result = $aRet;

            }

        }
    }
}
?>
