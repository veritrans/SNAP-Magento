<?php
/**
 * Midtrans VT Web form block
 *
 * @category   Mage
 * @package    Mage_Midtrans_VtWeb_Block_Form
 * @author     Kisman Hong, plihplih.com
 * when Midtrans payment method is chosen, vtweb/form.phtml template will be rendered through this class.
 */
class Midtrans_Snapinst_Block_Form extends Mage_Payment_Block_Form
{
    
    protected function _construct()
    {
        parent::_construct();

		    $this->setFormMessage(Mage::helper('snapinst/data')->_getFormMessage());
        $this->setTemplate('snapinst/form.phtml');
    }
}
?>