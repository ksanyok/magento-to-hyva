<?php

namespace MediaDivision\StoreLocator\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use \Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Mageplaza\StoreLocator\Model\LocationFactory;

class Data extends AbstractHelper
{

    private $logger;
    private $installDir;
    private $countryList = [
        "Great Britain" => "GB",
        "The Netherlands" => "NL",
        "Switzerland" => "CH",
        "Sweden" => "SE",
        "Poland" => "PL",
        "Norway" => "NO",
        "Italy" => "IT",
        "Germany" => "DE",
        "Austria" => "AT",
        "Belgium" => "BE",
        "Denmark" => "DK",
        "Finland" => "FI",
        "France" => "FR"
    ];
    private $locationFactory;
    private $resource;
    private $storeFile = '/var/texdata/storelocations/stores.csv';

    public function __construct(
            Context $context,
            DirectoryList $directoryList,
            LocationFactory $locationFactory,
            LoggerInterface $logger,
            ResourceConnection $resource) {
        $this->locationFactory = $locationFactory;
        $this->logger = $logger;
        $this->installDir = $directoryList->getRoot();
        $this->resource = $resource;
        parent::__construct($context);
    }

    public function importStoreLocations($debug = false) {
        if (($handle = fopen($this->installDir . $this->storeFile, "r")) !== FALSE) {
            $write = $this->resource->getConnection();
            $write->query("DELETE FROM mageplaza_storelocator_location_holiday");
            $write->query("DELETE FROM mageplaza_storelocator_holiday");
            $write->query("DELETE FROM mageplaza_storelocator_location");

            $head = fgetcsv($handle, 0, ",");
            $bom = pack('H*', 'EFBBBF');
            $head[0] = preg_replace("/^$bom/", '', $head[0]); // BOM entfernen
            
            while (($line = fgetcsv($handle, 0, ",")) !== FALSE) {
                if ($debug) {
                    echo ".";
                }
                $data = [];
                $location = $this->locationFactory->create();
                foreach ($head as $index => $field) {
                    $data[$field] = $line[$index];
                }
                $geo = $this->getGeoLocation($data);
                $location->setName($data["Name"])
                        ->setStatus(1)
                        ->setStoreIds(0)
                        ->setCity($data["Ort"])
                        ->setCountry($this->countryList[$data["Land"]])
                        ->setStreet($data["Strasse"])
                        ->setPostalCode($data["PLZ"])
                        ->setLatitude($geo["latitude"])
                        ->setLongitude($geo["longitude"])
                        ->setPhoneOne($data["Telefon"])
                        ->setWebsite($data["URL"])
                        ->setFax($data["Fax"])
                        ->setIsConfigWebsite(0)
                        ->setOperationMon('use_config')
                        ->setOperationTue('use_config')
                        ->setOperationWed('use_config')
                        ->setOperationThu('use_config')
                        ->setOperationFri('use_config')
                        ->setOperationSat('use_config')
                        ->setOperationSun('use_config')
                        ->setIsDefaultStore(0)
                        ->setTimeZone('use_config')
                        ->setIsConfigTimeZone(1)
                        ->setSortOrder(0)
                        ->setIsShowProductPage(0)
                        ->setIsSelectedAllProduct(0)
                        ->save();
            }
        }
    }

    private function getGeoLocation($data) {
        $geo = [
            "latitude" => 0,
            "longitude" => 0,
        ];
        $curlOptions = [
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true, // Folge redirects                                                                                                           
            CURLOPT_RETURNTRANSFER => true,
        ];

        $curlOptions[CURLOPT_URL] = "https://maps.googleapis.com/maps/api/geocode/json"
                . "?key=AIzaSyAwxUHZifmQaSfuCOUgmRDN8AFFsNklPB8"
                . "&address=" . urlencode($data["Strasse"] . ", " . $data["PLZ"] . " " . $data["Ort"] . ", " . $this->countryList[$data["Land"]]);

        $curl = curl_init();
        curl_setopt_array($curl, $curlOptions);
        $jsonResponse = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($jsonResponse, true);
        if (isset($response["results"]) && isset($response["results"][0])) {
            $location = $response["results"][0]["geometry"]["location"];
            $geo["latitude"] = preg_replace('/,/', '.', $location["lat"]);
            $geo["longitude"] = preg_replace('/,/', '.', $location["lng"]);
        }

        return $geo;
    }

}
