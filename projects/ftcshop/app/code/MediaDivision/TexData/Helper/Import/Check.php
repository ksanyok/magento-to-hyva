<?php

namespace MediaDivision\TexData\Helper\Import;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\DirectoryList;
use MediaDivision\TexData\Helper\Import\AbstractImport;
use MediaDivision\TexData\Helper\Import\Article;

class Check extends AbstractImport
{

    private $article;
    private $logFile = "/var/texdata/deactivatedProducts.txt";
    private $productFactory;
    private $productRepository;

    public function __construct(
            Article $article,
            CollectionFactory $productCollectionFactory,
            Context $context,
            DirectoryList $directoryList,
            ProductFactory $productFactory,
            ProductRepository $productRepository) {
        $this->article = $article;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        parent::__construct($productCollectionFactory, $context, $directoryList);
    }

    public function execute($debug = false) {
        $csvData = [];
        $this->debug = $debug;
        if ($this->debug) {
            echo "\n\nChecke Produkte.\n";
        }
        if (!$handle = fopen($this->installDir . $this->logFile, "w")) {
            if ($debug) {
                echo "Kann die Datei $logFile nicht öffnen.\n";
            }
            return;
        }
        if ($this->debug) {
            echo "\nBesorge Artikelnummern aus den XML.\n";
        }
        $xmlSkuList = $this->getArticleFromXmls();

        if ($this->debug) {
            echo "\nSuche nach Bildern.\n";
        }
        // fehlen bei einem konfigurierbaren Produkt zu einem simplen Produkt die Bilder
        $picturesAvailable = $this->getProductPictureList(); // key: sku -> value: alle Bilder vorhanden (true/false)

        $productList = $this->productCollectionFactory->create()
                ->addAttributeToFilter('type_id', 'configurable')
                ->addAttributeToSelect('sku')
                ->addAttributeToSelect('status');

        if ($this->debug) {
            echo "\nÜberprüfe Produkte.\n";
            $this->dot(true); // Zähler zurücksetzen
        }
        foreach ($productList as $product) {
            $simplesSkus = [];
            $this->dot();
            $deactivate = false;
            $message = '';
            $sku = $product->getSku();

            if ((count($xmlSkuList) > 1000) && (!in_array($sku, $xmlSkuList))) { // Wenn zu wenig skus in den XML gefunden wurden, dann lieber diesen Check übergehen anstatt zu viele Artikel zu deaktivieren.
                $message .= " -> Artikel nicht mehr in den XMLs";
                $deactivate = true;
            }

            if (!(isset($picturesAvailable[$sku]) && $picturesAvailable[$sku])) {
                $message .= " -> Bilder fehlen";
                $deactivate = true;
            }

            // name => 73; sku => 74, description => 75; short_description => 76; material => 154; 
            $attributeIds = [73, 74, 75, 76, 154];
            $childProducts = $product->getTypeInstance()->getUsedProducts($product, $attributeIds);

            foreach ($childProducts as $child) {
                $simplesSkus[] = $child->getSku();
                if (!$deactivate) {
                    if ($child->getPrice() <= 0) {
                        $message .= " -> Preis fehlt";
                        $deactivate = true;
                    }
                    if (!$child->getName()) {
                        $message .= " -> kein Name";
                        $deactivate = true;
                    }
                    if (!$child->getDescription()) {
                        $message .= " -> kein Produkttext";
                        $deactivate = true;
                    }
                    if (!$child->getShortDescription()) {
                        $message .= " -> keine Größen und Passform";
                        $deactivate = true;
                    }
                    if (!$child->getMaterial()) {
                        $message .= " -> kein Material";
                        $deactivate = true;
                    }
                }
            }


            if ($deactivate) {
                $csvData[] = [
                    "sku" => $sku,
                    "store" => "admin",
                    "status" => 2,
                    "configurable_attributes" => $this->configurableAttributes,
                    "simples_skus" => implode(",", $simplesSkus),
                ];
                //setStatus(2);
                $message = "\n" . $sku . $message . " -> deaktiviert\n";
                fwrite($handle, $message);
                if ($this->debug) {
                    echo $message;
                }
            } else {
                if ($product->getStatus() != 1) {
                    $csvData[] = [
                        "sku" => $sku,
                        "store" => "admin",
                        "status" => 1,
                        "configurable_attributes" => $this->configurableAttributes,
                        "simples_skus" => implode(",", $simplesSkus),
                    ];
                    if ($debug) {
                        echo "\n" . $sku . $message . " -> aktiviert\n";
                    }
                }
            }
        }
        if ($this->debug) {
            echo "\n\nProdukte gecheckt.\n\n";
        }
        fclose($handle);

        return $csvData;
    }

    private function getArticleFromXmls() {
        $skuList = [];
        $reimport = true; // Der Import ist ja gerade gelaufen. Deshalb die Daten holen als wäre es ein Reimport.
        $articleData = $this->article->getData($this->debug, $reimport);
        foreach ($articleData as $item) {
            $sku = $item["sku"];
            if (!in_array($sku, $skuList)) {
                $skuList[] = $sku;
            }
        }

        return $skuList;
    }

    private function getProductPictureList() {
        $products = $this->productCollectionFactory->create()
                ->addAttributeToSelect('sku')
                ->addAttributeToSelect('color')
                ->addAttributeToSelect('image')
                ->addAttributeToFilter('type_id', 'simple');

        // Sammle alle Daten
        // Gibt es für jedes Produkt in jeder Farbe mindestens ein Foto
        $pictureList = [];
        foreach ($products as $product) {
            $this->dot();
            $sku = preg_replace('/_.*/', '', $product->getSku());
            $color = $product->getAttributeText('color');
            if (!isset($pictureList[$sku])) {
                $pictureList[$sku] = [];
            }
            if (!isset($pictureList[$sku][$color])) {
                $pictureList[$sku][$color] = 0;
            }

            if ($product->getImage()) {
                $pictureList[$sku][$color]++;
            }
        }

        // Überprüfe, ob alle Bilder da sind.
        $skuList = [];
        foreach ($pictureList as $sku => $product) {
            $skuList[$sku] = true;
            foreach ($product as $color) {
                // wenn für eine Farbe kein Bild vorhanden ist, muss das Produkt deaktiviert werden
                if (!$color) {
                    $skuList[$sku] = false;
                }
            }
        }
        if ($this->debug) {
            echo "\n\n";
        }
        return $skuList;
    }

}
