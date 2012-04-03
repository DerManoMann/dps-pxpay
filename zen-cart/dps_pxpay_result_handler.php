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


/*
 * Check for DPS PxPay type request parameter
 */
if (isset($_GET['result'])) {
    // zen-cart dies silently without this line!
    define('GZIP_LEVEL', false);

    // assume this is an DPS PxPay callback or redirect
    require('includes/application_top.php');
    // set language things
    $language_page_directory = DIR_WS_LANGUAGES . $_SESSION['language'] . '/';
    require(DIR_WS_CLASSES . 'payment.php');
    // this will load everything we need...
    $payment_modules = new payment($_SESSION['payment']);

    // create own instance
    $dpspxpay = new dps_pxpay();
    $dpspxpay->log("dps_pxpay_result_handler called: " . serialize($_GET));
    // looks like a funny name here, but it's one of the zen-cart API methods
    // this will actually delegate to _processPxPayResponse()
    $dpspxpay->before_process();

    // complete checkout; this will create the order, do some validation and
    // then display the confirmation page
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PROCESS, 'dps_done=true&txnId='.$dpspxpay->getTxnId(), 'SSL'));
}
?>
