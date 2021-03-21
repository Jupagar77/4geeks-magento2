<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Bananacode\FourGeeks\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Gateway\Config\Config;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'fourgeeks';

    const CLIENT_ID = 'client_id';

    const SANDBOX_CLIENT_ID = 'sandbox_client_id';

    /**
     * @var \Magento\Payment\Gateway\ConfigInterface
     */
    protected $config;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * ConfigProvider constructor.
     * @param Config $config
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        Config $config,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->_encryptor = $encryptor;
        $this->config = $config;
        $this->config->setMethodCode(self::CODE);
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $this->config->setMethodCode(self::CODE);
        $sandbox = $this->config->getValue(
            'sandbox'
        );

        $nonce = str_split( $this->config->getValue(
            $sandbox ? self::SANDBOX_CLIENT_ID : self::CLIENT_ID
        ),3);

        return [
            'payment' => [
                self::CODE => [
                    'nonce' => $nonce[0] . $nonce[1] . $nonce[2],
                    'sandbox' => $sandbox
                ]
            ]
        ];
    }
}
