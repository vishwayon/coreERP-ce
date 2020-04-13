<?php

/*
 * Customised Json rendering class of yii
 * for cwf
 */

namespace yii\helpers;

/**
 * This is a customised version of the yii\helpers\Json
 * for cwf usage. We have methods overridden to avoid additional overheads if any 
 *
 * @author girish
 */
class Json extends BaseJson {

    // Override base function for faster processing, avoiding overhead
    // we do not use expressions in json
    static function encode($value, $options = 320) {
        \yii::info('inside extended json');
        return json_encode($value, $options);
    }

}
