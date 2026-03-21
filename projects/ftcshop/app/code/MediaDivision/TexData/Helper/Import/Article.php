<?php

namespace MediaDivision\TexData\Helper\Import;

use \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use MediaDivision\TexData\Helper\Import\AbstractImport;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\Filesystem\DirectoryList;

class Article extends AbstractImport
{

    private $simple = [];
    private $configurable = [];
    private $shopProduct = [];
    protected $articleTemplate = [
        "sku" => "__MAGMI_IGNORE__",
        "store" => "__MAGMI_IGNORE__",
        "ean" => "__MAGMI_IGNORE__",
        "saison_sku" => "__MAGMI_IGNORE__",
        "status" => "__MAGMI_IGNORE__",
        "tax_class_id" => "__MAGMI_IGNORE__",
        "material" => "__MAGMI_IGNORE__",
        "materials" => "__MAGMI_IGNORE__",
        "weight" => "__MAGMI_IGNORE__",
        "type" => "__MAGMI_IGNORE__",
        "visibility" => "__MAGMI_IGNORE__",
        "sorting" => "__MAGMI_IGNORE__",
        "is_in_stock" => "__MAGMI_IGNORE__",
        "artikelsaison" => "__MAGMI_IGNORE__",
        "color" => "__MAGMI_IGNORE__",
        "color_name" => "__MAGMI_IGNORE__",
        "color_group" => "__MAGMI_IGNORE__",
        "color_group_name" => "__MAGMI_IGNORE__",
        "rgb_farbe" => "__MAGMI_IGNORE__",
        "rgb_farbgruppe" => "__MAGMI_IGNORE__",
        "size" => "__MAGMI_IGNORE__",
        "form" => "__MAGMI_IGNORE__",
        "qual" => "__MAGMI_IGNORE__",
        "bereich" => "__MAGMI_IGNORE__",
        "gruppe" => "__MAGMI_IGNORE__",
        "kollektion" => "__MAGMI_IGNORE__",
        "material_zusatz_bezeichnung_1" => "__MAGMI_IGNORE__",
        "material_zusatz_bezeichnung_2" => "__MAGMI_IGNORE__",
        "material_zusatz_bezeichnung_3" => "__MAGMI_IGNORE__",
        "material_zusatz_bezeichnung_4" => "__MAGMI_IGNORE__",
        "material_zusatz_bezeichnung_5" => "__MAGMI_IGNORE__",
        "material_zusatz_prozent_1" => "__MAGMI_IGNORE__",
        "material_zusatz_prozent_2" => "__MAGMI_IGNORE__",
        "material_zusatz_prozent_3" => "__MAGMI_IGNORE__",
        "material_zusatz_prozent_4" => "__MAGMI_IGNORE__",
        "material_zusatz_prozent_5" => "__MAGMI_IGNORE__",
        "pflegesymbol" => "__MAGMI_IGNORE__",
        "pflegesymbol1" => "__MAGMI_IGNORE__",
        "pflegesymbol2" => "__MAGMI_IGNORE__",
        "pflegesymbol3" => "__MAGMI_IGNORE__",
        "pflegesymbol4" => "__MAGMI_IGNORE__",
        "pflegesymbol5" => "__MAGMI_IGNORE__",
        "pflegebezeichnung1" => "__MAGMI_IGNORE__",
        "pflegebezeichnung2" => "__MAGMI_IGNORE__",
        "pflegebezeichnung3" => "__MAGMI_IGNORE__",
        "pflegebezeichnung4" => "__MAGMI_IGNORE__",
        "pflegebezeichnung5" => "__MAGMI_IGNORE__",
        "pflegehinweis" => "__MAGMI_IGNORE__",
        "configurable_attributes" => "__MAGMI_IGNORE__",
        "simples_skus" => "__MAGMI_IGNORE__",
        "category_ids" => "__MAGMI_IGNORE__",
    ];

    public function __construct(
            Context $context,
            DirectoryList $directoryList,
            CollectionFactory $productCollectionFactory) {
        parent::__construct($productCollectionFactory, $context, $directoryList);
    }

    public function getData($debug, $reimport) {
        $this->debug = $debug;
        $this->reimport = $reimport;
        if ($this->debug) {
            echo "Hole Artikel-Daten.\n";
        }
        $this->getSkuList();
        
        $this->getShopProducts(); // Gewiise Attribute in bestehende Artikeln nicht überschreiben.
        $fileList = $this->getFileList("article", "artikel_", $debug, $reimport);
        foreach ($fileList as $filename) {
            $xml = simplexml_load_file($filename);
            $this->analyzeConfigurable($xml->BODY->{"FIRMA-NR"}->SHOP->ARTIKELSTAMM);
        }
        return array_merge($this->simple, $this->configurable);
    }

    /**
     * Analysiere den Artikel, um die Daten für das konfigurierbare Produkt zu extrahieren.
     *
     * @param SimpleXMLElement $artikel
     */
    private function analyzeConfigurable($artikel) {
        $sku = (string) $artikel->ARTIKEL;
        $simples_skus = $this->analyzeSimple($artikel, $sku);
        $this->dot();
        $item = $this->getArticleTemplate();
        $item["sku"] = $sku;
        $item["saison_sku"] = substr($sku, 0, 2);
        $item["store"] = "admin";
        $item["status"] = isset($this->shopProduct[$sku]) ? $this->shopProduct[$sku]["status"] : 1;
        $item["type"] = "configurable";
        $item["visibility"] = 4; // Katalog, Suche
        $item["is_in_stock"] = 1; // Lagerbestand -> Auf Lager
        $item["tax_class_id"] = 2; // MwSt. 20%
        $item["configurable_attributes"] = $this->configurableAttributes;
        $item["simples_skus"] = implode(",", $simples_skus);
        $item["form"] = (string) $artikel->FORM;
        $item["qual"] = (string) $artikel->QUAL;
        $item["artikelsaison"] = (string) $artikel->ARTIKELSAISON;
        $item["bereich"] = (string) $artikel->PRODUKTBEREICH;
        $item["gruppe"] = (string) $artikel->PRODUKTGRUPPE;
        $item["sorting"] = isset($this->shopProduct[$sku]) ? $this->shopProduct[$sku]["sorting"] : 1000;
        $item["kollektion"] = $artikel->KOLLEKTION;
        $item["material_zusatz_bezeichnung_1"] = $artikel->MATZUSBEZ1_SP0;
        $item["material_zusatz_bezeichnung_2"] = $artikel->MATZUSBEZ2_SP0;
        $item["material_zusatz_bezeichnung_3"] = $artikel->MATZUSBEZ3_SP0;
        $item["material_zusatz_bezeichnung_4"] = $artikel->MATZUSBEZ4_SP0;
        $item["material_zusatz_bezeichnung_5"] = $artikel->MATZUSBEZ5_SP0;
        $item["material_zusatz_prozent_1"] = $artikel->ARMATZUSPROZ1;
        $item["material_zusatz_prozent_2"] = $artikel->ARMATZUSPROZ2;
        $item["material_zusatz_prozent_3"] = $artikel->ARMATZUSPROZ3;
        $item["material_zusatz_prozent_4"] = $artikel->ARMATZUSPROZ4;
        $item["material_zusatz_prozent_5"] = $artikel->ARMATZUSPROZ5;
        $item["pflegesymbol"] = $artikel->PFLEGESYMBOL; // ASCII-Zeichen für Sartex Schrift für Pflegesymbole L;d;-;h;"                                                                                                                  
        $item["pflegesymbol1"] = $artikel->PFLEGESYMBOL1; // leer
        $item["pflegesymbol2"] = $artikel->PFLEGESYMBOL2; // leer
        $item["pflegesymbol3"] = $artikel->PFLEGESYMBOL3; // leer
        $item["pflegesymbol4"] = $artikel->PFLEGESYMBOL4; // leer
        $item["pflegesymbol5"] = $artikel->PFLEGESYMBOL5; // leer
        $item["pflegebezeichnung1"] = $artikel->PFLEGEBEZ1_SP0;
        $item["pflegebezeichnung2"] = $artikel->PFLEGEBEZ2_SP0;
        $item["pflegebezeichnung3"] = $artikel->PFLEGEBEZ3_SP0;
        $item["pflegebezeichnung4"] = $artikel->PFLEGEBEZ4_SP0;
        $item["pflegebezeichnung5"] = $artikel->PFLEGEBEZ5_SP0;
        $item["pflegehinweis"] = substr($artikel->PFLEGEHINWEIS, 0, 250);
//        if (in_array($sku, $this->skuList)) {
            $item["category_ids"] = "__MAGMI_IGNORE__";
//        } else {
//            $item["category_ids"] = 3; // What's New
//        }
//        $item["materials"] = [];
//        foreach ([1,2,3,4,5] as $number) {
//            $index = "material_zusatz_bezeichnung_" . $number;
//            if($item[$index] != "") {
//                $item["materials"][] = $item[$index];
//            }
//        }
//        $item["materials"] = implode(",",$item["materials"]);
        $this->configurable[] = $item;

        // die Unterschiede in den Stores als extra Artikel anlegen
        foreach ($this->deStores as $store) {
            $item = $this->getArticleTemplate();
            $item["sku"] = $sku;
            $item["store"] = $store;
            $item["configurable_attributes"] = $this->configurableAttributes;
            $item["simples_skus"] = implode(",", $simples_skus);
            $item["material_zusatz_bezeichnung_1"] = $artikel->MATZUSBEZ1_SP0;
            $item["material_zusatz_bezeichnung_2"] = $artikel->MATZUSBEZ2_SP0;
            $item["material_zusatz_bezeichnung_3"] = $artikel->MATZUSBEZ3_SP0;
            $item["material_zusatz_bezeichnung_4"] = $artikel->MATZUSBEZ4_SP0;
            $item["material_zusatz_bezeichnung_5"] = $artikel->MATZUSBEZ5_SP0;
            $item["pflegebezeichnung1"] = $artikel->PFLEGEBEZ1_SP0;
            $item["pflegebezeichnung2"] = $artikel->PFLEGEBEZ2_SP0;
            $item["pflegebezeichnung3"] = $artikel->PFLEGEBEZ3_SP0;
            $item["pflegebezeichnung4"] = $artikel->PFLEGEBEZ4_SP0;
            $item["pflegebezeichnung5"] = $artikel->PFLEGEBEZ5_SP0;
            $this->configurable[] = $item;
        }
        foreach ($this->enStores as $store) {
            $item = $this->getArticleTemplate();
            $item["sku"] = $sku;
            $item["store"] = $store;
            $item["configurable_attributes"] = $this->configurableAttributes;
            $item["simples_skus"] = implode(",", $simples_skus);
            $item["material_zusatz_bezeichnung_1"] = $artikel->MATZUSBEZ1_SP1;
            $item["material_zusatz_bezeichnung_2"] = $artikel->MATZUSBEZ2_SP1;
            $item["material_zusatz_bezeichnung_3"] = $artikel->MATZUSBEZ3_SP1;
            $item["material_zusatz_bezeichnung_4"] = $artikel->MATZUSBEZ4_SP1;
            $item["material_zusatz_bezeichnung_5"] = $artikel->MATZUSBEZ5_SP1;
            $item["pflegebezeichnung1"] = $artikel->PFLEGEBEZ1_SP1;
            $item["pflegebezeichnung2"] = $artikel->PFLEGEBEZ2_SP1;
            $item["pflegebezeichnung3"] = $artikel->PFLEGEBEZ3_SP1;
            $item["pflegebezeichnung4"] = $artikel->PFLEGEBEZ4_SP1;
            $item["pflegebezeichnung5"] = $artikel->PFLEGEBEZ5_SP1;
            $this->configurable[] = $item;
        }
    }

    /**
     *
     * @param type $artikelStructure
     * @param type $parentSku
     */
    private function analyzeSimple($artikelStructure, $parentSku) {
        $simples_skus = [];
        $artikelList = $artikelStructure->KATALOG;
        //echo $parentSku . "\n";
        foreach ($artikelList as $artikel) {
            foreach ($artikel->FARBEN as $farbe) {
                foreach ($farbe->VARIANTEN as $variante) {
                    //echo $variante->VARBEZ . "\n";
                    foreach ($variante->GROESSEN as $groesse) {
                        $colorgroup = "";
                        if (isset($this->colorgroupList[(string) $farbe->FARBE])) {
                            $colorgroup = $this->colorgroupList[(string) $farbe->FARBE];
                        }
                        $sku = $parentSku . '_' . $farbe->FARBE . "_" . $groesse->GROESSE;
                        $simples_skus[] = $sku;
                        $this->dot();
                        $item = $this->getArticleTemplate();
                        $item["sku"] = (string) $sku;
                        $item["store"] = "admin";
                        $item["type"] = "simple";
                        $item["tax_class_id"] = 2; // MwSt. 20%
                        $item["visibility"] = 1; // Einzeln nicht sichtbar
                        $item["ean"] = (string) $groesse->EAN;
                        $item["material"] = (string) $artikelStructure->ARTIKELBEZ2_SP0;
                        $item["weight"] = ($groesse->GEWICHT) / 1000.0;
                        $item["color"] = (string) $farbe->FARBE;
                        $item["color_name"] = (string) $farbe->FARBEBEZ;
                        $item["rgb_farbe"] = (string) $farbe->RGBFARBE;
                        $item["rgb_farbgruppe"] = (string) $farbe->RGBFARBGRUPPE;
                        $item["color_group"] = (string) $farbe->FARBGRUPPE;
                        $item["color_group_name"] = (string) $farbe->FARBGRUPPE_SP;
                        $item["size"] = (string) $groesse->GROESSE;
                        $item["form"] = (string) $artikelStructure->FORM;
                        $item["qual"] = (string) $artikelStructure->QUAL;
                        $item["artikelsaison"] = (string) $artikelStructure->ARTIKELSAISON;
                        $item["status"] = isset($this->shopProduct[$sku]) ? $this->shopProduct[$sku]["status"] : 1;
                        $this->simple[] = $item;

                        foreach ($this->deStores as $store) {
                            $item = $this->getArticleTemplate();
                            $item["sku"] = (string) $sku;
                            $item["store"] = $store;
                            $item["color_name"] = (string) $farbe->FARBEBEZ;
                            $item["color_group_name"] = (string) $farbe->FARBGRUPPE_SP;
                            $this->simple[] = $item;
                        }
                        foreach ($this->enStores as $store) {
                            $item = $this->getArticleTemplate();
                            $item["sku"] = (string) $sku;
                            $item["store"] = $store;
                            $item["color_name"] = (string) $farbe->FARBEBEZ1;
                            // color_group_name ist ein globaler Wert. Da kann man keine unterschiedlichen Sprachen einfügen.
                            //$item["color_group_name"] = (string) $farbe->FARBGRUPPE_SP1; 
                            $this->simple[] = $item;
                        }
                    }
                }
            }
        }
        return $simples_skus;
    }

    /**
     * Besorgt die Produkt-Attribute der bereits vorhandenen Produkte, die beim Import berücksichtigt werden müssen
     */
    private function getShopProducts() {
        $this->shopProduct = [];
        $productCollection = $this->productCollectionFactory->create()
                ->addAttributeToSelect("sku")
                ->addAttributeToSelect("sorting")
                ->addAttributeToSelect("status");
        foreach ($productCollection as $product) {
            $this->shopProduct[$product->getSku()] = [
                "sorting" => $product->getSorting(),
                "status" => $product->getStatus(),
            ];
        }
    }

}
