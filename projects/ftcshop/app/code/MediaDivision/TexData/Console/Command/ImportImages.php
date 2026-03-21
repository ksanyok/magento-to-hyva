<?php

namespace MediaDivision\TexData\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Filesystem\DirectoryList;
use MediaDivision\Magmi\Helper\Data as Magmi;
use MediaDivision\TexData\Helper\Import\Images;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportImages extends Command
{

    const DEBUG = "debug";
    const CHECK = "check";

    private $count = 0;
    private $check = false;
    private $debug = false;
    private $images;
    private $installDir;
    private $magmi;
    private $state;

    public function __construct(
            DirectoryList $directoryList,
            Images $images,
            Magmi $magmi,
            State $state,
            $name = null) {
        $this->images = $images;
        $this->installDir = $directoryList->getRoot();
        $this->magmi = $magmi;
        $this->state = $state;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::CHECK, "c", InputOption::VALUE_OPTIONAL, "check"),
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug")
        ];
        $this->setName("import:images")
                ->setDescription("Produktbilder importieren")->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        
        $manualStart = false;
        if (file_exists($this->installDir . "/var/magmi/importImages.txt")) {
            $manualStart = true;
            unlink($this->installDir . "/var/magmi/importImages.txt");
        }

        if ($input->getOption(self::DEBUG)) {
            $this->debug = true;
        }
        if ($input->getOption(self::CHECK)) {
            $this->check = true;
        }
        if ($this->debug) {
            echo "\n" . date("Y-m-d H:i:s") . ": Starte import:images. ";
        }

        // Wenn Parameter -c 1 gesetzt ist, dann nur importieren, wenn gewünscht (also Datei vorhanden)
        if ($this->check && !$manualStart) {
            if ($this->debug) {
                echo "Kein Bild-Import gewünscht.\n";
            }
            return;
        }

        $data = $this->images->getData($this->debug);

        if (isset($data[0])) {
            if ($this->debug) {
                echo "\n\nSchreibe Magmi-Datei für Bild-Daten\n\n";
            }
            $this->magmi->writeMagmiFile(array_keys($data[0]), $data);
            if ($this->debug) {
                echo "Starte Magmi\n\n";
            }
            $this->magmi->import("Products", 'update',false);
            if ($this->debug) {
                echo "Archiviere Bilder\n\n";
            }
            $this->images->archiveImages($data);
            if ($this->debug) {
                echo "\n\n";
            }
            if ($this->debug) {
                echo "Erstelle neuen Bilder-Cache\n\n";
            }
            $this->images->resizeImportedImages($data);
            
            if ($manualStart) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $checkCommand = $objectManager->create('MediaDivision\TexData\Helper\Import\Check');
                $checkCommand->execute($this->debug);
            }
            $this->magmi->reindex();
        } elseif ($this->debug) {
            echo "\nkeine Bild-Daten zu importieren.\n";
        }
    }

    private function dot($reset = false) {
        // Nur im Debug-Modus etwas ausgeben
        if (!$this->debug) {
            return;
        }
        if ($reset) {
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

}
