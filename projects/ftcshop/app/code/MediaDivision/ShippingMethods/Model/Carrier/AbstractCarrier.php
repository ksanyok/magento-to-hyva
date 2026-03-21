<?php

namespace MediaDivision\ShippingMethods\Model\Carrier;

use Magento\Backend\Model\Session\Quote;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AbstractCarrier extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements CarrierInterface
{

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     *  Magento\Framework\App\State
     */
    protected $state;

    /**
     * AbstractCarrier constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param Quote $quote
     * @param State $state
     * @param array $data
     */
    public function __construct(
            ScopeConfigInterface $scopeConfig,
            ErrorFactory $rateErrorFactory,
            LoggerInterface $logger,
            ResultFactory $rateResultFactory,
            MethodFactory $rateMethodFactory,
            StoreManagerInterface $storeManager,
            RequestInterface $request,
            Quote $quote,
            State $state,
            array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->quote = $quote;
        $this->state = $state;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function collectRates(RateRequest $request) {
        try {
            $this->setData('store', $this->getScopeId());
        } catch (LocalizedException $e) {
            return $this->showErrorResult($e->getMessage());
        }

        if (!$this->getConfigFlag('active')) {
            return false;
        }

        if ($postCode = $this->getConfigData('postcode')) {
            $zipCodes = array_map('trim', explode(';', $postCode));
            if (!in_array($request->getDestPostcode(), $zipCodes, true)) {
                return $this->showErrorResult();
            }
        }

        $result = $this->_rateResultFactory->create();
        $price = $request->getFreeShipping() ? 0 : (float) $this->getConfigData('price');
        $method = $this->_rateMethodFactory->create()->setData([
            'carrier' => $this->_code,
            'carrier_title' => $this->getCarrierTitle($this->getConfigData('title')),
            'method' => 'flatrate',
            'method_title' => $this->getConfigData('name'),
            'price' => $price,
            'cost' => $price,
        ]);

        return $result->append($method);
    }

    private function getCarrierTitle($title) {
        $daysMin = $this->getConfigData('days_min');
        $minRest = $daysMin % 5;
        $daysMax = $this->getConfigData('days_max');
        $maxRest = $daysMax % 5;
        
        // Für jede vollständige Arbeits-Woche 2 Tage (= ein Wochenende) zusätzliche Lieferzeit
        $daysMin += (int) ($daysMin / 5) * 2;
        $daysMax += (int) ($daysMax / 5) * 2;

        // Wenn die unvollständige Woche + den heutigen Wochentag einen Samstag oder Sonntag ergibt, dann nochmal einen
        // Wochentag ist numerisch: So = 0, Mo = 1, ... Sa = 6. Wenn Wochenrest + Wochentag > 5 -> Wochenende dazuzählen
        $begin = new \DateTime('now');
        $end = new \DateTime('now');
        $wochentag = $begin->format('w');
        if (($minRest + $wochentag) > 5) {
            $daysMin += 2;
        }
        if (($maxRest + $wochentag) > 5) {
            $daysMax += 2;
        }

        $begin->add(new \DateInterval('P' . $daysMin . 'D'));
        $end->add(new \DateInterval('P' . $daysMax . 'D'));

        $title .= " " . __("(Estimated delivery between %1 and %2)", $begin->format('d.m.y'), $end->format('d.m.y'));

        return $title;
    }

    /**
     * getAllowedMethods
     *
     * @return  array
     */
    public function getAllowedMethods() {
        return ['flatrate' => $this->getConfigData('name')];
    }

    /**
     * @param string|null $errorMsg
     *
     * @return bool|Error
     */
    private function showErrorResult($errorMsg = null) {
        if (!$this->getConfigData('showmethod')) {
            return false;
        }

        return $this->_rateErrorFactory->create()->setData([
                    'carrier' => $this->_code,
                    'carrier_title' => $this->getConfigData('title'),
                    'error_message' => $errorMsg ?: $this->getConfigData('specificerrmsg'),
        ]);
    }

    /**
     * @return int
     * @throws LocalizedException
     */
    protected function getScopeId() {
        if ($this->state->getAreaCode() === Area::AREA_ADMINHTML) {
            $storeId = $this->quote->getStoreId();
        } else {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $scope = $this->request->getParam(ScopeInterface::SCOPE_STORE) ?: $storeId;
        if ($website = $this->request->getParam(ScopeInterface::SCOPE_WEBSITE)) {
            /** @var Store $store */
            $store = $this->storeManager->getWebsite($website)->getDefaultStore();
            if ($store) {
                return $store->getId();
            }
        }

        return $scope;
    }

}
