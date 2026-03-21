<?php

namespace MediaDivision\TexData\Console\Command;

use Magento\Framework\App\State;
use MediaDivision\Magmi\Helper\Data as Magmi;
use MediaDivision\SwatchImages\Helper\Data as SwatchHelper;
use MediaDivision\TexData\Helper\Import\Article;
use MediaDivision\TexData\Helper\Import\Attribute;
use MediaDivision\TexData\Helper\Import\Check;
use MediaDivision\TexData\Helper\Import\Extension;
use MediaDivision\TexData\Helper\Import\Images;
use MediaDivision\TexData\Helper\Import\Price;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class ImportProducts extends Command {

    const DEBUG = "debug";
    const REIMPORT = "reimport";
    const STEPS = "steps";

    private $debug = false;
    private $article;
    private $attribute;
    private $check;
    private $extension;
    private $images;
    private $magmi;
    private $price;
    private $state;
    private $swatchHelper;

    public function __construct(
            Article $article,
            Attribute $attribute,
            Check $check,
            Extension $extension,
            Images $images,
            Magmi $magmi,
            Price $price,
            State $state,
            SwatchHelper $swatchHelper,
            $name = null) {
        $this->article = $article;
        $this->attribute = $attribute;
        $this->check = $check;
        $this->extension = $extension;
        $this->images = $images;
        $this->magmi = $magmi;
        $this->price = $price;
        $this->state = $state;
        $this->swatchHelper = $swatchHelper;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug"),
            new InputOption(self::REIMPORT, "r", InputOption::VALUE_OPTIONAL, "reimport"),
            new InputOption(self::STEPS, "s", InputOption::VALUE_OPTIONAL, "steps")
        ];
        $this->setName("import:products")
                ->setDescription("Produkte importieren")->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $success = true;
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        if ($input->getOption(self::DEBUG)) {
            $this->debug = true;
        }

        $reimport = false;
        if ($input->getOption(self::REIMPORT)) {
            $reimport = true;
        }

        $steps = ["article", "extension", "attribute", "price"];
        if ($input->getOption(self::STEPS)) {
            $steps = explode(",", $input->getOption(self::STEPS));
        }

        if ($this->debug) {
            echo "\n\n" . date("Y-m-d H:i:s") . ": Starte import:products\n\n";
        }

        if (in_array("article", $steps)) {
            $articleData = $this->article->getData($this->debug, $reimport);
            $success = $this->importData($articleData, "Article", "create");
        }

        if (in_array("extension", $steps)) {
            $extensionData = $this->extension->getData($this->debug, $reimport);
            $success = $this->importData($extensionData, "Extension", "update");
        }
        if (in_array("attribute", $steps)) {
            $attributeData = $this->attribute->getData($this->debug, $reimport);
            $success = $this->importData($attributeData, "Attribute", "update");
        }
        if (in_array("price", $steps)) {
            $priceData = $this->price->getData($this->debug, $reimport);
            $success = $this->importData($priceData, "Price", "update");
        }

        if ($success) {            
            $imageData = $this->images->getData($this->debug);
            $this->importData($imageData, "Image", "update");
            if ($this->debug) {
                echo "Archiviere Bilder\n\n";
            }
            $this->images->archiveImages($imageData);
            if ($this->debug) {
                echo "Erstelle neuen Bilder-Cache\n\n";
            }
            $this->images->resizeImportedImages($imageData);
            
            //if ($this->debug) {
            //    echo "Setze Farb-Swatches\n\n";
            //}
            //$this->swatchHelper->fillSwatches($this->debug);
            
            if ($this->debug) {
                echo "Checke Produkte auf Vollständigkeit\n\n";
            }
            $checkData = $this->check->execute($this->debug);
            $this->importData($checkData, "Check", "update");

            $this->magmi->reindex();
        }
    }

    private function importData($data, $dataType, $magmiMode) {
        $success = true;
        if (isset($data[0])) {
            if ($this->debug) {
                echo "\n\nSchreibe Magmi-Datei für $dataType-Daten\n\n";
            }
            $this->magmi->writeMagmiFile(array_keys($data[0]), $data);
            if ($this->debug) {
                echo "Starte Magmi\n\n";
            }
            $this->magmi->import("Products", $magmiMode, false);
        } elseif ($this->debug) {
            $success = false;
            echo "\nkeine $dataType-Daten zu importieren.\n";
        }
        return $success;
    }

}
