<?php
namespace app\cwf\sys\csvimport;

class ImportItem {
    public $name = '';
    public $bo = '';
    public $module = '';
    public $boPath = '';
    public $editView = '';
    public $primaryKey = '';
    public $crudKey = '';
    public $lookups = [];
    public $tablename = '';
    public $crudKeyName = '';
    public $forBranch = FALSE;
}