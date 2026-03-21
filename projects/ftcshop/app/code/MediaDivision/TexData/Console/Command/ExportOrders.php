<?php

namespace MediaDivision\TexData\Console\Command;

use MediaDivision\TexData\Helper\Export\Order;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class ExportOrders extends Command
{

    const DEBUG = "debug";

    private $debug = false;
    private $order;

    public function __construct(
            Order $order,
            $name = null) {
        $this->order = $order;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug")
        ];
        $this->setName("export:orders")
                ->setDescription("Bestellungen zu TexData exportieren")->setDefinition($options);
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
            echo "\n\nStarte export:orders\n\n";
        }
        $this->order->createReservations($this->debug);
    }

}
