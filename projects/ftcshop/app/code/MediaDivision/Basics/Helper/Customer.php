<?php

namespace MediaDivision\Basics\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;

Class Customer extends AbstractHelper
{

    /**
     * @var ConfigService $configService
     */
    private $customersFile = "/var/import/customers.csv";
    private $adressesFile = "/var/import/addresses.csv";
    private $installDir;
    private $resource;
    private $storeList = [
        '0' => '3',
        '1' => '3', // de-de
        '2' => '4', // en-de
        '3' => '5', // en-dk
        '4' => '6', // en-nl
        '5' => '7', // de-at
        '6' => '8', // en_at
        '7' => '9', // en-pl
        '8' => '10', // en-se
        '9' => '1', // de-ch
        '10' => '2', // en-ch
        '11' => '11', // en-eu
        '12' => '12', // en-us
    ];

    public function __construct(
            Context $context,
            DirectoryList $directoryList,
            ResourceConnection $resource) {
        $this->installDir = $directoryList->getRoot();
        $this->resource = $resource;

        parent::__construct($context);
    }

    public function insertCustomers($debug, $anonymise) {
        $write = $this->resource->getConnection();

        if (($chandle = fopen($this->installDir . $this->customersFile, "r")) !== FALSE) {
            $head = fgetcsv($chandle, 0, ";");
            $write->query("delete from customer_entity", []);
            //$write->query("delete from customer_entity where entity_id > 9", []);
            while (($line = fgetcsv($chandle, 0, ";")) !== FALSE) {
                $data = [];
                foreach ($head as $index => $field) {
                    $data[$field] = $line[$index];
                }
                if ($data["entity_id"] < 10) {
                    //continue; // Für Testzwecke die ersten IDs übergehen, damit die Testuser nicht überschrieben werden.
                }
                $email = $data["email"];
                if ($anonymise) {
                    $email = "kunde" . $data["entity_id"] . "@ftc-cashmere.com";
                }
                // dob?
                $query = 'insert into customer_entity '
                        . '(entity_id,website_id,email,group_id,store_id,created_at,updated_at,is_active,disable_auto_group_change,' // 9
                        . 'created_in,prefix,firstname,middlename,lastname,suffix,default_billing,default_shipping,gender) VALUES '
                        . '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                try {
                    $write->query($query, [
                        $data["entity_id"],
                        $data["website_id"],
                        $email,
                        $data["group_id"],
                        $this->storeList[$data["store_id"]],
                        $data["created_at"],
                        $data["updated_at"],
                        $data["is_active"],
                        $data["disable_auto_group_change"],
                        "Admin", // created_in
                        $data["prefix"],
                        $data["firstname"],
                        $data["middlename"],
                        $data["lastname"],
                        $data["suffix"],
                        $data["default_billing"],
                        $data["default_shipping"],
                        $data["gender"],
                    ]);
                } catch (\Exception $ex) {
                    echo $ex->getMessage() . "\n";
                    //print_r($data);
                }
                //print_r($data);
            }
            fclose($chandle);
        }

        if (($ahandle = fopen($this->installDir . $this->adressesFile, "r")) !== FALSE) {
            $head = fgetcsv($ahandle, 0, ";");
            $write->query("delete from customer_address_entity", []);
            //$write->query("delete from customer_address_entity where parent_id > 9", []);
            while (($line = fgetcsv($ahandle, 0, ";")) !== FALSE) {
                $data = [];
                foreach ($head as $index => $field) {
                    $data[$field] = $line[$index];
                }
                if ($data["parent_id"] < 10) {
                    continue; // Für Testzwecke die ersten IDs übergehen, damit die Testuser nicht überschrieben werden.
                }
                // dob?, gender?, default_shipping?
                $query = 'insert into customer_address_entity '
                        . '(entity_id,parent_id,created_at,updated_at,is_active,city,company,country_id,fax,firstname,lastname,' // 11
                        . 'middlename,postcode,prefix,region,region_id,street,suffix,telephone) VALUES '
                        . '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                try {
                    $write->query($query, [
                        $data["entity_id"],
                        $data["parent_id"],
                        $data["created_at"],
                        $data["updated_at"],
                        $data["is_active"],
                        $data["city"],
                        $data["company"],
                        $data["country_id"],
                        $data["fax"],
                        $data["firstname"],
                        $data["lastname"],
                        $data["middlename"],
                        $data["postcode"],
                        $data["prefix"],
                        $data["region"],
                        $data["region_id"],
                        $data["street"],
                        $data["suffix"],
                        $data["telephone"]
                    ]);
                } catch (\Exception $ex) {
                    echo $ex->getMessage() . "\n";
                }
            }
            fclose($ahandle);
        }
    }

}
