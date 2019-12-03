<?php
/**
 * Midtrans VT Web form block
 *
 * @category   Mage
 * @package    Mage_Midtrans_VtWeb_Block_Form
 * @author     Kisman Hong, plihplih.com
 * when Midtrans payment method is chosen, vtweb/info.phtml template will be rendered at the right side, in progress bar.
 */
class Midtrans_Snapspec_Block_Info extends Mage_Payment_Block_Info
{
    
    protected function _construct()
    {
        parent::_construct();
	$this->setInfoMessage( Mage::helper('snapspec/data')->_getInfoTypeIsImage() == true ?
		'<img src="'. $this->getSkinUrl('images/Midtrans.png'). '"/>' : '<b>'. Mage::helper('snapspec/data')->_getTitle() . '</b>');
	$this->setPaymentMethodTitle( Mage::helper('snapspec/data')->_getTitle() );
        $this->setTemplate('snapspec/info.phtml');
    }
}
?>
