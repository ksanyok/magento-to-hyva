<?php

namespace MediaDivision\SwatchImages\Helper;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Swatches\Model\ResourceModel\Swatch\CollectionFactory as SwatchCollectionFactory;

class Data extends AbstractHelper
{

    private $optionList = [];
    private $productCollectionFactory;
    private $swatchCollectionFactory;

    public function __construct(
            Context $context,
            ProductCollectionFactory $productCollectionFactory,
            SwatchCollectionFactory $swatchCollectionFactory
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->swatchCollectionFactory = $swatchCollectionFactory;
        parent::__construct($context);
    }

    public function fillSwatches($debug = false) {
        // Aus den Produkten den Zusammenhang von Farbe und RGB-Nummer herauslesen.
        $productCollection = $this->productCollectionFactory
                ->create()
                ->addAttributeToSelect('color_name')
                ->addAttributeToSelect('color')
                ->addAttributeToSelect('rgb_farbe');

        foreach ($productCollection as $product) {
            $colorIndex = $product->getAttributeText('color');
            if($colorIndex && $colorIndex < 100) { // Multicolor nicht setzen
                if($debug) {
                    echo $product->getAttributeText('color_name') . " als Multicolor übergangen.\n";
                }
                continue;
            }
            $this->optionList[$product->getColorName()] = $this->getRgbHex($product->getRgbFarbe());
        }

        foreach ($this->swatchCollectionFactory->create() as $swatch) {
            $optionId = $swatch->getOptionId();
            if (isset($this->optionList[$optionId])) {
                if($debug) {
                    echo "Setze " . $this->optionList[$optionId] . " für " . $optionId . "\n";
                }
                $swatch->setValue($this->optionList[$optionId])->save();
            }
        }
    }

    private function getRgbHex($farbNummer) {
        $r = substr($farbNummer, 0, 3);
        $rhex = strlen(dechex($r)) == 2 ? dechex($r) : "0" . dechex($r);
        $g = substr($farbNummer, 3, 3);
        $ghex = strlen(dechex($g)) == 2 ? dechex($g) : "0" . dechex($g);
        $b = substr($farbNummer, 6, 3);
        $bhex = strlen(dechex($b)) == 2 ? dechex($b) : "0" . dechex($b);

        return "#" . $rhex . $ghex . $bhex;
    }

}
