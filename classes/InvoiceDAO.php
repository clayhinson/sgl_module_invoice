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
* InvoiceDAO.php
*
* @category Modules
* @package Invoice
* @author Clay Hinson <clay.hinson@garick.com>
* @copyright 2009 Garick
* @license http://opensource.org/licenses/bsd-license.php BSD License
* @version SVN: $Id: InvoiceDAO.php 59 2009-11-30 21:08:05Z clay.hinson@garick.com $
* @link $URL: https://clay.hinson%40garick.com@venture2.projectlocker.com/Garick/sgl_module_invoice/svn/tags/stable/classes/InvoiceDAO.php $
*/

/**
 * PEAR libs
 */
require_once 'DB/DataObject.php';
require_once 'Pager/Pager.php';
require_once 'System.php';

/**
 * other includes
 */
require_once 'InvoiceXMLParsingStrategy.php';
require_once SGL_LIB_DIR . '/other/GarickDataAccessStrategy.php';
require_once SGL_LIB_DIR . '/other/XMLParsingStrategy.php';

/**
 * CMS & Media classes
 */
require_once SGL_MOD_DIR . '/cms/classes/Content.php';
require_once SGL_MOD_DIR . '/cms/classes/Finder.php';
require_once SGL_MOD_DIR . '/cms/classes/CmsDAO.php';
require_once SGL_MOD_DIR . '/media/classes/MediaDAO.php';
require_once SGL_CORE_DIR . '/Download.php';

/**
 * redefine CMS constants
 */
define('INVOICE_STATUS_IN_PROGRESS', SGL_CMS_STATUS_BEING_EDITED);
define('INVOICE_STATUS_APPROVED', SGL_CMS_STATUS_PUBLISHED);
define('IDOC_STATUS_UNLINKED', SGL_CMS_STATUS_FOR_APPROVAL);
define('IDOC_STATUS_LINKED', SGL_CMS_STATUS_APPROVED);
define('INVOICE_STATUS_REVIEW', SGL_CMS_STATUS_ARCHIVED);

/**
 * class InvoiceDAO
 * Data access object for the Invoice module.
 * Provides database connectivity, and various data processing functions
 *
 * @package Invoice
 * @author  Clay Hinson <clay.hinson@garick.com>
 * @version $Rev: 59 $
 */
class InvoiceDAO extends SGL_Manager {

    /**
     * Constructor - set default resources.
     *
     */
    function __construct()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        parent::SGL_Manager();
        // create CMS and garick data access objects
        $this->cmsDA = CmsDAO::singleton();
        $this->garickDA = GarickDataAccessStrategy::singleton();
    }

    /**
     * Returns a singleton InvoiceDAO instance.
     *
     * example usage:
     * $da = & InvoiceDAO::singleton();
     * warning: in order to work correctly, the DA
     * singleton must be instantiated statically and
     * by reference
     *
     * @access  public
     * @static
     * @return  InvoiceDAO reference to InvoiceDAO object
     */
    function &singleton()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        static $instance;

        if (!isset($instance)) {
            $c = __CLASS__;
            $instance = new $c();
        }

        return $instance;
    }
    /**
    * search content by name
    * @param int $contentName content name
    * @return int content ID
    */
    function searchByName($contentName) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $query = "SELECT content_id
                  FROM {$this->conf['table']['content']}
                  WHERE name = '{$contentName}'"
                  ;
        return $this->dbh->getOne($query);
    }
    /**
     * a wrapper for SGL_Cms Finder functions, to allow for easy paging & multiple options
     * @param array $aParams - array containing contentType,sortBy,sortOrder,limit,offset,filter
     * @return array array of content objects
     */
    function filteredSearch($aParams) {
        // aParams array contains:
        // contentType
        // sortBy
        // sortOrder
        // limit
        // offset
        // filter
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        // first, build the initial finder
       $aRet = SGL_Finder::factory('content');

       // then, add conditional attributes
       if(isset($aParams['typeName'])) { $aRet->addFilter('typeName', $aParams['typeName']); }
       if(isset($aParams['typeId'])) { $aRet->addFilter('typeId', $aParams['typeId']); }
       if(isset($aParams['status'])) { $aRet->addFilter('status', $aParams['status']); }
       if(isset($aParams['sortBy'])) { $aRet->addFilter('sortBy', $aParams['sortBy']); }
       if(isset($aParams['sortOrder'])) { $aRet->addFilter('sortOrder', $aParams['sortOrder']); }
       if(isset($aParams['filter'])) { $aRet->addFilter('attribute', $aParams['filter']); }
       if(isset($aParams['limit'])) { $aRet->addFilter('limit', $aParams['limit']); }

       return $aRet->retrieve();

    }
    /**
     * pager function - gets start range of pager
     * @param object $pager
     * @param int $offset
     * @return int $offset plus 1 or 0
     */
    function _getStartRange($pager, $offset) {
        return $offset+ ( ($pager->isFirstPage()) ? 1 : 0 );
    }
    /**
     *
     *
     */
    function _getEndRange($pager, $offset, $limit, $rangeSize) {
        if($pager->isLastPage()) {
            $end = $offset+$rangeSize;
        } else {
           // var_export($offset+$limit);
            $end = $offset+$limit - (($pager->isFirstPage()) ? 0 : 1);
        }
        return $end;
    }
    /**
     *
     *
     */
    function getNameAndIDArrayByTypeName($contentTypeName) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $content_type_id = $this->cmsDA->getContentTypeIdByName($contentTypeName);
        $query = 'SELECT name, content_id
                  FROM '.$this->conf['table']['content'].'
                  WHERE content_type_id = '.$content_type_id
                  ;
        return $this->dbh->getAssoc($query);
    }
    /**
     *
     *
     *
     */
    function getInvoiceCount() {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $content_type_id = $this->cmsDA->getContentTypeIdByName('Garick Invoice');
        $query = 'SELECT count(*)
                  FROM '.$this->conf['table']['content'].'
                  WHERE content_type_id = '.$content_type_id.' AND
                  status = '.INVOICE_STATUS_IN_PROGRESS
                  ;
        return $this->dbh->getOne($query);
    }
    /**
     *
     *
     */
    function getDocCount() {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $content_type_id = $this->cmsDA->getContentTypeIdByName('Invoice Document');
        $query = 'SELECT count(*)
                  FROM '.$this->conf['table']['content'].'
                  WHERE content_type_id = '.$content_type_id.' AND
                  status = '.IDOC_STATUS_UNLINKED
                  ;
        return $this->dbh->getOne($query);
    }
    function getDocTypes() {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $list = DB_DataObject::factory($this->conf['table']['attribute_list']);
        $list->get(2);

        $data = $this->_getDataFromParamString($list->params);
        return $data;
    }


    function updateDocumentType($documentId, $attributeId, $documentType) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $att = SGL_Attribute::getById($attributeId);
        $att->value = $documentType;
        $oAttrib = $att->save();
        $update = $this->cmsDA->updateAttribData($oAttrib, $documentId);
    }

    /* CONTENT LINKING METHODS */
    function markDocumentLinked($documentId) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $oDocument = SGL_Content::getById($documentId);
        $oDocument->status = IDOC_STATUS_LINKED;
        $oDocument->save();
    }
    function markContentErrored($contentId) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $oContent = SGL_Content::getById($contentId);
        $oContent->status = INVOICE_STATUS_REVIEW;
        $oContent->save();
    }
     /**
     * Function addDocumentLinks
     * Appends content links to the existing links for a content item
     * example usage:
     * $ok = $this->da->addDocumentLinks($documentId, $contentName);
     * @access  public
     * @return  true, or PEAR_ERROR object
     */
    function addDocumentLinks($sDocumentId, $sInvoice) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        // get the details of the content being updated
        $content = $this->cmsDA->getContentByName($sInvoice);

        $aOldAssocsAll = $this->cmsDA->getContentAssocsByContentId($content->id);
        // look for an existing link to the same document
        if(array_search($sDocumentId, $aOldAssocsAll) !== FALSE) {
            // just return
            return 1;
        } else {
            // if not found, rebuild the links list for the content
            $document = array(0 => $sDocumentId);
            $aNewAssocs = array_merge($aOldAssocsAll, $document);
            $ok = $this->cmsDA->addContentAssocsByContentId($content->id, $aNewAssocs);
            return $ok;
        }
    }
    /*wrapper for the above*/
    function addDocumentLinksById($sDocumentId, $sInvoice) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        // get the details of the content being updated
        $content = $this->cmsDA->getContentById($sInvoice);
        $ok = $this->addDocumentLinks($sDocumentId, $content->name);
        return $ok;

    }
    function removeDocumentLink($documentId, $invoiceName) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
                // get the details of the content being updated
         // get the details of the content being updated
        $content = $this->cmsDA->getContentByName($invoiceName);
        if(!PEAR::isError($content)) {
        $query = "UPDATE `content` SET status = ".IDOC_STATUS_UNLINKED." WHERE content_id = $documentId";
            $this->dbh->query($query);

        $query = "DELETE FROM `content-content`
            WHERE content_id_pk = {$content->id}
            AND content_id_fk = $documentId";
            $this->dbh->query($query);
        }

        return $content;
    }
    function _getDataFromParamString($paramString) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $fields = unserialize($paramString);
        if (empty($fields) && !is_array($fields)) {
            $fields = array();
        }
        $data = array();
        if (isset($fields['data-inline'])) {
            $data = $fields['data-inline'];
        } elseif (isset($fields['data'])) {
            $data = $fields['data'];
        }
        return $data;
    }
/* FILE SYSTEM METHODS */
    function getDirList($dir) {
        $dirList = array();
        $stack[] = $dir;
        while($stack) {
            $currentDir = array_pop($stack);
            if ($dh = opendir($currentDir)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_dir($currentDir . $file)) {
                        $dirList[] = $currentDir . $file;
                        print "Found Dir: $file\r\n";
                    }
               }
            }
        }
    }
     function getFileList($dir)
    {
        $fileList = array();
        $stack[] = $dir;
        while ($stack) {
            $currentDir = array_pop($stack);
            if ($dh = opendir($currentDir)) {
                while (($file = readdir($dh)) !== false) {
                    if (eregi('.PDF', $file)) {
                        $currentFile = $file;
                        $fileList[] = $currentFile;
                     //   print "Found TXT file: $file\r\n";
                    }
               }
           } else {
            print "nope";
            }
       }
       sort($fileList);
       return $fileList;
    }
    function ensureUploadDirWritable($targetDir)
    {
        //  check if uploads dir exists, create if not
        if (!is_writable($targetDir)) {
            require_once 'System.php';
            $success = System::mkDir(array($targetDir));
            if (!$success) {
                SGL::raiseError('The upload directory,'.$targetDir.', does not appear to be writable, please give the
                webserver permissions to write to it', SGL_ERROR_FILEUNWRITABLE);
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }
    }
    function getPDFText($filePath, $fileName)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $outpath = preg_replace("/\.pdf$/", "", $fileName).".txt";
        copy($filePath.$fileName, '/tmp/'.$fileName);
        $escapedFileName = str_replace(' ', '\ ', escapeshellcmd($fileName));
        system("pdftotext /tmp/$escapedFileName", $ret);
        if ($ret == 0)
        {
            $value = file_get_contents('/tmp/'.$outpath);
            unlink('/tmp/'.$outpath);
            unlink('/tmp/'.$fileName);
        }
        if ($ret == 127) {
            SGL::raiseError("Could not find pdftotext tool.");
            return false;
        }
        if ($ret == 1) {
            SGL::raiseError("Could not find pdf file.");
            return false;
        }

        return $value;

    }

    /* CONTENT CREATION METHODS */

    // default upload directory for all media files
    function createMedia($filePath, $baseFileName, $description) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        if (!$this->ensureUploadDirWritable(SGL_UPLOAD_DIR)) {
            return false;
        }

        // generating unique name for each uploaded file
        //$baseFileName = substr($fileName, 0, strlen($fileName)-4);
        $pdfName = $baseFileName . '.pdf';
        $uniqueName = md5($pdfName . SGL_Date::getTime());

        // move the file to its unique name location
        $targetLocation = SGL_UPLOAD_DIR . '/' . $uniqueName;
        copy($filePath.$pdfName, $targetLocation.'.pdf');
        //unlink($filePath.$pdfName);

        $media = DB_DataObject::factory($this->conf['table']['media']);
        //$media->setFrom($input->media);
        $media->media_id = $this->dbh->nextId($this->conf['table']['media']);
        $media->date_created  = SGL_Date::getTime();
        $media->added_by = 0;
        $media->name = SGL_String::censor($pdfName);
        $media->file_name = $uniqueName.'.pdf';
        $media->description = SGL_String::censor($description);
        $media->file_size = (filesize($targetLocation.'.pdf')/1024);
        $media->mime_type = 'application/pdf';
        $media->file_type_id = 8;

        if ($media->insert()) {
            return $media->media_id;
        }

    }

    function updateMediaDescription($mediaId, $description) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $media = DB_DataObject::factory($this->conf['table']['media']);
        $media->get($mediaId);
        $media->description = $description;
        return $media->update();
    }
     /**
     * Creates a Content item and returns the contentId
     *
     * example usage:
     * $docId = $this->da->createInvoiceDocContent($filePath, $fileName, $baseFileName, $batchFileName, $invoiceDescription, $invoiceRawXML);
     * This also creates a media object for use in the file attribute.
     *
     * @access  public
     * @static
     * @return  contentId of newly created document
     */
    function createInvoiceDocContent($filePath, $fileName, $baseFileName, $batchFileName, $invoiceDescription, $invoiceRawXML) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        // create the media
        $mediaId = $this->createMedia($filePath, $baseFileName, $invoiceDescription);

        /* create the Invoice Document content */
        $oInvoiceDoc = SGL_Content::getByType('Invoice Document');

        /* assign the properties of the invoice content */

        // name the content whatever was sent
        $oInvoiceDoc->name = $fileName;
        // always created by admin
        $oInvoiceDoc->createdByName = 'admin';
        $oInvoiceDoc->createdById = 1;
        $oInvoiceDoc->updatedById = 1;
        // insert the document contents
        $oInvoiceDoc->pdf = $mediaId;
        $cleanText = str_replace("'", "", $invoiceRawXML);
        $oInvoiceDoc->ocrText = addslashes($cleanText);
        $oInvoiceDoc->batchname = $batchFileName;
        // marks document as unlinked by default
        $oInvoiceDoc->status = IDOC_STATUS_UNLINKED;

        // save the content item
        $docOK = $oInvoiceDoc->save();

        // log a debug message
        SGL::logMessage('Creating InvoiceDoc: '.$oInvoiceDoc->name, PEAR_LOG_DEBUG);

        // if the creation was successful, get the id, otherwise return false and log an error
        if(!PEAR::isError($docOK)) {
            $docId = $oInvoiceDoc->id;
        } else {
            SGL::logMessage($docOK->getMessage(), PEAR_LOG_DEBUG);
            $docId = $docOK;
        }
        return $docId;
    }
    /**
     * Creates an Invoice content item and returns the contentId
     *
     * example usage:
     * $invoiceId = $this->da->createInvoiceContent(args);
     *
     *
     * @access  public
     * @static
     * @return  contentId of newly created document
     */
    function createInvoiceContent($invoiceContentName, $invoiceNum, $invoiceVendorNum,
                                  $invoiceVendorName, $invoiceUserId, $invoiceUserName,
                                  $invoiceUserEmail, $invoiceAmount,$batchName) {
        // the invoice content does not already exist
        SGL::logMessage('Adding Invoice Content '.$invoiceContentName, PEAR_LOG_DEBUG);

        // create the Garick Order Content if it doesn't exist
        $oGarickInvoice = SGL_Content::getByType('Garick Invoice');

        // create the other properties of the order content
        $oGarickInvoice->name = $invoiceContentName;

        $oGarickInvoice->createdByName = 'admin';
        $oGarickInvoice->createdById = 1;
        $oGarickInvoice->updatedById = 1;
        $oGarickInvoice->status = INVOICE_STATUS_APPROVED;
        $oGarickInvoice->invoiceNum = $invoiceNum;
        $oGarickInvoice->vendorNum = $invoiceVendorNum;
        $oGarickInvoice->vendorName = $invoiceVendorName;
        $oGarickInvoice->enteredBy = $invoiceUserName;
        $oGarickInvoice->enteredByUserId = $invoiceUserId;
        $oGarickInvoice->enteredByEmail = $invoiceUserEmail;
        $oGarickInvoice->amount = $invoiceAmount;
        $oGarickInvoice->batchname = $batchName;

        // save to the db
        $invoiceOK = $oGarickInvoice->save();

        // set the id for use in linking
        $invoiceId = $oGarickInvoice->id;

        // if the creation was successful, get the id, otherwise return false and log an error
        if(!PEAR::isError($invoiceOK)) {
            $invoiceId = $oGarickInvoice->id;
        } else {
            SGL::logMessage($invoiceOK->getMessage(), PEAR_LOG_DEBUG);
            $invoiceId = $invoiceOK;
        }
        return $invoiceId;
    }
    /* DELETE METHODS */
    function deleteProcessedPDF($filePath, $fileName) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        System::rm($filePath.$fileName);
    }
    function deleteProcessedXML($filePath, $fileName) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        // delete the version minus the _1, _2, etc
        $shortFileName = substr($fileName, 0, (strlen($fileName)-2));
        System::rm($filePath.$shortFileName.'.xml');
        System::rm($filePath.$fileName.'.xml');
    }
    function deleteMedia($mediaId) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $ok = MediaDAO::deleteMediaById($mediaId);
        return $ok;
    }
    function getMediaById($mediaId) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $media = DB_DataObject::factory($this->conf['table']['media']);
        $media->get($mediaId);

        return $media;
    }



    /* Garick Data PROCESSING FUNCTIONS */
    // a wrapper around GarickDataAccessStrategy functions
    function garickUserLookup($userId) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        return $this->garickDA->getLDAPUserByGarickId($userId);

    }
    function getSBTInvoice($invoiceNum, $vendorNum) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $invoiceData = $this->garickDA->getInvoiceDataFromSBT($invoiceNum, $vendorNum);
        return $invoiceData[0];
    }

    function parseScannedInvoiceXML($filePath, $baseFileName) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        // check to see if the file is named invoice_yyyyMMDD-HH_AAA99-ABC39202.pdf.
        //skip the XML parsing but still grab the contents
        if(ereg("^invoice_[0-9]{8}-[0-9]{2}_([A-Z]{3}[0-9]{2})-([A-Z0-9]{1,10})", $baseFileName, $match)) {
            $vendorNum = $match[1];
            $invoiceNum = $match[2];
            $rawXML = $this->getPDFText($filePath, $baseFileName.'.pdf');
            return array($invoiceNum, $vendorNum, $rawXML);
        } elseif (ereg("^invoice_[0-9]{8}-[0-9]{2}_([A-Z0-9]{1,10})-([A-Z]{3}[0-9]{2})", $baseFileName, $match)) {
            // in case they switched the vendornumber/invoice number
            $invoiceNum = $match[1];
            $vendorNum = $match[2];
            $rawXML = $this->getPDFText($filePath, $baseFileName.'.pdf');
            return array($invoiceNum, $vendorNum, $rawXML);
        } else {
            $parser = new InvoiceXMLParsingStrategy($filePath, $baseFileName);
            return $parser->parse();
        }
    }
}
?>
