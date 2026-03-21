<?php

namespace MediaDivision\Basics\Observer;

class LayoutViewer implements \Magento\Framework\Event\ObserverInterface
{

    public function execute(\Magento\Framework\Event\Observer $observer) {
        //$event = $observer->getEvent();
        //$controllerAction = $observer->getControllerAction();
        //$request = $observer->getRequest();
        //file_put_contents('test.txt', print_r($request->getFullActionName(),true), FILE_APPEND);
        return $this;
    }

}
