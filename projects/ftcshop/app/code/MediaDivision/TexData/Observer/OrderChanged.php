<?php

namespace MediaDivision\TexData\Observer;

use MediaDivision\TexData\Helper\Export\Order;
use Magento\Framework\Event\ObserverInterface;

class OrderChanged implements ObserverInterface
{

    private $order;
    
    public function __construct(Order $order) {
        $this->order = $order;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $order = $observer->getEvent()->getOrder();
        $this->order->orderChanged($order);
    }

}
