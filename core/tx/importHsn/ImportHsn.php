<?php

/*
 * This script is used to import hsn codes into the main database
 */

namespace app\core\tx\importHsn;

class ImportHsn {

    public static function importHsnCode($cn, $outstream) {
        if (($handle = fopen("core/tx/importHsn/hsn_code_final.csv", "r")) !== FALSE) {
            $cn->beginTransaction();

            $cmmtbl = 'insert into sys.hsn_sc (hsn_sc_id, hsn_sc_ch, hsn_sc_code, 
                hsn_sc_desc, hsn_sc_type, last_updated) 
            values (:phsn_sc_id, :phsn_sc_ch, trim(:phsn_sc_code), 
                :phsn_sc_desc, :phsn_sc_type, current_timestamp(0))';
            $query = $cn->prepare($cmmtbl);

            $i = 0;
            while (($src = fgetcsv($handle, 1000, ",")) !== FALSE) {
                //echo 'Importing: '.$src[0]."\n";
                if ($i > 0) {
                    $cmmpara = [
                        'phsn_sc_id' => $i,
                        'phsn_sc_ch' => substr($src[0], 0, 2),
                        'phsn_sc_code' => $src[0],
                        'phsn_sc_desc' => substr($src[1], 0, 250),
                        'phsn_sc_type' => 'G'
                    ];
                    $query->execute($cmmpara);
                }
                $i++;
            }
            $cn->commit();
            fclose($handle);
            
            fwrite($outstream, 'Imported HSN Code Rows: ' . $i . "\n");
        }
    }

    public static function importSacCode($cn, $outstream) {
        if (($handle = fopen("core/tx/importHsn/sac_code_final.csv", "r")) !== FALSE) {
            $cn->beginTransaction();

            // sac to start from 99000 series.
            $max_id = 99000;


            $cmmtbl = 'insert into sys.hsn_sc (hsn_sc_id, hsn_sc_ch, hsn_sc_code, 
                hsn_sc_desc, hsn_sc_type, last_updated) 
            values (:phsn_sc_id, :phsn_sc_ch, :phsn_sc_code, 
                :phsn_sc_desc, :phsn_sc_type, current_timestamp(0))';
            $query = $cn->prepare($cmmtbl);

            $i = 0;
            while (($src = fgetcsv($handle, 1000, ",")) !== FALSE) {
                //echo 'Importing: '.$src[0]."\n";
                if ($i > 0) {
                    $cmmpara = [
                        'phsn_sc_id' => $i + $max_id,
                        'phsn_sc_ch' => substr($src[0], 0, 2),
                        'phsn_sc_code' => $src[0],
                        'phsn_sc_desc' => substr($src[1], 0, 250),
                        'phsn_sc_type' => 'S'
                    ];
                    $query->execute($cmmpara);
                }
                $i++;
            }
            $cn->commit();
            fclose($handle);
            fwrite($outstream, 'Imported SAC Code Rows: ' . $i . "\n");
        }
    }
}
