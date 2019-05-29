<?php

namespace app\cwf\vsla\xmlbo;

/** Implement this interface in the business object to generate a new id **/
interface ISequence {
    /**
     * Generate and return the new primary key for the control table
     * @param \PDO $cn
     */
    function generateNewSeqID(\PDO $cn);
}
