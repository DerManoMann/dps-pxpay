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
 * $Id: dps_pxpay.php,v 1.2 2007/08/09 09:18:33 radebatz Exp $
 */
?>
<?php  

    // admin
    define('MODULE_PAYMENT_DPS_PXPAY_TEXT_TITLE', 'DPS PxPay Credit Card');
    define('MODULE_PAYMENT_DPS_PXPAY_TEXT_DESCRIPTION', 'DPS PxPay Method for Credit Card Processing - mixedmatter Ltd. ver. 1.0.1');

    // error messages
    define('MODULE_PAYMENT_DPS_PXPAY_TEXT_NOT_AVAILABLE', 'The selected payment type is currently not available.');
    define('MODULE_PAYMENT_DPS_PXPAY_TEXT_INVALID_STATE', 'Payment could not be completed - reference not found.');
    define('MODULE_PAYMENT_DPS_PXPAY_TEXT_DECLINED', 'Payment declined.');

    // logo and message
    define('MODULE_PAYMENT_DPS_PXPAY_LOGO', '<br><a href="http://www.paymentexpress.com/" target="_blank"><img src="http://www.paymentexpress.com/images/logos_white/paymentexpress.gif" alt="Payment Processor" width="276" height="42" /></a>');
    define('MODULE_PAYMENT_DPS_PXPAY_LOGO_TEXT', '<br>Real-time 128Bit SSL Secure Credit Card Transaction processing via Direct Payment Solutions (DPS) NZ.<br><br><a href="http://www.paymentexpress.com/PrivacyPolicy.htm" target="_blank">Click here to read DPS\'s Privacy Policy</a>');

    // email notification subjects
    define('MODULE_PAYMENT_DPS_PXPAY_TEXT_INVALID_GENERATE_REQUEST', 'DPS PxPay payment request failed - could not forward to payment form');
    define('MODULE_PAYMENT_DPS_PXPAY_TEXT_CANT_CONNECT', 'Could not connect to DPS PxPay');
    define('MODULE_PAYMENT_DPS_PXPAY_TEXT_PAYMENT_NOT_VALID', 'DPS PxPay payment not valid');
    define('MODULE_PAYMENT_DPS_PXPAY_TEXT_PAYMENT', 'DPS PxPay payment successful');

?>
