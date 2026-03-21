<?php

namespace MediaDivision\Basics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use \Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem\DirectoryList;

class Data extends AbstractHelper
{

    private $logger;
    private $installDir;

    public function __construct(Context $context, DirectoryList $directoryList, LoggerInterface $logger) {
        $this->logger = $logger;
        $this->installDir = $directoryList->getRoot();
        parent::__construct($context);
    }

    /**
     * Bereinigt das Verzeichnis. Gemeint ist, dass alle Dateien gelöscht werden,
     * deren Alter größer als $age Tage ist. Unterverzeichnisse werden ignoriert.
     *
     * @param string $directory Verzeichnis das bereinigt wird (relativ zum Installationsverzeichnis von Magento).
     * @param type $age Alter der Dateien in Tagen
     */
    public function cleanCacheDirectory($directory, $age) {
        if (!file_exists($this->installDir . '/' . $directory)) {
            return array(
                'success' => false,
                'message' => "Verzeichnis " . $directory . " existiert nicht.",
            );
        }
        $files = glob($directory . "/*");
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) >= 60 * 60 * 24 * $age)) {
                unlink($file);
            }
        }
        return array(
            'success' => true,
            'message' => '',
        );
    }

}
