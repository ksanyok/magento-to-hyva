<?php

namespace MediaDivision\TexData\Model;

class Sequence extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{

    const CACHE_TAG = 'md_sequence';

    protected $_cacheTag = 'md_sequence';
    protected $_eventPrefix = 'md_sequence';

    protected function _construct() {
        $this->_init('MediaDivision\TexData\Model\ResourceModel\Sequence');
    }

    public function getIdentities() {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues() {
        $values = [];

        return $values;
    }

    public function getSequence($usage) {
        $sequenceId = null;
        $collection = $this->getCollection()->addFieldToFilter('usage', $usage);
        if ($collection->count() > 0) {
            $sequenceId = $collection->getFirstItem()->getId();
        } else {
            $sequence = clone $this;
            $sequence->unsetData()->setUsage($usage)->setCreateDate(date('Y-m-d H:i:s'))->save();
            $sequenceId = $sequence->getId();
        }
        return $sequenceId;
    }

}
