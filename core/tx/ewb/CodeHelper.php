<?php

namespace app\core\tx\ewb;

class CodeHelper {
    
}

class SupplyType {

    const INWARD = 'I';
    const OUTWARD = 'O';

}

class SubSupplyType {

    const SUPPLY = 1;
    const IMPORT = 2;
    const EXPORT = 3;
    const JOB_WORK = 4;
    const FOR_OWN_USE = 5;
    const JOB_WORK_RETURNS = 6;
    const SALES_RETURN = 7;
    const OTHERS = 8;
    const SKD_CKD = 9;
    const LINE_SALES = 10;
    const RECIPIENT_NOT_KNOWN = 11;
    const EXHIBITION_OR_FAIRS = 12;

}

class DocType {

    const TAX_INVOICE = 'INV';
    const BILL_OF_SUPPLY = 'BIL';
    const BILL_OF_ENTRY = 'BOE';
    const DELIVERY_CHALLAN = 'CHL';
    const CREDIT_NOTE = 'CNT';
    const OTHERS = 'OTH';

}

class TransportationMode {

    const ROAD = 1;
    const RAIL = 2;
    const AIR = 3;
    const SHIP = 4;

}

class VehicleType {

    const REGULAR = 'R';
    const ODC = 'O';

}
