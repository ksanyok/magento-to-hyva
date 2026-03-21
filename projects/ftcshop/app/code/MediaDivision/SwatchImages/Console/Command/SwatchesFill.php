<?php

namespace MediaDivision\SwatchImages\Console\Command;

use MediaDivision\SwatchImages\Helper\Data as SwatchHelper;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class SwatchesFill extends Command
{

    const DEBUG = "debug";

    private $debug = false;
    private $optionList;
    private $productCollectionFactory;
    private $swatchCollectionFactory;
    private $swatchHelper;

    public function __construct(
            SwatchHelper $swatchHelper,
            $name = null) {
        $this->swatchHelper = $swatchHelper;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug")
        ];
        $this->setName("swatches:fill")
                ->setDescription("Fill Color Swatches")->setDefinition($options);
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
            echo "\n\nStarte swatches:fill\n\n";
        }
        $this->swatchHelper->fillSwatches($this->debug);
        if ($this->debug) {
            echo "\n\nBeende swatches:fill\n\n";
        }
    }

}
