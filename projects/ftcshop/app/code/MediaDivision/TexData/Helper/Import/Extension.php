<?php

namespace MediaDivision\TexData\Helper\Import;

use \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use MediaDivision\TexData\Helper\Import\AbstractImport;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\Filesystem\DirectoryList;

class Extension extends AbstractImport
{

    private $simple = [];
    private $configurable = [];
    protected $articleTemplate = [
        "sku" => "__MAGMI_IGNORE__",
        "store" => "__MAGMI_IGNORE__",
        "name" => "__MAGMI_IGNORE__",
        "url_key" => "__MAGMI_IGNORE__",
        "description" => "__MAGMI_IGNORE__",
        "short_description" => "__MAGMI_IGNORE__",
        "configurable_attributes" => "__MAGMI_IGNORE__",
        "simples_skus" => "__MAGMI_IGNORE__",
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
            echo "\n\nHole Extension-Daten.\n";
        }

        $fileList = $this->getFileList("extension", "artikelfarbtexte", $debug, $reimport);

        foreach ($fileList as $filename) {
            $xml = simplexml_load_file($filename);
            $xmlArticleList = $xml->{'FIRMA-NR'}->ARTIKELFARBE;

            foreach ($xmlArticleList as $article) {
                $confList = [];
                $form = $article->FORM;
                $qual = $article->QUAL;
                $farbe = $article->FARBE;
                $confSku = sprintf("%05d-%04d", $form, $qual);
                $longNameDE = (string) $article->ARTIKELBEZ01;
                $longNameEn = (string) $article->ARTIKELBEZ02;
                $sDescrDe = (string) $article->BULLET101;
                $sDescrEn = (string) $article->BULLET102;
                $urlKeyRawDe = $confSku . "-" . preg_replace('/[^0-9,a-z]/', '-', strtolower($longNameDE));
                $urlKeyDe = preg_replace('/-+/', '-', $urlKeyRawDe);
                //$urlKeyRawEn = $confSku . "-" . preg_replace('/[^0-9,a-z]/', '-', strtolower($this->deepl->translate($longNameDE, 'EN', $debug)));
                $urlKeyRawEn = $confSku . "-" . preg_replace('/[^0-9,a-z]/', '-', strtolower($longNameEn));
                $urlKeyEn = preg_replace('/-+/', '-', $urlKeyRawEn);


                foreach ($this->stores as $store) {
                    $confList[$confSku . "_" . $store] = $this->getArticleTemplate();
                    $confList[$confSku . "_" . $store]["sku"] = $confSku;
                    $confList[$confSku . "_" . $store]["store"] = $store;
                    $confList[$confSku . "_" . $store]["configurable_attributes"] = $this->configurableAttributes;
                    $confList[$confSku . "_" . $store]["simples_skus"] = implode(",", $this->searchSimpleSkuList($form, $qual));
                }

                if ($longNameDE) {
                    $confList[$confSku . "_admin"]["name"] = $longNameDE;
                    $confList[$confSku . "_admin"]["url_key"] = $urlKeyDe;
                    foreach ($this->deStores as $store) {
                        $confList[$confSku . "_" . $store]["name"] = $longNameDE;
                        $confList[$confSku . "_" . $store]["url_key"] = $urlKeyDe;
                    }
                }

                if ($longNameDE) {
                    foreach ($this->enStores as $store) {
                        //$urlKeyRaw = $confSku . "-" . preg_replace('/[^0-9,a-z]/', '-', strtolower($this->deepl->translate($longNameDE, 'EN', $debug)));
                        $urlKeyRaw = $confSku . "-" . preg_replace('/[^0-9,a-z]/', '-', strtolower($longNameEn));
                        $urlKey = preg_replace('/-+/', '-', $urlKeyRaw);
                        //$confList[$confSku . "_" . $store]["name"] = $this->deepl->translate($longNameDE, 'EN', $debug);
                        $confList[$confSku . "_" . $store]["name"] = $longNameEn;
                        $confList[$confSku . "_" . $store]["url_key"] = $urlKeyEn;
                    }
                }

                if ($sDescrDe) {
                    $confList[$confSku . "_admin"]['short_description'] = $sDescrDe;
                    foreach ($this->deStores as $store) {
                        $confList[$confSku . "_" . $store]['short_description'] = $sDescrDe;
                    }
                }
                if ($sDescrEn) {
                    foreach ($this->enStores as $store) {
                        $confList[$confSku . "_" . $store]['short_description'] = $sDescrEn;
                    }
                }
                foreach ($confList as $item) {
                    $this->configurable[] = $item;
                }
                $this->dot();

                $simpleSkuList = $this->searchSimpleSkuList($form, $qual, $farbe);
                foreach ($simpleSkuList as $sku) {
                    $simpleList = [];
                    $longNameDE = (string) $article->ARTIKELBEZ01;
                    $longNameEn = (string) $article->ARTIKELBEZ02;
                    $sDescrDe = (string) $article->BULLET101;
                    $sDescrEn = (string) $article->BULLET102;

                    foreach ($this->stores as $store) {
                        $simpleList[$sku . "_" . $store] = $this->getArticleTemplate();
                        $simpleList[$sku . "_" . $store]["sku"] = $sku;
                        $simpleList[$sku . "_" . $store]["store"] = $store;
                    }
                    $simpleList[$sku . "_admin"]["description"] = (string) $article->TEXT01;
                    foreach ($this->deStores as $store) {
                        $simpleList[$sku . "_" . $store]["description"] = (string) $article->TEXT01;
                    }
                    foreach ($this->enStores as $store) {
                        $simpleList[$sku . "_" . $store]["description"] = (string) $article->TEXT02;
                    }

                    if ($longNameDE) {
                        $simpleList[$sku . "_admin"]['name'] = $longNameDE;
                        foreach ($this->deStores as $store) {
                            $simpleList[$sku . "_" . $store]["name"] = $longNameDE;
                        }
                    }

                    if ($longNameDE) {
                        foreach ($this->enStores as $store) {
                            //$simpleList[$sku . "_" . $store]['name'] = $this->deepl->translate($longNameDE, 'EN', $debug);
                            $simpleList[$sku . "_" . $store]['name'] = $longNameEn;
                        }
                    }

                    if ($sDescrDe) {
                        $simpleList[$sku . "_admin"]['short_description'] = $sDescrDe;
                        foreach ($this->deStores as $store) {
                            $simpleList[$sku . "_" . $store]["short_descripiton"] = $sDescrDe;
                        }
                    }

                    if ($sDescrEn) {
                        foreach ($this->enStores as $store) {
                            $simpleList[$sku . "_" . $store]['short_description'] = $sDescrEn;
                        }
                    }
                    foreach ($simpleList as $item) {
                        $this->simple[] = $item;
                    }
                    $this->dot();
                }
            }
        }
        return array_merge($this->simple, $this->configurable);
    }

}
