<?php

namespace MediaDivision\StoreLocator\Controller\StoreImport;

use MediaDivision\StoreLocator\Helper\Data as StoreHelper;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\Controller\Result\JsonFactory;

class Index extends \Magento\Framework\App\Action\Action
{

    protected $jsonFactory;
    private $storeHelper;

    public function __construct(
            Context $context,
            JsonFactory $pageFactory,
            StoreHelper $storeHelper) {
        $this->jsonFactory = $pageFactory;
        $this->storeHelper = $storeHelper;
        return parent::__construct($context);
    }

    public function execute() {
        $message = "Import beendet";
        
        $this->storeHelper->importStoreLocations();
        
        return $this->jsonFactory->create()->setData(["message" => $message]);
    }

}
