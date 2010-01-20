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
* ImportBatch.php
*
* @category Modules
* @package Invoice
* @author Clay Hinson <clay.hinson@garick.com>
* @copyright 2009 Garick
* @license http://opensource.org/licenses/bsd-license.php BSD License
* @version SVN: $Id: ImportBatch.php 20 2009-11-13 21:37:06Z clay $
* @link $URL: https://clay.hinson%40garick.com@venture2.projectlocker.com/Garick/sgl_module_invoice/svn/tags/stable/classes/ImportBatch.php $
*/

// include SGL_Emailer2 for sending queued emails
require_once SGL_CORE_DIR . '/Emailer2.php';

/**
 * class ImportBatch
 * creates a batch used for importing invoices
 * emails users to let them know their invoices have been processed
 * keeps a cache of users to reduce lookups
 * accessed via a singleton instance
 *
 *
 * batch structure:
 *       $users = array('CLAYH' => array('invoiceUserName' => 'Clay Hinson',
 *                           'invoiceUserEmail' => 'clay.hinson@garick.com'),
 *       $invoices = array(0 => array('invoiceNum' => '31345',
 *                                    'vendorNum' => 'CRI09',
 *                                    'vendorName' => 'CIRES ELECTRIC',
 *                                    'invoiceAmount' => '10.00',
 *                                    'invoiceComment' => '',
 *                                    'invoiceUserId' => 'CLAYH'))
 *
 *
 * @package Invoice
 * @author  Clay Hinson <clay.hinson@garick.com>
 * @version $Rev: 20 $
 */
class ImportBatch {

    /**
     * @var string original filename of scanned documet
     */
    public $batchFileName;
    /**
     * @var string file name without _1, _2 for page numbers
     */
    public $baseFileName;
    /**
     * @var string batch type (default is mass import, other type is 'link' for linking single invoices)
     */
    public $batchType = 'default';

    /**
     * @var array stores users in the current batch
     */
    public $aUsers = array();
    /**
     * @var array stores users between batches
     */
    public $aUserCache = array();

    /**
     * @var array stores invoices in the current batch
     */
    public $aInvoices = array();
    /**
     * @var array stores errors for the current batch
     */
    public $aErrors = array();

    /**
     * @var array stores delivery options for email object
     * @access protected
     */
    private $aDeliveryOpts = array();
    /**
     * @var array stores template options for email object
     * @access protected
     */
    private $aTplOpts = array();
    /**
     * @var array stores queue options for email object
     * @access protected
     */
    private $aQueueOpts = array();

    /**
     * @var object singleton instance of the batch
     * @static
     * @access protected
     */
    private static $instance;

    /**
     * the constructor sets up the batch based on the config settings
     * private; only called when the singleton is instantiated
     *
     * @param int $simulate turns on simulation mode
     * @access protected
     * @todo move setting code into separate function
     */
    private function __construct($simulate)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        // set up a config instance
        $c = & SGL_Config::singleton();


        // set the delivery options based on simulate mode
        // (all emails redirected to admin in simulate)
        if($simulate) {
            $this->aDeliveryOpts['adminToRealName'] = $c->get('InvoiceMgr.simulateToName');
            $this->aDeliveryOpts['adminToEmail'] = $c->get('InvoiceMgr.simulateToEmail');

            $this->aDeliveryOpts['simulateToRealName'] = $c->get('InvoiceMgr.simulateToName');
            $this->aDeliveryOpts['simulateToEmail'] = $c->get('InvoiceMgr.simulateToEmail');
        } else {
            $this->aDeliveryOpts['adminToRealName'] = $c->get('InvoiceMgr.adminRealName');
            $this->aDeliveryOpts['adminToEmail'] = $c->get('InvoiceMgr.adminEmail');
        }

        // set up email defaults
        $this->aDeliveryOpts['subject'] = $c->get('InvoiceMgr.noticeSubject');
        $this->aDeliveryOpts['fromEmail'] = $c->get('InvoiceMgr.fromEmail');
        $this->aDeliveryOpts['fromRealName']  = $c->get('InvoiceMgr.fromRealName');
        $this->aDeliveryOpts['errorSubject'] = $c->get('InvoiceMgr.errorSubject');
        $this->aDeliveryOpts['replyTo'] = $c->get('InvoiceMgr.fromReplyTo');
        $this->aDeliveryOpts['bcc'] = $c->get('InvoiceMgr.bcc');
        $this->aDeliveryOpts['emailSubjectLink'] = $c->get('InvoiceMgr.emailSubjectLink');


        // template options
        $this->aTplOpts['htmlTemplate'] =  $c->get('InvoiceMgr.emailTemplate');
        $this->aTplOpts['emailTemplateLink'] = $c->get('InvoiceMgr.emailTemplateLink');
        $this->aTplOpts['moduleName'] =  'invoice';

        // html only
        $this->aTplOpts['mode'] = 2;

        // html file is set at time of send

        // set queue options
        // the delay is 3 sec between emails
        $this->aQueueOpts['sendDelay'] = 3;
        $this->aQueueOpts['groupId'] = 1;
        $this->aQueueOpts['userId'] = 1;
        $this->aQueueOpts['batchId'] = date('ymdhi');

        // make sure emailQueue is on
        $set = $c->set('emailQueue.enabled', true);
    }

     /**
     * Returns a singleton ImportBatch instance.
     *
     * example usage:
     * $batch = & ImportBatch::singleton();
     * warning: in order to work correctly, the batch
     * singleton must be instantiated statically and
     * by reference
     *
     * @access  public
     * @static
     * @return object reference to ImportBatch object
     */
    public static function singleton($simulate)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c($simulate);
        }

        return self::$instance;
    }

    /**
     * sets a passed property of the ImportBatch object.
     * if passed an array name as argument 3, will set the
     * corresponding key in that array rather than a direct property.
     *
     * example usage:
     * $this->set('batchFileName', 'abc123');
     *
     * @access  private
     * @deprecated not used
     */
/*    public function set($sPropertyName, $sPropertyValue, $sArrayName=null) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        // set the option directly
        if(is_null($sArrayName)) {
            $this->$sPropertyName = $sPropertyValue;
        } else {
            if(is_null($sPropertyName)) {
                // set a generic key
                array_push($this->$sArrayName, $sPropertyValue);
            } else {
                // set the option in an array
                $this->$sArrayName[$sPropertyName] = $sPropertyValue;
            }
        }
    }
*/

    /**
     * Finalizes the batch by sending email notices and calling reset()
     *
     * example usage:
     * $batch->get('batchFileName');
     *
     * @return mixed returns either the requested value or false
     * @access  public
     */
    public function get($sPropertyName=null, $sArrayName=null) {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        // look for no array specified, but option specified
        if(is_null($sArrayName) && !is_null($sPropertyName)) {
            // not found
            if(!isset($this->$sPropertyName)) {
                return false;
            }
            return $this->$sPropertyName;
        } else {
            // not found
            if(!isset($this->$sArrayName[$sPropertyName])) {
                return false;
            }
            // return a single value
            return $this->$sArrayName[$sPropertyName];
        }
        // by default nothing is returned
        return false;
    }

    /**
     * sends an email notification to all users of the current batch
     *
     * example usage:
     * $this->sendCompletionNotice();
     *
     * @access  protected
     */
    private function sendCompletionNotice()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        // make a copy so changes are local to this notice
        $aTplOpts = $this->aTplOpts;
        $aDeliveryOpts = $this->aDeliveryOpts;
        $aQueueOpts = $this->aQueueOpts;

        // template vars
        if($this->batchType == 'link') {
            $aDeliveryOpts['subject'] = $this->aDeliveryOpts['emailSubjectLink'];
            $aTplOpts['htmlTemplate'] =  $this->aTplOpts['emailTemplateLink'];
        }

        $aTplOpts['invoiceList'] = $this->aInvoices;
        $aTplOpts['errorList'] = $this->aInvoices;
        $aTplOpts['totalErrors'] = $this->getTotalErrors();
        $aTplOpts['totalInvoices'] = $this->getTotalInvoices();

        // loop through the users and email each
        $users = $this->aUsers;
        foreach($users AS $userId => $userData) {

            $userName = $userData['invoiceUserName'];
            $email = $userData['invoiceUserEmail'];

            // check to see if the To properties have been overridden
            $aDeliveryOpts['toEmail'] = isset($aDeliveryOpts['simulateToEmail']) ? $aDeliveryOpts['simulateToEmail'] : $email;
            $aDeliveryOpts['toRealName'] = isset($aDeliveryOpts['simulateToRealName']) ? $aDeliveryOpts['simulateToRealName'] : $userName;


            $ok = SGL_Emailer2::send($aDeliveryOpts, $aTplOpts, $aQueueOpts);
            SGL::logMessage(var_export($aDeliveryOpts,true).var_export($aTplOpts,true).var_export($aQueueOpts,true),PEAR_LOG_DEBUG);
            if(PEAR::isError($ok)) {
                SGL::raiseError('Email Confirmation Notice Failed. '.$ok->getMessage().var_export($aDeliveryOpts,true));
            }

        }

    }
    /**
     * Sends an email notice to the moderators
     * if an error in processing has occurred
     *
     * example usage:
     * if($this->totalErrors() > 0) {
     *     $this->sendErrorNotice();
     * }
     * @access  protected
     */
    private function sendErrorNotice()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $aTplOpts = $this->aTplOpts;
        $aDeliveryOpts = $this->aDeliveryOpts;
        $aQueueOpts = $this->aQueueOpts;

        // customize
        $aDeliveryOpts['subject'] = $aDeliveryOpts['errorSubject'];
        // check to see if the To properties have been overridden
        $aDeliveryOpts['toEmail'] = $aDeliveryOpts['adminToEmail'];
        $aDeliveryOpts['toRealName'] = $aDeliveryOpts['adminToRealName'];

        // template vars
        $aTplOpts['htmlTemplate']  = 'noticeEmailError.html';
        $aTplOpts['invoiceList'] = $this->aInvoices;
        $aTplOpts['errorList'] = $this->aErrors;
        $aTplOpts['totalErrors'] = $this->getTotalErrors();
        $aTplOpts['totalInvoices'] = $this->getTotalInvoices();

        // send the email
        $ok = SGL_Emailer2::send($aDeliveryOpts, $aTplOpts, $aQueueOpts);

        if(PEAR::isError($ok)) {
            SGL::raiseError('Email Error Notice Failed. '.$ok->getMessage().var_export($aDeliveryOpts,true));
        }
    }

    /**
     * Finalizes the batch by sending email notices and calling reset()
     *
     * example usage:
     * $batch->finalize();
     *
     * @access  public
     */
    public function finalize()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $this->sendCompletionNotice();
        if($this->getTotalErrors() != 0) {
            $this->sendErrorNotice();
        }
        $this->reset();
    }
    /**
     * reset filenames & arrays, excepting userCache.
     * this allows the batch singleton to be reused,
     * while maintaining the userCache
     *
     * example usage:
     * $this->reset();
     *
     * @access  protected
     */
    private function reset()
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $this->batchFileName = $this->baseFileName = null;
        $this->aUsers = $this->aInvoices = $this->aErrors = array();
    }

    /**
     * totals errors in batch
     * @access protected
     * @return int total errors
     */
    private function getTotalErrors()
    {
        return count($this->aErrors);
    }
    /**
     * totals invoices in batch
     * @access protected
     * @return int total invoices
     */
    private function getTotalInvoices()
    {
        return count($this->aInvoices);
    }

    /**
     * adds a new user to the batch, and caches user
     *
     * @param string $sUserId garickId of the user
     * @param string $sUserName friendly name of the user
     * @param string $sUserEmail email address of the user
     */
    public function addUser($sUserId, $sUserName, $sUserEmail)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $aUserInfo = array('invoiceUserName' => $sUserName,
                           'invoiceUserEmail' => $sUserEmail);
        $this->aUsers[$sUserId] = $aUserInfo;

        // add the user to batch userCache, to reduce LDAP lookups
        $this->cacheUser($sUserId, $aUserInfo);
    }
    /**
     * adds a new user to the batch cache
     *
     * @param string $sUserId garickId of the user
     * @param array $sUserInfo array of user info
     */
    public function cacheUser($sUserId, $aUserInfo)
    {
        $this->aUserCache[$sUserId] = $aUserInfo;
    }
    /**
     * retrieve a user's info - looks in the cache if not set
     *
     * @param string $sUserId garickId of the user
     * @return array user info
     */
    public function getUser($sUserId)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);

        $aUser = $this->get($sUserId, 'aUsers');
        if(!$aUser) {
            $aUser = $this->getCachedUser($sUserId);
        }
        return $aUser;
    }
    /**
     * retrieve a cached user
     *
     * @access protected
     * @param string $sUserId garickId of the user
     * @return array user info
     */
    private function getCachedUser($sUserId)
    {
        return $this->get($sUserId, 'aUserCache');
    }
    /**
     * adds a new invoice to the batch
     *
     * @param array $aInvoice array of invoice information
     */
    public function addInvoice($aInvoice)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        // add invoice to the array
        $this->aInvoices[] = $aInvoice;
    }
    /**
     * adds a new error to the batch
     *
     * @param array $aError array of error information
     */
    public function addError($aError)
    {
        SGL::logMessage(null, PEAR_LOG_DEBUG);
        $this->aErrors[] = $aError;
    }
}
?>
