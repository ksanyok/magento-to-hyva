<?php

namespace MediaDivision\TexData\Helper\Import;

use MediaDivision\TexData\Helper\Import\AbstractImport;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\Filesystem\DirectoryList;
use \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use \Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CurrencyFactory;

class Price extends AbstractImport
{

    private $currencyFactory;
    private $simple = [];
    private $storeManager;
    protected $articleTemplate = [
        "sku" => "__MAGMI_IGNORE__",
        "store" => "__MAGMI_IGNORE__",
        "price" => "",
        "special_price" => "",
        "group_price:group1" => "__MAGMI_IGNORE__",
        "group_price:group2" => "__MAGMI_IGNORE__",
    ];

    public function __construct(
            Context $context,
            DirectoryList $directoryList,
            CollectionFactory $productCollectionFactory,
            StoreManagerInterface $storeManager,
            CurrencyFactory $currencyFactory) {
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        parent::__construct($productCollectionFactory, $context, $directoryList);
    }

    public function getData($debug = false, $reimport = false) {
        $this->debug = $debug;
        $this->reimport = $reimport;
        if ($this->debug) {
            echo "\n\nHole Preis-Daten.\n";
        }

        $swissPrices = $this->getSwissPrices($debug, $reimport);
        $ratesList = $this->getCurrencyRates();

        $fileList = $this->getFileList("price", "preisliste_", $debug, $reimport);

        foreach ($fileList as $filename) {
            $xml = simplexml_load_file($filename);
            foreach ($xml->{'FIRMA-NR'}->ARTIKELPREIS as $artikel) {
                $skuListe = $this->searchSimpleSkuList($artikel->FORM, $artikel->QUAL);
                $preisListe = []; // $preisListe[farbe][groesse][preisart] Farbe 0 bedeutet alle Farben für die es keine extra Definition gibt.

                foreach ($artikel->VARIANTEN->FARBEN as $groessenListe) {
                    $farbe = (string) $groessenListe->FARBE;
                    foreach ($groessenListe->GROESSEN as $grData) {
                        $groesse = (string) $grData->GROESSE;
                        $preisListe[$farbe][$groesse] = [
                            "price" => preg_replace('/,/', '.', $grData->PREISE->EVPPREIS),
                            "special_price" => preg_replace('/,/', '.', $grData->PREISE->EVPPREIS_ORIGINAL),
                            "group1" => preg_replace('/,/', '.', $grData->PREISE->VOPREIS),
                            "group2" => preg_replace('/,/', '.', $grData->PREISE->EVPPREIS),
                        ];
                        if ($preisListe[$farbe][$groesse]['special_price'] == '0') {
                            $preisListe[$farbe][$groesse]['special_price'] = '';
                        }
                    }
                }
                // Hauptpreis - Basis für die Berechnung der anderen Preise
                foreach ($skuListe as $sku) {
                    $skuParts = explode('_', $sku);
                    $farbe = $skuParts[1];
                    $groesse = $skuParts[2];
                    $article = $this->getArticleTemplate();
                    $article["sku"] = $sku;
                    $article["store"] = "admin";
                    if (isset($preisListe[$farbe]) && isset($preisListe[$farbe][$groesse])) {
                        $article["price"] = $preisListe[$farbe][$groesse]["price"];
                        $article["special_price"] = $preisListe[$farbe][$groesse]["special_price"];
                        $article["group_price:group1"] = $preisListe[$farbe][$groesse]["group1"];
                        $article["group_price:group2"] = $preisListe[$farbe][$groesse]["group2"];
                    } elseif (isset($preisListe[0]) && isset($preisListe[0][""])) {
                        $article["price"] = $preisListe[0][""]["price"];
                        $article["special_price"] = $preisListe[0][""]["special_price"];
                        $article["group_price:group1"] = $preisListe[0][""]["group1"];
                        $article["group_price:group2"] = $preisListe[0][""]["group2"];
                    } else {
                        $article["price"] = "";
                        $article["special_price"] = "";
                        $article["group_price:group1"] = "";
                        $article["group_price:group2"] = "";
                    }
                    $this->dot();
                    $this->simple[] = $article;
                    $originalPrice = $article["price"];
                    $specialPrice = $article["special_price"];
                    // Preise in anderen Währungen berechnen
                    foreach ($ratesList as $rate) {
                        // Für die EURO-Shops keine anderen Preise berechnen.
                        if (in_array($rate["code"], ['de-at', 'en-at', 'en-be', 'de-de', 'en-de', 'en-fr', 'en-it', 'en-nl', 'nl-nl'])) {
                            $article = $this->getArticleTemplate();
                            $article["sku"] = $sku;
                            $article["store"] = $rate["code"];
                            $this->dot();
                            $this->simple[] = $article;
                            // Wenn für den Schweizer Shop ein Schweizer Preis definiert ist, den benutzen
                        } elseif (in_array($rate["code"], ['de-ch', 'en-ch'])) {
                            if (isset($swissPrices[$sku])) {
                                $article = $this->getArticleTemplate();
                                $article["sku"] = $sku;
                                $article["store"] = $rate["code"];
                                $article["price"] = $swissPrices[$sku]["price"];
                                $article["special_price"] = $swissPrices[$sku]["special_price"];
                                $this->dot();
                                $this->simple[] = $article;
                            }
                        } else {
                            $article = $this->getArticleTemplate();
                            $article["sku"] = $sku;
                            $article["store"] = $rate["code"];
                            $article["price"] = $this->calculatePrice($originalPrice, $rate["rate"]);
                            $article["special_price"] = $this->calculatePrice($specialPrice, $rate["rate"]);
                            $this->dot();
                            $this->simple[] = $article;
                        }
                    }
                }
            }
        }
        return $this->simple;
    }

    private function getCurrencyRates() {
        $storeViewList = [];
        $baseCurrencyCode = $this->storeManager->getStore(0)->getBaseCurrencyCode();
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = $store->getStoreId();
            $shopCurrency = $this->storeManager->getStore($storeId)->getCurrentCurrencyCode();
            $currencyRate = 1;
            foreach ($this->currencyFactory->create()->getCurrencyRates($baseCurrencyCode, [$shopCurrency]) as $rate) {
                $currencyRate = $rate;
            }

            $storeViewList[] = [
                "code" => $store->getCode(),
                "rate" => $currencyRate,
            ];
        }
        return $storeViewList;
    }

    private function getSwissPrices($debug, $reimport) {
        $swissPriceList = [];
        $fileList = $this->getFileList("pricepl5", "preisliste_", $debug, $reimport);
        foreach ($fileList as $filename) {
            $xml = simplexml_load_file($filename);
            foreach ($xml->{'FIRMA-NR'}->ARTIKELPREIS as $artikel) {
                $skuListe = $this->searchSimpleSkuList($artikel->FORM, $artikel->QUAL);
                $preise = $artikel->VARIANTEN->FARBEN->GROESSEN->PREISE;
                $originalPrice = preg_replace('/,/', '.', $preise->EVPPREIS);
                $specialPrice = preg_replace('/,/', '.', $preise->EVPPREIS_ORIGINAL);
                $voPrice = preg_replace('/,/', '.', $preise->VOPREIS);
                if ($specialPrice == '0') {
                    $specialPrice = '';
                }

                foreach ($skuListe as $sku) {
                    $swissPriceList[$sku] = [
                        "sku" => $sku,
                        "price" => $originalPrice,
                        "special_price" => $specialPrice,
                        "vo_price" => $voPrice,
                    ];
                }
            }
        }
        return $swissPriceList;
    }

    /**
     * Errechnet einen Preis in einer anderen Währung und rundet diesen nach Kundenwunsch.
     * 
     * @param float $price
     * @param float $rate
     * @return float
     */
    private function calculatePrice($price, $rate) {
        if (!$price) {
            return '';
        }
        $realPrice = floatval($price) * $rate;
        $endPrice = 0;
        $roundedPrice = ceil($realPrice / 10.0) * 10 - 1; // auf Zehnerstelle nach oben runden und eins abziehen 311 -> 319
        if ($realPrice >= 1000) {
            if (substr($roundedPrice, 1, 2) == substr($roundedPrice - 20, 1, 2)) { // Ändern sich die vordersten 2 Ziffern, wenn ich 20 abziehe?
                $endPrice = $roundedPrice;
            } else {
                $endPrice = (floor($roundedPrice / 100) * 100) - 1;
            }
        } else {
            $endPrice = $roundedPrice;
        }
        return $endPrice;
    }

}
