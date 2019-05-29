<?php

namespace app\cwf\vsla\xmlbo;

/**
 * base class for all ElongateTables
 * @author girishshenoy
 */
abstract class ElongateTableBase {
    
    protected $bo;
    
    
    
    
    
    abstract function onFetch() {
        
    }
}
