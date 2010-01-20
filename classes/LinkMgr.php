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
* LinkMgr.php
*
* @category Modules
* @package Invoice
* @author Clay Hinson <clay.hinson@garick.com>
* @copyright 2009 Garick
* @license http://opensource.org/licenses/bsd-license.php BSD License
* @version SVN: $Id: LinkMgr.php 47 2009-11-17 20:07:45Z clay $
* @link $URL: https://clay.hinson%40garick.com@venture2.projectlocker.com/Garick/sgl_module_invoice/svn/tags/stable/classes/LinkMgr.php $
*/

/*ini_set('error_reporting', E_ALL);
ini_set('display_errors',1);*/

/* CMS Libraries */
require_once SGL_MOD_DIR . '/cms/classes/LinkerMgr.php';
require_once SGL_MOD_DIR . '/cms/classes/CmsDAO.php';

/* local libraries */
require_once 'InvoiceDAO.php';
require_once 'ImportBatch.php';

/**
 * class LinkMgr
 * User interface to for linking invoices & invoice documents
 *
 * @package Invoice
 * @author  Clay Hinson <clay.hinson@garick.com>
 * @version $Rev: 47 $
 */
class LinkMgr extends SGL_Manager
{
    /**
     * standard constructor for SGL_Manager objects
     *
     */
    function __construct()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        parent::SGL_Manager();

        $this->pageTitle    = 'Invoice Link Manager';
        $this->template     = 'linkUnlinkedList.html';

        // set up the action mappings
        $this->_aActionsMapping =  array(
            'list'      => array('list'),
            'update'    => array('linkDocuments'),
            'unlink'    => array('unlinkDocument'),
            'delete'    => array('delete'),

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
        $input->action      = ($req->get('action')) ? $req->get('action') : 'list';
        $input->aDelete     = $req->get('frmDelete');
        $input->submitted   = $req->get('submitted');
        $input->documents = $req->get('frmDocuments');
        
        // module-specific variables coming from the form
        $input->documentId = $req->get('frmDocumentId');
        $input->invoiceId = $req->get('frmInvoiceId');
        $input->vendorId = $req->get('frmVendorId');
        $input->pageId = ($req->get('pageID')) ? $req->get('pageID') : 1;

        /**
         * validation
         * @todo use validator
         */
        if($input->submitted) {
            $empty = 0;
            foreach($input->documents AS $d => $invoice) {
                if(empty($invoice['invoiceId']) && empty($invoice['vendorId'])) {
                    // empty order, count it
                    $empty++;
                } elseif(!ereg(InvoiceXMLParsingStrategy::$invoiceNumRegEx, $invoice['invoiceId'] . '+')) {
                    $aErrors['invoiceFormat'] = 'You must enter a 10 digit alphanumeric invoice number.';
                } elseif(!ereg(InvoiceXMLParsingStrategy::$vendorNumRegEx, $invoice['vendorId'] . '.')) {
                    $aErrors['vendorFormat'] = 'You must enter the vendor number as AAA99.';
                }

            }
            if($empty == count($input->documents)) {
                $aErrors['empty'] = 'You must enter at least 1 invoice to link.';
            }
        }
        //  if errors have occurred, output a notice
        if (isset($aErrors) && count($aErrors)) {
            SGL::raiseMsg('An error has occurred that must be corrected before continuing.');
            $input->error = $aErrors;
            $this->validated = false;

        }
    }

    /**
     * sets output variables & handles any actions needed by all displayed pages
     *
     * @param SGL_Output $output
     */
    function display(&$output)
    {
        if ($this->conf['LinkMgr']['showUntranslated'] == false) {
            $c = &SGL_Config::singleton();
            $c->set('debug', array('showUntranslated' => false));
        }

    }

    /**
     * default action when module is loaded
     * displays a list of unlinked documents, with a linking form for each
     *
     * @param SGL_Registry $input
     * @param SGL_Output $output
     * @todo set up separate pager function to reduce duplication
     */
    function _cmd_list(&$input, &$output)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        // set template defaults
        $output->masterTemplate = "masterNoCols.html";
        $output->template  = 'linkUnlinkedList.html';
        $output->pageTitle = 'Unlinked Documents';

        // get a total count
        $totalDocs = $this->da->getDocCount();
        $output->totalDocs = $totalDocs;


        // get a paged list
        $aPrefs = SGL_Session::get("aPrefs");
        $limit = $aPrefs['resPerPage'];
        if ($limit < $totalDocs) {
            /* pager*/
            $themePrefix = SGL_BASE_URL . '/themes/' . $_SESSION['aPrefs']['theme'] . '/images/';

            $pagerOptions = array(
            'mode'     => 'Sliding',
            'delta'    => 3,
            'httpMethod' => 'GET',
            'importQuery' => false,
            'firstPagePre' => '<img src="' . $themePrefix . 'move_left.gif" /><img src="' . $themePrefix . 'move_left.gif" /> ',
            'firstPagePost' =>'',
            'prevImg' => '<img src="' . $themePrefix . 'move_left.gif" />',
            'altPrev' => 'previous page',
            'nextImg' => '<img src="' . $themePrefix . 'move_right.gif" />',
            'altNext' => 'next page',
            'lastPagePre' => '',
            'lastPagePost' => ' <img src="' . $themePrefix . 'move_right.gif" /><img src="' . $themePrefix . 'move_right.gif" />',
            );
            $pagerOptions['path'] = SGL_Output::makeUrl('','link','invoice');
            $pagerOptions['perPage'] = $limit;
            $pagerOptions['totalItems'] = $totalDocs;
            $pagerOptions['currentPage'] = $input->pageId;
            $pager = & Pager::factory($pagerOptions);

            $output->pager = $pager;
            $offset = ($pager->getCurrentPageID()>1) ? ($pager->getCurrentPageID()-1)*$limit : 0;
         } else {
            $offset = 0;
         }
        //($input->page!=1) ? (($input->page * $aPrefs['resPerPage']) - $aPrefs['resPerPage'])+1 : 0;

        // perform the search
        $aParams = array('typeName' => 'Invoice Document',
                         'status' => IDOC_STATUS_UNLINKED,
                         'sortBy' => 'date_created',
                         'sortOrder' => 'DESC',
                         'limit' => array('offset' => $offset, 'count' => $limit),
                         );
        $aRet = $this->da->filteredSearch($aParams);


        if(isset($pager)) {

            $output->startRange = $this->da->_getStartRange($pager, $offset);
            $output->endRange = $this->da->_getEndRange($pager, $offset, $limit, count($aRet));

        }


        // send the result to the template
        $output->result = $aRet;
        if(empty($aRet))
                SGL::raiseMsg('Currently, no unmatched documents are available.');
    }

    /**
     * performs the document linking, redirects to list
     *
     * @param SGL_Registry $input
     * @param SGL_Output $output
     */
    function _cmd_linkDocuments(&$input, &$output) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        // set up some totals
        $links = array();
        $errors = array();

        // loop through the form
        foreach($input->documents AS $d => $invoice) {

            $invoiceId = $invoice['invoiceId'];
            $vendorId = $invoice['vendorId'];
            $invoiceName = $vendorId.'-'.$invoiceId;

            // make sure the order exists in the system
            $invoiceContent = $this->da->searchByName($invoiceName);

            // if not, add it first
            if(is_null($invoiceContent)) {
               $invoiceContentId = $this->_createGarickInvoice($d, $invoiceId, $vendorId);
            } else {
                $invoiceContentId = $invoiceContent;
            }
            // check for errors adding or finding the invoice content
            if(!PEAR::isError($invoiceContentId) && !is_null($invoiceContentId) && $invoiceContentId !== false) {
                // then add the links
                //SGL::logMessage('Linking DocId '.$d.' and InvId '.var_export($invoiceContentId,true) ,PEAR_LOG_DEBUG);
                $ok = $this->da->addDocumentLinks($d, $invoiceName);

                // if no errors updating the links, save the status as linked and change the document type
                if (!PEAR::isError($ok)) {
                    $links[] = $invoiceName;
                    $this->da->markDocumentLinked($d);

                    // update the media description to use the filename
                    $oDoc = SGL_Content::getById($d);
                    $this->da->updateMediaDescription($oDoc->pdf, $invoiceName);
                    unset($oDoc);
                } else {
                    $this->da->markContentErrored($d);
                    $this->da->markContentErrored($invoiceContentId);
                    $errors[] = $invoiceName;
                }
            }
            // in case of error
            if($invoiceContentId === false) {
                SGL::raiseError('Error - invoice not created for '.$invoiceId.' DocId '.$d, SGL_ERROR_NOAFFECTEDROWS);
                $errors[] = $invoiceName;
            }
        }


        // post-processing
        // display messages depending on status
        if(!empty($links)) {
            $linklist = join(',', $links);
            SGL::raiseMsg($linklist . ' successfully linked', false, SGL_MESSAGE_INFO);
        }
        if(!empty($errors)) {
            $errorlist = join(',', $errors);
            SGL::raiseError($errorlist.' were unable to link.', SGL_ERROR_NOAFFECTEDROWS);
        }

        // redirect back to the page this request came from
        $options = array(
            'moduleName' => 'invoice',
            'managerName' => 'link',
        );
        if($input->pageId != 1) {
            $options['action'] = 'list';
	    $options['pageID'] = $input->pageId;
        }
        SGL_HTTP::redirect($options);
    }

    /**
     * deletes un-needed documents from the database
     *
     * @param SGL_Registry $input
     * @param SGL_Output $output
     */
    function _cmd_delete(&$input, &$output) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        // delete document content
        $oDoc  = SGL_Content::getById($input->documentId);
        $media = $oDoc->pdf;
        $oDoc->delete();

        // delete media
        $ok = $this->da->deleteMedia($media);

        if(!PEAR::isError($ok)) {
            // raise message
            SGL::raiseMsg('Document deleted!');
        } else {
            SGL::raiseError('There was a problem deleting this document.');
        }
        // redirect back to page delete was initiated on
        $options = array(
            'moduleName' => 'invoice',
            'managerName' => 'link',
            'action'      => 'list',
	    'pageID'	  => $input->pageId,
        );
        SGL_HTTP::redirect($options);
    }

    /**
     * creates the invoice content using the ImportBatch class
     *
     * @param int $documentId contentId of the document
     * @param string $invoiceNum invoice number to be linked
     * @param string $invoiceVendorNum vendorId the invoice belongs to
     * @return int|boolean invoiceId if successful, false if failed
     * @todo generalize to reduce duplication between this and ImportMgr
     */
    function _createGarickInvoice($documentId, $invoiceNum, $invoiceVendorNum) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $oDocument = SGL_Content::getById($documentId);

        // set up a batch for handling emails, etc

        // get a batch singleton
        $batch = & ImportBatch::singleton();
        $batch->batchFileName = $oDocument->batchFileName;
        $batch->batchType = 'link';

        // get the info about the invoice from SBT
        $SBTData = $this->da->getSBTInvoice($invoiceNum, $invoiceVendorNum);

        if(!$SBTData) {
            // there was a problem.
            SGL::raiseMsg('Error: Invoice # '.$invoiceNum.' could not be found for Vendor # '.$invoiceVendorNum);
            SGL::raiseError('Invoice # '.$invoiceNum.' could not be found for Vendor # '.$invoiceVendorNum, SGL_ERROR_NODATA, PEAR_ERROR_RETURN);
            return false;
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
                                   'batchPageNumber' => '')); // TODO: add page number - page number as property of document content?
        // look up the user
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
            $invoiceUserName = $userInfo['invoiceUserName'];
            $invoiceUserEmail = $userInfo['invoiceUserEmail'];
        }

        $invoiceName =  $invoiceVendorNum.'-'.$invoiceNum;
        $invoiceId = $this->da->createInvoiceContent($invoiceName, $invoiceNum, $invoiceVendorNum,
                                                        $invoiceVendorName, $invoiceUserId, $invoiceUserName,
                                                        $invoiceUserEmail, $invoiceAmount,$batch->get('batchFileName'));

        if(!PEAR::isError($invoiceId)) {
            $batch->finalize();
        } else {
            SGL::raiseMsg('There was an error creating the invoice document.');
        }
     return $invoiceId;
     }

}
?>
