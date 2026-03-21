<?php

namespace MediaDivision\Basics\Helper\Module;

use MediaDivision\Basics\Helper\ModuleAbstract;

class Acl extends ModuleAbstract
{

    private $function;

    public function __construct(
    \Magento\Framework\App\Helper\Context $context) {
        parent::__construct($context);
    }

    public function createAcl($company, $module, $function, $name) {
        $this->company = $company;
        $this->module = $module;
        $this->function = $function;

        if (!file_exists('etc')) {
            mkdir('etc');
        }
        if (file_exists("etc/acl.xml")) {
            $xml = simplexml_load_file("etc/acl.xml");
        } else {
            $xmlString = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd"></config>';
            $xmlData = [
                [
                    "acl" => [
                        0 => [
                            [
                                "resources" => [
                                    0 => [
                                        [
                                            "resource" => [
                                                "id" => "Magento_Backend::admin",
                                                0 => [
                                                    [
                                                        "resource" => [
                                                            "id" => "MediaDivision_Basics::general"
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
        }
        $resourceMediaDivision = $xml->acl->resources->resource->resource;
        $newResource = [
            [
                "resource" => [
                    "id" => $this->company . "_" . $this->module . "::" . $this->function,
                    "title" => $name,
                    "sortOrder" => "25"
                ]
            ]
        ];
        $this->addSimpleXml($resourceMediaDivision, $newResource);
        $this->saveXml($xml, 'etc/acl.xml');
    }

}
