<?php
namespace Utrust\Payment\Controller\Payment;

use Utrust\Payment\Model\Payment\Utrust;

class Redirect extends \Magento\Framework\App\Action\Action
{
    protected $checkoutSession;

    protected $api;

    protected $cookieManager;
    private $cookieMetadataFactory;

    /**
     * @var Utrust\Payment\Logger\Logger
     */
    protected $logger;

    protected $helper;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Utrust\Payment\Model\Api $api,
        \Utrust\Payment\Logger\Logger $logger,
        \Utrust\Payment\Helper\Data $helper,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->checkoutSession       = $checkoutSession;
        $this->api                   = $api;
        $this->logger                = $logger;
        $this->helper                = $helper;
        $this->cookieManager         = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->clearLegacyCookies();
        $flow = $this->helper->getConfig('payment/utrust/checkout_flow/flow');
        if ($flow) {
            $quote = $this->checkoutSession->getQuote();
            if ($quote && $quote->getId()) {
                $result = $this->api->pay($quote);
                if ($this->isRedirectResult($result)) {
                    $payment = $quote->getPayment();
                    $payment->setUtrustPaymentId($result['data']['id']);
                    $payment->save();
                    $this->checkoutSession->setData(Utrust::SESSION_PAYMENT_ID, (string) $result['data']['id']);
                    $this->checkoutSession->setData(Utrust::SESSION_QUOTE_ID, (string) $quote->getId());
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setUrl($result['data']['attributes']['redirect_url']);
                    return $resultRedirect;
                }
                $this->logger->info(json_encode($result));
                $this->messageManager->addErrorMessage($this->failureMessage($result));
                return $this->_redirect('checkout/cart');
            }
        } else {
            $order = $this->checkoutSession->getLastRealOrder();
            if ($order && $order->getId()) {
                try {
                    $result = $this->api->pay($order);
                } catch (\Throwable $exception) {
                    $this->logger->info($exception->getMessage());
                    $this->messageManager->addErrorMessage(
                        __('Unable to start the crypto payment. Please try again or choose another payment method.')
                    );
                    return $this->_redirect('checkout/cart');
                }
                if ($this->isRedirectResult($result)) {
                    $payment = $order->getPayment();
                    $payment->setUtrustPaymentId($result['data']['id']);
                    $payment->save();
                    $this->checkoutSession->setData(Utrust::SESSION_PAYMENT_ID, (string) $result['data']['id']);
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setUrl($result['data']['attributes']['redirect_url']);
                    return $resultRedirect;
                }
                $this->logger->info(json_encode($result));
                if ($order->getId()) {
                    $this->checkoutSession->restoreQuote();
                    try {
                        $order->cancel();
                        $order->addStatusToHistory(
                            $order->getStatus(),
                            'xMoney could not start the payment.'
                        );
                        $order->save();
                    } catch (\Exception $exception) {
                        $this->logger->info(
                            'Could not cancel order ' . $order->getIncrementId()
                        );
                    }
                }
                $this->messageManager->addErrorMessage($this->failureMessage($result));
                return $this->_redirect('checkout/cart');
            }
        }

        $this->messageManager->addErrorMessage(
            __('Unable to start the crypto payment. Please try again or choose another payment method.')
        );
        return $this->_redirect('checkout/cart');
    }

    /**
     * @param mixed $result
     * @return bool
     */
    private function isRedirectResult($result)
    {
        return is_array($result)
            && isset($result['data']['type'])
            && $result['data']['type'] === 'orders_redirect'
            && isset($result['data']['attributes']['redirect_url']);
    }

    /**
     * Drop quote and payment ids left by older releases.
     *
     * @return void
     */
    private function clearLegacyCookies()
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
        $metadata->setPath('/');
        $this->cookieManager->deleteCookie('utrust_payment_id', $metadata);
        $this->cookieManager->deleteCookie('quote_id', $metadata);
    }

    /**
     * @param mixed $result
     * @return \Magento\Framework\Phrase
     */
    private function failureMessage($result)
    {
        $detail = is_array($result) ? ($result['errors'][0]['detail'] ?? null) : null;
        $encoded = is_array($detail) ? json_encode($detail) : '';
        if ($encoded !== '' && strpos($encoded, 'country') !== false && strpos($encoded, 'invalid') !== false) {
            return __(
                'This billing country is not supported for crypto payment. Use a different billing address or another payment method.'
            );
        }

        return __('Unable to start the crypto payment. Please try again or choose another payment method.');
    }
}
