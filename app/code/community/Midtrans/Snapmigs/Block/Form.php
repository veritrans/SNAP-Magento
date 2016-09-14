<?php
/**
 * Veritrans VT Web form block
 *
 * @category   Mage
 * @package    Mage_Veritrans_VtWeb_Block_Form
 * @author     Kisman Hong, plihplih.com
 * when Veritrans payment method is chosen, vtweb/form.phtml template will be rendered through this class.
 */
class Midtrans_Snapmigs_Block_Form extends Mage_Payment_Block_Form
{
    
    protected function _construct()
    {
        parent::_construct();

		    $this->setFormMessage(Mage::helper('snapmigs/data')->_getFormMessage());
        $this->setTemplate('snapmigs/form.phtml');
    }
}
?>