<?php

namespace MediaDivision\Image\Helper;

use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\MediaStorage\Service\ImageResize;

class Data extends AbstractHelper
{
    
    private $productFactory;
    private $imageResize;
    
    public function __construct(
            Context $context,
            ImageResize $imageResize,
            ProductFactory $productFactory) {
        $this->imageResize = $imageResize;
        $this->productFactory = $productFactory;
        parent::__construct($context);
    }

    public function resizeImages($sku) {
        $productId = $this->productFactory->create()->getIdBySku($sku);
        $product = $this->productFactory->create()->load($productId);
        echo $sku . ": " . $product->getName() . "\n";

        $galleryImages = $product->getMediaGalleryImages();
        if ($galleryImages) {
            foreach ($galleryImages as $image) {
                echo $image->getFile() . "\n";
                $this->imageResize->resizeFromImageName($image->getFile());
            }
        }
        //echo $sku . "\n";
    }

}
