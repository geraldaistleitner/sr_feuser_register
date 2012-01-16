<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2003 Kasper Skårhøj <kasperYYYY@typo3.com>
*  (c) 2004-2007 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca)>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 *
 * Front End creating/editing/deleting records authenticated by fe_user login.
 * A variant restricted to front end user self-registration and profile maintenance, with a number of enhancements (see the manual).
 *
 * $Id$
 * 
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author	Franz Holzinger <kontakt@fholzinger.com>
 * @maintainer	Franz Holzinger <kontakt@fholzinger.com> 
 *
 *
 */

require_once(PATH_tslib.'class.tslib_pibase.php');
	// To get the pid language overlay:
require_once(PATH_t3lib.'class.t3lib_page.php');

	// For translating items from other extensions
// require_once (t3lib_extMgm::extPath('lang').'lang.php');

require_once(PATH_BE_srfeuserregister.'pi1/class.tx_srfeuserregister_pi1_urlvalidator.php');
require_once(PATH_BE_srfeuserregister.'control/class.tx_srfeuserregister_control.php');
require_once(PATH_BE_srfeuserregister.'control/class.tx_srfeuserregister_setfixed.php');
require_once(PATH_BE_srfeuserregister.'model/class.tx_srfeuserregister_controldata.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_auth.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_email.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_lang.php');
require_once(PATH_BE_srfeuserregister.'lib/class.tx_srfeuserregister_tca.php');
require_once(PATH_BE_srfeuserregister.'marker/class.tx_srfeuserregister_marker.php');
require_once(PATH_BE_srfeuserregister.'model/class.tx_srfeuserregister_url.php');
require_once(PATH_BE_srfeuserregister.'model/class.tx_srfeuserregister_data.php');
require_once(PATH_BE_srfeuserregister.'view/class.tx_srfeuserregister_display.php');



class tx_srfeuserregister_pi1 extends tslib_pibase {
	var $cObj;
	var $conf = array();
	var $config = array();

		// Plugin initialization variables
	var $prefixId = 'tx_srfeuserregister_pi1';  // Same as class name
	var $scriptRelPath = 'pi1/class.tx_srfeuserregister_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'sr_feuser_register';  // The extension key.

	var $incomingData = FALSE;
	var $nc = ''; // "&no_cache=1" if you want that parameter sent.
	var $additionalUpdateFields = '';
	var $sys_language_content;
	var $freeCap; // object of type tx_srfreecap_pi2
	var $auth; // object of type tx_srfeuserregister_auth
	var $control; // object of type tx_srfeuserregister_control
	var $data; // object of type tx_srfeuserregister_data
	var $urlObj;
	var $display; // object of type tx_srfeuserregister_display
	var $email; // object of type tx_srfeuserregister_email
	var $langObj; // object of type tx_srfeuserregister_lang
	var $tca;  // object of type tx_srfeuserregister_tca
	var $marker; // object of type tx_srfeuserregister_marker


	function main($content, &$conf) {
		global $TSFE;

		$failure = false; // is set if data did not have the required fields set.
		$adminFieldList = 'username,password,name,disable,usergroup,by_invitation';
		$this->init($conf, 'fe_users', $adminFieldList);	

		$error_message = '';
		$content = $this->control->doProcessing ($error_message);
		$rc = $this->pi_wrapInBaseClass($content);
		return $rc; 
	}


	/**
	* Initialization
	*
	* @return void
	*/
	function init(&$conf, $theTable, &$adminFieldList) {
		global $TSFE, $TCA, $TYPO3_CONF_VARS;

			// plugin initialization
		$this->conf = $conf;

		if (t3lib_extMgm::isLoaded('sr_freecap') ) {
			require_once(t3lib_extMgm::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
			$this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
		}

		$this->langObj = t3lib_div::makeInstance('tx_srfeuserregister_lang');
		$this->urlObj = t3lib_div::makeInstance('tx_srfeuserregister_url');
		$this->data = t3lib_div::makeInstance('tx_srfeuserregister_data');
		$this->auth = t3lib_div::makeInstance('tx_srfeuserregister_auth');
		$this->marker = t3lib_div::makeInstance('tx_srfeuserregister_marker');
		$this->tca = t3lib_div::makeInstance('tx_srfeuserregister_tca');
		$this->display = t3lib_div::makeInstance('tx_srfeuserregister_display');
		$this->setfixedObj = t3lib_div::makeInstance('tx_srfeuserregister_setfixed');
		$this->email = t3lib_div::makeInstance('tx_srfeuserregister_email');
		$this->control = t3lib_div::makeInstance('tx_srfeuserregister_control');
		$this->controlData = t3lib_div::makeInstance('tx_srfeuserregister_controldata');
		$this->controlData->init($conf, $this->prefixId, $this->extKey, $this->piVars, $theTable);
		$this->urlObj->init ($this->controlData, $this->cObj);
		$this->langObj->init($this, $this->conf, $this->LLkey);
		$this->langObj->pi_loadLL();

		$this->tca->init($this, $this->conf, $this->config, $this->controlData, $this->langObj, $this->extKey);
		$this->data->init($this, $this->conf, $this->config,$this->langObj, $this->tca, $this->auth, $this->control, $this->freeCap, $theTable, $adminFieldList, $this->controlData);
		$this->control->init($this, $this->conf, $this->config, $this->controlData, $this->display, $this->data, $this->marker, $this->auth, $this->email, $this->tca, $this->setfixedObj);

		$this->pi_USER_INT_obj = 1;
		$this->pi_setPiVarDefaults();
		$this->sys_language_content = t3lib_div::testInt($TSFE->config['config']['sys_language_uid']) ? intval($TSFE->config['config']['sys_language_uid']) : 0;

		$this->auth->init($this, $this->conf, $this->config, $this->controlData->getFeUserData('aC'));
		$uid=$this->data->getRecUid();
		$backUrl = rawurldecode($this->controlData->getFeUserData('backURL'));
		$authCode = $this->auth->getAuthCode();
		$this->marker->init($this, $this->conf, $this->config, $this->tca, $this->langObj, $authCode, $this->freeCap, $this->controlData, $this->urlObj, $backUrl, $uid);
		$this->display->init($this, $this->conf, $this->config, $this->data, $this->marker, $this->tca, $this->control, $this->auth);
		$this->email->init($this, $this->conf, $this->config, $this->display, $this->data, $this->marker, $this->tca, $this->controlData, $this->auth, $this->setfixedObj);
		$this->setfixedObj->init($this->cObj, $this->conf, $this->config, $this->controlData, $this->auth, $this->tca, $this->display, $this->email, $this->marker);

	}	// init



}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sr_feuser_register/pi1/class.tx_srfeuserregister_pi1.php']);
}
?>