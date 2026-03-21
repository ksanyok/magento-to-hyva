<?php

namespace MediaDivision\Magmi\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\Filesystem\DirectoryList;
use \Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{

    protected $installDir;
    private $magmiDirectory = "var/magmi/";
    private $magmiFile = "products.csv";
    private $phpBin;
    private $options;

    public function __construct(Context $context, DirectoryList $directoryList) {
        $this->installDir = $directoryList->getRoot();
        parent::__construct($context);
    }

    /**
     * Do not use scopeConfig in __construct !
     */
    private function getPhpConfig() {
        $this->phpBin = $this->scopeConfig->getValue("basics/general/php_binary");
        $this->options = $this->scopeConfig->getValue("basics/general/php_options", ScopeInterface::SCOPE_STORE, null);
    }

    /**
     * Schreibt die für Magmi aufbereiteten Daten in $data in die Datei $this->magmiFile.
     *
     * @param array $header Daten für die Headerzeile
     * @param array $data CSV-Daten
     */
    public function writeMagmiFile($header, $data) {
        $connection = fopen($this->installDir . "/" . $this->magmiDirectory . $this->magmiFile, "w");
        fputcsv($connection, $header, ";");
        foreach ($data as $line) {
            $csvline = array();
            foreach ($header as $column) {
                $csvline[] = $line[$column];
            }
            fputcsv($connection, $csvline, ";");
        }
        fclose($connection);
        copy(
                $this->installDir . "/" . $this->magmiDirectory . $this->magmiFile, $this->installDir . "/" . $this->magmiDirectory . "archive/" . date('YmdHis') . "_" . $this->magmiFile
        );
        $result = array(
            'success' => true,
            'message' => ''
        );
        return $result;
    }

    /**
     * Mode:
     * update: Update existing items only,skip new ones
     * create: create new items & update existing ones
     * xcreate: create new items only, skip existing ones
     *
     * @param string $profile Magmi-Profil
     * @param string $mode Magmi-Modus
     * @param array|false $reindex Liste von Indizes, die reindexiert werden sollen. Wenn leer werden alle Indizes reindexiert. Wenn false wird nicht reindexiert.
     */
    public function import($profile, $mode, $reindex = []) {
        $this->getPhpConfig(); // Fill $this->phpBin and $this->options
        if (!file_exists($this->phpBin)) {
            die($this->phpBin . ": File does not exist. Please check your Magento-Konfiguration!\n");
        }

        $command = $this->phpBin . " " . $this->options . " magmi.cli.php -profile=" . $profile . " -mode=" . $mode;
        // Magmi starten
        chdir($this->installDir . '/magmi/cli');
        `$command`;
        chdir($this->installDir);
        copy("magmi/state/progress.txt", $this->magmiDirectory . "archive/" . date('YmdHis') . "_progress.txt");

        if (is_array($reindex)) {
            $indexParams = "";
            foreach ($reindex as $index) {
                $indexParams .= $index . " ";
            }
            chdir($this->installDir . "/bin");
            system($this->phpBin . " " . $this->options . " magento indexer:reset " . $indexParams);
            chdir($this->installDir);
        }
    }

    public function reindex($params = []) {
        $this->getPhpConfig(); // Fill $this->phpBin and $this->options
        $indexParams = "";
        foreach ($params as $index) {
            $indexParams .= $index . " ";
        }
        chdir($this->installDir . "/bin");
        system($this->phpBin . " " . $this->options . " magento indexer:reindex " . $indexParams);
        chdir($this->installDir);
    }

}
