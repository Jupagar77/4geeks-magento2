<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Bananacode\FourGeeks\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class CaptureRequest
 * @package Bananacode\FourGeeks\Gateway\Request
 */
class CaptureRequest implements BuilderInterface
{
    use Formatter;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * CaptureRequest constructor.
     * @param ConfigInterface $config
     * @param SubjectReader $subjectReader
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        ConfigInterface $config,
        SubjectReader $subjectReader,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->config = $config;
        $this->subjectReader = $subjectReader;
        $this->_encryptor = $encryptor;
    }

    /**
     * Builds required request data
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof OrderPaymentInterface) {
            throw new \LogicException('Order payment should be provided.');
        }

        $sandbox = $this->config->getValue(
            'sandbox',
            $order->getStoreId()
        );

        $nonce = str_split($this->config->getValue(
            $sandbox ? 'sandbox_client_id' : 'client_id',
            $order->getStoreId()
        ), 3);

        return [
            'currency' => $order->getCurrencyCode(),
            'amount' => $this->formatPrice($this->subjectReader->readAmount($buildSubject)),
            'payment_method_nonce' => $payment->getAdditionalInformation('payment_method_nonce'),
            'entity_description' => substr($this->config->getValue('transaction_description'), 0, 10) . ' #' . $order->getOrderIncrementId(),
            'description' => $this->config->getValue('transaction_description') . ' #' . $order->getOrderIncrementId(),
            'nonce' => $nonce[0] . $nonce[1] . $nonce[2],
            'client_id' => $this->config->getValue(
                $sandbox ? 'sandbox_client_id' : 'client_id',
                $order->getStoreId()
            ),
            'client_secret' => $this->_encryptor->decrypt($this->config->getValue(
                $sandbox ? 'sandbox_client_secret' : 'client_secret',
                $order->getStoreId()
            )),
            'sandbox' => $sandbox,
            'grant_type' => 'client_credentials'
        ];
    }
}
