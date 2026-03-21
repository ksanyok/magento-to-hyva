<?php

namespace MediaDivision\TexData\Helper\Import;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\DirectoryList;
use MediaDivision\Image\Helper\Data as ImageHelper;
use MediaDivision\TexData\Helper\Import\AbstractImport;

class Images extends AbstractImport
{

    private $archiveDir = '/pub/media/import/images/archive/';
    protected $articleTemplate = [
        "sku" => "__MAGMI_IGNORE__",
        "store" => "__MAGMI_IGNORE__",
        "image" => "__MAGMI_IGNORE__",
        "small_image" => "__MAGMI_IGNORE__",
        "thumbnail" => "__MAGMI_IGNORE__",
        "media_gallery" => "__MAGMI_IGNORE__",
        "configurable_attributes" => "__MAGMI_IGNORE__",
        "simples_skus" => "__MAGMI_IGNORE__",
    ];
    private $configurable = [];
    private $imageDir = '/pub/media/import/images/';
    private $imageHelper;
    private $simple = [];

    public function __construct(
            Context $context,
            DirectoryList $directoryList,
            CollectionFactory $productCollectionFactory,
            ImageHelper $imageHelper) {
        $this->installDir = $directoryList->getRoot();
        $this->imageHelper = $imageHelper;
        parent::__construct($productCollectionFactory, $context, $directoryList);
    }

    public function getData($debug) {
        $this->debug = $debug;
        if ($this->debug) {
            echo "Hole Bild-Daten.\n";
        }
        foreach ($this->getSkuList() as $sku) {
            $article = $this->getArticleTemplate();
            $article["sku"] = $sku;
            $article["store"] = "admin";

            if ($this->isConfigurable($sku)) {
                $images = $this->getConfigurableImages($sku);
                $formQual = explode('-', $sku);
                $simpleSkuList = $this->searchSimpleSkuList($formQual[0], $formQual[1]);

                if ($images["image"]) {
                    $article["image"] = $images["image"];
                    $article["small_image"] = $images["small_image"];
                    $article["thumbnail"] = $images["thumbnail"];
                    $article["media_gallery"] = $images["media_gallery"];
                    $article["configurable_attributes"] = $this->configurableAttributes;
                    $article["simples_skus"] = implode(',', $simpleSkuList);
                    $this->configurable[] = $article;
                    if ($this->debug) {
                        $this->dot();
                    }
                }
            } else {
                $images = $this->getSimpleImages($sku);

                if ($images["image"]) {
                    $article["image"] = $images["image"];
                    $article["small_image"] = $images["small_image"];
                    $article["thumbnail"] = $images["thumbnail"];
                    $article["media_gallery"] = $images["media_gallery"];
                    $this->simple[] = $article;
                }
            }
        }
        return array_merge($this->simple, $this->configurable);
    }

    public function archiveImages($data) {
        if (!file_exists($this->installDir . $this->archiveDir)) {
            mkdir($this->installDir . $this->archiveDir);
        }

        foreach ($data as $item) {
            if ($item["image"] && file_exists($this->installDir . $this->imageDir . $item["image"])) {
                $this->dot();
                rename($this->installDir . $this->imageDir . $item["image"], $this->installDir . $this->archiveDir . $item["image"]);
            }
            foreach (explode(";", $item["media_gallery"]) as $image) {
                $filename = preg_replace('/::.*/', '', $image);
                if ($filename && file_exists($this->installDir . $this->imageDir . $filename)) {
                    $this->dot();
                    rename($this->installDir . $this->imageDir . $filename, $this->installDir . $this->archiveDir . $filename);
                }
            }
        }
    }

    public function resizeImportedImages($imageData) {
        foreach($imageData as $item) {
            $this->imageHelper->resizeImages($item["sku"]);
        }
    }
    
    private function isConfigurable($sku) {
        return !preg_match('/_/', $sku);
    }

    /**
     * wenn ein konfigurierbarer Artikel einen einfachen Artikel mit Modelbild enthält (Kennzeichnung mf1) 
     * > dann muss dieser einfache Artikel zuerst in PDP angezeigt werden
     * 
     * wenn ein konfigurierbarer Artikel mehrere einfache Artikel mit Modelbild enthält (Kennzeichnung mf1) 
     * > dann geht es nach der Farbnummer, welches Bild zuerst in der PDP angezeigt wird
     * 
     * wenn im konfigurierbaren Artikel kein einziger einfacher Artikel ein Modelbild enthält, 
     * > dann Freisteller front (pfg) desjenigen Produktes mit dem niedrigsten Farbcode.
     */
    private function getConfigurableImages($sku) {
        $dir = $this->installDir . $this->imageDir;
        $fileList = glob($dir . $sku . "*");

        $mediaGallery = [];
        foreach ($fileList as $file) {
            // für konfigurierbare Produkte sind nur mf1 und pfg relevant
            if (preg_match('/_mh/', $file) || preg_match('/_mf1/', $file) || preg_match('/_pfg/', $file)) {
                $mediaFile = preg_replace('?' . $dir . '?', '', $file);
                $mediaGallery[] = $mediaFile . '::' . $sku;
            }
        }
        // Sortierung gibt die Liste so zurück, dass das erste Bild sicherlich den Regeln oben entspricht.
        usort($mediaGallery, [Images::class, "fileSortConfigurable"]);

        $mainImage = preg_replace('/::.*/', '', array_shift($mediaGallery));
        return [
            "image" => $mainImage,
            "small_image" => $mainImage,
            "thumbnail" => $mainImage,
            "media_gallery" => "", // implode(';', $mediaGallery), // konf. Produkte nur ein Bild
        ];
    }

    private function getSimpleImages($sku) {
        $skuPart = preg_replace('/(_.+)_.+$/', '$1', $sku);
        $dir = $this->installDir . $this->imageDir;
        $fileList = glob($dir . $skuPart . "_*");

        $thumbNail = '';
        $mediaGallery = [];
        foreach ($fileList as $file) {
            $mediaFile = preg_replace('?' . $dir . '?', '', $file);
            // MainImage zum Thumbnail machen, weil Magento sonst die Reihenfolge der Bilder durcheinanderwürfelt.
            //if(preg_match('/_pfg/', $file)) {
            //    $thumbNail = $file;
            //} else {
            $mediaGallery[] = $mediaFile . '::' . $sku;
            //}
        }

        usort($mediaGallery, [Images::class, "fileSort"]);
        $mainImage = preg_replace('/::.*/', '', array_shift($mediaGallery));

        return [
            "image" => $mainImage,
            "small_image" => $mainImage,
            "thumbnail" => $mainImage, //$thumbNail,
            "media_gallery" => implode(';', $mediaGallery),
        ];
    }

    
    private static function fileSortConfigurable($a, $b) {
        $imageSort = ['mh' => 1, 'mf1' => 2, 'mf2' => 3, 'mf3' => 4, 'mfz1' => 5, 'mfz2' => 6, 'md' => 7, 'mb' => 8,
            'pfg' => 9, 'pbg' => 10, 'pd' => 11];
        $aFile = explode('_', preg_replace('/\..*/', '', $a));
        $bFile = explode('_', preg_replace('/\..*/', '', $b));
        
        // Irgendwas stimmt mit der Syntax der Bilddatei nicht. Also die Sortierung hier mal unverändert lassen
        if((count($aFile) < 3) || (count($bFile) < 3)) {
            return 0;
        }
        $aFarbe = $aFile[1];
        $bFarbe = $bFile[1];
        $aArt = $aFile[2];
        $bArt = $bFile[2];

        if(isset($imageSort[$aArt]) && isset($imageSort[$bArt])) {
            if ($imageSort[$aArt] > $imageSort[$bArt]) {
                return 1;
            } elseif ($imageSort[$aArt] < $imageSort[$bArt]) {
                return -1;
            }
        }
        
        if ($aFarbe > $bFarbe) {
            return 1;
        } elseif ($aFarbe < $bFarbe) {
            return -1;
        }
        return 0;
    }
    
    private static function fileSort($a, $b) {
        $imageSort = ['mh' => 1 ,'mf1' => 2, 'mf2' => 3, 'mf3' => 4, 'mfz1' => 5, 'mfz2' => 6, 'md' => 7, 'mb' => 8,
            'pfg' => 9, 'pbg' => 10, 'pd' => 11];
        $aFile = explode('_', preg_replace('/\..*/', '', $a));
        $bFile = explode('_', preg_replace('/\..*/', '', $b));
        $aFarbe = $aFile[1];
        $bFarbe = $bFile[1];
        $aArt = $aFile[2];
        $bArt = $bFile[2];
        // Zuerst nach Art, dann nach Farbe sortieren.
        // Auf diese Art sind alle Modellbilder oben in der Liste 
        // Wenn mehrere vorhanden sind, sind die mit der kleinen Farbnummern weiter oben.
        if ($aFarbe > $bFarbe) {
            return 1;
        } elseif ($aFarbe < $bFarbe) {
            return -1;
        }
        if(isset($imageSort[$aArt]) && isset($imageSort[$bArt])) {
            if ($imageSort[$aArt] > $imageSort[$bArt]) {
                return 1;
            } elseif ($imageSort[$aArt] < $imageSort[$bArt]) {
                return -1;
            }
        }
        return 0;
    }

}
