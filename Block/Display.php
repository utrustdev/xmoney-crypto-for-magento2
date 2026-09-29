<?php
namespace Utrust\Payment\Block;

use Utrust\Payment\Model\Payment\Utrust;

class Display extends \Magento\Framework\View\Element\Template
{
    public $_template = 'Utrust_Payment::display.phtml';

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return string|null
     */
    public function getUtrustPaymentIdValue()
    {
        $paymentId = $this->checkoutSession->getData(Utrust::SESSION_PAYMENT_ID);

        return $paymentId === null ? null : (string) $paymentId;
    }
}
