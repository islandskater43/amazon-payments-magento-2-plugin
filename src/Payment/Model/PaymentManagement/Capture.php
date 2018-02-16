<?php
/**
 * Copyright 2016 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *  http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
namespace Amazon\Payment\Model\PaymentManagement;

use Amazon\Core\Client\ClientFactoryInterface;
use Amazon\Payment\Api\Data\PendingCaptureInterface;
use Amazon\Payment\Api\Data\PendingCaptureInterfaceFactory;
use Amazon\Payment\Api\PaymentManagement\CaptureInterface;
use Amazon\Payment\Api\PaymentManagementInterface;
use Amazon\Payment\Domain\AmazonCaptureDetailsResponseFactory;
use Amazon\Payment\Domain\AmazonCaptureStatus;
use Amazon\Payment\Domain\Details\AmazonCaptureDetails;
use Exception;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Capture extends AbstractOperation implements CaptureInterface
{
    /**
     * @var ClientFactoryInterface
     */
    protected $clientFactory;

    /**
     * @var PendingCaptureInterfaceFactory
     */
    protected $pendingCaptureFactory;

    /**
     * @var AmazonCaptureDetailsResponseFactory
     */
    protected $amazonCaptureDetailsResponseFactory;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    protected $orderPaymentRepository;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var PaymentManagementInterface
     */
    protected $paymentManagement;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $throwExceptions = false;

    /**
     * Capture constructor.
     *
     * @param ClientFactoryInterface              $clientFactory
     * @param PendingCaptureInterfaceFactory      $pendingCaptureFactory
     * @param AmazonCaptureDetailsResponseFactory $amazonCaptureDetailsResponseFactory
     * @param NotifierInterface                   $notifier
     * @param UrlInterface                        $urlBuilder
     * @param SearchCriteriaBuilderFactory        $searchCriteriaBuilderFactory
     * @param InvoiceRepositoryInterface          $invoiceRepository
     * @param OrderPaymentRepositoryInterface     $orderPaymentRepository
     * @param OrderRepositoryInterface            $orderRepository
     * @param TransactionRepositoryInterface      $transactionRepository
     * @param StoreManagerInterface               $storeManager
     * @param PaymentManagementInterface          $paymentManagement
     * @param LoggerInterface                     $logger
     */
    public function __construct(
        ClientFactoryInterface $clientFactory,
        PendingCaptureInterfaceFactory $pendingCaptureFactory,
        AmazonCaptureDetailsResponseFactory $amazonCaptureDetailsResponseFactory,
        NotifierInterface $notifier,
        UrlInterface $urlBuilder,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        OrderRepositoryInterface $orderRepository,
        TransactionRepositoryInterface $transactionRepository,
        StoreManagerInterface $storeManager,
        PaymentManagementInterface $paymentManagement,
        LoggerInterface $logger
    ) {
        $this->clientFactory                       = $clientFactory;
        $this->pendingCaptureFactory               = $pendingCaptureFactory;
        $this->amazonCaptureDetailsResponseFactory = $amazonCaptureDetailsResponseFactory;
        $this->orderPaymentRepository              = $orderPaymentRepository;
        $this->orderRepository                     = $orderRepository;
        $this->transactionRepository               = $transactionRepository;
        $this->storeManager                        = $storeManager;
        $this->paymentManagement                   = $paymentManagement;
        $this->logger                              = $logger;

        parent::__construct($notifier, $urlBuilder, $searchCriteriaBuilderFactory, $invoiceRepository);
    }

    /**
     * {@inheritdoc}
     */
    public function setThrowExceptions($throwExceptions)
    {
        $this->throwExceptions = $throwExceptions;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function updateCapture($pendingCaptureId, AmazonCaptureDetails $captureDetails = null)
    {
        try {
            $pendingCapture = $this->pendingCaptureFactory->create();
            $pendingCapture->getResource()->beginTransaction();
            $pendingCapture->setLockOnLoad(true);
            $pendingCapture->load($pendingCaptureId);

            if ($pendingCapture->getCaptureId()) {
                $order   = $this->orderRepository->get($pendingCapture->getOrderId());
                $payment = $this->orderPaymentRepository->get($pendingCapture->getPaymentId());
                $order->setPayment($payment);
                $order->setData(OrderInterface::PAYMENT, $payment);

                $storeId = $order->getStoreId();
                $this->storeManager->setCurrentStore($storeId);

                if (null === $captureDetails) {
                    $responseParser = $this->clientFactory->create($storeId)->getCaptureDetails([
                        'amazon_capture_id' => $pendingCapture->getCaptureId()
                    ]);

                    $response = $this->amazonCaptureDetailsResponseFactory->create(['response' => $responseParser]);
                    $captureDetails = $response->getDetails();
                }

                $this->processUpdateCaptureResponse($captureDetails, $pendingCapture, $payment, $order);
            }

            $pendingCapture->getResource()->commit();
        } catch (Exception $e) {
            $this->logger->error($e);
            $pendingCapture->getResource()->rollBack();

            if ($this->throwExceptions) {
                throw $e;
            }
        }
    }

    protected function processUpdateCaptureResponse(
        AmazonCaptureDetails $details,
        PendingCaptureInterface $pendingCapture,
        OrderPaymentInterface $payment,
        OrderInterface $order
    ) {
        $status = $details->getStatus();

        switch ($status->getState()) {
            case AmazonCaptureStatus::STATE_COMPLETED:
                $this->completePendingCapture($pendingCapture, $payment, $order);
                break;
            case AmazonCaptureStatus::STATE_DECLINED:
                $this->declinePendingCapture($pendingCapture, $payment, $order);
                break;
        }
    }

    protected function completePendingCapture(
        PendingCaptureInterface $pendingCapture,
        OrderPaymentInterface $payment,
        OrderInterface $order
    ) {
        $transactionId   = $pendingCapture->getCaptureId();
        $transaction     = $this->paymentManagement->getTransaction($transactionId, $payment, $order);
        $invoice         = $this->getInvoice($transactionId, $order);
        $formattedAmount = $order->getBaseCurrency()->formatTxt($invoice->getBaseGrandTotal());
        $message         = __('Captured amount of %1 online', $formattedAmount);

        $this->getInvoiceAndSetPaid($transactionId, $order);
        $payment->setDataUsingMethod('base_amount_paid_online', $invoice->getBaseGrandTotal());
        $this->setProcessing($order);
        $payment->addTransactionCommentsToOrder($transaction, $message);
        $order->save();

        $this->paymentManagement->closeTransaction($transactionId, $payment, $order);
        $pendingCapture->delete();
    }

    protected function declinePendingCapture(
        PendingCaptureInterface $pendingCapture,
        OrderPaymentInterface $payment,
        OrderInterface $order
    ) {
        $transactionId   = $pendingCapture->getCaptureId();
        $transaction     = $this->paymentManagement->getTransaction($transactionId, $payment, $order);
        $invoice         = $this->getInvoice($transactionId, $order);
        $formattedAmount = $order->getBaseCurrency()->formatTxt($invoice->getBaseGrandTotal());
        $message         = __('Declined amount of %1 online', $formattedAmount);

        $this->getInvoiceAndSetCancelled($transactionId, $order);
        $this->setOnHold($order);
        $payment->addTransactionCommentsToOrder($transaction, $message);
        $order->save();

        $this->paymentManagement->closeTransaction($transactionId, $payment, $order);
        $pendingCapture->delete();

        $this->addCaptureDeclinedNotice($order);
    }
}
