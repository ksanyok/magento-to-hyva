<?php

namespace MediaDivision\TexData\Helper\Export;

use MediaDivision\TexData\Model\SequenceFactory;
use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\App\Helper\Context;
use \Magento\Framework\Filesystem\DirectoryList;
use \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use \Magento\Catalog\Model\ProductFactory;
use \Magento\Sales\Model\OrderFactory;
use \Mirasvit\Rma\Model\ResourceModel\Item\CollectionFactory as RmaItemCollectionFactory;
use \Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class Order extends AbstractHelper
{

    private $kundennummern = array(
        'AT' => '100862',
        'BE' => '101234',
        'DE' => '100863',
        'DK' => '101026',
        'FR' => '101232',
        'IT' => '101233',
        'NL' => '101025',
        'PL' => '101028',
        'SE' => '101027',
        'CH' => '121100',
    );
    private $mehrWertSteuer = array(
        'DE' => '19',
        'AT' => '20',
    );
    private $bruttorechnung = array(
        'DE' => 'N',
        'AT' => 'J',
    );
    private $installDir;
    private $orderFactory;
    private $rmaItemCollectionFactory;
    private $orderCollectionFactory;
    private $orderDirectory = "/var/texdata/order/";
    private $productCollectionFactory;
    private $productFactory;
    private $sequenceFactory;

    public function __construct(
            Context $context,
            DirectoryList $directoryList,
            OrderCollectionFactory $orderCollectionFactory,
            OrderFactory $orderFactory,
            RmaItemCollectionFactory $rmaItemCollectionFactory,
            ProductCollectionFactory $productCollectionFactory,
            ProductFactory $productFactory,
            SequenceFactory $sequenceFactory) {
        $this->installDir = $directoryList->getRoot();
        $this->orderFactory = $orderFactory;
        $this->rmaItemCollectionFactory = $rmaItemCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productFactory = $productFactory;
        $this->sequenceFactory = $sequenceFactory;
        parent::__construct($context);
    }

    public function createRmaXml($rma) {
        date_default_timezone_set('Europe/Berlin');
        if (!file_exists($this->installDir . $this->orderDirectory)) {
            mkdir($this->installDir . $this->orderDirectory);
        }
        $xml = $this->createOrderXML($rma, 'return', 'AUF', 9);
        if($xml) {   
            $this->saveXml($xml, $this->installDir . $this->orderDirectory . "ORDERS-B2C-" . date('YmdHis') . ".xml");
        }
    }

    public function createReservations($debug = false) {
        $this->debug = $debug;
        date_default_timezone_set('Europe/Berlin');
        if (!file_exists($this->installDir . $this->orderDirectory)) {
            mkdir($this->installDir . $this->orderDirectory);
        }

        foreach ($this->orderCollectionFactory->create() as $order) {
            if (!$this->isExported($order)) {
                if ($this->debug) {
                    echo " - Exportiere Bestellung " . $order->getIncrementId() . "\n";
                }
                $xml = $this->createOrderXML($order, 'order', 'RESV', 0);
                $this->saveXml($xml, $this->installDir . $this->orderDirectory . "ORDERS-B2C-" . date('YmdHis') . ".xml");
                $order->addStatusHistoryComment('exported')->save();
                sleep(2); // um sicherzugehen, dass 2 aufeinanderfolgende Bestellungen unterschiedliche Dateinamen erhalten
            }
        }
    }

    public function orderChanged($order) {
        date_default_timezone_set('Europe/Berlin');
        if (!file_exists($this->installDir . $this->orderDirectory)) {
            // mkdir($this->installDir . $this->orderDirectory);
        }
        if (($order->getState() == 'canceled') && ($order->getOrigData('state') != 'canceled')) {
            $xml = $this->createOrderXML($order, 'order', 'RESVX', 0);
            $this->saveXml($xml, $this->installDir . $this->orderDirectory . "ORDERS-B2C-" . date('YmdHis') . ".xml");
        }
        if (($order->getState() == 'complete') && ($order->getOrigData('state') != 'complete')) {
            $xml = $this->createOrderXML($order, 'order', 'RESVOK', 0);
            $this->saveXml($xml, $this->installDir . $this->orderDirectory . "ORDERS-B2C-" . date('YmdHis') . ".xml");
        }
    }

    private function createOrderXML($order, $orderType, $docFunction, $rechnungsTyp) {
        $bestandskennzeichen = "J";
        $sequenceString = "Order " . $order->getIncrementId() . " erstellt.";
        $rma = false;
        if($orderType == 'return') {
            $rma = $order;
            $order = $this->orderFactory->create()->load($rma->getOrderId());
            $rmaOrderService = \Magento\Framework\App\ObjectManager::getInstance()->create('Mirasvit\Rma\Api\Service\Rma\RmaOrderInterface');
            $order =$rmaOrderService->getOrder($rma);
            if(!$order->getId()) {
                return false;
            }
            $sequenceString = "Return " . $order->getIncrementId() . " erstellt.";
            $bestandskennzeichen = "N";
        }
        $orderSequence = $this->sequenceFactory->create()->getSequence($sequenceString);
        $xmlDate = date('Y.m.d', strtotime($order->getCreatedAt()));
        $countryId = $order->getShippingAddress()->getCountryId();

        $xmlstr = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><EDI><HEADER></HEADER><BODY><ORDERS></ORDERS></BODY></EDI>";
        $xml = new \SimpleXMLElement($xmlstr);

        $xml->HEADER->DOKUMENTTYP = 'ORDERS';
        $xml->HEADER->DOKUMENTID = $orderSequence;
        $xml->HEADER->DOKUMENTDATUM = $xmlDate;
        $xml->HEADER->EMPFAENGERID = 'DIAMOD';
        $xml->HEADER->ABSENDERID = "B2C";

        $xml->BODY->ORDERS->DocFunktion = $docFunction; // Bestellung: AUF, Reklamation: RE
        $xml->BODY->ORDERS->FirmaNr = '10';
        $xml->BODY->ORDERS->OrderNr = $orderSequence;
        $xml->BODY->ORDERS->KundenOrderNr = $order->getIncrementId();
        $xml->BODY->ORDERS->OrderDatum = $xmlDate;
        $xml->BODY->ORDERS->AuftragsDatum = $xmlDate;
        $xml->BODY->ORDERS->Saison = '000';
        $xml->BODY->ORDERS->Auftragstyp = '1';
        $xml->BODY->ORDERS->Auftragsart = '6';
        $xml->BODY->ORDERS->Terminart = '0';
        $xml->BODY->ORDERS->LieferDatumAnfang = $xmlDate;
        $xml->BODY->ORDERS->LieferDatumEnde = $xmlDate;
        $xml->BODY->ORDERS->NichtLiefernVor = '';
        $xml->BODY->ORDERS->ILNBesteller = isset($this->kundennummern[$countryId]) ? $this->kundennummern[$countryId] : '';
        $xml->BODY->ORDERS->BestName1 = ''; //$billing['b_firstname'];
        $xml->BODY->ORDERS->BestName2 = ''; //$billing['b_lastname'];
        $xml->BODY->ORDERS->BestName3 = '';
        $xml->BODY->ORDERS->BestStrasse = ''; //$billing['b_street'];
        $xml->BODY->ORDERS->BestLand = ''; //$billing['b_country_id'];
        $xml->BODY->ORDERS->BestOrt = ''; //$billing['b_city'];
        $xml->BODY->ORDERS->BestPlz = ''; //$billing['b_postcode'];
        $xml->BODY->ORDERS->BesteMail = ''; //$billing['b_email'];
        $xml->BODY->ORDERS->BestTelefon = ''; //$billing['b_telephone'];
        $xml->BODY->ORDERS->BestFax = ''; //$billing['b_fax'];
        $xml->BODY->ORDERS->ILNLiAnschrift = '';
        $xml->BODY->ORDERS->LiefAdressID = '';
        $xml->BODY->ORDERS->LiefName1 = ''; //$shipping['s_firstname'];
        $xml->BODY->ORDERS->LiefName2 = ''; //$shipping['s_lastname'];
        $xml->BODY->ORDERS->LiefName3 = '';
        $xml->BODY->ORDERS->LiefStrasse = ''; //$shipping['s_street'];
        $xml->BODY->ORDERS->LiefLand = ''; //$shipping['s_country_id'];
        $xml->BODY->ORDERS->LiefOrt = ''; //$shipping['s_city'];
        $xml->BODY->ORDERS->LiefPlz = ''; //$shipping['s_postcode'];
        $xml->BODY->ORDERS->LiefeMail = ''; //$shipping['s_email'];
        $xml->BODY->ORDERS->LiefTelefon = ''; //$shipping['s_telephone'];
        $xml->BODY->ORDERS->LiefFax = ''; //$shipping['s_fax'];
        $xml->BODY->ORDERS->ILNReAnschrift = '';
        $xml->BODY->ORDERS->RechName1 = ''; //$billing['b_firstname'];
        $xml->BODY->ORDERS->RechName2 = ''; //$billing['b_lastname'];
        $xml->BODY->ORDERS->RechName3 = '';
        $xml->BODY->ORDERS->RechStrasse = ''; //$billing['b_street'];
        $xml->BODY->ORDERS->RechLand = ''; //$billing['b_country_id'];
        $xml->BODY->ORDERS->RechOrt = ''; //$billing['b_city'];
        $xml->BODY->ORDERS->RechPlz = ''; //$billing['b_postcode'];
        $xml->BODY->ORDERS->RecheMail = ''; //$billing['b_email'];
        $xml->BODY->ORDERS->RechTelefon = ''; //$billing['b_telephone'];
        $xml->BODY->ORDERS->RechFax = ''; //$billing['b_fax'];
        $xml->BODY->ORDERS->Bestellart = '90';
        $xml->BODY->ORDERS->Versandart = '';
        $xml->BODY->ORDERS->ZahlungsBed = '';
        $xml->BODY->ORDERS->LieferBed = '';
        $xml->BODY->ORDERS->Waehrung = 'EUR';
        $xml->BODY->ORDERS->Bemerkung = '';
        $xml->BODY->ORDERS->MwStStatus = '';
        $xml->BODY->ORDERS->MwSt1Satz = isset($this->mehrWertSteuer[$countryId]) ? $this->mehrWertSteuer[$countryId] : '';
        $xml->BODY->ORDERS->MwSt2Satz = '';
        $xml->BODY->ORDERS->BruttoRechnung = isset($this->bruttorechnung[$countryId]) ? $this->bruttorechnung[$countryId] : '';
        $xml->BODY->ORDERS->PreislisteNr = '';
        $xml->BODY->ORDERS->PreislisteProgramm = '';
        $xml->BODY->ORDERS->SWIFT = '';
        $xml->BODY->ORDERS->IBAN = '';
        $xml->BODY->ORDERS->SEPAMandattyp = '';
        $xml->BODY->ORDERS->SEPAMandatnr = '';
        $xml->BODY->ORDERS->Sprache = '';
        $xml->BODY->ORDERS->Teillieferung = '';
        $xml->BODY->ORDERS->Valutadatum = '';
        $xml->BODY->ORDERS->VersandKosten = '';
        $xml->BODY->ORDERS->VersandkostenGebuehr = '';
        $xml->BODY->ORDERS->VersandkostenText = '';
        $xml->BODY->ORDERS->VersandkostenMwst = '';
        $xml->BODY->ORDERS->ZANummer = '';
        $xml->BODY->ORDERS->ZABezeichnung = '';
        $xml->BODY->ORDERS->ZAReferenz = '';
        $xml->BODY->ORDERS->ZAAbschlag = '';
        $xml->BODY->ORDERS->ZATyp = '';
        $xml->BODY->ORDERS->ZAPreis = '';
        $xml->BODY->ORDERS->ZABankname = '';
        $xml->BODY->ORDERS->ZABLZ = '';
        $xml->BODY->ORDERS->ZAKontonr = '';
        $xml->BODY->ORDERS->ZAKontoinhaber = '';
        $xml->BODY->ORDERS->ZABezahltAm = '';
        $xml->BODY->ORDERS->ZAText1 = '';
        $xml->BODY->ORDERS->Besteller = '';
        $xml->BODY->ORDERS->UstId = '';
        $xml->BODY->ORDERS->Skonto1Tage = '';
        $xml->BODY->ORDERS->Skonto1Proz = '';
        $xml->BODY->ORDERS->Skonto2Tage = '';
        $xml->BODY->ORDERS->Skonto2Proz = '';
        $xml->BODY->ORDERS->NettoTage = '';
        $xml->BODY->ORDERS->VertreterNr = '';
        $xml->BODY->ORDERS->Lagerort = '3';
        $xml->BODY->ORDERS->{"LagerOrt-Nach"} = '';
        $xml->BODY->ORDERS->Shopnummer = '1';
        $xml->BODY->ORDERS->KopfRabatt1 = '';
        $xml->BODY->ORDERS->KopfRabatt1Id = '';
        $xml->BODY->ORDERS->KopfRabatt2 = '';
        $xml->BODY->ORDERS->KopfRabatt2Id = '';
        $xml->BODY->ORDERS->Kollektion = '-1';
        $xml->BODY->ORDERS->Produktbereich = '-1';
        $xml->BODY->ORDERS->DiverseKundenNr = '';
        $xml->BODY->ORDERS->OrderIDFremdsystem = '';
        $xml->BODY->ORDERS->Suchmerkmal = '';
        $xml->BODY->ORDERS->Rechnungstyp = $rechnungsTyp;
        $xml->BODY->ORDERS->Freitext1 = '';
        $xml->BODY->ORDERS->BemerkungIntern = '';
        $xml->BODY->ORDERS->Streckenlieferung = '0';

        $itemNr = 1;
        $itemList = $order->getItemsCollection();
        if($rma) {
            $itemList = $this->rmaItemCollectionFactory->create()->addFieldToFilter('rma_id', $rma->getId());
            foreach ($itemList as $rmaItem) {
                //print_r($rmaItem->getData());
            }
        }
        foreach ($itemList as $item) {
            if ($item->getParentItemId()) {
                continue; // Gibt es eine parent_id ist dies ein einfaches Produkt, das zu einem konfigurierbaren gehört.
            }
            
            $productOptions = $item->getProductOptions();
            $simpleProductId = false;
            if (isset($productOptions['simple_sku'])) {
                $simpleProductId = $this->productCollectionFactory
                        ->create()
                        ->addFieldToFilter("sku", $productOptions['simple_sku'])
                        ->getFirstItem()
                        ->getId();
            } else {
                $sku = $item->getSku();
                if(!$sku) {
                    $sku = $item->getProductSku();
                }
                $simpleProductId = $this->productCollectionFactory
                        ->create()
                        ->addFieldToFilter("sku", $sku)
                        ->getFirstItem()
                        ->getId();
            }
            
            $simpleProduct = $this->productFactory->create()->load($simpleProductId);
            $simplePrice = '';
            if(is_array($simpleProduct->getData('tier_price'))){
                foreach ($simpleProduct->getData('tier_price') as $gPrice) {
                    if ($gPrice['cust_group'] == 4) { // group1
                        $simplePrice = $gPrice['price'];
                    }
                }
            }

            $ordpos = $xml->BODY->ORDERS->addChild('ORDPOS');
            $ordpos->addChild('PosFunktion', 'VR');
            $ordpos->addChild('PosNummer', $itemNr);
            $ordpos->addChild('Barcode', $simpleProduct->getEan());
            $ordpos->addChild('ArtNumForm', $simpleProduct->getForm());
            $ordpos->addChild('ArtNumQual', $simpleProduct->getQual());
            $ordpos->addChild('ArtFarbe', $simpleProduct->getAttributeText('color'));
            $ordpos->addChild('ArtVariante', ''); // Leer -> keine Variante
            $ordpos->addChild('ArtAufmachung', ''); // Leer -> keine Aufmachung
            $ordpos->addChild('ArtGroesse', $simpleProduct->getAttributeText('size'));
            $ordpos->addChild('ArtSetKZ', '');
            $ordpos->addChild('PosMenge', $rma ? (int) $item->getQtyRequested() : (int) $item->getQtyOrdered());
            $ordpos->addChild('ArtNumKunde', '');
            $ordpos->addChild('PosMwStSchl', '1');
            $ordpos->addChild('PreisReg', $simplePrice);
            $ordpos->addChild('PreisEff', $simplePrice);
            $ordpos->addChild('PosRabatt', '');
            $ordpos->addChild('PosRabattId', '');
            $ordpos->addChild('VonLiefertermin', ''); // Leer -> heute
            $ordpos->addChild('BisLiefertermin', ''); // Leer -> heute
            $ordpos->addChild('PosBemerkung', '');
            $ordpos->addChild('SortimentsKZ', '');
            $ordpos->addChild('SortimentsAnzahl', '');
            $ordpos->addChild('Thema', '');
            $ordpos->addChild('VE', ''); // Verpackungseinheit: Leer = 1
            $ordpos->addChild('SchluesselNr', '');
            $ordpos->addChild('GutscheinNr', '');
            $ordpos->addChild('GutscheinSerie', '');
            $ordpos->addChild('Rueckgabegrund', '');
            $ordpos->addChild('Bestandskz', $bestandskennzeichen);
            $ordpos->addChild('ohneBerechnung', '0');
            $itemNr++;
        }
        return $xml;
    }

    private function saveXml($xml, $file) {
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        file_put_contents($file, $dom->saveXML());
    }

    private function isExported($order) {
        $exported = false;
        $history = $order->getAllStatusHistory();

        foreach ($history as $historyItem) {
            if ($historyItem->getData('comment') == 'exported') {
                $exported = true;
            }
        }

        return $exported;
    }

}
