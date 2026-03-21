<?php

namespace MediaDivision\Basics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class DateCheck extends AbstractHelper {
    protected $localeDate;

    public function __construct(TimezoneInterface $localeDate) {
        $this->localeDate = $localeDate;
    }

    public function isProductNew($product) {
        $newsFromDate = $product->getNewsFromDate();
        $newsToDate = $product->getNewsToDate();

        if (!$newsFromDate && !$newsToDate) {
            return false;
        }

        return $this->localeDate->isScopeDateInInterval(
            $product->getStore(),
            $newsFromDate,
            $newsToDate
        );
    }

    public function isProductOnSale($product) {
        $specialFromDate = $product->getSpecialFromDate();
        $specialToDate = $product->getSpecialToDate();

        if (!$specialFromDate && !$specialToDate) {
            return false;
        }

        return $this->localeDate->isScopeDateInInterval(
            $product->getStore(),
            $specialFromDate,
            $specialToDate
        );
    }
}
