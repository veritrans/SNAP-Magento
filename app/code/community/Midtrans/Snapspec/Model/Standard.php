<?php
/**
 * Midtrans Web Model Standard
 *
 * @category   Mage
 * @package    Mage_Midtrans_Snap_Model_Standard
 * @author     Kisman Hong, plihplih.com
 * this class is used after placing order, if the payment is Veritrans, this class will be called and link to redirectAction at Veritrans_Vtweb_PaymentController class
 */
class Midtrans_Snapspec_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'snapspec';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	
	protected $_formBlockType = 'snapspec/form';
  	protected $_infoBlockType = 'snapspec/info';
	
	// call to redirectAction function at Veritrans_Vtweb_PaymentController
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('snapspec/payment/redirect', array('_secure' => true));
	}
}
?>