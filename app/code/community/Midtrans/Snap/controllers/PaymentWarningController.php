<?php
  class Midtrans_Snap_PayController extends Mage_Core_Controller_Front_Action {

    public function openAction() {

      
      $template = 'snap/open.phtml';

      //Get current layout state
      $this->loadLayout();          
      
      $block = $this->getLayout()->createBlock(
          'Mage_Core_Block_Template',
          'bcaklikpay',
          array('template' => $template)
      );
      
      $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
      $this->getLayout()->getBlock('content')->append($block);
      $this->_initLayoutMessages('core/session'); 
      $this->renderLayout();

    }
  }