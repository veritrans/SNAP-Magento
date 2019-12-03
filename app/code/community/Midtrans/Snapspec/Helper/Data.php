<?php
/**
 * Veritrans VT Web Helper Data
 *
 * @category   Mage
 * @package    Mage_Midtrans_snap_PaymentController
 * @author     Harry
 * this class is used for declaring variable of Veritrans's constant.
 */
class Midtrans_Snapspec_Helper_Data extends Mage_Core_Helper_Abstract
{

	// Veritrans payment method title 
	function _getTitle(){
		return Mage::getStoreConfig('payment/snapspec/title');
	}
	
	// progress side bar, if true then show logo image, vice versa
	function _getInfoTypeIsImage(){
		return Mage::getStoreConfig('payment/snapspec/info_type');
	}
	
	// Message to be shown when Veritrans payment method is chosen
	function _getFormMessage(){
		return Mage::getStoreConfig('payment/snapspec/form_message');
	}
}