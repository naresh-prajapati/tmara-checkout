<?php

namespace Tamara\Checkout\Gateway\Request;

use Magento\Framework\App\ProductMetadata;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Tamara\Checkout\Model\Helper\LocaleHelper;
use Tamara\Model\Money;
use Tamara\Model\Order\Discount;

class CommonDataBuilder implements BuilderInterface
{
    public const
        ORDER_ID = 'order_id',
        ORDER_REFERENCE_ID = 'order_reference_id',
        LOCALE = 'locale',
        CURRENCY = 'currency',
        TOTAL_AMOUNT = 'total_amount',
        COUNTRY_CODE = 'country_code',
        PAYMENT_TYPE = 'payment_type',
        PLATFORM = 'platform',
        DESCRIPTION = 'description',
        TAX_AMOUNT = 'tax_amount',
        DISCOUNT_AMOUNT = 'discount_amount',
        SHIPPING_AMOUNT = 'shipping_amount',
        RISK_ASSESSMENT = 'risk_assessment',
        NUMBER_OF_INSTALLMENTS = 'number_of_installments';

    protected $tamaraCoreHelper;

    /**
     * @var ProductMetadata
     */
    private $productMetaData;

    /**
     * @param ProductMetadata $productMetaData
     */
    public function __construct(ProductMetadata $productMetaData,
        \Tamara\Checkout\Helper\Core $tamaraCoreHelper
    )
    {
        $this->productMetaData = $productMetaData;
        $this->tamaraCoreHelper = $tamaraCoreHelper;
    }

    public function build(array $buildSubject): array
    {
        if (!isset($buildSubject['order'])
            || !$buildSubject['order'] instanceof OrderInterface
        ) {
            throw new \InvalidArgumentException('Order data object should be provided');
        }

        /** @var OrderInterface $order */
        $order = $buildSubject['order'];
        $currencyCode = $buildSubject['order_currency_code'];
        $phoneVerified = $buildSubject['phone_verified'];
        $numberOfInstallments = null;

        $magentoDiscountAmount = abs(floatval($order->getDiscountAmount()));
        if ($magentoDiscountAmount < 0.00000001) {
            $discountName = "";
            $magentoDiscountAmount = 0.00;
        } else {
            if (empty($order->getCouponCode())) {
                $discountName = "N/A";
            } else {
                $discountName = $order->getCouponCode();
            }
        }
        $discountAmount = new Discount($discountName, new Money($magentoDiscountAmount, $currencyCode));
        $paymentMethod = $order->getPayment()->getMethod();
        $paymentType = \Tamara\Checkout\Gateway\Config\BaseConfig::convertPaymentMethodFromMagentoToTamara($paymentMethod);
        if ($paymentType == \Tamara\Checkout\Gateway\Config\InstalmentConfig::PAY_BY_INSTALMENTS) {
            if ($paymentMethod == \Tamara\Checkout\Gateway\Config\SingleCheckoutConfig::PAYMENT_TYPE_CODE) {
                $numberOfInstallments = 3;
            } else {
                $numberOfInstallments = \Tamara\Checkout\Gateway\Config\InstalmentConfig::getInstallmentsNumberByPaymentCode($paymentMethod);
            }
        }

        return [
            self::ORDER_ID => $order->getEntityId(),
            self::ORDER_REFERENCE_ID => $order->getIncrementId(),
            self::LOCALE => LocaleHelper::getLocale(),
            self::CURRENCY => $currencyCode,
            self::TOTAL_AMOUNT => new Money($order->getGrandTotal(), $currencyCode),
            self::TAX_AMOUNT => new Money($order->getTaxAmount(), $currencyCode),
            self::SHIPPING_AMOUNT => new Money($order->getShippingAmount(), $currencyCode),
            self::DISCOUNT_AMOUNT => $discountAmount,
            self::COUNTRY_CODE => $order->getBillingAddress()->getCountryId(),
            self::PAYMENT_TYPE => $paymentType,
            self::PLATFORM => 'Magento Version: ' . $this->productMetaData->getVersion() . ', Plugin Version: ' . $this->tamaraCoreHelper->getPluginVersion(),
            self::DESCRIPTION => 'Description',
            self::RISK_ASSESSMENT => ['phone_verified' => $phoneVerified],
            self::NUMBER_OF_INSTALLMENTS => $numberOfInstallments
        ];
    }
}
