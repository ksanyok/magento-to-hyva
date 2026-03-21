<?php

namespace MediaDivision\TexData\Console\Command;

use MediaDivision\Magmi\Helper\Data as Magmi;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use MediaDivision\TexData\Helper\Import\Check;
use \Magento\Framework\App\State;

class CheckProducts extends Command
{

    const DEBUG = "debug";

    private $debug = false;
    private $check;
    private $magmi;
    private $state;

    public function __construct(
            Check $check,
            Magmi $magmi,
            State $state,
            $name = null) {
        $this->check = $check;
        $this->magmi = $magmi;
        $this->state = $state;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug")
        ];
        $this->setName("check:products")
                ->setDescription("Überprüfe Produkte auf Vollständigkeit")->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        if ($input->getOption(self::DEBUG)) {
            $this->debug = true;
            echo "\nSetze debug mode.\n\n";
        }

        if ($this->debug) {
            echo "\n\nStarte check:products\n\n";
        }
        
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        $data = $this->check->execute($this->debug);
        
        if (isset($data[0])) {
            if ($this->debug) {
                echo "\n\nSchreibe Magmi-Datei für Produkt-Check\n\n";
            }
            $this->magmi->writeMagmiFile(array_keys($data[0]), $data);
            if ($this->debug) {
                echo "Starte Magmi\n\n";
            }
            $this->magmi->import("Products", 'update');
        } elseif ($this->debug) {
            echo "\nkeine Produkte zu ändern.\n";
        }
    }

}
