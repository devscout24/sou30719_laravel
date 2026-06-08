<?php

namespace App\Services;

use Square\SquareClient;
use Square\Types\Money;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Payments\Requests\GetPaymentsRequest;
use Square\Payments\Requests\ListPaymentsRequest;
use Square\Payments\Requests\CancelPaymentsRequest;
use Square\Payments\Requests\CompletePaymentRequest;
use Square\Refunds\Requests\RefundPaymentRequest;
use Square\Exceptions\ApiException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Square\Locations\Requests\ListLocationsRequest;

class SquarePaymentService
{
    protected $client;
    protected $locationId;

public function __construct()
{
    $this->locationId = config('square.location_id');

    $this->client = new \Square\SquareClient(
        config('square.access_token'),
        config('square.environment', 'sandbox') 
    );

    \Log::info('Square Client Initialized', [
        'environment' => config('square.environment'),
        'location_id' => $this->locationId,
        'token_prefix' => substr(config('square.access_token'), 0, 15)
    ]);
}


    public function processPayment($sourceId, $amount, $currency = 'USD', $note = null)
    {
        try {
            Log::info('Process Payment Called', [
                'sourceId' => $sourceId,
                'amount' => $amount,
                'currency' => $currency
            ]);

            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Amount must be greater than zero'];
            }

            if (empty($sourceId)) {
                return ['success' => false, 'message' => 'Source ID is required'];
            }

            $amountMoney = new Money();
            $amountMoney->setAmount((int)($amount * 100));
            $amountMoney->setCurrency($currency);

            $requestData = [
                'sourceId' => $sourceId,
                'idempotencyKey' => Str::uuid()->toString(),
                'amountMoney' => $amountMoney,
                'autocomplete' => true,
                'locationId' => $this->locationId,
            ];

            if ($note) {
                $requestData['note'] = $note;
            }

            Log::info('Square Payment Request Data:', [
                'sourceId' => $sourceId,
                'amount' => (int)($amount * 100),
                'currency' => $currency,
                'locationId' => $this->locationId
            ]);

            $body = new CreatePaymentRequest($requestData);

            $apiResponse = $this->client->payments->create($body);

            if ($apiResponse->isSuccess()) {
                $payment = $apiResponse->getResult()->getPayment();

                Log::info('Square Payment Success', [
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'amount' => $amount
                ]);

                return [
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'amount' => $amount,
                    'currency' => $currency,
                    'receipt_url' => $payment->getReceiptUrl(),
                    'created_at' => $payment->getCreatedAt(),
                ];
            } else {
                $errors = $apiResponse->getErrors();
                $errorMessage = isset($errors[0]) ? $errors[0]->getDetail() : 'Payment failed';

                Log::error('Square payment failed', [
                    'source_id' => $sourceId,
                    'amount' => $amount,
                    'errors' => $errors
                ]);

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => $errors,
                ];
            }

        } catch (\Exception $e) {
            Log::error('Square payment exception', [
                'source_id' => $sourceId,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing error: ' . $e->getMessage()
            ];
        }
    }

    public function getPayment($paymentId)
    {
        try {
            $requestData = [
                'paymentId' => $paymentId
            ];

            $request = new GetPaymentsRequest($requestData);
            $apiResponse = $this->client->payments->get($request);

            if ($apiResponse->isSuccess()) {
                $payment = $apiResponse->getResult()->getPayment();
                return [
                    'success' => true,
                    'payment' => [
                        'id' => $payment->getId(),
                        'status' => $payment->getStatus(),
                        'amount' => $payment->getAmountMoney()->getAmount() / 100,
                        'currency' => $payment->getAmountMoney()->getCurrency(),
                        'receipt_url' => $payment->getReceiptUrl(),
                        'created_at' => $payment->getCreatedAt(),
                        'note' => $payment->getNote(),
                        'source_type' => $payment->getSourceType(),
                    ],
                ];
            }

            return ['success' => false, 'message' => 'Payment not found'];
        } catch (ApiException $e) {
            Log::error('Get Square payment API exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Square API error: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Get payment exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Error retrieving payment: ' . $e->getMessage()
            ];
        }
    }

    public function refundPayment($paymentId, $amount, $currency = 'USD', $reason = null)
    {
        try {
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Refund amount must be greater than zero'];
            }

            $amountMoney = new Money();
            $amountMoney->setAmount((int)($amount * 100));
            $amountMoney->setCurrency($currency);

            $requestData = [
                'idempotencyKey' => Str::uuid()->toString(),
                'amountMoney' => $amountMoney,
                'paymentId' => $paymentId,
            ];

            if ($reason) {
                $requestData['reason'] = $reason;
            }

            $body = new RefundPaymentRequest($requestData);
            $apiResponse = $this->client->refunds->createRefund($body);

            if ($apiResponse->isSuccess()) {
                $refund = $apiResponse->getResult()->getRefund();
                return [
                    'success' => true,
                    'refund_id' => $refund->getId(),
                    'status' => $refund->getStatus(),
                    'amount' => $amount,
                    'currency' => $currency,
                    'created_at' => $refund->getCreatedAt(),
                    'refund' => [
                        'id' => $refund->getId(),
                        'status' => $refund->getStatus(),
                        'amount' => $amount,
                        'currency' => $currency,
                    ]
                ];
            } else {
                $errors = $apiResponse->getErrors();
                $errorMessage = isset($errors[0]) ? $errors[0]->getDetail() : 'Refund failed';
                Log::error('Square refund failed', [
                    'payment_id' => $paymentId,
                    'amount' => $amount,
                    'errors' => $errors
                ]);
                return [
                    'success' => false,
                    'errors' => $errors,
                    'message' => $errorMessage
                ];
            }
        } catch (ApiException $e) {
            Log::error('Square refund API exception', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Square API error: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Square refund exception', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Refund processing error: ' . $e->getMessage()
            ];
        }
    }

    public function listPayments($limit = 10, $cursor = null)
    {
        try {
            $requestData = [
                'locationId' => $this->locationId,
                'limit' => $limit,
            ];

            if ($cursor) {
                $requestData['cursor'] = $cursor;
            }

            $request = new ListPaymentsRequest($requestData);
            $apiResponse = $this->client->payments->list($request);

            if ($apiResponse->isSuccess()) {
                $payments = $apiResponse->getResult()->getPayments() ?? [];
                return [
                    'success' => true,
                    'payments' => $payments,
                ];
            }

            return ['success' => false, 'message' => 'Failed to list payments'];
        } catch (ApiException $e) {
            Log::error('List Square payments API exception', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Square API error: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('List payments exception', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Error listing payments: ' . $e->getMessage()
            ];
        }
    }

    public function cancelPayment($paymentId)
    {
        try {
            $requestData = [
                'paymentId' => $paymentId
            ];

            $request = new CancelPaymentsRequest($requestData);
            $apiResponse = $this->client->payments->cancel($request);

            if ($apiResponse->isSuccess()) {
                $payment = $apiResponse->getResult()->getPayment();
                return [
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'message' => 'Payment cancelled successfully',
                ];
            } else {
                $errors = $apiResponse->getErrors();
                $errorMessage = isset($errors[0]) ? $errors[0]->getDetail() : 'Cancel payment failed';
                Log::error('Square cancel payment failed', [
                    'payment_id' => $paymentId,
                    'errors' => $errors
                ]);
                return [
                    'success' => false,
                    'errors' => $errors,
                    'message' => $errorMessage
                ];
            }
        } catch (ApiException $e) {
            Log::error('Square cancel payment API exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Square API error: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Square cancel payment exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Cancel payment error: ' . $e->getMessage()
            ];
        }
    }

    public function completePayment($paymentId)
    {
        try {
            $requestData = [
                'paymentId' => $paymentId
            ];

            $request = new CompletePaymentRequest($requestData);
            $apiResponse = $this->client->payments->complete($request);

            if ($apiResponse->isSuccess()) {
                $payment = $apiResponse->getResult()->getPayment();
                return [
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'message' => 'Payment completed successfully',
                ];
            } else {
                $errors = $apiResponse->getErrors();
                $errorMessage = isset($errors[0]) ? $errors[0]->getDetail() : 'Complete payment failed';
                Log::error('Square complete payment failed', [
                    'payment_id' => $paymentId,
                    'errors' => $errors
                ]);
                return [
                    'success' => false,
                    'errors' => $errors,
                    'message' => $errorMessage
                ];
            }
        } catch (ApiException $e) {
            Log::error('Square complete payment API exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Square API error: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Square complete payment exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Complete payment error: ' . $e->getMessage()
            ];
        }
    }

   


public function verifyConnection()
{
    try {
        \Log::info('Testing Square Connection...');

        $locationsApi = $this->client->locations;
        $apiResponse = $locationsApi->list(); 

        if ($apiResponse->isSuccess()) {
            $locations = $apiResponse->getResult()->getLocations();

            \Log::info('Square Connection Successful', [
                'locations_count' => count($locations),
                'location_names' => array_map(function($location) {
                    return [
                        'id' => $location->getId(),
                        'name' => $location->getName(),
                        'status' => $location->getStatus(),
                    ];
                }, $locations)
            ]);

            return [
                'success' => true,
                'message' => 'Square connection successful!',
                'environment' => config('square.environment'),
                'locations_count' => count($locations),
                'current_location_id' => $this->locationId,
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Square connection failed',
                'errors' => $apiResponse->getErrors(),
            ];
        }

    } catch (\Square\Exceptions\ApiException $e) {
        return [
            'success' => false,
            'message' => 'Square API error: ' . $e->getMessage(),
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'Square connection exception: ' . $e->getMessage(),
        ];
    }
}








    public function getApplicationId()
    {
        return config('square.application_id');
    }

    public function getLocationId()
    {
        return $this->locationId;
    }
}
