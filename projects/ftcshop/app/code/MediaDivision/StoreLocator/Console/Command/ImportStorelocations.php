<?php

namespace MediaDivision\StoreLocator\Console\Command;

use MediaDivision\StoreLocator\Helper\Data as StoreHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportStorelocations extends Command
{

    const DEBUG = "debug";

    private $debug = false;
    private $storeHelper;

    public function __construct(
            StoreHelper $storeHelper,
            $name = null) {
        $this->storeHelper = $storeHelper;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug")
        ];
        $this->setName("import:storelocations")
                ->setDescription("Locations für den Storelocator importieren")->setDefinition($options);
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
            echo "\n\nStarte import:storelocations\n\n";
        }
        
        $this->storeHelper->importStoreLocations($this->debug);
    }

}
