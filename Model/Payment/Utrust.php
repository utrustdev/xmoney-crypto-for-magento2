<?php

namespace Utrust\Payment\Model\Payment;

class Utrust extends \Magento\Payment\Model\Method\AbstractMethod
{
    const SESSION_QUOTE_ID = 'xmoney_quote_id';

    const SESSION_PAYMENT_ID = 'xmoney_payment_id';

    protected $_code = "utrust";
    protected $_isOffline = true;

    protected $_infoBlockType = \Utrust\Payment\Block\Info::class;
}
