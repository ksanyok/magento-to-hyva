<?php

namespace MediaDivision\Image\Console;

use MediaDivision\Image\Helper\Data as Helper;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class ImageCacheRenew extends Command
{

    const DEBUG = "debug";
    const SKU = "sku";

    private $debug = false;
    private $helper;
    private $state;

    public function __construct(
            Helper $helper,
            State $state) {
        $this->helper = $helper;
        $this->state = $state;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug"),
            new InputOption(self::SKU, "s", InputOption::VALUE_OPTIONAL, "sku")
        ];
        $this->setName("images:cache:renew")
                ->setDescription("Erneuere den Bilder-Cache")->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
        
        if ($input->getOption(self::DEBUG)) {
            $this->debug = true;
            echo "\nSetze debug mode.\n\n";
        }

        if ($input->getOption(self::SKU)) {
            $sku = $input->getOption(self::SKU);
        }

        if ($this->debug) {
            echo "\n\nStarte images:cache:renew\n\n";
        }
        $this->helper->resizeImages($sku);
    }

}
