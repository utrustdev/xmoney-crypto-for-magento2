<?php
namespace Utrust\Payment\Controller\Payment;

class Cancel extends \Magento\Framework\App\Action\Action
{
    protected $checkoutSession;
    protected $cart;
    protected $helper;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Utrust\Payment\Helper\Data $helper,
        \Magento\Checkout\Model\Cart $cart
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cart = $cart;
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $flow=$this->helper->getConfig('payment/utrust/checkout_flow/flow');
        if($flow){
            $this->_redirect('checkout/cart');
        }else{
            $order = $this->checkoutSession->getLastRealOrder();
            if ($order) {
            $order->cancel()->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
            $order->addStatusToHistory($order->getStatus(), 'xMoney payment was canceled by the shopper.');
            $order->save();
            $items = $order->getItemsCollection();
                foreach ($items as $item) {
                    try {
                        $this->cart->addOrderItem($item);
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                $this->cart->save();
            }
            $this->messageManager->addNoticeMessage(
                __('The crypto payment was canceled. Your cart has been restored.')
            );
            $this->_redirect('checkout/cart');
        }
    }
}
