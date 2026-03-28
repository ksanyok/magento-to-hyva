<?php
declare(strict_types=1);

namespace MediaDivision\DisabledProductView\Plugin;

use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

class AllowDisabledProductView
{
    public function aroundCanShow(ProductHelper $subject, callable $proceed, Product $product): bool
    {
        if ($proceed($product)) {
            return true;
        }

        if (!$product->getId()) {
            return false;
        }

        // Mirror original storefront behavior where some disabled products remain viewable by URL.
        if ((int) $product->getStatus() !== Status::STATUS_DISABLED) {
            return false;
        }

        return (bool) $product->isVisibleInSiteVisibility();
    }
}
