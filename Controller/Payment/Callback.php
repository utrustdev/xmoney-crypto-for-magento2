<?php
namespace Utrust\Payment\Controller\Payment;

use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;

class Callback extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    const AUTHENTICATION_ERROR = 'Authentication error.';

    const PROCESSING_ERROR = 'Payment notification could not be processed.';

    /**
     * @var \Utrust\Payment\Helper\Data
     */
    protected $helper;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \Utrust\Payment\Logger\Logger
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterfaceFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Utrust\Payment\Helper\Data $helper
     * @param \Utrust\Payment\Logger\Logger $logger
     * @param \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param QuoteFactory $quoteFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Utrust\Payment\Helper\Data $helper,
        \Utrust\Payment\Logger\Logger $logger,
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        QuoteFactory $quoteFactory
    ) {
        parent::__construct($context);
        $this->helper = $helper;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * @return void
     */
    public function execute()
    {
        $response = '';
        $status = 200;

        try {
            $payload = json_decode($this->getRequest()->getContent(), true);
            if (!is_array($payload)) {
                $this->logger->info('Webhook payload is not valid JSON.');
                $response = self::AUTHENTICATION_ERROR;
                $status = 400;
            } elseif (!$this->signatureMatches($payload)) {
                $this->logger->info('Webhook signature verification failed.');
                $response = self::AUTHENTICATION_ERROR;
                $status = 400;
            } else {
                $this->handleEvent($payload);
            }
        } catch (\Exception $exception) {
            $this->logger->info($exception->getMessage());
            $response = self::PROCESSING_ERROR;
            $status = 500;
        }
        $this->getResponse()->setStatusCode($status)->setBody($response);
    }

    /**
     * @param array $payload
     * @return bool
     */
    private function signatureMatches(array $payload)
    {
        $provided = $payload['signature'] ?? '';
        $calculated = $this->helper->getPayloadSignature($payload);
        if (!is_string($provided) || $provided === '' || $calculated === '') {
            return false;
        }

        return hash_equals($calculated, $provided);
    }

    /**
     * @param array $payload
     * @return void
     */
    private function handleEvent(array $payload)
    {
        $alternativeFlow = (bool) $this->helper->getConfig('payment/utrust/checkout_flow/flow');
        $eventType = isset($payload['event_type']) ? (string) $payload['event_type'] : '';

        if ($eventType === 'ORDER.PAYMENT.DETECTED') {
            if ($alternativeFlow) {
                $this->resolveOrder($payload, true, true);
            }
            return;
        }

        if ($eventType === 'ORDER.PAYMENT.RECEIVED') {
            $order = $this->resolveOrder($payload, $alternativeFlow, $alternativeFlow);
            if (!$order) {
                $this->logger->info('Webhook payment received could not find the order.');
                throw new \RuntimeException('Order was not available for payment received.');
            }
            $this->invoiceOrder($order, $payload);
            return;
        }

        if ($eventType === 'ORDER.PAYMENT.CANCELLED') {
            $order = $this->resolveOrder($payload, $alternativeFlow, false);
            if (!$order) {
                if ($alternativeFlow) {
                    return;
                }
                $this->logger->info('Webhook cancel could not find the order.');
                throw new \RuntimeException('Order was not available for payment cancel.');
            }
            $this->cancelOrder($order);
            return;
        }

        $this->logger->info('Webhook event ignored.');
    }

    /**
     * Alternative flow stores the quote id in resource.reference.
     * Standard flow stores the order increment id.
     *
     * @param array $payload
     * @param bool $alternativeFlow
     * @param bool $createIfMissing
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    private function resolveOrder(array $payload, $alternativeFlow, $createIfMissing)
    {
        $reference = isset($payload['resource']['reference']) ? (string) $payload['resource']['reference'] : '';
        if ($reference === '') {
            return null;
        }

        if (!$alternativeFlow) {
            $order = $this->orderFactory->create()->loadByIncrementId($reference);
            return $order->getId() ? $order : null;
        }

        $quote = $this->quoteFactory->create()->load($reference);
        if (!$quote->getId()) {
            return null;
        }

        $incrementId = (string) $quote->getReservedOrderId();
        if ($incrementId !== '') {
            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            if ($order->getId()) {
                return $order;
            }
        }

        if (!$createIfMissing) {
            return null;
        }

        return $this->createOrderFromQuote($quote);
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    private function createOrderFromQuote($quote)
    {
        if ($quote->getData('customer_id') == null) {
            $quote->setCustomerId(null)
                ->setCustomerEmail($quote->getBillingAddress()->getEmail())
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);
        }

        $result = $this->helper->createOrder($quote);
        if (empty($result['success'])) {
            return null;
        }

        $order = $this->orderFactory->create()->loadByIncrementId($result['success']);

        return $order->getId() ? $order : null;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param array $payload
     * @return void
     */
    private function invoiceOrder($order, array $payload)
    {
        $payment = $order->getPayment();
        if (!$payment || $payment->getMethod() !== 'utrust' || !$order->canInvoice()) {
            return;
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register();
        $currency = isset($payload['resource']['currency']) ? (string) $payload['resource']['currency'] : '';
        $amount = isset($payload['resource']['amount']) ? (string) $payload['resource']['amount'] : '';
        $message = __(
            'xMoney payment received. Amount: %1 %2. Invoice %3 created.',
            $currency,
            $amount,
            $invoice->getIncrementId()
        );
        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        $order->addStatusToHistory($order->getStatus(), $message);
        $this->transaction->addObject($invoice)->addObject($order)->save();
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     */
    private function cancelOrder($order)
    {
        if ($order->getState() === Order::STATE_CANCELED) {
            return;
        }

        $order->cancel()->setState(Order::STATE_CANCELED);
        $order->addStatusToHistory($order->getStatus(), 'xMoney has canceled the payment (expired).');
        $order->save();
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Webhooks cannot send a Magento form key. The signature check is the authenticator.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
