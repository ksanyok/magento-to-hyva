<?php

namespace MediaDivision\TexData\Helper\Import;

use MediaDivision\TexData\Helper\Import\AbstractImport;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\Filesystem\DirectoryList;
use \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class Stock extends AbstractImport
{

    private $simple = [];
    private $stockList = [];
    private $schweizerStock = "lager_schweiz.csv";
    protected $articleTemplate = [
        "sku" => "__MAGMI_IGNORE__",
        "store" => "admin",
        "inventory_source:default" => 0, // default ist keiner Seite zugewiesen, deshalb 0 setzen
        "inventory_source:austria" => "__MAGMI_IGNORE__",
        "inventory_source:switzerland" => "__MAGMI_IGNORE__",
    ];

    public function __construct(
            Context $context,
            DirectoryList $directoryList,
            CollectionFactory $productCollectionFactory) {
        parent::__construct($productCollectionFactory, $context, $directoryList);
    }

    public function getData($stocks, $reimport = false, $debug = false) {
        $this->stockList = $stocks;
        $this->debug = $debug;
        $this->reimport = $reimport;
        if ($this->debug) {
            echo "\n\nHole Reservierungs-Daten.\n";
        }
        $reservations = $this->getReservations();

        if ($this->debug) {
            echo "\n\nHole Lagerbestands-Daten.\n";
        }
        $productList = [];
        // Produktliste mit Standardwerten füllen
        foreach ($this->productCollectionFactory->create()->addAttributeToFilter('type_id', 'simple') as $product) {
            $productList[$product->getSku()] = $this->getArticleTemplate();
            $productList[$product->getSku()]["sku"] = $product->getSku();  
            // Wenn Schweizer Lager aktualisiert werden soll.
            if(in_array('switzerland', $this->stockList)) {
                // Anzahl mit Reserivierungen füllen, falls vorhanden, ansonsten 0
                if (isset($reservations[2]) && isset($reservations[2][$product->getSku()])) {
                    $productList[$product->getSku()]["inventory_source:switzerland"] = (-1) * $reservations[2][$product->getSku()];
                } else {
                    $productList[$product->getSku()]["inventory_source:switzerland"] = 0;
                }
            }
            // Wenn österreichisches Lager aktualisiert werden soll.
            if(in_array('austria', $this->stockList)) {
                // Anzahl mit Reserivierungen füllen, falls vorhanden, ansonsten 0
                if (isset($reservations[3]) && isset($reservations[3][$product->getSku()])) {
                    $productList[$product->getSku()]["inventory_source:austria"] = (-1) * $reservations[3][$product->getSku()];
                } else {
                    $productList[$product->getSku()]["inventory_source:austria"] = 0;
                }
            }
        }

        if (in_array('austria', $this->stockList)) {
            $productList = $this->fillAustrianSource($productList, $debug, $reimport, $reservations[3]);
        }
        if (in_array('switzerland', $this->stockList)) {
            $productList = $this->fillSwissSource($productList, $debug, $reimport, $reservations[2]);
        }

        foreach ($productList as $product) {
            $this->simple[] = $product;
        }
        return $this->simple;
    }

    private function getReservations() {
        // stock_id = 2 -> Schweiz | stock_id = 3 -> Österreich
        $reservations = ["2" => [], "3" => []];
        $resource = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $sql = "SELECT * FROM inventory_reservation";
        foreach ($connection->fetchAll($sql) as $item) {
            if (!isset($reservations[$item["stock_id"]][$item["sku"]])) {
                $reservations[$item["stock_id"]][$item["sku"]] = 0;
            }
            $reservations[$item["stock_id"]][$item["sku"]] += (int) $item["quantity"];
        }
        return $reservations;
    }

    private function fillSwissSource($productList, $debug, $reimport, $reservations) {
        $stockFile = $this->installDir . $this->fileDir . "stock/" . $this->schweizerStock;
        if (file_exists($stockFile) && ((($handle = fopen($stockFile, "r")) !== FALSE) )) { 
            $headLine = fgetcsv($handle, 0, ";");
            while (($line = fgetcsv($handle, 0, ";")) !== FALSE) {
                $skuPart = $line[0] . '_' . $line[1];
                foreach ($line as $index => $item) {
                    $item = intval($item);
                    $sku = $skuPart . '_' . $headLine[$index];
                    $sku = preg_replace('/_PCS/', '_pcs', $sku);
                    if ($item < 0) {
                        $item = 0;
                    }

                    if (isset($productList[$sku])) {
                        $productList[$sku]["inventory_source:switzerland"] += $item;
                    }
                }
            }
            fclose($handle);
        }

        return $productList;
    }

    private function fillAustrianSource($productList, $debug, $reimport, $reservations) {
        $fileList = $this->getFileList("stock", "stockinfo_", $debug, $reimport);
        foreach ($fileList as $filename) {
            $xml = simplexml_load_file($filename);
            $artikelList = $xml->{"FIRMA-NR"}->ARTIKEL;
            foreach ($artikelList as $artikel) {
                foreach ($artikel->VARIANTE as $variante) {
                    foreach ($variante->FARBE as $farbe) {
                        foreach ($farbe->GROESSEN as $groesse) {
                            $form = (string) $artikel->FORM;
                            $qual = (string) $artikel->QUAL;
                            $farbNr = (string) $farbe->FARBNR;
                            $groesseStr = (string) $groesse->GROESSE;
                            $sku = $this->buildSku($form, $qual, $farbNr, $groesseStr);
                            $qty = (string) $groesse->BESTAND;
                            if (isset($productList[$sku])) {
                                $productList[$sku]["inventory_source:austria"] += $qty;
                            }
                        }
                    }
                }
            }
        }
        return $productList;
    }

    protected function getArticleTemplate() {
        $articleTemplate = $this->articleTemplate;

        // Wenn einer der beiden Lager nicht genannt ist, dann dieses Lager nicht ändern.
        if (!in_array('austria', $this->stockList)) {
            $articleTemplate["inventory_source:austria"] = "__MAGMI_IGNORE__";
        }
        if (!in_array('switzerland', $this->stockList)) {
            $articleTemplate["inventory_source:switzerland"] = "__MAGMI_IGNORE__";
        }
        return $articleTemplate;
    }

}
