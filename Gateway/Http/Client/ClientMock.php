<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bananacode\FourGeeks\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

/**
 * Class ClientMock
 * @package Bananacode\FourGeeks\Gateway\Http\Client
 */
class ClientMock implements ClientInterface
{
    /**
     * FourGeeks Success Code
     */
    const SUCCESS = 200;

    /**
     * FourGeeks Production URLS
     */
    const FOUR_GEEKS_URL = 'https://api.payments.4geeks.io/';

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    private $_curl;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $_storeManager;

    /**
     * @var \Bananacode\FourGeeks\Helper\Encryption
     */
    private $_bananaCryptor;

    /**
     * @var \Bananacode\Base\Helper\Notify
     */
    protected $_bananaNotify;

    /**
     * @var \Magento\Directory\Block\Currency
     */
    private $_currency;

    /**
     * @var \Bananacode\Base\Helper\CurrencyExchange
     */
    private $_currencyExchange;

    /**
     * ClientMock constructor.
     * @param Logger $logger
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Bananacode\FourGeeks\Helper\Encryption $bananaCryptor
     * @param \Bananacode\Base\Helper\Notify $bananaNotify
     * @param \Magento\Directory\Block\Currency $currency
     * @param \Bananacode\Base\Helper\CurrencyExchange $currencyExchange
     */
    public function __construct(
        Logger $logger,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Bananacode\FourGeeks\Helper\Encryption $bananaCryptor,
        \Bananacode\Base\Helper\Notify $bananaNotify,
        \Magento\Directory\Block\Currency $currency,
        \Bananacode\Base\Helper\CurrencyExchange $currencyExchange
    ) {
        $this->logger = $logger;
        $this->_curl = $curl;
        $this->_storeManager = $storeManager;
        $this->_bananaCryptor = $bananaCryptor;
        $this->_bananaNotify = $bananaNotify;
        $this->_currency = $currency;
        $this->_currencyExchange = $currencyExchange;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array|bool|string
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $requestData = $transferObject->getBody();

        /**
         * Get payment order
         */
        $response = false;
        if ($authorize = $this->getAuthorizeOrder($requestData)) {
            $response = $this->placeOrder($requestData, $authorize);
        }

        if (is_array($response)) {
            if (array_key_exists('error', $response)) {
                $this->_bananaNotify
                    ->discord('sales', 'Intento fallido de pago por un monto de ' . $requestData['amount'] . ' colones | https://gph.is/1lmtBdO', 'ERROR de Pago');
            }
        }

        return $response;
    }

    /**
     * @param $requestData
     * @return array|bool|string
     */
    private function getAuthorizeOrder($requestData)
    {
        $parameters = [
            'grant_type' => ($requestData['grant_type']) ?? '',
            'client_id' => ($requestData['client_id']) ?? '',
            'client_secret' => ($requestData['client_secret']) ?? '',
        ];

        $this->_curl->post(self::FOUR_GEEKS_URL . 'authentication/token/', json_encode($parameters));
        if ($response  = $this->_curl->getBody()) {
            $response = (array)json_decode($response);
            if (is_array($response)) {
                if (isset($response['access_token']) & isset($response['token_type'])) {
                    return $response;
                } else {
                    $this->logger->debug($response);
                }
            }
        }

        return false;
    }

    /**
     * @param $requestData
     * @param $authorize
     * @return array|string
     */
    private function placeOrder($requestData, $authorize)
    {
        $parameters = (array)json_decode($this->_bananaCryptor->decrypt($requestData['payment_method_nonce'], $requestData['nonce']));
        $parameters['currency'] =  ($requestData['currency']) ?? '';
        $parameters['entity_description'] =  ($requestData['entity_description']) ?? '';
        $parameters['description'] =  ($requestData['description']) ?? '';
        $parameters['amount'] =  ($requestData['amount']) ?? '';
        $headers = [
            "Content-Type" => "application/json",
            "Authorization" => $authorize['token_type'] . " " . $authorize['access_token']
        ];

        $currentCurrencyCode = $this->_currency->getCurrentCurrencyCode();
        if ($currentCurrencyCode != $parameters['currency']) {
            $venta = $this->_currencyExchange->colonToDollarCompra();
            $parameters['currency'] = $currentCurrencyCode;
            $parameters['amount'] = $parameters['amount']/$venta;
        }

        $this->_curl->setHeaders($headers);
        $this->_curl->post(self::FOUR_GEEKS_URL . 'v1/charges/simple/create/', json_encode($parameters));
        if ($response = $this->_curl->getBody()) {
            $response = (array)json_decode($response);
            $this->logger->debug($response);
        }

        return $response;
    }
}
