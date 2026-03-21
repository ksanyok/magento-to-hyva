<?php
            
namespace MediaDivision\TexData\Model\ResourceModel\Sequence;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'md_sequence_collection';
    protected $_eventObject = 'sequence_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct() {
        $this->_init('MediaDivision\TexData\Model\Sequence', 'MediaDivision\TexData\Model\ResourceModel\Sequence');
    }

}