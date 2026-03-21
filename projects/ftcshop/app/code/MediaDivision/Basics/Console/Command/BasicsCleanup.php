<?php

namespace MediaDivision\Basics\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use MediaDivision\Basics\Helper\Data;

class BasicsCleanup extends Command
{

    const DEBUG = "debug";

    private $debug = false;
    private $helper;

    public function __construct(Data $helper) {
        $this->helper = $helper;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug")
        ];
        $this->setName("basics:cleanup")
                ->setDescription("Clean Cache-Directories")->setDefinition($options);
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
            echo "\n\nStarte basics:cleanup\n\n";
        }
        $result = $this->helper->cleanCacheDirectory('var/magmi/archive', 3);
        if ($this->debug) {
            print_r($result);
        }
    }

}
