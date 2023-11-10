<?php


namespace Tamara\Checkout\Model\Config\Backend;

use Tamara\Checkout\Gateway\Config\BaseConfig;
use Tamara\Checkout\Model\Adapter\TamaraAdapterFactory;

class EnableWebHook extends \Magento\Framework\App\Config\Value
{
    protected $tamaraConfig;
    protected $tamaraAdapterFactory;
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        TamaraAdapterFactory $tamaraAdapterFactory,
        BaseConfig $tamaraConfig,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->tamaraAdapterFactory = $tamaraAdapterFactory;
        $this->tamaraConfig = $tamaraConfig;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return \Magento\Framework\App\Config\Value
     * @throws \Exception
     */
    public function beforeSave()
    {
        $webhookEnabled = false;
        if (!empty($this->getValue())) {
            $webhookEnabled = true;
        }

        $scopeId = $this->tamaraConfig->getTamaraCore()->getCurrentScopeId();
        $scope = $this->tamaraConfig->getTamaraCore()->getCurrentScope();
        $webhookId = $this->tamaraConfig->getScopeConfig()->getValue('payment/tamara_checkout/webhook_id', $scope, $scopeId);
        try {
            if ($webhookEnabled && empty($webhookId)) {
                $adapter = $this->tamaraAdapterFactory->create();
                $adapter->registerWebhook();
            }
        } catch (\Exception $exception) {
            throw new \Exception("Tamara checkout config, error when register web hook, error message: " . __($exception->getMessage()));
        }

        if (!$this->isValueChanged()) {
            return parent::beforeSave();
        }
        try {
            if (!$webhookEnabled && $webhookId) {
                $adapter = $this->tamaraAdapterFactory->create();
                $adapter->deleteWebhook($webhookId);
            }
        } catch (\Exception $exception) {
            $this->_logger->debug("Tamara checkout config, error when delete web hook, error message: " . __($exception->getMessage()));
        }
        return parent::beforeSave();
    }
}