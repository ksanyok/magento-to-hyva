<?php

namespace MediaDivision\TexData\Model\Sequence;

use MediaDivision\TexData\Model\ResourceModel\Sequence\CollectionFactory;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
    $name, $primaryFieldName, $requestFieldName, CollectionFactory $collectionFactory, array $meta = [], array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData() {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        $this->loadedData = array();
        /** @var Customer $customer */
        foreach ($items as $item) {
            $this->loadedData[$item->getId()]['category'] = $item->getData();
        }


        return $this->loadedData;
    }

}