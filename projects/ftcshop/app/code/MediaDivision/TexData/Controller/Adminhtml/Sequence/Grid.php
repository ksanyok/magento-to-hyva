<?php
namespace MediaDivision\TexData\Controller\Adminhtml\Sequence;

class Grid extends \Magento\Backend\App\Action
{
    protected $_pageFactory;

    public function __construct(\Magento\Backend\App\Action\Context $context, \Magento\Framework\View\Result\PageFactory $pageFactory) {
        $this->_pageFactory = $pageFactory;
        return parent::__construct($context);
    }

    public function execute() {
        return $this->_pageFactory->create();
    }
    protected function _isAllowed() {
        return $this->_authorization->isAllowed('MediaDivision_TexData::sequence_grid');
    }

}