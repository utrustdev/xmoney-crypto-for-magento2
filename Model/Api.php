<?php

declare(strict_types=1);

namespace Utrust\Payment\Model;

use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Utrust\Payment\Gateway\Config;
use Utrust\Payment\Helper\Data;

class Api
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * Api constructor.
     * @param Data $helper
     * @param SerializerInterface $serializer
     * @param Config $config
     * @param ClientInterface $client
     */
    public function __construct(
        Data $helper,
        SerializerInterface $serializer,
        Config $config,
        ClientInterface $client
    ) {
        $this->helper = $helper;
        $this->serializer = $serializer;
        $this->config = $config;
        $this->client = $client;
    }

    /**
     * @param $order
     * @return array|bool|float|int|string|null
     */
    public function pay($order)
    {   
        $flow=$this->helper->getConfig('payment/utrust/checkout_flow/flow');    
        if($flow){  
            $orderData = $this->helper->getQuoteData($order);   
        }else{  
            $orderData = $this->helper->getOrderData($order);   
        }
        $data = $this->serializer->serialize($orderData);

        try {
            $response = $this->request($data);
            if ($response === '' || $response === null) {
                return [
                    'errors' => [
                        ['detail' => 'Empty response from payment gateway'],
                    ],
                ];
            }

            return $this->serializer->unserialize($response);
        } catch (\Exception $exception) {
            return [
                'errors' => [
                    ['detail' => $exception->getMessage()],
                ],
            ];
        }
    }

    /**
     * @param string $data
     * @return string
     */
    private function request($data)
    {
        $this->client->addHeader('Authorization', $this->getAuthorization());
        $this->client->addHeader('Content-Type', 'application/json');
        $this->client->addHeader('Content-Length', strlen($data));
        $this->client->post($this->getOrdersUrl(), $data);

        $response = $this->client->getBody();

        return $response;
    }

    /**
     * @return string
     */
    private function getAuthorization(): string
    {
        return 'Bearer ' . $this->config->getApiKey();
    }

    /**
     * @return string
     */
    private function getOrdersUrl(): string
    {
        return $this->config->getApiUrl() . '/stores/orders';
    }
}
