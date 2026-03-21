<?php

namespace MediaDivision\Basics\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use MediaDivision\Basics\Helper\Customer as CustomerHelper;

class ImportCustomers extends Command
{

    const ANONYMISE = "anonymise";
    const DEBUG = "debug";

    private $anonymise = false;
    private $debug = false;
    private $customerHelper;

    public function __construct(CustomerHelper $customerHelper, $name = null) {
        $this->customerHelper = $customerHelper;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::ANONYMISE, "a", InputOption::VALUE_OPTIONAL, "anonymise"),
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug"),
        ];
        $this->setName("import:customers")
                ->setDescription("Kunden importieren")->setDefinition($options);
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

        if ($input->getOption(self::ANONYMISE)) {
            $this->anonymise = true;
            echo "\nAnonymisiere Kunden.\n\n";
        }

        if ($this->debug) {
            echo "\n\nStarte import:customers\n\n";
        }
        $this->customerHelper->insertCustomers($this->debug, $this->anonymise);
    }

}
