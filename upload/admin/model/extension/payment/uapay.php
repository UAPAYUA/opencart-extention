<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 12.03.2019
 * Time: 11:40
 */

use Cardinity\Client;
use Cardinity\Method\Payment;
use Cardinity\Method\Refund;

class ModelExtensionPaymentUapay extends Model
{
    public function install()
    {
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD uapay_payment TEXT");
    }

    public function uninstall()
    {
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` DROP uapay_payment");
    }
}