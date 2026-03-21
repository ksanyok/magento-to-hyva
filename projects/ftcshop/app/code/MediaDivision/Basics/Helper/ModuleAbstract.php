<?php

namespace MediaDivision\Basics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

abstract class ModuleAbstract extends AbstractHelper
{

    protected $company;
    protected $module;

    protected function saveXml($xml, $file) {
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        file_put_contents($file, $dom->saveXML());
    }

    protected function getSetupVersion() {
        $xml = simplexml_load_file("etc/module.xml");
        return (string) $xml->module->attributes()->setup_version;
    }

    protected function createSimpleXml($rootElement, $xmlData) {
        $xml = new \SimpleXMLElement($rootElement);
        $this->createXmlTag($xml, $xmlData);
        return $xml;
    }

    protected function addSimpleXml($xml, $xmlData) {
        $this->createXmlTag($xml, $xmlData);
    }

    private function createXmlTag($xml, $xmlData) {
        foreach ($xmlData as $element) {
            $name = array_keys($element)[0]; 
            $child = $xml->addChild($name);
            foreach ($element[$name] as $attribute => $value) {
                if (($attribute === 0) && is_array($value)) {
                    $this->createXmlTag($child, $value);
                    continue; // nicht als Attribut dazufügen
                }
                if (($attribute === 0) && is_string($value)) {
                    $child[0] = $value;
                    continue; // nicht als Attribut dazufügen
                }
                if (preg_match('/^xsi:/', $attribute)) {
                    $child->addAttribute($attribute, $value, "http://www.w3.org/2001/XMLSchema-instance");
                } else {
                    $child->addAttribute($attribute, $value);
                }
            }
        }
    }

}
