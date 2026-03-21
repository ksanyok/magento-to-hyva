<?php

namespace MediaDivision\Basics\Helper\Module;

use MediaDivision\Basics\Helper\Module\Acl;
use MediaDivision\Basics\Helper\Module\AdminMenu;
use MediaDivision\Basics\Helper\Module\Controller;
use MediaDivision\Basics\Helper\Module\Model;
use MediaDivision\Basics\Helper\ModuleAbstract;

class AdminGrid extends ModuleAbstract
{

    private $acl;
    private $action = "Grid";
    private $adminMenu;
    private $controller;
    private $dbTable;
    private $menuItemName;
    private $model;
    private $modelFactory;

    public function __construct(Acl $acl, AdminMenu $adminMenu, \Magento\Framework\App\Helper\Context $context, Controller $controller, Model $model) {
        $this->acl = $acl;
        $this->adminMenu = $adminMenu;
        $this->controller = $controller;
        $this->modelFactory = $model;
        parent::__construct($context);
    }

    public function handleTask($company, $module) {
        $this->company = $company;
        $this->module = $module;
        echo "\n - Admin-Grid anlegen - \n\n";

        $this->model = readline("\nName des Datenbank-Model? ");
        $this->dbTable = readline("\nName der Datenbank-Tabelle? ");

        if (file_exists("Model/" . $this->model . ".php")) {
            echo "\n\nDatenbank-Model schon vorhanden. Überspringe Model-Creation.\n\n";
        } else {
            $this->modelFactory->create($this->company, $this->module, $this->model, $this->dbTable);
        }
        echo "\n -- Admin - Menü - Eintrag -- \n\n";
        $this->menuItemName = readline("Wie lautet der Eintrag? => ");
        $this->adminMenu->create($this->company, $this->module, $this->menuItemName, $this->model, $this->action);
        $this->controller->create($this->company, $this->module, "adminhtml", $this->model, "Edit");
        $this->controller->create($this->company, $this->module, "adminhtml", $this->model, "New");

        $this->editDiXml();
        $this->createAdminPageXml();
        $this->createComponentLayoutFile();
        $this->createActionsFile();
        $this->createEditXmlFiles();
        $this->createFormXml();
        $this->createDataProvider();
        $this->createButtonClasses();
        $this->createControllerClasses();

        echo "\n\nFALLS EIN NEUES MODEL ANGELEGT WURDE__";
        echo "\n\n - Die Datei Setup/InstallSchema.php bzw. Setup/UpdateSchema.php muss noch editiert werden!\n";
        echo " - Um die Datenbank-Änderung aufzurufen, muss in der Datei etc/module.xml die setup_version erhöht werden.\n";
        echo " - Wurde die Datei InstallSchema.php bei einem bereits aktivierten Modul erzeugt, muss in der Tabelle \n";
        echo "   setup_module die Zeile mit " . $this->company . "_" . $this->module . " in der Spalte module entfernt werden, \n";
        echo "   damit das InstallSchema aufgerufen wird\n";
        echo " - Danach dann bin/magento setup:upgrade aufrufen, um die Datenbank-Änderungen einzubauen.\n\n";

        echo "\n - In der Datei view/adminhtml/ui_component/" .
        strtolower($this->company . "_" . $this->module . "_" . $this->model . "_listing") .
        ".xml muss die Anzeige der Columns noch angepasst werden.\n\n";
        echo "\nbin/magento cache:flush ausführen, damit der Admin-Menü-Eintrag sichtbar wird.\n\n";
    }

    /**
     * 
     * @param string $company Firma, die das Modul erstellt hat
     * @param string $module Name des Moduls
     * @param string $name Name des Datenbank-Modells
     * @param string $dbTable Name der Datenbank-Tabelle
     * @param string $menuItemName Name des Menü-Eintrags
     */
    public function create($company, $module, $name, $dbTable, $menuItemName) {
        $this->company = $company;
        $this->module = $module;
        $this->menuItemName = $menuItemName;
        $this->model = $name;
        $this->dbTable = $dbTable;

        // Datenbank-Modell nur erstellen, wenn es noch nicht vorhanden ist.
        if (!file_exists("Model/" . $this->model . ".php")) {
            $this->modelFactory->create($this->company, $this->module, $this->model, $this->dbTable);
        }
        $this->adminMenu->create($this->company, $this->module, $this->menuItemName, $this->model, $this->action);
        $this->controller->create($this->company, $this->module, "adminhtml", $this->model, "Edit");
        $this->controller->create($this->company, $this->module, "adminhtml", $this->model, "New");

        $this->editDiXml();
        $this->createAdminPageXml();
        $this->createComponentLayoutFile();
        $this->createActionsFile();
        $this->createEditXmlFiles();
        $this->createFormXml();
        $this->createDataProvider();
        $this->createButtonClasses();
        $this->createControllerClasses();
    }

    private function createComponentLayoutFile() {
        $componentName = strtolower($this->company . "_" . $this->module . "_" . $this->model . "_listing");
        if (!file_exists("view")) {
            mkdir("view");
        }
        if (!file_exists("view/adminhtml")) {
            mkdir("view/adminhtml");
        }
        if (!file_exists("view/adminhtml/ui_component")) {
            mkdir("view/adminhtml/ui_component");
        }
        $xmlString = '<?xml version="1.0"?><listing xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd"></listing>';

        $xmlData = [
            ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                        ["item" => ["name" => "js_config", "xsi:type" => "array", 0 => [
                                    ["item" => ["name" => "provider", "xsi:type" => "string", 0 => $componentName . "." . $componentName . "_data_source"]],
                                    ["item" => ["name" => "deps", "xsi:type" => "string", 0 => $componentName . "." . $componentName . "_data_source"]]
                                ]]],
                        ["item" => ["name" => "spinner", "xsi:type" => "string", 0 => "spinner_columns"]],
                        ["item" => ["name" => "buttons", "xsi:type" => "array", 0 => [
                                    ["item" => ["name" => "add", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "name", "xsi:type" => "string", 0 => "add"]],
                                                ["item" => ["name" => "label", "xsi:type" => "string", "translate" => "true", 0 => "Add New"]],
                                                ["item" => ["name" => "class", "xsi:type" => "string", 0 => "primary"]],
                                                ["item" => ["name" => "url", "xsi:type" => "string", 0 => "*/*/new"]]
                                            ]]]]]]]]],
            ["dataSource" => ["name" => "nameOfDataSource", 0 => [
                        ["argument" => ["name" => "dataProvider", "xsi:type" => "configurableObject", 0 => [
                                    ["argument" => ["name" => "class", "xsi:type" => "string", 0 => "Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider"]],
                                    ["argument" => ["name" => "name", "xsi:type" => "string", 0 => $componentName . "_data_source"]],
                                    ["argument" => ["name" => "primaryFieldName", "xsi:type" => "string", 0 => "id"]],
                                    ["argument" => ["name" => "requestFieldName", "xsi:type" => "string", 0 => "id"]],
                                    ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                            ["item" => ["name" => "component", "xsi:type" => "string", 0 => "Magento_Ui/js/grid/provider"]],
                                                            ["item" => ["name" => "update_url", "xsi:type" => "url", "path" => "mui/index/render"]],
                                                            ["item" => ["name" => "storageConfig", "xsi:type" => "array", 0 => [
                                                                        ["item" => ["name" => "indexField", "xsi:type" => "string", 0 => "id"]]
                                                                    ]]]]]]]]]]]]]]],
            ["listingToolbar" => ["name" => "listing_top", 0 => [
                        ["filters" => ["name" => "listing_filters", 0 => [
                                    ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                            ["item" => ["name" => "params", "xsi:type" => "array", 0 => [
                                                                        ["item" => ["name" => "filters_modifier", "xsi:type" => "array"]]]]],
                                                            ["item" => ["name" => "observers", "xsi:type" => "array"]]]]]]]],
                                    ["settings" => [0 => [
                                                ["statefull" => [0 => [
                                                            ["property" => ["name" => "applied", "xsi:type" => "boolean", 0 => "false"]]]]]]]]]]],
                        ["paging" => ["name" => "listing_paging"]]]]],
            ["columns" => ["name" => "spinner_columns", 0 => [
                        ["selectionsColumn" => ["name" => "ids", 0 => [
                                    ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                            ["item" => ["name" => "resizeEnabled", "xsi:type" => "boolean", 0 => "false"]],
                                                            ["item" => ["name" => "resizeDefaultWidth", "xsi:type" => "string", 0 => "55"]],
                                                            ["item" => ["name" => "indexField", "xsi:type" => "string", 0 => "id"]]
                                                        ]]]]]]]]],
                        ["column" => ["name" => "id", 0 => [
                                    ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                            ["item" => ["name" => "filter", "xsi:type" => "string", 0 => "textRange"]],
                                                            ["item" => ["name" => "sorting", "xsi:type" => "string", 0 => "asc"]],
                                                            ["item" => ["name" => "label", "xsi:type" => "string", "translate" => "true", 0 => "ID"]]
                                                        ]]]]]]]]],
                        ["column" => ["name" => "name", 0 => [
                                    ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                            ["item" => ["name" => "filter", "xsi:type" => "string", 0 => "text"]],
                                                            ["item" => ["name" => "editor", "xsi:type" => "array", 0 => [
                                                                        ["item" => ["name" => "editorType", "xsi:type" => "string", 0 => "text"]],
                                                                        ["item" => ["name" => "validation", "xsi:type" => "array", 0 => [
                                                                                    ["item" => ["name" => "required-entry", "xsi:type" => "boolean", 0 => "true"]]
                                                                                ]]]]]],
                                                            ["item" => ["name" => "label", "xsi:type" => "string", "translate" => "true", 0 => "Name"]]
                                                        ]]]]]]]]],
                        ["actionsColumn" => ["name" => "actions", "class" => $this->company . "\\" . $this->module . "\\Ui\\Component\\Listing\\Column\\" . $this->model . "Actions",
                                0 => [
                                    ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                            ["item" => ["name" => "resizeEnabled", "xsi:type" => "boolean", 0 => "false"]],
                                                            ["item" => ["name" => "resizeDefaultWidth", "xsi:type" => "string", 0 => "110"]],
                                                            ["item" => ["name" => "indexField", "xsi:type" => "string", 0 => "id"]],
                                                        ]
                                                    ]
                                                ]
                                            ]
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

        $this->saveXml($xml, 'view/adminhtml/ui_component/' . $componentName . ".xml");
    }

    private function createAdminPageXml() {
        $xmlString = '<?xml version="1.0"?><page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd"></page>';

        $xmlData = [
            ["update" => ["handle" => "styles"]],
            ["head" => [0 => [["title" => [0 => $this->menuItemName]]]]],
            ["body" => [0 => [
                        ["referenceContainer" => ["name" => "content", 0 => [
                                    ["block" => ["class" => "Magento\Backend\Block\Template", "template" => $this->company . "_" . $this->module . "::" . strtolower($this->action) . ".phtml"]],
                                    ["uiComponent" => ["name" => strtolower($this->company . "_" . $this->module . "_" . $this->model . "_listing")]]
                                ]]]]]]
        ];
        $xml = $this->createSimpleXml($xmlString, $xmlData);
        $this->saveXml($xml, 'view/adminhtml/layout/' . strtolower($this->module . "_" . $this->model . "_" . $this->action . ".xml"));
    }

    private function editDiXml() {
        if (!file_exists("etc")) {
            mkdir("etc");
        }
        $xml = false;
        $typeData = [
            ["type" => ["name" => "Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\CollectionFactory", 0 => [
                        ["arguments" => [0 => [
                                    ["argument" => ["name" => "collections", "xsi:type" => "array"]]
                                ]]]]]]
        ];
        if (file_exists("etc/di.xml")) {
            $xml = simplexml_load_file("etc/di.xml");
            if (!$xml->xpath('/config/type[@name="Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\CollectionFactory"]')) {
                $this->addSimpleXml($xml, $typeData);
            }
        } else {
            $xmlString = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd"></config>';
            $xml = $this->createSimpleXml($xmlString, $typeData);
        }
        $argumentList = $xml->xpath('/config/type[@name="Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\CollectionFactory"]/arguments/argument');
        $argument = $argumentList[0];

        // type - Eintrag
        $name = strtolower($this->company . "_" . $this->module . "_" . $this->model . "_listing_data_source");
        $virtualType = $this->company . "\\" . $this->module . "\\Model\\ResourceModel\\" . $this->model . "\\Grid\\Collection";
        $itemData = [
            ["item" => ["name" => $name, "xsi:type" => "string", 0 => $virtualType]]
        ];
        $this->addSimpleXml($argument, $itemData);

        // virtualtype - Eintrag
        $virtualTypeData = [
            ["virtualType" => [
                    "name" => $virtualType,
                    "type" => "Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\SearchResult",
                    0 => [
                        ["arguments" => [0 => [
                                    ["argument" => [
                                            "name" => "mainTable",
                                            "xsi:type" => "string",
                                            0 => $this->dbTable
                                        ]
                                    ],
                                    ["argument" => [
                                            "name" => "resourceModel",
                                            "xsi:type" => "string",
                                            0 => $this->company . "\\" . $this->module . "\\Model\\ResourceModel\\" . $this->model . "\\Collection"
                                        ]
                                    ]
                                ]]]
                    ]
                ]
            ]
        ];
        $this->addSimpleXml($xml, $virtualTypeData);

        $this->saveXml($xml, "etc/di.xml");
    }

    private function createActionsFile() {
        if (!file_exists('Ui')) {
            mkdir('Ui');
        }
        if (!file_exists('Ui/Component')) {
            mkdir('Ui/Component');
        }
        if (!file_exists('Ui/Component/Listing')) {
            mkdir('Ui/Component/Listing');
        }
        if (!file_exists('Ui/Component/Listing/Column')) {
            mkdir('Ui/Component/Listing/Column');
        }
        $content = "<?php

namespace " . $this->company . "\\" . $this->module . "\\Ui\\Component\\Listing\\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class " . $this->model . "Actions extends Column
{

    protected \$urlBuilder;

    public function __construct(ContextInterface \$context, UiComponentFactory \$uiComponentFactory, UrlInterface \$urlBuilder, array \$components = [], array \$data = []) {
        \$this->urlBuilder = \$urlBuilder;
        parent::__construct(\$context, \$uiComponentFactory, \$components, \$data);
    }

    public function prepareDataSource(array \$dataSource) {
        if (isset(\$dataSource['data']['items'])) {
            foreach (\$dataSource['data']['items'] as &\$item) {
                \$item[\$this->getData('name')]['edit'] = [
                    'href' => \$this->urlBuilder->getUrl('" . strtolower($this->module) . "/" . strtolower($this->model) . "/edit', ['id' => \$item['id']]),
                    'label' => __('Edit'),
                    'hidden' => false
                ];
                \$item[\$this->getData('name')]['delete'] = [
                    'href' => \$this->urlBuilder->getUrl('" . strtolower($this->module) . "/" . strtolower($this->model) . "/delete', ['id' => \$item['id']]),
                    'label' => __('Delete'),
                    'hidden' => false,
                    'confirm' => [
                        'title' => __('Delete item'),
                        'message' => __('Are you sure?')
                    ]
                ];
            }
        }

        return \$dataSource;
    }

}";
        file_put_contents('Ui/Component/Listing/Column/' . $this->model . "Actions.php", $content);
    }

    private function createEditXmlFiles() {
        if (!file_exists('view')) {
            mkdir('view');
        }
        if (!file_exists('view/adminhtml')) {
            mkdir('view/adminhtml');
        }
        if (!file_exists('view/adminhtml/layout')) {
            mkdir('view/adminhtml/layout');
        }

        $xmlString = '<?xml version="1.0"?><page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd"></page>';
        $xmlData = [
            ["head" => [0 => [["title" => [0 => $this->model . " - Edit"]]]]],
            ["referenceContainer" => ["name" => "content", 0 => [
                        ["block" => [
                                "class" => $this->company . "\\" . $this->module . "\\Block\\Adminhtml\\" . $this->model . "\\Edit",
                                "name" => strtolower($this->module . "_" . $this->model . "_edit"),
                                "template" => $this->company . "_" . $this->module . "::edit.phtml"]],
                        ["uiComponent" => ["name" => strtolower($this->module . "_" . $this->model . "_form")]]
                    ]]]
        ];
        $xml = $this->createSimpleXml($xmlString, $xmlData);
        $this->saveXml($xml, 'view/adminhtml/layout/' . strtolower($this->module . "_" . $this->model . "_edit.xml"));

        $xmlString = '<?xml version="1.0"?><page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd"></page>';
        $xmlData = [
            ["update" => ["handle" => strtolower($this->module . "_" . $this->model . "_edit")]],
            ["head" => [0 => [["title" => [0 => $this->model . " - New"]]]]],
        ];
        $xml = $this->createSimpleXml($xmlString, $xmlData);
        $this->saveXml($xml, 'view/adminhtml/layout/' . strtolower($this->module . "_" . $this->model . "_new.xml"));
    }

    private function createFormXml() {
        if (!file_exists('view')) {
            mkdir('view');
        }
        if (!file_exists('view/adminhtml')) {
            mkdir('view/adminhtml');
        }
        if (!file_exists('view/adminhtml/ui_component')) {
            mkdir('view/adminhtml/ui_component');
        }

        $blockPath = $this->company . "\\" . $this->module . "\\Block\\Adminhtml\\" . $this->model . "\\Edit\\";
        $xmlString = '<?xml version="1.0" encoding="UTF-8"?><form xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd"></form>';
        $xmlData = [
            ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                        ["item" => ["name" => "js_config", "xsi:type" => "array", 0 => [
                                    ["item" => ["name" => "provider", "xsi:type" => "string",
                                            0 => strtolower($this->module . "_" . $this->model . "_form." . $this->model . "_form_data_source")]],
                                    ["item" => ["name" => "deps", "xsi:type" => "string",
                                            0 => strtolower($this->module . "_" . $this->model . "_form." . $this->model . "_form_data_source")]],
                                ]]],
                        ["item" => ["name" => "label", "xsi:type" => "string", "translate" => "true", 0 => $this->model . " - Form"]],
                        ["item" => ["name" => "layout", "xsi:type" => "array", 0 => [
                                    ["item" => ["name" => "type", "xsi:type" => "string", 0 => "tabs"]]]]],
                        ["item" => ["name" => "buttons", "xsi:type" => "array", 0 => [
                                    ["item" => ["name" => "back", "xsi:type" => "string", 0 => $blockPath . "BackButton"]],
                                    ["item" => ["name" => "delete", "xsi:type" => "string", 0 => $blockPath . "DeleteButton"]],
                                    ["item" => ["name" => "reset", "xsi:type" => "string", 0 => $blockPath . "ResetButton"]],
                                    ["item" => ["name" => "save", "xsi:type" => "string", 0 => $blockPath . "SaveButton"]]
                                ]]]]]],
            ["dataSource" => ["name" => strtolower($this->model . "_form_data_source"), 0 => [
                        ["argument" => ["name" => "dataProvider", "xsi:type" => "configurableObject", 0 => [
                                    ["argument" => ["name" => "class", "xsi:type" => "string",
                                            0 => $this->company . "\\" . $this->module . "\\Model\\" . $this->model . "\\DataProvider"]],
                                    ["argument" => ["name" => "name", "xsi:type" => "string", 0 => strtolower($this->model . "_form_data_source")]],
                                    ["argument" => ["name" => "primaryFieldName", "xsi:type" => "string", 0 => "id"]],
                                    ["argument" => ["name" => "requestFieldName", "xsi:type" => "string", 0 => "id"]],
                                    ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                            ["item" => ["name" => "submit_url", "xsi:type" => "url", "path" => strtolower($this->module . "/" . $this->model . "/save")]]
                                                        ]]]]]]]]],
                        ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                    ["item" => ["name" => "js_config", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "component", "xsi:type" => "string", 0 => "Magento_Ui/js/form/provider"]]
                                            ]]]]]]]]],
            ["fieldset" => ["name" => strtolower($this->model), 0 => [
                        ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                    ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "label", "xsi:type" => "string", "translate" => "true", 0 => $this->model . " Fieldset"]]
                                            ]]]]]],
                        ["field" => ["name" => "id", 0 => [
                                    ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                            ["item" => ["name" => "visible", "xsi:type" => "boolean", 0 => "false"]],
                                                            ["item" => ["name" => "dataType", "xsi:type" => "string", 0 => "text"]],
                                                            ["item" => ["name" => "formElement", "xsi:type" => "string", 0 => "input"]],
                                                            ["item" => ["name" => "source", "xsi:type" => "string", 0 => strtolower($this->model)]]
                                                        ]]]]]]]]],
                        ["field" => ["name" => "name", 0 => [
                                    ["argument" => ["name" => "data", "xsi:type" => "array", 0 => [
                                                ["item" => ["name" => "config", "xsi:type" => "array", 0 => [
                                                            ["item" => ["name" => "visible", "xsi:type" => "boolean", 0 => "true"]],
                                                            ["item" => ["name" => "dataType", "xsi:type" => "string", 0 => "text"]],
                                                            ["item" => ["name" => "formElement", "xsi:type" => "string", 0 => "input"]],
                                                            ["item" => ["name" => "source", "xsi:type" => "string", 0 => strtolower($this->model)]]
                                                        ]]]]]]]]],
                    ]]]
        ];

        $xml = $this->createSimpleXml($xmlString, $xmlData);
        $this->saveXml($xml, 'view/adminhtml/ui_component/' . strtolower($this->module . "_" . $this->model . "_form.xml"));
    }

    private function createDataProvider() {
        if (!file_exists('Model')) {
            mkdir('Model');
        }
        if (!file_exists('Model/' . $this->model)) {
            mkdir('Model/' . $this->model);
        }

        $content = "<?php

namespace " . $this->company . "\\" . $this->module . "\\Model\\" . $this->model . ";

use " . $this->company . "\\" . $this->module . "\\Model\\ResourceModel\\" . $this->model . "\\CollectionFactory;

class DataProvider extends \\Magento\\Ui\\DataProvider\\AbstractDataProvider
{

    /**
     * @param string \$name
     * @param string \$primaryFieldName
     * @param string \$requestFieldName
     * @param CollectionFactory \$collectionFactory
     * @param array \$meta
     * @param array \$data
     */
    public function __construct(
    \$name, \$primaryFieldName, \$requestFieldName, CollectionFactory \$collectionFactory, array \$meta = [], array \$data = []
    ) {
        \$this->collection = \$collectionFactory->create();
        parent::__construct(\$name, \$primaryFieldName, \$requestFieldName, \$meta, \$data);
    }

    public function getData() {
        if (isset(\$this->loadedData)) {
            return \$this->loadedData;
        }

        \$items = \$this->collection->getItems();
        \$this->loadedData = array();
        /** @var Customer \$customer */
        foreach (\$items as \$item) {
            \$this->loadedData[\$item->getId()]['category'] = \$item->getData();
        }


        return \$this->loadedData;
    }

}";
        file_put_contents('Model/' . $this->model . "/DataProvider.php", $content);
    }

    private function createButtonClasses() {
        if (!file_exists('Block')) {
            mkdir('Block');
        }
        if (!file_exists('Block/Adminhtml')) {
            mkdir('Block/Adminhtml');
        }
        if (!file_exists('Block/Adminhtml/' . $this->model)) {
            mkdir('Block/Adminhtml/' . $this->model);
        }
        if (!file_exists('Block/Adminhtml/' . $this->model . '/Edit')) {
            mkdir('Block/Adminhtml/' . $this->model . '/Edit');
        }

        $content = "<?php

namespace " . $this->company . "\\" . $this->module . "\\Block\\Adminhtml\\" . $this->model . "\\Edit;

use Magento\\Search\\Controller\\RegistryConstants;

/**
 * Class GenericButton
 */
class GenericButton
{

    /**
     * Url Builder
     *
     * @var \\Magento\\Framework\\UrlInterface
     */
    protected \$urlBuilder;

    /**
     * Registry
     *
     * @var \\Magento\\Framework\\Registry
     */
    protected \$registry;

    /**
     * Constructor
     *
     * @param \\Magento\\Backend\\Block\\Widget\\Context \$context
     * @param \\Magento\\Framework\\Registry \$registry
     */
    public function __construct(
    \\Magento\\Backend\\Block\\Widget\\Context \$context, \\Magento\\Framework\\Registry \$registry
    ) {
        \$this->urlBuilder = \$context->getUrlBuilder();
        \$this->registry = \$registry;
    }

    /**
     * Return the synonyms group Id.
     *
     * @return int|null
     */
    public function getId() {
        \$item = \$this->registry->registry('" . strtolower($this->model) . "');
        return \$item ? \$item->getId() : null;
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string \$route
     * @param   array \$params
     * @return  string
     */
    public function getUrl(\$route = '', \$params = []) {
        return \$this->urlBuilder->getUrl(\$route, \$params);
    }

}
";
        file_put_contents('Block/Adminhtml/' . $this->model . '/Edit/GenericButton.php', $content);

        $content = "<?php

namespace " . $this->company . "\\" . $this->module . "\\Block\\Adminhtml\\" . $this->model . "\\Edit;

use Magento\\Framework\\View\\Element\\UiComponent\\Control\\ButtonProviderInterface;

/**
 * Class BackButton
 */
class BackButton extends GenericButton implements ButtonProviderInterface
{

    /**
     * @return array
     */
    public function getButtonData() {
        return [
            'label' => __('Back'),
            'on_click' => sprintf(\"location.href = '%s';\", \$this->getBackUrl()),
            'class' => 'back',
            'sort_order' => 10
        ];
    }

    /**
     * Get URL for back (reset) button
     *
     * @return string
     */
    public function getBackUrl() {
        return \$this->getUrl('*/*/grid');
    }

}
";

        file_put_contents('Block/Adminhtml/' . $this->model . '/Edit/BackButton.php', $content);

        $content = "<?php
namespace " . $this->company . "\\" . $this->module . "\\Block\\Adminhtml\\" . $this->model . "\\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Class DeleteButton
 */
class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData()
    {
        \$data = [];
        if (\$this->getId()) {
            \$data = [
                'label' => __('Delete'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\''
                    . __('Are you sure you want to delete this contact ?')
                    . '\', \'' . \$this->getDeleteUrl() . '\')',
                'sort_order' => 20,
            ];
        }
        return \$data;
    }

    /**
     * @return string
     */
    public function getDeleteUrl()
    {
        return \$this->getUrl('*/*/delete', ['id' => \$this->getId()]);
    }
}";

        file_put_contents('Block/Adminhtml/' . $this->model . '/Edit/DeleteButton.php', $content);

        $content = "<?php

namespace " . $this->company . "\\" . $this->module . "\\Block\\Adminhtml\\" . $this->model . "\\Edit;

use Magento\\Framework\\View\\Element\\UiComponent\\Control\\ButtonProviderInterface;

/**
 * Class ResetButton
 */
class ResetButton implements ButtonProviderInterface
{

    /**
     * @return array
     */
    public function getButtonData() {
        return [
            'label' => __('Reset'),
            'class' => 'reset',
            'on_click' => 'location.reload();',
            'sort_order' => 30
        ];
    }

}
";

        file_put_contents('Block/Adminhtml/' . $this->model . '/Edit/ResetButton.php', $content);

        $content = "<?php
namespace " . $this->company . "\\" . $this->module . "\\Block\\Adminhtml\\" . $this->model . "\\Edit;

use Magento\\Framework\\View\\Element\\UiComponent\\Control\\ButtonProviderInterface;

/**
 * Class SaveButton
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData()
    {
        return [
            'label' => __('Save'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}";

        file_put_contents('Block/Adminhtml/' . $this->model . '/Edit/SaveButton.php', $content);
    }

    private function createControllerClasses() {
        if (!file_exists('Controller')) {
            mkdir('Controller');
        }
        if (!file_exists('Controller/Adminhtml')) {
            mkdir('Controller/Adminhtml');
        }
        if (!file_exists('Controller/Adminhtml/' . $this->model)) {
            mkdir('Controller/Adminhtml/' . $this->model);
        }

        $content = "<?php

namespace " . $this->company . "\\" . $this->module . "\\Controller\\Adminhtml\\" . $this->model . ";

class Save extends \\Magento\\Backend\\App\\Action
{

    const ADMIN_RESOURCE = '" . $this->model . "';

    protected \$resultPageFactory;
    protected \$itemFactory;

    public function __construct(
    \Magento\Backend\App\Action\Context \$context, \Magento\Framework\View\Result\PageFactory \$resultPageFactory, \\" . $this->company . "\\" . $this->module . "\\Model\\" . $this->model . "Factory \$itemFactory
    ) {
        \$this->resultPageFactory = \$resultPageFactory;
        \$this->itemFactory = \$itemFactory;
        parent::__construct(\$context);
    }

    public function execute() {
        \$resultRedirect = \$this->resultRedirectFactory->create();
        \$data = \$this->getRequest()->getPostValue('" . strtolower($this->model) . "');

        if (\$data) {
            try {
                
                \$item = \$this->itemFactory->create();
                if (isset(\$data[\"id\"])) {
                    \$item->load(\$data[\"id\"]);
                }

                \$data = array_filter(\$data, function(\$value) {
                    return \$value !== '';
                });

                \$item->setData(\$data);
                \$item->save();
                \$this->messageManager->addSuccess(__('Successfully saved the item.'));
                \$this->_objectManager->get('Magento\Backend\Model\Session')->setFormData(false);
                return \$resultRedirect->setPath('*/*/grid');
            } catch (\Exception \$e) {
                \$this->messageManager->addError(\$e->getMessage());
                \$this->_objectManager->get('Magento\Backend\Model\Session')->setFormData(\$data);
                if (isset(\$item) && \$item->getId()) {
                    return \$resultRedirect->setPath('*/*/edit', ['id' => \$item->getId()]);
                } else {
                    return \$resultRedirect->setPath('*/*/grid');
                }
            }
        }

        return \$resultRedirect->setPath('*/*/grid');
    }

}
";
        file_put_contents('Controller/Adminhtml/' . $this->model . '/Save.php', $content);

        $content = "<?php

namespace " . $this->company . "\\" . $this->module . "\\Controller\\Adminhtml\\" . $this->model . ";

use " . $this->company . "\\" . $this->module . "\\Model\\" . $this->model . ";
use Magento\Backend\App\Action;

class Delete extends \Magento\Backend\App\Action
{

    public function execute() {
        \$id = \$this->getRequest()->getParam('id');

        if (!(\$item = \$this->_objectManager->create(" . $this->model . "::class)->load(\$id))) {
            \$this->messageManager->addError(__('Unable to proceed. Please, try again.'));
            \$resultRedirect = \$this->resultRedirectFactory->create();
            return \$resultRedirect->setPath('*/*/grid', array('_current' => true));
        }
        try {
            \$item->delete();
            \$this->messageManager->addSuccess(__('Your item has been deleted !'));
        } catch (Exception \$e) {
            \$this->messageManager->addError(__('Error while trying to delete item: '));
            \$resultRedirect = \$this->resultRedirectFactory->create();
            return \$resultRedirect->setPath('*/*/grid', array('_current' => true));
        }

        \$resultRedirect = \$this->resultRedirectFactory->create();
        return \$resultRedirect->setPath('*/*/grid', array('_current' => true));
    }

}
";

        file_put_contents('Controller/Adminhtml/' . $this->model . '/Delete.php', $content);
    }

}
