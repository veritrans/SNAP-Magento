<?php

class Veritrans_Notification {

  private $response;

  public function __construct($input_source = "php://input")
  {
    $raw_notification = json_decode(file_get_contents($input_source), true);
    $status_response = Veritrans_Transaction::status($raw_notification['transaction_id']);
    //Mage::log('error:'.print_r($e->getMessage(),true),null,'vtweb.log',true);
     $notificationLog = ' Notif Object updated :: '.print_r(json_decode(file_get_contents('php://input'),TRUE),TRUE);
     Mage::log($notificationLog, null, 'vtnotif.log', true);
     //Mage::log('error:'.print_r($e->getMessage(),true),null,'vtnotif.log',true);
    $this->response = $status_response;
  }

  public function __get($name)
  {
    if (array_key_exists($name, $this->response)) {
      return $this->response->$name;
    }
  }
}



/*public function __construct($input_source = "php://input")
  {
    $notificationLog = '';
    $notificationLog .= ' Notif Object updated :: '.print_r(file_get_contents('php://input'),TRUE);
    //$notificationLog .= ' Notif Object :: '.print_r(file_get_contents('php://input'));
    Mage::log($notificationLog, 6, 'vt-notification.log', true);
    $this->response = json_decode(file_get_contents('php://input'), true);
  }
*/
?>