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

    const ERROR_MESSAGES = array(
        "Your card was declined. Please review it with your Bank." => "01",
        "Card's expiration year is not valid." => "02",
        "Card's expiration month is not valid." => "03",
        "Card's security code is not incorrect." => "04",
        "Card's security code is not correct." => "05",
        "Card's security code is not valid." => "06",
        "Your card has expired. Please review it with your Bank." => "07",
    );

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
            $errors = $this->extractErrorObject($response);
            return $this->createResult(
                false,
                [
                    $errors[1]
                ],
                [
                    $errors[0]
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
    private function extractErrorObject(array $response)
    {
        if (isset($response['error'])) {
            $response['error'] = (array)$response['error'];
            if (isset($response['error']['en'])) {
                if(isset(self::ERROR_MESSAGES[$response['error']['en']])) {
                    return [
                        self::ERROR_MESSAGES[$response['error']['en']],
                        $response['error']['en']
                    ];
                } else {
                    return [
                        '01',
                        'Your card was declined. Please review it with your Bank.'
                    ];
                }
            }
        }
        return [
            '01',
            'Your card was declined. Please review it with your Bank.'
        ];
    }
}
