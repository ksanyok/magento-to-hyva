<?php

namespace MediaDivision\Basics\Helper\Module;

use MediaDivision\Basics\Helper\ModuleAbstract;

class Configuration extends ModuleAbstract
{

    private $groupLabel;
    private $variableName;
    private $variableLabel;
    private $variableComment;
    private $standardValue;

    public function handleTask($company, $module) {
        $this->company = $company;
        $this->module = $module;
        echo "\n - Konfigurations-Variable anlegen. - \n\n";
        $groupList = [];
        if (file_exists('etc/adminhtml/system.xml')) {
            $xml = simplexml_load_file('etc/adminhtml/system.xml');
            foreach ($xml->xpath('system/section/group') as $index => $group) {
                $groupList[$index] = (string) $group->label;
                echo $index . ": " . (string) $group->label . "\n";
            }
            $groupNumber = readline("\nBereits vorhandene Gruppe verwenden? (Return für neue Gruppe) ");
            if (isset($groupList[$groupNumber])) {
                $this->groupLabel = $groupList[$groupNumber];
            } else {
                $this->groupLabel = readline("\nNamen für neue Gruppe: ");
            }
        } else {
            $this->groupLabel = readline("\nName der Variablen-Gruppe (z.B.: PHP-Konfiguration): ");
        }
        $this->variableName = readline("\nName der Variablen (z.B.: php_binary): ");
        $this->variableLabel = readline("\nLabel für die Variable (z.B. Pfad zum PHP-Binary): ");
        $this->variableComment = readline("\nKommentar für die Variable: ");
        $this->standardValue = readline("\nStandard-Wert für die Variable (z.B. /usr/bin/php): ");
        $this->editSystemXml();
        $this->editConfigXml();
        echo "\n\nCache löschen, um die Änderungen zu aktivieren.\n\n";
        echo "\n\nAufruf der neuen Variablen mit \$this->scopeConfig->getValue('"
        . strtolower($this->module) . "/"
        . strtolower(preg_replace('/[^a-zA-Z0-9]/', "_", $this->groupLabel)) . "/" . $this->variableName . "');.\n\n";
    }

    public function create($company, $module, $groupLabel, $variableName, $variableLabel, $variableComment, $standardValue) {
        $this->company = $company;
        $this->module = $module;
        $this->groupLabel = $groupLabel;
        $this->variableName = $variableName;
        $this->variableLabel = $variableLabel;
        $this->variableComment = $variableComment;
        $this->standardValue = $standardValue;
        $this->editSystemXml();
        $this->editConfigXml();
    }

    private function editSystemXml() {
        if (!file_exists("etc")) {
            mkdir("etc");
        }
        if (!file_exists("etc/adminhtml")) {
            mkdir("etc/adminhtml");
        }

        $xml = false;
        $typeData = [
            ["system" => [0 => [
                        ["section" => [
                                "id" => strtolower($this->module),
                                "translate" => "label",
                                "sortOrder" => "130",
                                "showInDefault" => "1",
                                "showInWebsite" => "1",
                                "showInStore" => "1",
                                0 => [
                                    ["class" => [0 => "separator-top"]],
                                    ["label" => [0 => $this->module]],
                                    ["tab" => [0 => "mediadivision"]],
                                    ["resource" => [0 => $this->company . "_" . $this->module . "::" . strtolower($this->module) . "_config"]]]]]]]]];

        if (file_exists('etc/adminhtml/system.xml')) {
            $xml = simplexml_load_file('etc/adminhtml/system.xml');
            if (!$xml->xpath('/config/system/section[@id="' . strtolower($this->module) . '"]')) {
                $this->addSimpleXml($xml, $typeData);
            }
        } else {
            $xmlString = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd"></config>';
            $xml = $this->createSimpleXml($xmlString, $typeData);
        }

        $systemTag = $xml->xpath('/config/system/section[@id="' . strtolower($this->module) . '"]')[0];
        $groupId = strtolower(preg_replace('/[^a-zA-Z0-9]/', "_", $this->groupLabel));

        if (!$systemTag->xpath('group[@id="' . $groupId . '"]')) {
            $this->addSimpleXml($systemTag, [
                ["group" => [
                        "id" => $groupId,
                        "translate" => "label",
                        "type" => "text",
                        "sortOrder" => "10",
                        "showInDefault" => "1",
                        "showInWebsite" => "0",
                        "showInStore" => "0",
                        0 => [
                            ["label" => [0 => $this->groupLabel]]]]]]);
        }
        $groupTag = $systemTag->xpath('group[@id="' . $groupId . '"]')[0];

        $itemData = [
            ["field" => ["id" => $this->variableName, "translate" => "label", "type" => "text", "sortOrder" => "10", "showInDefault" => "1", "showInWebsite" => "0", "showInStore" => "0", 0 => [
                        ["label" => [0 => $this->variableLabel]],
                        ["comment" => [0 => $this->variableComment]]]]]];

        $this->addSimpleXml($groupTag, $itemData);
        $this->saveXml($xml, 'etc/adminhtml/system.xml');
    }

    private function editConfigXml() {
        if (!file_exists("etc")) {
            mkdir("etc");
        }
        $xml = false;
        $default = false;

        if (file_exists('etc/config.xml')) {
            $xml = simplexml_load_file('etc/config.xml');
            if ($xml->xpath('/config/default')) {
                $default = $xml->xpath('/config/default')[0];
            } else {
                $default = $xml->addChild('default');
            }
        } else {
            $xmlString = '<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd"></config>';
            $xml = $xml = new \SimpleXMLElement($xmlString);
            $default = $xml->addChild('default');
        }

        $moduleTag = false;
        if ($default->xpath(strtolower($this->module))) {
            $moduleTag = $default->xpath(strtolower($this->module))[0];
        } else {
            $moduleTag = $default->addChild(strtolower($this->module));
        }

        $groupTag = false;
        $groupId = strtolower(preg_replace('/[^a-zA-Z0-9]/', "_", $this->groupLabel));
        if ($moduleTag->xpath($groupId)) {
            $groupTag = $moduleTag->xpath($groupId)[0];
        } else {
            $groupTag = $moduleTag->addChild($groupId);
        }

        $fieldTag = false;
        if ($groupTag->xpath($this->variableName)) {
            $fieldTag = $groupTag->xpath($this->variableName);
        } else {
            $fieldTag = $groupTag->addChild($this->variableName);
        }
        $fieldTag[0] = $this->standardValue;

        $this->saveXml($xml, 'etc/config.xml');
    }

}
