<?php

namespace MediaDivision\Basics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use \Psr\Log\LoggerInterface;

class Nagios extends AbstractHelper
{

    private $liveServer = "fizz224.lcube-server.de"; // nur auf diesem Host NSCA-Meldungen schicken
    private $nagiosHost = '83.246.47.178';
    private $hostname = "fizz224.lcube-server.de";
    private $sendNsca = "/usr/sbin/send_nsca";
    private $sendNscaCfg = '/var/www/vhosts/fizz224.lcube-server.de/Sortimo/data/send_nsca.cfg';
    private $logger;
    
    public function __construct(Context $context,LoggerInterface $logger) {
        $this->logger = $logger;
        parent::__construct($context);
    }
    
    public function sendNscaReport($service, $status, $text) {
        // Eine lokale Installation oder ein Staging-System sollte keine Resultate an Nagios schicken
        // sondern nur das Live-System
        if (!$this->isLiveServer(gethostname())) {
            $this->logger->info('NSCA-Report nicht versendet!');
            return;
        }
        if (!file_exists($this->sendNsca)) {
            $this->logger->critical('Nagios-Plugin-Konfiguration-Fehler: ' . $this->sendNsca . " ist nicht vorhanden!");
            return;
        }
        # Kommandozeile
        $command = "echo -e \"" . $this->hostname . "\\t" . $service . "\\t" . $status . "\\t" . $text . "\\n\"|"
            . $this->sendNsca . " -H " . $this->nagiosHost . " -c " . $this->sendNscaCfg;
        $this->logger->info($command);
        $output = `$command`;
        $this->logger->info($output);
    }

    private function isLiveServer($hostname) {
        return $this->liveServer == $hostname;
    }

}
