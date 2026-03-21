<?php

namespace MediaDivision\Basics\Helper\Module;

use MediaDivision\Basics\Helper\ModuleAbstract;
use MediaDivision\Basics\Helper\Module\Acl;

class Controller extends ModuleAbstract
{

    private $type; // frontend | adminhtml
    private $acl;
    private $name;
    private $action;

    public function __construct(
    \Magento\Framework\App\Helper\Context $context, Acl $acl) {
        $this->acl = $acl;
        parent::__construct($context);
    }

    public function handleTask($company, $module) {
        $this->company = $company;
        $this->module = $module;
        echo "\n\n - Controller anlegen - \n\n";
        echo "1: Frontend-Controller\n";
        echo "2: Backend-Controller\n";
        do {
            $typeId = readline("\nWelche Art Controller? ");
        } while (($typeId != 1) && ($typeId != 2));
        if ($typeId == 1) {
            $this->type = "frontend";
        } else {
            $this->type = "adminhtml";
        }
        // Routing anlegen
        $this->editRouteXml();

        $this->name = readline("\n\nName des Controllers? (Standardcontroller => Index)\n => ");
        $this->action = readline("\n\nName der Action? (Standardaction => Index)\n => ");
        $this->createController();
        $this->createLayoutFile();
        $this->createBlockFile();
        $this->createTemplateFile();
        echo "\n\nCache löschen, um die Änderungen zu aktivieren.\n\n";
    }

    public function create($company, $module, $type, $name, $action) {
        $this->company = $company;
        $this->module = $module;
        $this->type = $type;
        $this->name = $name;
        $this->action = $action;
        $this->editRouteXml();
        $this->createController();
        $this->createLayoutFile();
        $this->createBlockFile();
        $this->createTemplateFile();
    }

    private function createTemplateFile() {
        if (!file_exists('view')) {
            mkdir('view');
        }
        if (!file_exists('view/' . $this->type)) {
            mkdir('view/' . $this->type);
        }
        if (!file_exists('view/' . $this->type . '/templates')) {
            mkdir('view/' . $this->type . '/templates');
        }
        $content = "<h2>Welcome to Module " . $this->module . " Controller " . $this->name . " and action " . $this->action . "</h2>";
        file_put_contents("view/" . $this->type . "/templates/" . strtolower($this->action) . ".phtml", $content);
    }

    private function createBlockFile() {
        if (!file_exists('Block')) {
            mkdir('Block');
        }
        if (!file_exists('Block/' . $this->name) && ($this->type == "frontend")) {
            mkdir('Block/' . $this->name);
        }
        if (!file_exists('Block/Adminhtml') && ($this->type == "adminhtml")) {
            mkdir('Block/Adminhtml');
        }
        if (!file_exists('Block/Adminhtml/' . $this->name) && ($this->type == "adminhtml")) {
            mkdir('Block/Adminhtml/' . $this->name);
        }

        $className = $this->action;
        if ($className == "New") {
            $className = "NewAction";
        }
        
        $filename = 'Block/' . $this->name . "/" . $className . ".php";
        if ($this->type == "adminhtml") {
            $filename = 'Block/Adminhtml/' . $this->name . "/" . $className . ".php";
        }
        
        $namespace = $this->company . "\\" . $this->module . "\\Block\\" . $this->name;
        if ($this->type == "adminhtml") {
            $namespace = $this->company . "\\" . $this->module . "\\Block\\Adminhtml\\" . $this->name;
        }
        
        $content = "<?php
            
namespace " . $namespace . ";

class " . $className . " extends \Magento\Framework\View\Element\Template
{

}";
        file_put_contents($filename, $content);
    }

    private function createLayoutFile() {
        if (!file_exists('view')) {
            mkdir('view');
        }
        if (!file_exists('view/' . $this->type)) {
            mkdir('view/' . $this->type);
        }
        if (!file_exists('view/' . $this->type . '/layout')) {
            mkdir('view/' . $this->type . '/layout');
        }
        $layout = 'layout="1column"';
        if ($this->type == "adminhtml") {
            $layout = '';
        }
        $className = $this->action;
        if ($className == "New") {
            $className = "NewAction";
        }
        $classPath = $this->company . "\\" . $this->module . "\\Block\\" . $this->name ."\\";
        if ($this->type == "adminhtml") {
            $classPath = $this->company . "\\" . $this->module . "\\Block\\Adminhtml\\" . $this->name ."\\";
        }
        $filename = strtolower($this->module . "_" . $this->name . "_" . $this->action . ".xml");
        $xmlString = '<?xml version="1.0"?><page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ' . $layout . ' xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd"></page>';

        $xmlData = [
            ["head" => [0 => [["title" => [0 => $this->name . ' - ' . $this->action]]]]],
            ["referenceContainer" => ["name" => "content", 0 => [
                        ["block" => [
                                "class" => $classPath . $className,
                                "name" => strtolower($this->module . "_" . $this->name . "_" . $this->action),
                                "template" => $this->company . "_" . $this->module . "::" . strtolower($this->action) . ".phtml"
                            ]]]]]
        ];
        $xml = $this->createSimpleXml($xmlString, $xmlData);
        $this->saveXml($xml, "view/" . $this->type . "/layout/" . $filename);
    }

    private function createController() {
        if (!file_exists('Controller')) {
            mkdir('Controller');
        }
        if (!file_exists('Controller/' . $this->name) && ($this->type == "frontend")) {
            mkdir('Controller/' . $this->name);
        }
        if (!file_exists('Controller/Adminhtml') && ($this->type == "adminhtml")) {
            mkdir('Controller/Adminhtml');
        }
        if (!file_exists('Controller/Adminhtml/' . $this->name) && ($this->type == "adminhtml")) {
            mkdir('Controller/Adminhtml/' . $this->name);
        }
        $className = $this->action;
        if ($className == "New") {
            $className = "NewAction";
        }
        $filename = "Controller/" . $this->name . "/" . $className . ".php";
        $namespace = $this->company . "\\" . $this->module . "\\Controller\\" . $this->name;
        $action = "\\Magento\\Framework\\App\\Action\\Action";
        $context = "\\Magento\\Framework\\App\\Action\\Context";
        $aclFunction = "";
        if ($this->type == "adminhtml") {
            $filename = "Controller/Adminhtml/" . $this->name . "/" . $className . ".php";
            $action = "\\Magento\\Backend\\App\\Action";
            $context = "\\Magento\\Backend\\App\\Action\\Context";
            $namespace = $this->company . "\\" . $this->module . "\\Controller\\Adminhtml\\" . $this->name;
            $this->acl->createAcl($this->company, $this->module, strtolower($this->name . "_" . $this->action), $this->name . " " . $this->action);
            $aclFunction = "protected function _isAllowed() {\n        return \$this->_authorization->isAllowed('"
                    . $this->company . "_" . $this->module . "::" . strtolower($this->name . "_" . $this->action) . "');\n    }\n";
        }
        $content = "<?php
namespace " . $namespace . ";

class " . $className . " extends " . $action . "
{
    protected \$_pageFactory;

    public function __construct(" . $context . " \$context, \Magento\Framework\View\Result\PageFactory \$pageFactory) {
        \$this->_pageFactory = \$pageFactory;
        return parent::__construct(\$context);
    }

    public function execute() {
        return \$this->_pageFactory->create();
    }
    $aclFunction
}";
        file_put_contents($filename, $content);
    }

    private function editRouteXml() {
        if (!file_exists('etc')) {
            mkdir('etc');
        }
        if (!file_exists('etc/' . $this->type)) {
            mkdir('etc/' . $this->type);
        }
        $xmlString = '<?xml version="1.0" ?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:App/etc/routes.xsd"></config>';
        $xmlData = [
            [
                "router" => [
                    "id" => $this->type == "frontend" ? "standard" : "admin",
                    0 => [
                        [
                            "route" => [
                                "frontName" => strtolower($this->module),
                                "id" => strtolower($this->module),
                                0 => [
                                    [
                                        "module" => [
                                            "name" => $this->company . "_" . $this->module
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $xml = $this->createSimpleXml($xmlString, $xmlData);
        $this->saveXml($xml, 'etc/' . $this->type . '/routes.xml');
    }

}
