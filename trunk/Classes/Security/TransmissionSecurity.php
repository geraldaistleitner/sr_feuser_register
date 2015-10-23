<?php
namespace SJBR\SrFeuserRegister\Security;

/*
 *  Copyright notice
 *
 *  (c) 2012-2015 Stanislas Rolland <typo3(arobas)sjbr.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

use SJBR\SrFeuserRegister\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Rsaauth\Backend\BackendFactory;
use TYPO3\CMS\Rsaauth\Storage\StorageFactory;

/**
 * Transmission security functions
 */
class TransmissionSecurity
{
	/**
	 *  Extension name
	 *
	 * @var string
	 */
	static protected $extensionName = 'SrFeuserRegister';

	/**
	 * Extension key
	 *
	 * @var string
	 */
	static protected $extensionKey = 'sr_feuser_register';

	/**
	 * Gets the transmission security level
	 *
	 * @return string the transmission security level
	 */
	static public function getTransmissionSecurityLevel()
	{
		return $GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel'];
	}

	/**
	 * Decrypts fields that were encrypted for transmission
	 *
	 * @param array $row: incoming data array that may contain encrypted fields
	 * @return boolean true, if decryption was successful
	 */
	static public function decryptIncomingFields(array &$row)
	{
		$success = true;
		if (!empty($row)) {
			switch (self::getTransmissionSecurityLevel()) {
				case 'rsa':
					// Get services from rsaauth
					// Can't simply use the authentication service because we have two fields to decrypt
					/** @var $backend \TYPO3\CMS\Rsaauth\Backend\AbstractBackend */
					$backend = BackendFactory::getBackend();
					/** @var $storage \TYPO3\CMS\Rsaauth\Storage\AbstractStorage */
					$storage = StorageFactory::getStorage();
					if (is_object($backend) && is_object($storage)) {
						$key = $storage->get();
						if ($key !== null) {
							foreach ($row as $field => $value) {
								if (isset($value) && $value !== '') {
									if (substr($value, 0, 4) === 'rsa:') {
										// Decode password
										$result = $backend->decrypt($key, substr($value, 4));
										if ($result) {
											$row[$field] = $result;
										} else {
											// RSA auth service failed to process incoming password
											// May happen if the key is wrong
											// May happen if multiple instance of rsaauth on same page
											$success = false;
											$message = LocalizationUtility::translate('internal_rsaauth_process_incoming_password_failed', self::$extensionName);
											GeneralUtility::sysLog($message, self::$extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
										}
									}
								}
							}
							// Remove the key
							$storage->put(null);
						} else {
							// RSA auth service failed to retrieve private key
							// May happen if the key was already removed
							$success = false;
							$message = LocalizationUtility::translate('internal_rsaauth_retrieve_private_key_failed', self::$extensionName);
							GeneralUtility::sysLog($message, self::$extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
						}
					} else {
						// Required RSA auth backend not available
						// Should not happen
						$success = false;
						$message = LocalizationUtility::translate('internal_rsaauth_backend_not_available', self::$extensionName);
						GeneralUtility::sysLog($message, self::$extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
					}
					break;
				case 'normal':
				default:
					// Nothing to decrypt
					break;
			}
		}
		return $success;
	}

	/**
	 * Gets value for ###FORM_ONSUBMIT### and ###HIDDENFIELDS### markers
	 *
	 * @param array $markerArray: marker array
	 * @param boolean $usePasswordAgain: whether the password again field is configured
	 * @return void
	 */
	static public function getMarkers(array &$markerArray, $usePasswordAgain)
	{
		$markerArray['###FORM_ONSUBMIT###'] = '';
 		switch (self::getTransmissionSecurityLevel()) {
			case 'rsa':
				$onSubmit = '';
				$extraHiddenFields = '';
				$extraHiddenFieldsArray = array();
				$params = array();
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['loginFormOnSubmitFuncs'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['loginFormOnSubmitFuncs'] as $funcRef) {
						list($onSubmit, $hiddenFields) = GeneralUtility::callUserFunction($funcRef, $params, $this);
						$extraHiddenFieldsArray[] = $hiddenFields;
					}
				} else {
					// Extension rsaauth not installed
					// Should not happen
					$message = sprintf(LocalizationUtility::translate('internal_required_extension_missing', self::$extensionName), 'rsaauth');
					GeneralUtility::sysLog($message, self::$extensionKey, GeneralUtility::SYSLOG_SEVERITY_ERROR);
				}
				if ($usePasswordAgain) {
					$onSubmit = 'if (this.pass.value != this[\'FE[fe_users][password_again]\'].value) {this.password_again_failure.value = 1; this.pass.value = \'X\'; this[\'FE[fe_users][password_again]\'].value = \'\'; return true;} else { this[\'FE[fe_users][password_again]\'].value = \'\'; ' . $onSubmit . '}';
					$extraHiddenFieldsArray[] = '<input type="hidden" name="password_again_failure" value="0">';
				}
				$markerArray['###FORM_ONSUBMIT###'] = ' onsubmit="' . $onSubmit . '"';
				if (count($extraHiddenFieldsArray)) {
					$extraHiddenFields = implode(LF, $extraHiddenFieldsArray);
				}
				$markerArray['###HIDDENFIELDS###'] .= LF . $extraHiddenFields;
				break;
			case 'normal':
			default:
				$markerArray['###HIDDENFIELDS###'] .= LF;
				break;
		}
	}
}