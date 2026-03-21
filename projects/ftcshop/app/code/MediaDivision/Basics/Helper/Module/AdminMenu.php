<?php

namespace MediaDivision\Basics\Helper\Module;

use MediaDivision\Basics\Helper\Module\Acl;
use MediaDivision\Basics\Helper\Module\Controller;
use MediaDivision\Basics\Helper\ModuleAbstract;

class AdminMenu extends ModuleAbstract
{

    private $acl;
    private $controller;
    private $menufile = "etc/adminhtml/menu.xml";
    private $name;
    private $parents = [
        "1" => [
            "label" => "System",
            "code" => "Magento_Backend::system",
        ],
        "2" => [
            "label" => "Stores",
            "code" => "Magento_Backend::stores",
        ],
        "3" => [
            "label" => "Stores - MediaDivision",
            "code" => "MediaDivision_Basics::mediadivision",
        ],
        "4" => [
            "label" => "Reports",
            "code" => "Magento_Reports::report",
        ],
    ];

    public function __construct(\Magento\Framework\App\Helper\Context $context, Acl $acl, Controller $controller) {
        $this->acl = $acl;
        $this->controller = $controller;
        parent::__construct($context);
    }

    public function handleTask($company, $module) {
        $this->company = $company;
        $this->module = $module;
        echo "\n - Admin-Menü anlegen - \n\n";
        $itemList = [];
        foreach ($this->parents as $id => $parent) {
            $itemList[] = $id;
            echo $id . ": " . $parent["label"] . "\n";
        }
        echo "\n";
        do {
            $parentId = readline("Zu welcher Oberkategorie soll der Menüpunkt zugeordnet werden? => ");
        } while (!in_array($parentId, $itemList));

        $this->name = readline("Wie lautet der Eintrag? => ");
        $controller = readline("An welchen Controller sollen die Anfragen geschickt werden? => ");
        $action = readline("An welche Action im  Controller sollen die Anfragen geschickt werden? => ");

        $this->editMenuXml($parentId, $action, $controller);
        $this->controller->create($this->company, $this->module, "adminhtml", $controller, $action);

        echo "\nbin/magento cache:flush ausführen, damit der Menü-Eintrag sichtbar wird.\n\n";
    }

    public function create($company, $module, $name, $controller, $action) {
        $this->company = $company;
        $this->module = $module;
        $this->name = $name;
        $parentId = "3";
        $this->editMenuXml($parentId, $action, $controller);
        $this->controller->create($this->company, $this->module, "adminhtml", $controller, $action);
    }

    private function editMenuXml($parentId, $action, $controller) {
        $nameId = strtolower(preg_replace('/[^a-zA-Z_0-9]/', "_", $this->name));
        if (!file_exists("etc")) {
            mkdir("etc");
        }
        if (!file_exists("etc/adminhtml")) {
            mkdir("etc/adminhtml");
        }
        if (!file_exists($this->menufile)) {
            $xmlString = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Backend:etc/menu.xsd"></config>';
            $xml = new \SimpleXMLElement($xmlString);
            $xml->addChild("menu");
            $this->saveXml($xml, $this->menufile);
        }

        $xml = simplexml_load_file($this->menufile);

        $xmlData = [
            [
                "add" => [
                    "id" => $this->company . "_" . $this->module . "::" . $nameId,
                    "title" => $this->name,
                    "module" => $this->company . "_" . $this->module,
                    "sortOrder" => "10",
                    "action" => strtolower($this->module) . "/" . strtolower($controller) . "/" . strtolower($action),
                    "resource" => $this->company . "_" . $this->module . "::" . $nameId,
                    "parent" => $this->parents[$parentId]["code"]
                ]
            ]
        ];
        $this->addSimpleXml($xml->menu, $xmlData);

        $this->acl->createAcl($this->company, $this->module, $nameId, $this->name);

        $this->saveXml($xml, $this->menufile);
    }

}
