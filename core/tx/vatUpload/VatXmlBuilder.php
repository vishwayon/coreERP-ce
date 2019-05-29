<?php

namespace app\core\tx\vatUpload;

class VatXmlBuilder {
    const OUTPUT_LOCAL_SALE = 0;
    const OUTPUT_LOCAL_SALE_RETURN = 1;
    
    
    public static function getDef_localSale($options) : \SimpleXMLElement {
        $xml = new \SimpleXMLElement('<?xml version="1.0"?><SaleDetails></SaleDetails>');
        $xml->addChild('Version', '13.11');
        $xml->addChild('TinNo', $options['TinNo']);
        $xml->addChild('RetPerdEnd', $options['RetPerdEnd']);
        $xml->addChild('FilingType', 'M');
        $xml->addChild('Period', $options['Period']);
        return $xml;
    }
    
    public static function getDef_localSaleReturn($options) : \SimpleXMLElement {
        $xml = new \SimpleXMLElement('<?xml version="1.0"?><CreditDetails></CreditDetails>');
        $xml->addChild('Version', '13.11');
        $xml->addChild('TinNo', $options['TinNo']);
        $xml->addChild('RetPerdEnd', $options['RetPerdEnd']);
        $xml->addChild('FilingType', 'M');
        $xml->addChild('Period', $options['Period']);
        $xml->addChild('TranCode', 'I');
        return $xml;
    }
    
    public static function getDef_interstateSale($options) : \SimpleXMLElement {
        $xml = new \SimpleXMLElement('<?xml version="1.0"?><ISSale></ISSale>');
        $xml->addChild('Version', '13.11');
        $xml->addChild('TinNo', $options['TinNo']);
        $xml->addChild('RetPerdEnd', $options['RetPerdEnd']);
        $xml->addChild('FilingType', 'M');
        $xml->addChild('Period', $options['Period']);
        return $xml;
    }
    
    public static function getDef_interstateSaleReturn($options) : \SimpleXMLElement {
        $xml = new \SimpleXMLElement('<?xml version="1.0"?><CreditDetails></CreditDetails>');
        $xml->addChild('Version', '13.11');
        $xml->addChild('TinNo', $options['TinNo']);
        $xml->addChild('RetPerdEnd', $options['RetPerdEnd']);
        $xml->addChild('FilingType', 'M');
        $xml->addChild('Period', $options['Period']);
        $xml->addChild('TranCode', 'O');
        return $xml;
    }
    
    public static function getDef_localPurchase($options) : \SimpleXMLElement {
        $xml = new \SimpleXMLElement('<?xml version="1.0"?><PurchaseDetails></PurchaseDetails>');
        $xml->addChild('Version', '13.11');
        $xml->addChild('TinNo', $options['TinNo']);
        $xml->addChild('RetPerdEnd', $options['RetPerdEnd']);
        $xml->addChild('FilingType', 'M');
        $xml->addChild('Period', $options['Period']);
        return $xml;
    }
    
    public static function getDef_interstatePurchase($options) : \SimpleXMLElement {
        $xml = new \SimpleXMLElement('<?xml version="1.0"?><ISPur></ISPur>');
        $xml->addChild('Version', '13.11');
        $xml->addChild('TinNo', $options['TinNo']);
        $xml->addChild('RetPerdEnd', $options['RetPerdEnd']);
        $xml->addChild('FilingType', 'M');
        $xml->addChild('Period', $options['Period']);
        return $xml;
    }
    
    
    public static function toElements(\SimpleXMLElement $parentXml, string $parentNode, array $nodeDef, \app\cwf\vsla\data\DataTable $data) {
        foreach($data->Rows() as $dr) {
            $outNode = $parentXml->addChild($parentNode);
            foreach($nodeDef as $ndKey => $ndOut) {
                $outNode->addChild($ndOut, $dr[$ndKey]);
            }
        }
    }
    
}

