<?php
/**
 * Midtrans VT Web Helper Data
 *
 * @category   Mage
 * @package    Mage_Midtrans_snap_PaymentController
 * @author     Harry
 * this class is used for declaring variable of Midtrans's constant.
 */
class Midtrans_Snapinst_Helper_Data extends Mage_Core_Helper_Abstract
{

	// Midtrans payment method title
	function _getTitle(){
		return Mage::getStoreConfig('payment/snapinst/title');
	}
	
	// progress side bar, if true then show logo image, vice versa
	function _getInfoTypeIsImage(){
		return Mage::getStoreConfig('payment/snapinst/info_type');
	}
	
	// Message to be shown when Midtrans payment method is chosen
	function _getFormMessage(){
		return Mage::getStoreConfig('payment/snapinst/form_message');
	}
}