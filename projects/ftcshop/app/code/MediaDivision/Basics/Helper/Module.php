<?php

namespace MediaDivision\Basics\Helper;

use MediaDivision\Basics\Helper\ModuleAbstract;
use MediaDivision\Basics\Helper\Module\AdminGrid;
use MediaDivision\Basics\Helper\Module\AdminMenu;
use MediaDivision\Basics\Helper\Module\Command;
use MediaDivision\Basics\Helper\Module\Configuration;
use MediaDivision\Basics\Helper\Module\Controller;
use MediaDivision\Basics\Helper\Module\Model;
use Magento\Framework\App\Helper\Context;

class Module extends ModuleAbstract
{

    private $adminGrid;
    private $adminMenu;
    private $configuration;
    private $controller;
    private $command;
    private $model;

    public function __construct(AdminGrid $adminGrid, AdminMenu $adminMenu, Command $command, Configuration $configuration, Context $context, Controller $controller, Model $model) {
        $this->adminGrid = $adminGrid;
        $this->adminMenu = $adminMenu;
        $this->configuration = $configuration;
        $this->controller = $controller;
        $this->command = $command;
        $this->model = $model;
        parent::__construct($context);
    }

    public function create($company, $module) {
        chdir('app/code');
        $this->company = $company;
        $this->module = $module;
        if (!file_exists($this->company)) {
            $result = mkdir($this->company);
            if (!$result) {
                return false;
            }
        }
        echo $this->company . " " . $this->module . "\n";
        chdir($this->company);
        if (!file_exists($this->module)) {
            $result = mkdir($this->module);
            if (!$result) {
                return false;
            }
        }
        chdir($this->module);
        $this->createModuleXml();
        $this->createRegistrationPhp();
    }

    public function chooseTask() {
        if (!$this->company || !$this->module) {
            return false; // Irgendwas ist bei der Auswahl des Moduls schiefgegangen -> Abbruch.
        }
        do {
            echo "1: Nichts weiter, danke.\n";
            echo "2: Controller anlegen\n";
            echo "3: Model anlegen\n";
            echo "4: Admin Menü anlegen\n";
            echo "5: Admin Grid anlegen\n";
            echo "6: Neues CLI-Kommando anlegen\n";
            echo "7: Konfigurations-Variable anlegen\n";

            $taskId = readline("Welcher Task? : ");

            switch ($taskId) {
                case 1:
                    return true;
                case 2:
                    $this->controller->handleTask($this->company, $this->module);
                    break;
                case 3:
                    $this->model->handleTask($this->company, $this->module);
                    break;
                case 4:
                    $this->adminMenu->handleTask($this->company, $this->module);
                    break;
                case 5:
                    $this->adminGrid->handleTask($this->company, $this->module);
                    break;
                case 6:
                    $this->command->handleTask($this->company, $this->module);
                    break;
                case 7:
                    $this->configuration->handleTask($this->company, $this->module);
                    break;
                default:
                    echo "\nTask nicht implementiert.\n\n";
                    break;
            }
        } while ($taskId != 1);
    }

    public function chooseModule() {
        if (!$this->company) {
            return false; // Irgendwas ist bei der Auswahl der Firma schiefgegangen -> Abbruch.
        }
        $dirList = ['<neuer Eintrag>'];
        foreach (glob("*") as $dir) {
            $dirList[] = $dir;
        }
        foreach ($dirList as $key => $dir) {
            echo $key . " => " . $dir . "\n";
        }
        $moduleId = readline("Welches Modul? : ");
        if ($moduleId && isset($dirList[$moduleId])) {
            $this->module = $dirList[$moduleId];
            chdir($this->module);
        } elseif ($moduleId === "0") {
            echo "Neues Modul.\n";
            $moduleName = readline("Name des Moduls: ");
            $result = mkdir($moduleName);
            if (!$result) {
                echo "Fehler beim Anlegen des Verzeichnisses " . $moduleName . "\n";
                return false;
            }
            $this->module = $moduleName;
            chdir($this->module);
            $this->createModuleXml();
            $this->createRegistrationPhp();
            echo "\n\nNeues Modul erstellt!\nbin/magento setup:upgrage aufrufen, um es zu aktivieren!\n\n";
        } else {
            echo "Abbruch.\n";
            return false; // Fehler beim Modul wählen
        }
        echo "\nGewähltes Modul: " . $this->module . "\n";
        return true; // Alles korrekt gelaufen
    }

    private function createModuleXml() {
        if (!file_exists("etc")) {
            mkdir("etc");
        }
        $xmlString = '<?xml version="1.0"?>
            <config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd"></config>';
        $xmlData = [
            [
                "module" => [
                    "name" => $this->company . "_" . $this->module,
                    "setup_version" => "1.0.0"
                ]
            ]
        ];
        $xml = $this->createSimpleXml($xmlString, $xmlData);
        $this->saveXml($xml, "etc/module.xml");
    }

    private function createRegistrationPhp() {
        $content = "<?php\n\n\\Magento\\Framework\\Component\\ComponentRegistrar::register(\n\t"
                . "\\Magento\\Framework\\Component\\ComponentRegistrar::MODULE, '"
                . $this->company . "_" . $this->module . "', __DIR__ \n);";
        file_put_contents("registration.php", $content);
    }

    public function chooseCompany() {
        // Selbstgeschriebene Module sind im Verzeichnis app/code/
        chdir('app/code');
        $dirList = ['<neuer Eintrag>'];
        foreach (glob("*") as $dir) {
            $dirList[] = $dir;
        }
        foreach ($dirList as $key => $dir) {
            echo $key . " => " . $dir . "\n";
        }
        $companyId = readline("Welche Firma? : ");
        if ($companyId && isset($dirList[$companyId])) {
            $this->company = $dirList[$companyId];
        } elseif ($companyId === "0") {
            echo "Neue Firma.\n";
            $companyName = readline("Name der Firma: ");
            $result = mkdir($companyName);
            if ($result) {
                $this->company = $companyName;
            } else {
                echo "Fehler beim Anlegen des Verzeichnisses " . $companyName . "\n";
                return false;
            }
        } else {
            echo "Abbruch.\n";
            return false; // Fehler beim Modul wählen
        }
        chdir($this->company);
        echo "\nGewählte Firma: " . $this->company . "\n";
    }

}
