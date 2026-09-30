<?php
namespace Utrust\Payment\Controller\Payment;

use Magento\Quote\Model\QuoteFactory;
use Utrust\Payment\Model\Payment\Utrust;

class Response extends \Magento\Framework\App\Action\Action
{
    const ORDER_LOOKUP_ATTEMPTS = 6;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Utrust\Payment\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Quote\Api\GuestCartManagementInterface
     */
    protected $guestCart;

    /**
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterfaceFactory
     */
    protected $orderFactory;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Utrust\Payment\Helper\Data $helper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param QuoteFactory $quoteFactory
     * @param \Magento\Quote\Api\GuestCartManagementInterface $guestCart
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Utrust\Payment\Helper\Data $helper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        QuoteFactory $quoteFactory,
        \Magento\Quote\Api\GuestCartManagementInterface $guestCart
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->guestCart = $guestCart;
        $this->orderFactory = $orderFactory;
        $this->quoteFactory = $quoteFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $this->clearLegacyCookies();
        $flow = $this->helper->getConfig('payment/utrust/checkout_flow/flow');
        if ($flow) {
            return $this->respondToAlternativeFlow();
        }

        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getPayment() && $order->getPayment()->getMethod() === 'utrust') {
            return $this->_redirect('checkout/onepage/success');
        }

        return $this->_redirect('/');
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    private function respondToAlternativeFlow()
    {
        $quoteId = (string) $this->checkoutSession->getData(Utrust::SESSION_QUOTE_ID);
        $order = $quoteId === '' ? null : $this->waitForOrder($quoteId);
        if (!$order) {
            $this->messageManager->addNoticeMessage(
                __('Your crypto payment is still confirming. The order will appear once the payment notification arrives.')
            );
            return $this->_redirect('checkout/cart');
        }

        try {
            $this->guestCart->createEmptyCart();
        } catch (\Exception $exception) {
            // The order is already placed. A cart reset must not block the success page.
        }
        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getEntityId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());
        $this->checkoutSession->unsetData(Utrust::SESSION_QUOTE_ID);

        return $this->_redirect('checkout/onepage/success');
    }

    /**
     * @param string $quoteId
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    private function waitForOrder($quoteId)
    {
        for ($attempt = 0; $attempt < self::ORDER_LOOKUP_ATTEMPTS; $attempt++) {
            $order = $this->loadOrderForQuote($quoteId);
            if ($order || $attempt === self::ORDER_LOOKUP_ATTEMPTS - 1) {
                return $order;
            }
            sleep(1);
        }

        return null;
    }

    /**
     * @param string $quoteId
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    private function loadOrderForQuote($quoteId)
    {
        $quote = $this->quoteFactory->create()->load($quoteId);
        if (!$quote->getId()) {
            return null;
        }
        $incrementId = (string) $quote->getReservedOrderId();
        if ($incrementId === '') {
            return null;
        }
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);

        return $order->getId() ? $order : null;
    }

    /**
     * @return void
     */
    private function clearLegacyCookies()
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
        $metadata->setPath('/');
        $this->cookieManager->deleteCookie('utrust_payment_id', $metadata);
        $this->cookieManager->deleteCookie('quote_id', $metadata);
    }
}
