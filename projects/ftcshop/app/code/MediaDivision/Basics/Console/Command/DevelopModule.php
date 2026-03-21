<?php

namespace MediaDivision\Basics\Console\Command;

use MediaDivision\Basics\Helper\Module;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DevelopModule extends Command
{
    private $module;

    public function __construct(Module $module) {
        $this->module = $module;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this->setName('develop:module')
                ->setDescription('Module programmieren leicht gemacht.');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
            $output->writeln('<info>Module entwickeln</info>');
            $this->module->chooseCompany();
            $this->module->chooseModule();
            // Modul ist nun gewählt oder neu angelegt. 
            // Was soll nun im Modul gemacht werden.
            $this->module->chooseTask();
    }

}
