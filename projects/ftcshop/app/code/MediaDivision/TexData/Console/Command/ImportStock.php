<?php

namespace MediaDivision\TexData\Console\Command;

use MediaDivision\Magmi\Helper\Data as Magmi;
use MediaDivision\TexData\Helper\Import\Stock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class ImportStock extends Command
{

    const DEBUG = "debug";
    const REIMPORT = "reimport";
    const STOCKS = "stocks";

    private $debug = false;
    private $stock;
    private $magmi;

    public function __construct(
            Stock $stock,
            Magmi $magmi,
            $name = null) {
        $this->stock = $stock;
        $this->magmi = $magmi;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug"),
            new InputOption(self::REIMPORT, "r", InputOption::VALUE_OPTIONAL, "reimport"),
            new InputOption(self::STOCKS, "s", InputOption::VALUE_OPTIONAL, "stocks")
        ];
        $this->setName("import:stock")
                ->setDescription("Lagerbestands-Import")->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        if ($input->getOption(self::DEBUG)) {
            $this->debug = true;
        }

        $reimport = false;
        if ($input->getOption(self::REIMPORT)) {
            $reimport = true;
        }

        //$stocks = ["austria", "switzerland"];
        $stocks = ["austria"];
        if ($input->getOption(self::STOCKS)) {
            $stocks = explode(",", $input->getOption(self::STOCKS));
        }

        if ($this->debug) {
            echo "\n\n" . date("Y-m-d H:i:s") . ": Starte import:stock für " . implode(",", $stocks) . "\n\n";
        }

        $data = $this->stock->getData($stocks, $reimport, $this->debug);

        if (isset($data[0])) {
            if ($this->debug) {
                echo "\n\nSchreibe Magmi-Datei für Lagerbestands-Daten\n\n";
            }
            $this->magmi->writeMagmiFile(array_keys($data[0]), $data);
            if ($this->debug) {
                echo "Starte Magmi\n\n";
            }
            $this->magmi->import("Products", 'update',['inventory','cataloginventory_stock']);
        } elseif ($this->debug) {
            echo "\nkeine Lagerbestands-Daten zu importieren.\n";
        }
    }

}
