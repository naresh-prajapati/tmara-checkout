<?php

namespace Tamara\Checkout\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;

class Invoice extends \Tamara\Checkout\Helper\AbstractData
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $magentoOrderRepository;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;
    /**
     * @var \Magento\Framework\DB\Transaction
     */
    private $dbTransaction;

    public function __construct(
        Context $context,
        \Magento\Framework\Locale\Resolver $locale,
        StoreManagerInterface $storeManager,
        \Magento\Framework\App\CacheInterface $magentoCache,
        \Tamara\Checkout\Gateway\Config\BaseConfig $tamaraConfig,
        \Magento\Sales\Api\OrderRepositoryInterface $magentoOrderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $dbTransaction,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Tamara\Checkout\Model\Adapter\TamaraAdapterFactory $tamaraAdapterFactory
    ) {
        $this->magentoOrderRepository = $magentoOrderRepository;
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->invoiceSender = $invoiceSender;
        parent::__construct($context, $locale, $storeManager, $magentoCache, $tamaraConfig, $tamaraAdapterFactory);
    }

    /**
     * @param $orderId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function generateInvoice($orderId)
    {
        try {
            $order = $this->magentoOrderRepository->get($orderId);
            if ($order->canInvoice()) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->save();
                $transactionSave = $this->dbTransaction->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
                $transactionSave->save();
                $this->invoiceSender->send($invoice);

                //send notification code
                $order->addCommentToStatusHistory(
                    __('Notified customer about invoice #%1.', $invoice->getIncrementId())
                )
                    ->setIsCustomerNotified(true)
                    ->save();
            }
        } catch (\Exception $exception) {
            $this->log([$exception->getMessage()]);
        }
    }
}