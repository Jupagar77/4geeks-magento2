<?php
/**
 * Copyright © 2019 Bananacode SA, All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Bananacode\FourGeeks\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Bananacode\FourGeeks\Gateway\Http\Client\ClientMock;

/**
 * Class ResponseCodeValidator
 * @package Bananacode\FourGeeks\Gateway\Validator
 */
class ResponseCodeValidator extends AbstractValidator
{
    const RESULT_CODE = 'charge_id';

    /**
     * Performs validation of result code
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response']) || !is_array($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];
        if ($this->isSuccessfulTransaction($response)) {
            return $this->createResult(
                true,
                []
            );
        } else {
            $error = $this->extractError($response);
            return $this->createResult(
                false,
                [
                    $error
                ]
            );
        }
    }

    /**
     * @param array $response
     * @return bool
     */
    private function isSuccessfulTransaction(array $response)
    {
        return isset($response[self::RESULT_CODE]);
    }

    /**
     * @param array $response
     * @return \Magento\Framework\Phrase|mixed
     */
    private function extractError(array $response)
    {
        if (isset($response['error'])) {
            $response['error'] = (array)$response['error'];
            if (isset($response['error']['es'])) {
                return $response['error']['es'];
            }
        }
        return __('Transaction has been declined. Please try again later.');
    }
}
