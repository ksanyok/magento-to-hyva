<?php

namespace MediaDivision\TexData\Helper\Import;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\Filesystem\DirectoryList;
use \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

abstract class AbstractImport extends AbstractHelper
{

    protected $count = 0;
    protected $debug;
    protected $fileDir = "/var/texdata/";
    protected $installDir;
    protected $reimport;
    protected $productCollectionFactory;
    protected $skuList;
    protected $configurableAttributes = "color_name,size";
    protected $articleTemplate;

    /**
     * Vorhandene Stores
     * 1. Deutschland - deutsch (de_de) 
     * 2. Deutschland - englisch (en_de)
     * 3. Österreich - deutsch (de_at)
     * 4. Österreich - englisch (en_at)
     * 5. Schweden - englisch (en_se)
     * 6. Dänemark - englisch (en_dk)
     * 7. Niederlande - englisch (en_nl)
     * 8. Polen - englisch (en_pl)
     * 9. Schweiz - deutsch (de_ch)
     * 10. Schweiz - englisch (en_ch)
     * 11. EU - englisch
     * 12. International - englisch
     *
     * @var array
     */
    protected $stores = [
        "admin",
        "de-de",
        "en-de",
        "de-at",
        "en-at",
        "en-se",
        "en-dk",
        "en-nl",
        "en-pl",
        "de-ch",
        "en-ch",
        "en-be",
        "en-fr",
        "en-it",
        //"en-eu",
        //"en-us",
    ];
    protected $enStores = [
        "en-de",
        "en-at",
        "en-se",
        "en-dk",
        "en-nl",
        "en-pl",
        "en-ch",
        "en-be",
        "en-fr",
        "en-it",
        //"en-eu",
        //"en-us",
    ];
    protected $deStores = [
        "de-de",
        "de-at",
        "de-ch",
    ];

    public function __construct(CollectionFactory $productCollectionFactory, Context $context, DirectoryList $directoryList) {
        $this->installDir = $directoryList->getRoot();
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($context);
    }

    protected function getFileList($path, $fileGlob, $debug, $reimport) {
        $this->debug = $debug;
        $this->reimport = $reimport;

        $this->moveToWorkinDirectory($path, $fileGlob, $reimport);

        $fileList = [];
        foreach (glob($this->installDir . $this->fileDir . $path . "/work/" . $fileGlob . "*") as $filename) {
            $fileList[] = $filename;
        }

        return $fileList;
    }

    protected function getArticleTemplate() {
        return $this->articleTemplate;
    }

    protected function moveToWorkinDirectory($path, $fileGlob, $reimport = false) {
        $dir = $this->installDir . $this->fileDir . $path . "/";
        if (!file_exists($dir . "work/")) {
            mkdir($dir . "work/");
        }
        // Bei einem zweiten Import derselben Daten, Dateien weder löschen noch verschieben
        if (!$reimport) {
            foreach (glob($dir . "work/*") as $filename) {
                unlink($filename);
            }
            foreach (glob($dir . $fileGlob . "*") as $filename) {
                $newfilename = preg_replace('?' . $dir . '?', $dir . "work/", $filename);
                rename($filename, $newfilename);
            }
        }
    }

    protected function buildSku($form, $qual, $color = false, $size = false) {
        $confSku = sprintf("%05d-%04d", $form, $qual);
        if ($color && $size) {
            return $confSku . "_" . $color . "_" . $size;
        } else {
            return $confSku;
        }
    }

    protected function dot($reset = false) {
        if(!$this->debug) {
            return;
        }
        if($reset) {
            $this->count = 0;
            return;
        }
        $this->count++;
        echo ".";
        if (($this->count % 10) == 0) {
            echo " ";
        }
        if (($this->count % 100) == 0) {
            echo " " . $this->count . "\n";
        }
    }

    public function searchSimpleSkuList($form, $qual, $farbe = '') {
        $skuList = [];
        $confSku = sprintf("%05d-%04d", $form, $qual);
        foreach ($this->getSkuList() as $sku) {
            if (preg_match('/^' . $confSku . '_' . $farbe . '/', $sku)) {
                $skuList[] = $sku;
            }
        }
        return $skuList;
    }

    protected function getSkuList() {
        if (!$this->skuList) {
            $productCollection = $this->productCollectionFactory->create()->addAttributeToSelect("sku");
            foreach ($productCollection as $product) {
                $this->skuList[] = $product->getSku();
            }
        }
        return $this->skuList;
    }

}
