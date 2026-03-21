<?php

namespace MediaDivision\TexData\Helper\Import;

use MediaDivision\TexData\Helper\Import\AbstractImport;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\Filesystem\DirectoryList;
use \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class Attribute extends AbstractImport
{

    private $simple = [];
    private $configurable = [];
    protected $articleTemplate = [
        "sku" => "__MAGMI_IGNORE__",
        "store" => "__MAGMI_IGNORE__",
        "material" => "__MAGMI_IGNORE__",
        "seacell" => "__MAGMI_IGNORE__",
        "new" => "__MAGMI_IGNORE__",
        "sale" => "__MAGMI_IGNORE__",
        "upcycled" => "__MAGMI_IGNORE__",
        "cashmere" => "__MAGMI_IGNORE__",
        "configurable_attributes" => "__MAGMI_IGNORE__",
        "simples_skus" => "__MAGMI_IGNORE__",
        "materials" => [],
        "themes" => "__MAGMI_IGNORE__"
    ];

    public function __construct(
            Context $context,
            DirectoryList $directoryList,
            CollectionFactory $productCollectionFactory) {
        parent::__construct($productCollectionFactory, $context, $directoryList);
    }

    public function getData($debug = false, $reimport = false) {
        $this->debug = $debug;
        $this->reimport = $reimport;
        if ($this->debug) {
            echo "\n\nHole Attribute-Daten.\n";
        }

        $fileList = $this->getFileList("attribute", "attribute_", $debug, $reimport);
        $skuList = $this->getSkuList();

        foreach ($fileList as $filename) {
            $confList = [];
            $xml = simplexml_load_file($filename);
            $article = $xml->BODY->{'FIRMA-NR'}->{'SHOP-NR'}->ARTIKELNR;

            $confSku = $this->buildSku($article->FORM, $article->QUAL);

            // admin - Produkt
            $confList[$confSku . "_admin"] = $this->getArticleTemplate();
            $confList[$confSku . "_admin"]["sku"] = $confSku;
            $confList[$confSku . "_admin"]["store"] = "admin";
            $confList[$confSku . "_admin"]["seacell"] = 0;
            $confList[$confSku . "_admin"]["new"] = 0;
            $confList[$confSku . "_admin"]["sale"] = 0;
            $confList[$confSku . "_admin"]["upcycled"] = 0;
            $confList[$confSku . "_admin"]["materials"] = [];
            $confList[$confSku . "_admin"]["themes"] = [];
            $confList[$confSku . "_admin"]["configurable_attributes"] = $this->configurableAttributes;
            $confList[$confSku . "_admin"]["simples_skus"] = implode(",", $this->searchSimpleSkuList($article->FORM, $article->QUAL));

            foreach ($article->ATTRIBUT as $attribute) {
                if ($attribute->KATEGORIE_SP1 == "Material") {
                    $materialDe = (string) $attribute->INHALT_SP1;
                    if ($materialDe) {
                        $confList[$confSku . "_admin"]["material"] = $materialDe;
                        $confList[$confSku . "_admin"]["materials"][] = $materialDe;
                        foreach ($this->deStores as $store) {
                            $confList[$confSku . "_" . $store] = $this->getArticleTemplate();
                            $confList[$confSku . "_" . $store]["sku"] = $confSku;
                            $confList[$confSku . "_" . $store]["store"] = $store;
                            $confList[$confSku . "_" . $store]["material"] = $materialDe;
                            $confList[$confSku . "_" . $store]["materials"] = "__MAGMI_IGNORE__";
                            $confList[$confSku . "_" . $store]["configurable_attributes"] = $this->configurableAttributes;
                            $confList[$confSku . "_" . $store]["simples_skus"] = implode(",", $this->searchSimpleSkuList($article->FORM, $article->QUAL));
                        }
                    }

                    $materialEn = (string) $attribute->INHALT_SP2;
                    if ($materialEn) {
                        foreach ($this->enStores as $store) {
                            $confList[$confSku . "_" . $store] = $this->getArticleTemplate();
                            $confList[$confSku . "_" . $store]["sku"] = $confSku;
                            $confList[$confSku . "_" . $store]["store"] = $store;
                            $confList[$confSku . "_" . $store]["material"] = $materialEn;
                            $confList[$confSku . "_" . $store]["materials"] = "__MAGMI_IGNORE__";
                            $confList[$confSku . "_" . $store]["configurable_attributes"] = $this->configurableAttributes;
                            $confList[$confSku . "_" . $store]["simples_skus"] = implode(",", $this->searchSimpleSkuList($article->FORM, $article->QUAL));
                        }
                    }
                }
                if ($attribute->KATEGORIE_SP1 == "Neu") {
                    $confList[$confSku . "_admin"]["new"] = (int) $attribute->INHALT_SP1;
                    $confList[$confSku . "_admin"]["themes"][] = "New";
                }
                if ($attribute->KATEGORIE_SP1 == "Sale") {
                    $confList[$confSku . "_admin"]["sale"] = (int) $attribute->INHALT_SP1;
                    $confList[$confSku . "_admin"]["themes"][] = "Sale";
                }
                if ($attribute->KATEGORIE_SP1 == "Upcycled") {
                    $confList[$confSku . "_admin"]["upcycled"] = (int) $attribute->INHALT_SP1;
                    $confList[$confSku . "_admin"]["themes"][] = "Upcycled";
                }
                if ($attribute->KATEGORIE_SP1 == "Seacell") {
                    $confList[$confSku . "_admin"]["seacell"] = (int) $attribute->INHALT_SP1;
                    $confList[$confSku . "_admin"]["materials"][] = "Seacell";
                }
                if ($attribute->KATEGORIE_SP1 == "Cashmere") {
                    $confList[$confSku . "_admin"]["cashmere"] = (int) $attribute->INHALT_SP1;
                    $confList[$confSku . "_admin"]["materials"][] = "Cashmere";
                }

                // Wenn cashmere nicht vorhanden ist -> auf 1 setzen
                if (!isset($admin["cashmere"])) {
                    $confList[$confSku . "_admin"]["cashmere"] = 1;
                }
                $this->dot();
            }
            foreach ($confList as $item) {
                $item["materials"] = is_array($item["materials"]) ? implode(',',$item["materials"]) : "__MAGMI_IGNORE__";
                $item["themes"] = is_array($item["themes"]) ? implode(',',$item["themes"]) : "__MAGMI_IGNORE__";
                $this->configurable[] = $item;
            }
            $simpleSkuList = $this->searchSimpleSkuList($article->FORM, $article->QUAL);
            foreach ($simpleSkuList as $sku) {
                $simpleAdmin = $this->getArticleTemplate();
                $simpleAdmin["sku"] = $sku;
                $simpleAdmin["store"] = "admin";
                $simpleAdmin["seacell"] = 0;
                $simpleAdmin["new"] = 0;
                $simpleAdmin["sale"] = 0;
                $simpleAdmin["upcycled"] = 0;
                $simpleAdmin["seacell"] = 0;
                $simpleAdmin["new"] = 0;
                $simpleAdmin["sale"] = 0;
                $simpleAdmin["upcycled"] = 0;
                $simpleAdmin["materials"] = [];

                $materialDe = (string) $article->ATTRIBUT->INHALT_SP1;
                if ($materialDe) {
                    $simpleAdmin["material"] = $materialDe;
                    $simpleAdmin["materials"][] = $materialDe;
                    foreach ($this->deStores as $store) {
                        $simple = $this->getArticleTemplate();
                        $simple["sku"] = $sku;
                        $simple["store"] = $store;
                        $simple["material"] = $materialDe;
                        $simple["materials"] = "__MAGMI_IGNORE__";
                        $this->simple[] = $simple;
                    }
                }

                $materialEn = (string) $article->ATTRIBUT->INHALT_SP2;
                if ($materialEn) {
                    foreach ($this->enStores as $store) {
                        $simple = $this->getArticleTemplate();
                        $simple["sku"] = $sku;
                        $simple["store"] = $store;
                        $simple["material"] = $materialEn;
                        $simple["materials"] = "__MAGMI_IGNORE__";
                        $this->simple[] = $simple;
                    }
                }
                $simpleAdmin["materials"] = is_array($simpleAdmin["materials"]) ? implode(',',$simpleAdmin["materials"]) : "__MAGMI_IGNORE__";
                $this->simple[] = $simpleAdmin;
                $this->dot();
            }
        }
        return array_merge($this->simple, $this->configurable);
    }

}
