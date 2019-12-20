<?php
/**
 * Midtrans VT Web Payment Controller
 *
 * @category   Mage
 * @package    Mage_Midtrans_snap_PaymentController
 * @author     Kisman Hong (plihplih.com), Ismail Faruqi (@ifaruqi_jpn)
 * This class is used for handle redirection after placing order.
 * function redirectAction -> redirecting to Midtrans VT Web
 * function responseAction -> when payment at Midtrans VT Web is completed or
 * failed, the page will be redirected to this function,
 * you must set this url in your Midtrans MAP merchant account.
 * http://yoursite.com/snap/payment/notification
 */

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;

require_once(Mage::getBaseDir('lib') . '/midtrans-php/Midtrans.php');

class Midtrans_Snap_PaymentController
    extends Mage_Core_Controller_Front_Action
{

    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    // The redirect action is triggered when someone places an order,
    // redirecting to Midtrans payment page.
    public function redirectAction()
    {
        $orderIncrementId = $this->_getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')
            ->loadByIncrementId($orderIncrementId);
        $sessionId = Mage::getSingleton('core/session');

        $payment_type = Mage::getStoreConfig('payment/snap/payment_types');

        Config::$isProduction = Mage::getStoreConfig('payment/snap/environment') == 'production' ? true : false;

        Config::$serverKey = Mage::getStoreConfig('payment/snap/server_key');

        Config::$is3ds = Mage::getStoreConfig('payment/snap/enable_3d_secure') == '1' ? true : false;
        Config::$isSanitized = true;

        $enable_snap_redirect = Mage::getStoreConfig('payment/snap/snapredirect') == '1' ? true : false;
        $oneClick = Mage::getStoreConfig('payment/snap/oneclick') == '1' ? true : false;


        Mage::log($mixpanel_key . Config::$isProduction, null, 'snap.log', true);

        $transaction_details = array();
        $transaction_details['order_id'] = $orderIncrementId;

        $order_billing_address = $order->getBillingAddress();
        $billing_address = array();
        $billing_address['first_name'] = $order_billing_address->getFirstname();
        $billing_address['last_name'] = $order_billing_address->getLastname();
        $billing_address['address'] = $order_billing_address->getStreet(1);
        $billing_address['city'] = $order_billing_address->getCity();
        $billing_address['postal_code'] = $order_billing_address->getPostcode();
        $billing_address['country_code'] = $this->convert_country_code($order_billing_address->getCountry());
        $billing_address['phone'] = $order_billing_address->getTelephone();

        $order_shipping_address = $order->getShippingAddress();
        $shipping_address = array();
        $shipping_address['first_name'] = $order_shipping_address->getFirstname();
        $shipping_address['last_name'] = $order_shipping_address->getLastname();
        $shipping_address['address'] = $order_shipping_address->getStreet(1);
        $shipping_address['city'] = $order_shipping_address->getCity();
        $shipping_address['postal_code'] = $order_shipping_address->getPostcode();
        $shipping_address['phone'] = $order_shipping_address->getTelephone();
        $shipping_address['country_code'] = $this->convert_country_code($order_shipping_address->getCountry());

        $customer_details = array();
        $customer_details['billing_address'] = $billing_address;
        $customer_details['shipping_address'] = $shipping_address;
        $customer_details['first_name'] = $order_billing_address->getFirstname();
        $customer_details['last_name'] = $order_billing_address->getLastname();
        $customer_details['email'] = $order_billing_address->getEmail();
        $customer_details['phone'] = $order_billing_address->getTelephone();

        $items = $order->getAllItems();
        $shipping_amount = $order->getShippingAmount();
        $shipping_tax_amount = $order->getShippingTaxAmount();
        $tax_amount = $order->getTaxAmount();

        $item_details = array();

        foreach ($items as $each) {
            $item = array(
                'id' => $each->getProductId(),
                'price' => $each->getPrice(),
                'quantity' => $each->getQtyToInvoice(),
                'name' => $each->getName()
            );

            if ($item['quantity'] == 0) continue;
            $item_details[] = $item;
        }

        $num_products = count($item_details);

        unset($each);

        if ($order->getDiscountAmount() != 0) {
            $couponItem = array(
                'id' => 'DISCOUNT',
                'price' => $order->getDiscountAmount(),
                'quantity' => 1,
                'name' => 'DISCOUNT'
            );
            $item_details[] = $couponItem;
        }

        if ($shipping_amount > 0) {
            $shipping_item = array(
                'id' => 'SHIPPING',
                'price' => $shipping_amount,
                'quantity' => 1,
                'name' => 'Shipping Cost'
            );
            $item_details[] = $shipping_item;
        }

        if ($shipping_tax_amount > 0) {
            $shipping_tax_item = array(
                'id' => 'SHIPPING_TAX',
                'price' => $shipping_tax_amount,
                'quantity' => 1,
                'name' => 'Shipping Tax'
            );
            $item_details[] = $shipping_tax_item;
        }

        if ($tax_amount > 0) {
            $tax_item = array(
                'id' => 'TAX',
                'price' => $tax_amount,
                'quantity' => 1,
                'name' => 'Tax'
            );
            $item_details[] = $tax_item;
        }

        // convert to IDR
        $current_currency = Mage::app()->getStore()->getCurrentCurrencyCode();
        if ($current_currency != 'IDR') {
            $conversion_func = function ($non_idr_price) {
                return $non_idr_price *
                    Mage::getStoreConfig('payment/snap/conversion_rate');
            };
            foreach ($item_details as &$item) {
                $item['price'] =
                    intval(round(call_user_func($conversion_func, $item['price'])));
            }
            unset($item);
        } else {
            foreach ($item_details as &$each) {
                $each['price'] = (int)$each['price'];
            }
            unset($each);
        }

        $enabled_payments = Mage::getStoreConfig('payment/snap/enablepayment');
        $bank = Mage::getStoreConfig('payment/snap/bank');

        $credit_card = array();
        $payloads = array();

        if (!empty($bank)) {
            $credit_card['bank'] = $bank;
        }

        if (Config::$is3ds == true) {
            $credit_card['secure'] = true;
        }

        if ($oneClick == true) {
            $credit_card['save_card'] = true;
            $payloads['user_id'] = hash('sha256', $order_billing_address->getEmail());
        }

        $payloads['transaction_details'] = $transaction_details;
        $payloads['item_details'] = $item_details;
        $payloads['customer_details'] = $customer_details;
        $payloads['credit_card'] = $credit_card;


        if (!empty($enabled_payments)) {
            $enabled_payments = explode(',', $enabled_payments);
            $payloads['enabled_payments'] = $enabled_payments;
        }


        $expire = explode(",", Mage::getStoreConfig('payment/snap/expiry'));
        $expiry_unit = $expire[1];
        $expiry_duration = (int)$expire[0];
        error_log($expiry_unit . $expiry_duration);

        if (!empty($expiry_unit) && !empty($expiry_duration)) {
            $time = time();
            $payloads['expiry'] = array(
                'start_time' => date("Y-m-d H:i:s O", $time),
                'unit' => $expiry_unit,
                'duration' => (int)$expiry_duration
            );
        }

        $totalPrice = 0;

        foreach ($item_details as $item) {
            $totalPrice += $item['price'] * $item['quantity'];
        }
        Mage::log(json_encode($payloads), null, 'snap.log', true);

        if ($enable_snap_redirect == false) {
            try {
                $snapToken = Snap::getSnapToken($payloads);
                Mage::log('snap token from controller = ' . $snapToken, null, 'snap.log', true);
                $this->_getCheckout()->setToken($snapToken);
                $this->_getCheckout()->setEnv(Mage::getStoreConfig('payment/snap/environment'));

                foreach (Mage::getSingleton('checkout/session')->getQuote()->getItemsCollection() as $item) {
                    Mage::getSingleton('checkout/cart')->removeItem($item->getId())->save();
                }

                Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getBaseUrl() . 'snap/payment/opensnap');

            } catch (Exception $e) {
                error_log($e->getMessage());
                Mage::log('error:' . print_r($e->getMessage(), true), null, 'snap.log', true);
            }
        } else {
            try {
                $redirectUrl = Snap::createTransaction($payloads)->redirect_url;
                Mage::log('SNAP Redirect URL = ' . $redirectUrl, null, 'snap.log', true);
                $this->_getCheckout()->setEnv(Mage::getStoreConfig('payment/snap/environment'));

                foreach (Mage::getSingleton('checkout/session')->getQuote()->getItemsCollection() as $item) {
                    Mage::getSingleton('checkout/cart')->removeItem($item->getId())->save();
                }

                Mage::app()->getFrontController()->getResponse()->setRedirect($redirectUrl);
            } catch (Exception $e) {
                error_log($e->getMessage());
                Mage::log('error:' . print_r($e->getMessage(), true), null, 'snap.log', true);
            }
        }
    }

    public function opensnapAction()
    {

        $template = 'snap/open.phtml';

        //Get current layout state
        $this->loadLayout();

        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'snap',
            array('template' => $template)
        );

        $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
        $this->getLayout()->getBlock('content')->append($block);
        $this->_initLayoutMessages('core/session');
        $this->renderLayout();
    }

    // The response action is triggered when your gateway sends back a response
    // after processing the customer's payment, we will not update to success
    // because success is valid when notification (security reason)
    public function responseAction()
    {
        //var_dump($_POST); use for debugging value.
        if ($_GET['order_id']) {
            $orderId = $_GET['order_id']; // Generally sent by gateway
            $status = $_GET['status_code'];
            if ($status == '200' || $status == '201' && !is_null($orderId) && $orderId != '') {
                // Redirected by Midtrans, if ok
                Mage::getSingleton('checkout/session')->unsQuoteId();
                Mage_Core_Controller_Varien_Action::_redirect(
                    'checkout/onepage/success', array('_secure' => false));
            } else {
                // There is a problem in the response we got
                $this->cancelAction();
                Mage_Core_Controller_Varien_Action::_redirect(
                    'checkout/onepage/failure', array('_secure' => true));
            }
        } else {
            Mage_Core_Controller_Varien_Action::_redirect('');
        }
    }

    // Response early with 200 OK status for Midtrans notification & handle HTTP GET
    public function earlyResponse()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            die('This endpoint should not be opened using browser (HTTP GET). This endpoint is for Midtrans notification URL (HTTP POST)');
            exit();
        }

        ob_start();

        $input_source = "php://input";
        $raw_notification = json_decode(file_get_contents($input_source), true);
        echo "Notification Received: \n";
        print_r($raw_notification);

        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        ob_flush();
        flush();
    }

    public function notificationAction()
    {
        //echo "hahaha";
        error_log('payment notification');
        Config::$isProduction =
            Mage::getStoreConfig('payment/snap/environment') == 'production' ? true : false;
        Config::$serverKey = Mage::getStoreConfig('payment/snap/server_key');
        $this->earlyResponse();
        $notif = new Notification();
        Mage::log('get status result' . print_r($notif, true), null, 'snap.log', true);

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($notif->order_id);

        $transaction = $notif->transaction_status;
        $fraud = $notif->fraud_status;
        $payment_type = $notif->payment_type;

        if ($transaction == 'capture') {
            if ($fraud == 'challenge') {
                $order->setStatus(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
            } else if ($fraud == 'accept') {
                $invoice = $order->prepareInvoice()
                    ->setTransactionId($order->getId())
                    ->addComment('Payment successfully processed by Midtrans.')
                    ->register()
                    ->pay();

                $transaction_save = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transaction_save->save();

                $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                $order->sendOrderUpdateEmail(true,
                    'Thank you, your payment is successfully processed.');
            }
        } else if ($transaction == 'settlement') {
            if ($payment_type != 'credit_card') {
                $invoice = $order->prepareInvoice()
                    ->setTransactionId($order->getId())
                    ->addComment('Payment successfully processed by Midtrans.')
                    ->register()
                    ->pay();

                $transaction_save = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transaction_save->save();

                $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                $order->sendOrderUpdateEmail(true,
                    'Thank you, your payment is successfully processed.');
            }
        } else if ($transaction == 'pending') {
            $order->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
            $order->sendOrderUpdateEmail(true,
                'Thank you, your payment is successfully processed.');
        } else if ($transaction == 'cancel' || $transaction == 'deny' || $transaction == 'expire') {
            if ($order->canCancel()) {
                $order->cancel();
                $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has Notif Failure Transaction.');
            }
        }
        $order->save();
    }

    // The cancel action is triggered when an order is to be cancelled
    public function cancelAction()
    {
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(
                Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if ($order->getId()) {
                // Flag the order as 'cancelled' and save it
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED,
                    true, 'Gateway has declined the payment.')->save();
            }
        }
    }

    /**
     * Convert 2 digits coundry code to 3 digit country code
     *
     * @param String $country_code Country code which will be converted
     */
    public function convert_country_code($country_code)
    {

        // 3 digits country codes
        $cc_three = array(
            'AF' => 'AFG',
            'AX' => 'ALA',
            'AL' => 'ALB',
            'DZ' => 'DZA',
            'AD' => 'AND',
            'AO' => 'AGO',
            'AI' => 'AIA',
            'AQ' => 'ATA',
            'AG' => 'ATG',
            'AR' => 'ARG',
            'AM' => 'ARM',
            'AW' => 'ABW',
            'AU' => 'AUS',
            'AT' => 'AUT',
            'AZ' => 'AZE',
            'BS' => 'BHS',
            'BH' => 'BHR',
            'BD' => 'BGD',
            'BB' => 'BRB',
            'BY' => 'BLR',
            'BE' => 'BEL',
            'PW' => 'PLW',
            'BZ' => 'BLZ',
            'BJ' => 'BEN',
            'BM' => 'BMU',
            'BT' => 'BTN',
            'BO' => 'BOL',
            'BQ' => 'BES',
            'BA' => 'BIH',
            'BW' => 'BWA',
            'BV' => 'BVT',
            'BR' => 'BRA',
            'IO' => 'IOT',
            'VG' => 'VGB',
            'BN' => 'BRN',
            'BG' => 'BGR',
            'BF' => 'BFA',
            'BI' => 'BDI',
            'KH' => 'KHM',
            'CM' => 'CMR',
            'CA' => 'CAN',
            'CV' => 'CPV',
            'KY' => 'CYM',
            'CF' => 'CAF',
            'TD' => 'TCD',
            'CL' => 'CHL',
            'CN' => 'CHN',
            'CX' => 'CXR',
            'CC' => 'CCK',
            'CO' => 'COL',
            'KM' => 'COM',
            'CG' => 'COG',
            'CD' => 'COD',
            'CK' => 'COK',
            'CR' => 'CRI',
            'HR' => 'HRV',
            'CU' => 'CUB',
            'CW' => 'CUW',
            'CY' => 'CYP',
            'CZ' => 'CZE',
            'DK' => 'DNK',
            'DJ' => 'DJI',
            'DM' => 'DMA',
            'DO' => 'DOM',
            'EC' => 'ECU',
            'EG' => 'EGY',
            'SV' => 'SLV',
            'GQ' => 'GNQ',
            'ER' => 'ERI',
            'EE' => 'EST',
            'ET' => 'ETH',
            'FK' => 'FLK',
            'FO' => 'FRO',
            'FJ' => 'FJI',
            'FI' => 'FIN',
            'FR' => 'FRA',
            'GF' => 'GUF',
            'PF' => 'PYF',
            'TF' => 'ATF',
            'GA' => 'GAB',
            'GM' => 'GMB',
            'GE' => 'GEO',
            'DE' => 'DEU',
            'GH' => 'GHA',
            'GI' => 'GIB',
            'GR' => 'GRC',
            'GL' => 'GRL',
            'GD' => 'GRD',
            'GP' => 'GLP',
            'GT' => 'GTM',
            'GG' => 'GGY',
            'GN' => 'GIN',
            'GW' => 'GNB',
            'GY' => 'GUY',
            'HT' => 'HTI',
            'HM' => 'HMD',
            'HN' => 'HND',
            'HK' => 'HKG',
            'HU' => 'HUN',
            'IS' => 'ISL',
            'IN' => 'IND',
            'ID' => 'IDN',
            'IR' => 'RIN',
            'IQ' => 'IRQ',
            'IE' => 'IRL',
            'IM' => 'IMN',
            'IL' => 'ISR',
            'IT' => 'ITA',
            'CI' => 'CIV',
            'JM' => 'JAM',
            'JP' => 'JPN',
            'JE' => 'JEY',
            'JO' => 'JOR',
            'KZ' => 'KAZ',
            'KE' => 'KEN',
            'KI' => 'KIR',
            'KW' => 'KWT',
            'KG' => 'KGZ',
            'LA' => 'LAO',
            'LV' => 'LVA',
            'LB' => 'LBN',
            'LS' => 'LSO',
            'LR' => 'LBR',
            'LY' => 'LBY',
            'LI' => 'LIE',
            'LT' => 'LTU',
            'LU' => 'LUX',
            'MO' => 'MAC',
            'MK' => 'MKD',
            'MG' => 'MDG',
            'MW' => 'MWI',
            'MY' => 'MYS',
            'MV' => 'MDV',
            'ML' => 'MLI',
            'MT' => 'MLT',
            'MH' => 'MHL',
            'MQ' => 'MTQ',
            'MR' => 'MRT',
            'MU' => 'MUS',
            'YT' => 'MYT',
            'MX' => 'MEX',
            'FM' => 'FSM',
            'MD' => 'MDA',
            'MC' => 'MCO',
            'MN' => 'MNG',
            'ME' => 'MNE',
            'MS' => 'MSR',
            'MA' => 'MAR',
            'MZ' => 'MOZ',
            'MM' => 'MMR',
            'NA' => 'NAM',
            'NR' => 'NRU',
            'NP' => 'NPL',
            'NL' => 'NLD',
            'AN' => 'ANT',
            'NC' => 'NCL',
            'NZ' => 'NZL',
            'NI' => 'NIC',
            'NE' => 'NER',
            'NG' => 'NGA',
            'NU' => 'NIU',
            'NF' => 'NFK',
            'KP' => 'MNP',
            'NO' => 'NOR',
            'OM' => 'OMN',
            'PK' => 'PAK',
            'PS' => 'PSE',
            'PA' => 'PAN',
            'PG' => 'PNG',
            'PY' => 'PRY',
            'PE' => 'PER',
            'PH' => 'PHL',
            'PN' => 'PCN',
            'PL' => 'POL',
            'PT' => 'PRT',
            'QA' => 'QAT',
            'RE' => 'REU',
            'RO' => 'SHN',
            'RU' => 'RUS',
            'RW' => 'EWA',
            'BL' => 'BLM',
            'SH' => 'SHN',
            'KN' => 'KNA',
            'LC' => 'LCA',
            'MF' => 'MAF',
            'SX' => 'SXM',
            'PM' => 'SPM',
            'VC' => 'VCT',
            'SM' => 'SMR',
            'ST' => 'STP',
            'SA' => 'SAU',
            'SN' => 'SEN',
            'RS' => 'SRB',
            'SC' => 'SYC',
            'SL' => 'SLE',
            'SG' => 'SGP',
            'SK' => 'SVK',
            'SI' => 'SVN',
            'SB' => 'SLB',
            'SO' => 'SOM',
            'ZA' => 'ZAF',
            'GS' => 'SGS',
            'KR' => 'KOR',
            'SS' => 'SSD',
            'ES' => 'ESP',
            'LK' => 'LKA',
            'SD' => 'SDN',
            'SR' => 'SUR',
            'SJ' => 'SJM',
            'SZ' => 'SWZ',
            'SE' => 'SWE',
            'CH' => 'CHE',
            'SY' => 'SYR',
            'TW' => 'TWN',
            'TJ' => 'TJK',
            'TZ' => 'TZA',
            'TH' => 'THA',
            'TL' => 'TLS',
            'TG' => 'TGO',
            'TK' => 'TKL',
            'TO' => 'TON',
            'TT' => 'TTO',
            'TN' => 'TUN',
            'TR' => 'TUR',
            'TM' => 'TKM',
            'TC' => 'TCA',
            'TV' => 'TUV',
            'UG' => 'UGA',
            'UA' => 'UKR',
            'AE' => 'ARE',
            'GB' => 'GBR',
            'US' => 'USA',
            'UY' => 'URY',
            'UZ' => 'UZB',
            'VU' => 'VUT',
            'VA' => 'VAT',
            'VE' => 'VEN',
            'VN' => 'VNM',
            'WF' => 'WLF',
            'EH' => 'ESH',
            'WS' => 'WSM',
            'YE' => 'YEM',
            'ZM' => 'ZMB',
            'ZW' => 'ZWE'
        );

        // Check if country code exists
        if (isset($cc_three[$country_code]) && $cc_three[$country_code] != '') {
            $country_code = $cc_three[$country_code];
        }

        return $country_code;
    }
}

?>