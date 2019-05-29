<?php

namespace app\core\st\stockSelect;

/*
 * Stcock Search class that finds and returns a stock item
 * Author: Girish
 */
class StockSearch {
    /*
     * Will find items that match the search criteria
     */
    public function findStockItems(string $matsearch) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select material_id, material_name, material_code From st.material Where material_name ilike :pmatsearch');
        $cmm->addParam('pmatsearch', $matsearch);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    /*
     * Pass an array of match criteria.
     * To search for field level match, pass a subarray with field=>value
     */
    public function findStockItemsSphinx(array $matsearch) {
        if(!isset(\yii::$app->params['cwf_config']['sphinxSearch'])) {
            throw new \Exception('Sphinx Search not configured. Search failed');
        }
        $config = \yii::$app->params['cwf_config']['sphinxSearch'];
        
        $link = mysqli_connect($config['server'], $config['user'], $config['pass'], '', $config['port']);
        if ( mysqli_connect_errno() )
            die ( "connect failed: " . mysqli_connect_error());
        
        $pmatsearch = implode(' ', $matsearch);
        $pmatsearch = mysqli_real_escape_string($pmatsearch);
        $sql = 'Select material_id, material_name, material_code From stock Where match('.$pmatsearch.');';
        $result = mysqli_query($link, $sql);
        $rows = mysqli_fetch_all($result);
        mysqli_close($link);
        return $rows;
    }
}

