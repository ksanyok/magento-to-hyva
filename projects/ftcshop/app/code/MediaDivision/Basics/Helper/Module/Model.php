<?php

/**
 * Unter dieser URL findet sich ein Code-Schnipsel, mit dem man dann das neue Model testen kann.
 * https://www.mageplaza.com/magento-2-module-development/how-to-create-crud-model-magento-2.html#step-5-factory-object
 */

namespace MediaDivision\Basics\Helper\Module;

use MediaDivision\Basics\Helper\ModuleAbstract;

class Model extends ModuleAbstract
{

    private $name;
    private $dbTable;

    public function __construct(
    \Magento\Framework\App\Helper\Context $context) {
        parent::__construct($context);
    }

    public function handleTask($company, $module) {
        $this->company = $company;
        $this->module = $module;
        echo "\n\n - Datenbank-Model anlegen - \n\n";

        $this->name = readline("\nName des Datenbank-Model? ");
        $this->dbTable = readline("\nName der Datenbank-Tabelle? ");
        $this->createSetupScript();
        $this->createModel();
        $this->createResourceModel();
        $this->createCollection();

        echo "\n\n - Die Datei Setup/InstallSchema.php bzw. Setup/UpgradeSchema.php muss noch editiert werden!\n";
        echo " - Um die Datenbank-Änderung aufzurufen, muss in der Datei etc/module.xml die setup_version erhöht werden.\n";
        echo " - In der if-Abfrage des UpgradeSchema muss die neue setup_version der module.xml stehen, damit das Upgrade eingespielt wird.\n";
        echo " - Wurde die Datei InstallSchema.php bei einem bereits aktivierten Modul erzeugt, muss in der Tabelle \n";
        echo "   setup_module die Zeile mit " . $this->company . "_" . $this->module . " in der Spalte module entfernt werden, \n";
        echo "   damit das InstallSchema aufgerufen wird\n";
        echo " - Danach dann bin/magento setup:upgrade aufrufen, um die Datenbank-Änderungen einzubauen.\n\n";
    }

    public function create($company, $module, $name, $dbTable) {
        $this->company = $company;
        $this->module = $module;
        $this->name = $name;
        $this->dbTable = $dbTable;
        $this->createSetupScript();
        $this->createModel();
        $this->createResourceModel();
        $this->createCollection();
    }

    private function createCollection() {
        if (!file_exists("Model")) {
            mkdir("Model");
        }
        if (!file_exists("Model/ResourceModel")) {
            mkdir("Model/ResourceModel");
        }
        if (!file_exists("Model/ResourceModel/" . $this->name)) {
            mkdir("Model/ResourceModel/" . $this->name);
        }
        $content = "<?php
            
namespace " . $this->company . "\\" . $this->module . "\\Model\\ResourceModel\\" . $this->name . ";

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected \$_idFieldName = 'id';
    protected \$_eventPrefix = '" . $this->dbTable . "_collection';
    protected \$_eventObject = '" . strtolower($this->name) . "_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct() {
        \$this->_init('" . $this->company . "\\" . $this->module . "\\Model\\" . $this->name . "', '" . $this->company . "\\" . $this->module . "\\Model\\ResourceModel\\" . $this->name . "');
    }

}";
        file_put_contents("Model/ResourceModel/" . $this->name . "/Collection.php", $content);
    }

    private function createResourceModel() {
        if (!file_exists("Model")) {
            mkdir("Model");
        }
        if (!file_exists("Model/ResourceModel")) {
            mkdir("Model/ResourceModel");
        }
        $content = "<?php
            
namespace " . $this->company . "\\" . $this->module . "\\Model\ResourceModel;


class " . $this->name . " extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    
    public function __construct(\Magento\Framework\Model\ResourceModel\Db\Context \$context) {
        parent::__construct(\$context);
    }
    
    protected function _construct() {
        \$this->_init('" . $this->dbTable . "', 'id');
    }
    
}";
        file_put_contents("Model/ResourceModel/" . $this->name . ".php", $content);
    }

    private function createModel() {
        if (!file_exists("Model")) {
            mkdir("Model");
        }
        $content = "<?php
namespace " . $this->company . "\\" . $this->module . "\\Model;
    
class " . $this->name . " extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = '" . $this->dbTable . "';

    protected \$_cacheTag = '" . $this->dbTable . "';
    protected \$_eventPrefix = '" . $this->dbTable . "';

    protected function _construct() {
        \$this->_init('" . $this->company . "\\" . $this->module . "\\Model\\ResourceModel\\" . $this->name . "');
    }

    public function getIdentities() {
        return [self::CACHE_TAG . '_' . \$this->getId()];
    }

    public function getDefaultValues() {
        \$values = [];

        return \$values;
    }
}";
        file_put_contents("Model/" . $this->name . ".php", $content);
    }

    private function createSetupScript() {
        if (!file_exists("Setup")) {
            mkdir("Setup");
        }
        $classname = "InstallSchema";
        $function = "install";
        $upgradeIfBegin = "";
        $upgradeIfEnd = "";
        if (file_exists("Setup/" . $classname . ".php")) {
            $classname = "UpgradeSchema";
            $function = "upgrade";
            $upgradeIfBegin = "if(version_compare(\$context->getVersion(), '" . $this->getSetupVersion() . "', '<')) {\n";
            $upgradeIfEnd = "}\n";
        }
        $content = "<?php
namespace " . $this->company . "\\" . $this->module . "\\Setup;

class " . $classname . " implements \Magento\Framework\Setup\\" . $classname . "Interface
{

    public function " . $function . "(\\Magento\\Framework\\Setup\\SchemaSetupInterface \$setup, \\Magento\\Framework\\Setup\\ModuleContextInterface \$context)
    {
        \$installer = \$setup;
        \$installer->startSetup();
        
        " . $upgradeIfBegin . "
        if (!\$installer->tableExists('" . $this->dbTable . "')) {
            \$table = \$installer->getConnection()->newTable(
                \$installer->getTable('" . $this->dbTable . "')
            )
                ->addColumn(
                    'id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'nullable' => false,
                        'primary'  => true,
                        'unsigned' => true,
                    ],
                    'ID'
                )
                ->addColumn(
                    'name',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    255,
                    ['nullable => false'],
                    'Name'
                );
            \$installer->getConnection()->createTable(\$table);

        }
        " . $upgradeIfEnd . "
        \$installer->endSetup();
    }
}";
        $filename = "Setup/" . $classname . ".php";
        if (file_exists($filename)) {
            $filename = "Setup/" . $classname . "_" . uniqid() . ".php";
            echo "\n\nAchtung: UpgradeSchema.php exisitiert bereits. Erzeuge Datei $filename.\n";
            echo "Diese muss von Hand mit UpgradeSchema.php zusammengefügt werden.\n\n";
        }
        file_put_contents($filename, $content);
    }

}
