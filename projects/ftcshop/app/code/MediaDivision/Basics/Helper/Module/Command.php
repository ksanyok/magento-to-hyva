<?php

namespace MediaDivision\Basics\Helper\Module;

use MediaDivision\Basics\Helper\ModuleAbstract;

class Command extends ModuleAbstract
{

    private $className;
    private $commandName;
    private $description;

    public function handleTask($company, $module) {
        $this->company = $company;
        $this->module = $module;
        echo "\n\n - CLI-Kommando anlegen - \n\n";

        $this->commandName = readline("\nName des Kommandos (z.B.: import:products): ");
        $this->description = readline("\nBeschreibung des Kommandos: ");
        $this->createClassname();
        $this->createClass();
        $this->editDiXml();
        echo "\n\nbin/magento setup:di:compile ausführen, um das Kommando zu aktivieren.\n\n";
    }

    public function create($company, $module, $commandName, $description) {
        $this->company = $company;
        $this->module = $module;
        $this->commandName = $commandName;
        $this->description = $description;
        $this->createClassname();
        $this->createClass();
        $this->editDiXml();
    }

    private function createClassname() {
        $classNameParts = [];
        foreach (explode(":", $this->commandName) as $part) {
            $classNameParts[] = ucfirst($part);
        }
        $this->className = implode("", $classNameParts);
    }

    private function createClass() {
        if (!file_exists("Console")) {
            mkdir("Console");
        }
        if (!file_exists("Console/Command")) {
            mkdir("Console/Command");
        }
        $content = '<?php

namespace ' . $this->company . '\\' . $this->module . '\\Console\\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class ' . $this->className . ' extends Command
{


    const DEBUG = "debug";

    private $debug = false;
    
    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $options = [
            new InputOption(self::DEBUG, "d", InputOption::VALUE_OPTIONAL, "debug")
        ];
        $this->setName("' . $this->commandName . '")
                ->setDescription("' . $this->description . '")->setDefinition($options);
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
            echo "\n\nStarte ' . $this->commandName . '\n\n";
        }
            $output->writeln("<info>Kommando ausgeführt.</info>");
    }

}';
        file_put_contents("Console/Command/" . $this->className . ".php", $content);
    }

    private function editDiXml() {
        echo "editDiXml\n";
        if (!file_exists("etc")) {
            mkdir("etc");
        }
        $xml = false;
        $typeData = [
            ["type" => ["name" => "Magento\\Framework\\Console\\CommandList", 0 => [
                        ["arguments" => [0 => [
                                    ["argument" => ["name" => "commands", "xsi:type" => "array"]]
                                ]]]]]]
        ];
        if (file_exists("etc/di.xml")) {
            $xml = simplexml_load_file("etc/di.xml");
            if (!$xml->xpath('/config/type[@name="Magento\\Framework\\Console\\CommandList"]')) {
                $this->addSimpleXml($xml, $typeData);
            }
        } else {
            $xmlString = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd"></config>';
            $xml = $this->createSimpleXml($xmlString, $typeData);
        }
        $argumentList = $xml->xpath('/config/type[@name="Magento\\Framework\\Console\\CommandList"]/arguments/argument');
        $argument = $argumentList[0];

        $itemData = [
            ["item" => [
                    "name" => preg_replace('/:/', '_', $this->commandName),
                    "xsi:type" => "object",
                    0 => $this->company . '\\' . $this->module . '\\Console\\Command\\' . $this->className]]
        ];
        $this->addSimpleXml($argument, $itemData);

        $this->saveXml($xml, "etc/di.xml");
    }

}
