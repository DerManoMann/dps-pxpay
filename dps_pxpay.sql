#################################################
#
# tracking table for DPS PxPay transactions
#
#################################################
CREATE TABLE dps_pxpay (
  dps_pxpay_id int(11) NOT NULL auto_increment,

  txn_id varchar(16) NOT NULL,
  txn_type varchar(16) NOT NULL,
  merchant_ref varchar(64) NOT NULL default '',
  order_id int(11) NOT NULL default 0,

  success tinyint(1) NOT NULL default 0,
  response_text varchar(32) NOT NULL default '',
  auth_code varchar(22) NOT NULL default '',
  txn_ref varchar(16) NOT NULL default '',

  PRIMARY KEY (dps_pxpay_id),
  KEY idx_dpx_pxpay_txn_id (txn_id)
);


#################################################
#
# extend to fit in long DPS redirect URLs
#
#################################################
ALTER TABLE whos_online MODIFY last_page_url varchar(2048) NOT NULL default '';
