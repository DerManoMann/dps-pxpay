<?php
/*
 * DPS PxPay payment module for zen-cart.
 * Copyright (c) 2006 mixedmatter Ltd
 *
 * Portions Copyright (c) 2003 The zen-cart developers
 * Portions Copyright (c) 2003 osCommerce
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 *
 */
?>
<?php  

define('TABLE_DPS_PXPAY', DB_PREFIX . 'dps_pxpay');


/**
 * DPS PxPay payment module.
 *
 * zen-cart payment module for use with DPS PxPay.
 * See http://www.dps.co.nz/technical_resources/ecommerce_hosted/pxpay.html
 * for details.
 *
 * @author Martin Rademacher, mixed matter Ltd; martin@mixedmatter.co.nz
 * @version $Id: dps_pxpay.php,v 1.6 2007/08/09 09:18:33 radebatz Exp $
 */
class dps_pxpay {
    var $code, $title, $description, $enabled;

    // the DPS PxPay URL
    var $_dpsPxpayUrl;
    // the URL for DPS to redirect (also used for fail proof result notification)
    var $_dpsResultRedirect;
    var $_txnId;
    var $_nl;
    var $_trace;
    var $_dpsInfo;


    /**
     * Default c'tor.
     */
    function dps_pxpay() {
    global $order;

        $this->code = 'dps_pxpay';
        $this->title = MODULE_PAYMENT_DPS_PXPAY_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_DPS_PXPAY_TEXT_DESCRIPTION;
        $this->enabled = ((MODULE_PAYMENT_DPS_PXPAY_STATUS == 'True') ? true : false);
        $this->sort_order = MODULE_PAYMENT_DPS_PXPAY_SORT_ORDER;

        if ((int)MODULE_PAYMENT_DPS_PXPAY_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_DPS_PXPAY_ORDER_STATUS_ID;
        }

        if (is_object($order)) $this->update_status();

        $this->_dpsPxpayUrl = 'https://sec.paymentexpress.com/pxpay/pxaccess.aspx';
        // support for fail proof result notification
        $this->_dpsResultRedirect = ('true' == ENABLE_SSL ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_HTTPS_CATALOG . 'dps_pxpay_result_handler.php';
        // without
        #$this->_dpsResultRedirect = zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', false);
        $this->_txnId = null;
        $this->_nl = "\n";
        $this->_trace = 'True' == MODULE_PAYMENT_DPS_PXPAY_TRACE;
        $this->_dpsInfo = 'True' == MODULE_PAYMENT_DPS_PXPAY_SHOW_LOGO;
    }

    
    /**
     * Update the module status.
     *
     * This is called after the order instance is set up to allow both payment
     * module and order to synchronise.
     */
    function update_status() {
    global $order, $db;

        if ($this->enabled && ((int)MODULE_PAYMENT_DPS_PXPAY_ZONE > 0)) {
            $check_flag = false;
            $sql = "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " 
                    where geo_zone_id = '" . MODULE_PAYMENT_DPS_PXPAY_ZONE . "'
                      and zone_country_id = :countryId
                    order by zone_id";
            $sql = $db->bindVars($sql, ':countryId', $order->billing['country']['id'], 'integer');

            $results = $db->Execute($sql);
            while (!$results->EOF) {
                if ($results->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($results->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $results->MoveNext();
            }

            if (!$check_flag) {
                $this->enabled = false;
            }
        }
    }


    /**
     * JavaScript form validation.
     *
     * @return JavaScript form validation code for this module.
     */
    function javascript_validation() {
        return '';
    }


    /**
     * Generate form fields for this module.
     *
     * This would usually be fomr input fields for credit card number, etc.
     * Since the form is provided by DPS, there is nothing to do here.
     *
     * @return array Form fields to be displayed durin checkout.
     */
    function selection() {
        $selection = array('id' => $this->code, 'module' => $this->title);	
        if ($this->_dpsInfo) {
            $selection['fields'] = array(
                   array('title' => MODULE_PAYMENT_DPS_PXPAY_LOGO,
                         'field' => MODULE_PAYMENT_DPS_PXPAY_LOGO_TEXT),                                                 
            );
        }
        return $selection;
    }


    /**
     * Called before the confirmation page is displayed.
     *
     * This method typically would implement server side validation and would
     * do either of the following:
     * <ul>
     *   <li>add error messages to the <code>$messageStack</code></li>
     *   <li>redirect to <code>FILENAME_CHECKOUT_PAYMENT</code> with a parameter
     *    <code>payment_error</code> set to the payment module.
     *    This will trigger a call to <code>get_error()</code> on the payment
     *    page.</li>
     * </ul>
     *
     */
    function pre_confirmation_check() {
    }


    /**
     * Called during display of the order confirmation page.
     *
     * @return array Payment information that should be displayed on the
     *  order confirmation page.
     */
    function confirmation() {
        $confirmation = array('title' => $title);
        return $confirmation;
    }


    /**
     * The return value of this method is inserted into the confirmation page form.
     *
     * Usually this method would create hidden fields to be added to the form.
     *
     * @return string Valid HTML.
     */
    function process_button() {
        return '';
    }


    /**
     * Called before the checkout is actually performed.
     *
     * This is the central method for most payment modules.
     */
    function before_process() {
        // if present, this is the redirect back from DPS PxPay
        if (array_key_exists('dps_done', $_GET)) {
            // this means redirect from dps_pxpay_result_handler.php
            $this->_validatePayment();
        } else if (array_key_exists('result', $_GET)) {
            $this->_processPxPayResponse();
        } else {
            $this->_processPxPay();
        }
    }


    /**
     * Simple log method.
     *
     * @param string msg The message.
     */
    function log($msg) {
        if ($this->_trace) {
            error_log($msg);
            //trigger_error($msg, E_USER_NOTICE);
        }
    }

    /**
     * Create inital DPS request and process response.
     *
     * If successful, this will redirect to the DPS PxPay payments page.
     */
    function _processPxPay() {
    global $order, $messageStack;

        // customerId
        $customerId = $_SESSION['customer_id'];

        // merchant reference
        $merchantRef = $customerId."-".date("YmdHis");
        // unique id
        $txnId = uniqid(rand(0, 999), false);

        $this->_createTrackingEntry($txnId, $merchantRef);

        // custom data
        $customer = $order->customer;
        $txnData1 = '' == $customer['company'] ? ($customer['firstname'].' '.$customer['lastname']) : $customer['company'];
        $txnData2 = $customer['telephone'];
        $txnData3 = '';

        $generateRequest = $this->_valueXml(array(
            'PxPayUserId' => MODULE_PAYMENT_DPS_PXPAY_USERID,
            'PxPayKey' => MODULE_PAYMENT_DPS_PXPAY_KEY,
            'AmountInput' => number_format($order->info['total'], 2, '.', ''),
            'CurrencyInput' => $order->info['currency'],
            'MerchantReference' => $merchantRef,
            'TxnData1' => $txnData1,
            'TxnData2' => $txnData2,
            'TxnData3' => $txnData3,
            'TxnType' => MODULE_PAYMENT_DPS_PXPAY_METHOD,
            'TxnId' => $txnId,
            'UrlFail' => $this->_dpsResultRedirect,
            'UrlSuccess' => $this->_dpsResultRedirect
        ));
        $generateRequest = $this->_valueXml('GenerateRequest', $generateRequest);

        $this->log("generateRequest:\n".str_replace(MODULE_PAYMENT_DPS_PXPAY_USERID, 'userid', str_replace(MODULE_PAYMENT_DPS_PXPAY_KEY, 'key', $generateRequest)));
        $curl = $this->_initCURL($generateRequest);
        $success = false;

        if ($response = curl_exec($curl)) {
            curl_close($curl);
            $this->log("response:\n".$response);
            $valid = $this->_xmlAttribute($response, 'valid');
            if (1 == $valid) {
                // redirect to DPS PxPay payments form
                $uri = $this->_xmlElement($response, 'URI');
                zen_redirect($uri);
            } else {
                // redisplay checkout
                $this->_emailNotify(MODULE_PAYMENT_DPS_PXPAY_TEXT_INVALID_PAYMENT_REQUEST, $response);
                $messageStack->add_session('checkout_payment', MODULE_PAYMENT_DPS_PXPAY_TEXT_NOT_AVAILABLE, 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            }
        } else {
            // calling DPS failed
            $this->log("response:\n".curl_error());
            $this->_emailNotify(MODULE_PAYMENT_DPS_PXPAY_TEXT_CANT_CONNECT, $generateRequest);
            $messageStack->add_session('checkout_payment', MODULE_PAYMENT_DPS_PXPAY_TEXT_NOT_AVAILABLE, 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
    }


    /**
     * Process the DPS response that is included in the DPS redirect back to zen-cart.
     */
    function _processPxPayResponse() {
    global $order, $messageStack;

        $processResponse = $this->_valueXml(array(
            'PxPayUserId' => MODULE_PAYMENT_DPS_PXPAY_USERID,
            'PxPayKey' => MODULE_PAYMENT_DPS_PXPAY_KEY,
            'Response' => $_GET['result']
        ));
        $processResponse = $this->_valueXml('ProcessResponse', $processResponse);

        $this->log("processResponse:\n".$processResponse);
        $curl = $this->_initCURL($processResponse);
        $success = false;
        if ($response = curl_exec($curl)) {
            curl_close($curl);
            $this->log("response:\n".$response);
            $valid = $this->_xmlAttribute($response, 'valid');
            $success = $this->_xmlElement($response, 'Success');
            $txnId = $this->_xmlElement($response, 'TxnId');
            $responseText = $this->_xmlElement($response, 'ResponseText');
            $authCode = $this->_xmlElement($response, 'AuthCode');
            $txnRef = $this->_xmlElement($response, 'DpsTxnRef');

            $tracking = $this->_findTrackingEntry($txnId);
            if (null == $tracking) {
                $this->_emailNotify(MODULE_PAYMENT_DPS_PXPAY_TEXT_INVALID_STATE, $response);
                $messageStack->add_session('checkout_confirmation', MODULE_PAYMENT_DPS_PXPAY_TEXT_INVALID_STATE, 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL', true, false));
            } else {
                $this->_updateTrackingEntry($txnId, null, $success, $responseText, $authCode, $txnRef);
            }

            if (1 != $valid || 1 != $success) {
                // redisplay checkout
                $this->_emailNotify(MODULE_PAYMENT_DPS_PXPAY_TEXT_PAYMENT_NOT_VALID, $response);
                $messageStack->add_session('checkout_payment', MODULE_PAYMENT_DPS_PXPAY_TEXT_DECLINED, 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            }

            // remember for update of orderId
            $this->_txnId = $txnId;
        } else {
            // calling DPS failed
            $this->log("response:\n".curl_error());
            $this->_emailNotify(MODULE_PAYMENT_DPS_PXPAY_TEXT_CANT_CONNECT, $processResponse);
            $messageStack->add_session('checkout_payment', MODULE_PAYMENT_DPS_PXPAY_TEXT_NOT_AVAILABLE, 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }

        if (null == $tracking) {
            $this->_emailNotify(MODULE_PAYMENT_DPS_PXPAY_TEXT_INVALID_STATE, $response);
            $messageStack->add_session('checkout_confirmation', MODULE_PAYMENT_DPS_PXPAY_TEXT_INVALID_STATE, 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL', true, false));
        }

        // all green
        $this->_emailNotify(MODULE_PAYMENT_DPS_PXPAY_TEXT_PAYMENT, $response);
        $order->info['cc_type'] = $this->_xmlElement($response, 'CardName');
        $order->info['cc_owner'] = $this->_xmlElement($response, 'CardHolderName');
    }


    /**
     * Validate order.
     */
    function _validatePayment() {
    global $messageStack;

        // this is also needed later to update the orderId
        $this->_txnId = $_GET['txnId'];
        $entry = $this->_findTrackingEntry($this->_txnId);
        if (null == $entry || '1' != $entry['success']) {
            $this->log('Payment failed: ' . $this->_txnId);
            $this->_emailNotify(MODULE_PAYMENT_DPS_PXPAY_TEXT_PAYMENT_NOT_VALID, 'Payment failed: ' . $this->_txnId);
            $messageStack->add_session('checkout_payment', MODULE_PAYMENT_DPS_PXPAY_TEXT_DECLINED, 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
        // payment ok
    }


    /**
     * Get the current txnId.
     *
     * @return string The txnId or <code>null</code>.
     */
    function getTxnId() {
        return $this->_txnId;
    }


    /**
     * If configured, create email notification.
     *
     */
    function _emailNotify($subject, $text) {
        if (zen_validate_email(MODULE_PAYMENT_DPS_PXPAY_EMAIL)) {
            zen_mail('', MODULE_PAYMENT_DPS_PXPAY_EMAIL, $subject, $text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }        
    }


    /**
     * Create new database tracking entry.
     * 
     * @param string txnId The transaction id.
     * @param string merchantRef Merchant reference.
     */
    function _createTrackingEntry($txnId, $merchantRef) {
    global $db;

        $sql = "insert into " . TABLE_DPS_PXPAY . " (txn_id, txn_type, merchant_ref)
                values (:txnId, :txnType, :merchantRef)";
        $sql = $db->bindVars($sql, ':txnId', $txnId, 'string');
        $sql = $db->bindVars($sql, ':txnType', MODULE_PAYMENT_DPS_PXPAY_METHOD, 'string');
        $sql = $db->bindVars($sql, ':merchantRef', $merchantRef, 'string');

        $results = $db->Execute($sql);
    }


    /**
     * Find database tracking entry.
     * 
     * @param string txnId The transaction id.
     * @return array Array of tracking values or <code>null</code>.
     */
    function _findTrackingEntry($txnId) {
    global $db;

        $sql = "select * from " . TABLE_DPS_PXPAY . " 
                where txn_id = :txnId";
        $sql = $db->bindVars($sql, ':txnId', $txnId, 'string');

        $results = $db->Execute($sql);
        if (1 == $results->RecordCount()) {
            return $results->fields;
        }

        return null;
    }


    /**
     * Update database tracking entry.
     * 
     * @param string txnId The transaction id.
     * @param int orderId The order id.
     * @param string success The payment success flag (1 = approved)
     * @param string responseText The payments response text.
     * @param string authCode The returned auth code.
     * @param string txnRef The DPS transaction reference.
     */
    function _updateTrackingEntry($txnId, $orderId=null, $success=null, $responseText=null, $authCode=null, $txnRef=null) {
    global $db;

        $where = "txn_id = :txnId";
        $where = $db->bindVars($where, ':txnId', $txnId, 'string');
        $data = array();
        if (null !== $orderId) { $data['order_id'] = $orderId; }
        if (null !== $success) { $data['success'] = $success; }
        if (null !== $responseText) { $data['response_text'] = $responseText; }
        if (null !== $authCode) { $data['auth_code'] = $authCode; }
        if (null !== $txnRef) { $data['txn_ref'] = $txnRef; }
        if (0 < count($data)) {
            zen_db_perform(TABLE_DPS_PXPAY, $data, 'update', $where);
        }
    }


    /**
     * Called after the all checkout processing is complete, but before the
     * redirect to the confirmation page.
     */
    function after_process() {
    }


    /**
     * Called after the order is created.
     *
     * @param int orderId The order id of the new order.
     */
    function after_order_create($orderId) {
        if (isset($this->_txnId) && null != $this->_txnId) {
            $this->_updateTrackingEntry($this->_txnId, $orderId);
        }
    }


    /**
     * Access method for errors in this module.
     *
     * Errors are usually the result of failed server side validations in
     * <code>pre_confirmation_check()</code>.
     *
     * @return array Error information.
     */
    function get_error() {
        return array();
    }


    /**
     * Check if this module is enabled or not.
     *
     * Admin function.
     *
     * @return bool <code>true</code> if this module is enabled, <code>false</code> if not.
     */
    function check() {
    global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . "
                                         where configuration_key = 'MODULE_PAYMENT_DPS_PXPAY_STATUS'");
            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }


    /**
     * Install this module.
     *
     * Admin function.
     *
     * Typically inserts this modules configuration settings into the database.
     */
    function install() {
    global $db;

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable DPS PxPay Module', 'MODULE_PAYMENT_DPS_PXPAY_STATUS', 'True', 'Do you want to accept DPS PxPay payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable tracing', 'MODULE_PAYMENT_DPS_PXPAY_TRACE', 'False', 'Do you want to trace all DPS communication in the webserver logfile??', '6', '5', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('User Id', 'MODULE_PAYMENT_DPS_PXPAY_USERID', '', 'The DPS PxPay account userId.', '6', '10', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Account Key', 'MODULE_PAYMENT_DPS_PXPAY_KEY', '', 'The DPS PxPay account key.', '6', '15', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Method', 'MODULE_PAYMENT_DPS_PXPAY_METHOD', 'Purchase', 'Transaction method used for processing orders.', '6', '20', 'zen_cfg_select_option(array(\'Purchase\', \'Auth\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Email Logging', 'MODULE_PAYMENT_DPS_PXPAY_EMAIL', '', 'Enter an email address here if you would like to log all DPS transactions by email.', '6', '25', '', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display DPS Logo', 'MODULE_PAYMENT_DPS_PXPAY_SHOW_LOGO', 'True', 'Do you want to display the DPS logo and message?', '6', '25', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Display sort order', 'MODULE_PAYMENT_DPS_PXPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '30', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_DPS_PXPAY_ZONE', '0', 'If a zone is selected, allow this payment method for that zone only.', '6', '35', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Order Status', 'MODULE_PAYMENT_DPS_PXPAY_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value.', '6', '40', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    }


    /**
     * Remove this module and all associated configuration values/files etc.
     *
     * Admin function.
     */
    function remove() {
    global $db;

        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }


    /**
     * Returns the configuration keys used by this module.
     *
     * Admin function.
     *
     * @return array List of configuration keys used by this module.
     */
    function keys() {
    global $db;

        $results = $db->Execute("select configuration_key from " . TABLE_CONFIGURATION . " 
                                 where configuration_key like 'MODULE_PAYMENT_DPS_PXPAY_%' " . "
                                 order by sort_order");
        $keys = array();
        while (!$results->EOF) {
            array_push($keys, $results->fields['configuration_key']);
            $results->MoveNext();
        }

        return $keys;
    }
    


    /**
     * Set up CURL.
     *
     * @param string query The query parameter.
     * @return mixed A curl handle.
     */
    function &_initCURL($query) {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->_dpsPxpayUrl);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
        curl_setopt($curl, CURLOPT_POSTFIELDSIZE, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_SSLVERSION, 3);

        if (strtoupper(substr(@php_uname('s'), 0, 3)) === 'WIN') {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        return $curl;
    }


    /**
     * Create XML value element.
     *
     * @param mixed element Either element name (then value is expected), or a hash of 'element' => 'value'.
     * @param string value The value.
     * @return string The formatted XML.
     */
    function _valueXml($element, $value=null) {
        if (is_array($element)) {
            $xml = '';
            foreach ($element as $elem => $value) {
               $xml .= $this->_valueXml($elem, $value); 
            }
            return $xml;
        }
        return "<".$element.">".$value."</".$element.">".$this->_nl;
    }


    /**
     * Find element value in XML fragment.
     *
     * @param string xml A XML fragment.
     * @param string name The element name.
     * @return string The element value or <code>null</code>.
     */
    function _xmlElement($xml, $name) {
        $value = preg_replace('/.*<'.$name.'[^>]*>(.*)<\/'.$name.'>.*/', '\1', $xml);
        return $value;
    }


    /**
     * Find attribute value in XML fragment.
     *
     * @param string xml A XML fragment.
     * @param string name The attribute name.
     * @return string The attribute value or <code>null</code>.
     */
    function _xmlAttribute($xml, $name) {
        $value = preg_replace('/<.*'.$name.'="([^"]*)".*>/', '\1', $xml);
        return $value != $xml ? $value : null;
    }

}
  
?>
