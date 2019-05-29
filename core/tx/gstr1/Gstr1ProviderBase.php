<?php

namespace app\core\tx\gstr1;

abstract class Gstr1ProviderBase {
    
    public abstract function preProcessPendingDocs(Gstr1ProviderOption $option) : \app\cwf\vsla\data\DataTable;
    
    
}

