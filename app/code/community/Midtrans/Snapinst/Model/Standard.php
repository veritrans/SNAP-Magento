<?php
/**
 * Midtrans VT Web Model Standard
 *
 * @category   Mage
 * @package    Mage_Midtrans_Snap_Model_Standard
 * @author     Kisman Hong, plihplih.com
 * this class is used after placing order, if the payment is Midtrans, this class will be called and link to redirectAction at Midtrans_Vtweb_PaymentController class
 */
class Midtrans_Snapinst_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'snapinst';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	
	protected $_formBlockType = 'snapinst/form';
  	protected $_infoBlockType = 'snapinst/info';
	
	// call to redirectAction function at Midtrans_Vtweb_PaymentController
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('snapinst/payment/redirect', array('_secure' => true));
	}
}
?>