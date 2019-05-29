<?php



namespace app\cwf\console\utils\workers;
use YaLinqo\Enumerable;

class FileAgedWorker {
    private $basePath = '';
    private $pattern = "/^[a-zA-Z0-9_-]{1,}_\d{8}_\d{4}/i";
    private $removeCount = 0;
    
    public function __construct($basePath) {
        if(is_link($basePath)) {
            $this->basePath = readlink($basePath);
        } else {
            if(is_dir($basePath)) {
                if(strpos($basePath, "/", strlen($basePath)) == 0) {
                    $basePath .= "/";
                }
                $this->basePath=$basePath;
            } else {
                throw new \Exception("Invalid base path supplied. ".$basePath." is not a directory");
            }
        }
    }
    
    public function cleanUp() {
        $files = scandir($this->basePath);
        $unsortedFiles = [];
        $firstLevelDirs = [];
        foreach ($files as $file) {
            if($file !== "." && $file !=="..") {
                if(!is_dir($this->basePath.$file)) {
                    // Resolve files at first level
                    if(preg_match($this->pattern, $file)) {
                        array_push($unsortedFiles, $file);
                    }
                } elseif (is_dir($this->basePath.$file)) {
                    // create first level sub directories for further monitoring
                    array_push($firstLevelDirs, $this->basePath.$file."/");
                }
            }
        }
            
        // Resolve files at first level
        $sortedFiles = $this->sortByDate($unsortedFiles, $this->basePath);
        $this->isRequired($sortedFiles);
        $this->removePhysicalFiles($sortedFiles);
        
        // Resolve directories at first level
        $unsortedFirstlevelFiles = [];
        foreach($firstLevelDirs as $dirPath) {
            $files = scandir($dirPath);
            foreach ($files as $file) {
                if($file !== "." && $file !==".." && !is_dir($dirPath.$file)) {
                    // Resolve files at first level only
                    if(preg_match($this->pattern, $file)) {
                        array_push($unsortedFirstlevelFiles, $file);
                    }
                }
            }
            $sortedFirstlevelFiles = $this->sortByDate($unsortedFirstlevelFiles, $dirPath);
            $this->isRequired($sortedFirstlevelFiles);
            $this->removePhysicalFiles($sortedFirstlevelFiles);
            $unsortedFirstlevelFiles = [];
        } 
    }
    
    private function removePhysicalFiles($sortedFiles) {
        foreach($sortedFiles as $file) {
            if(!$file->required) {
                unlink($file->filePath.$file->fileName);
                $this->removeCount += 1;
                echo "Removed File: ".$file->dump();
            }
        }
    }
    
    public function getRemoveCount() {
        return $this->removeCount;
    }
    
    private function sortByDate($files, $dirPath) {
        $sortedFiles = [];
        foreach($files as $file) {
            $nameParts = explode("_", $file);
            $fileInfo = new FileInfo();
            $fileInfo->fileName = $file;
            $fileInfo->filePath = $dirPath;
            $lastPart = count($nameParts) - 1;
            $fileInfo->createTime = strtotime($nameParts[$lastPart-1].substr($nameParts[$lastPart], 0, strpos($nameParts[$lastPart], ".")));
            $fileInfo->createDate = date("Y-m-d H:i", $fileInfo->createTime);
            $sortedFiles[$fileInfo->createTime] = $fileInfo;
        }
        ksort($sortedFiles);        
        return $sortedFiles;
    }
    
    private function isRequired(array $sortedFiles) {
        // if the file is less than 10 days old, retain it
        $cutoffTime = time() - (10 * (24 * 60 * 60)); //No. of seconds in a day (24 * 60 * 60)
        foreach($sortedFiles as $key => $file) {
            if(($key - $cutoffTime) >0) {
                $file->required=TRUE;
            } 
        }
        
        // if the file is the last file of the month, retain it
        foreach($sortedFiles as $key => $file) {
            $file->createMonth = $this->lastDayOfMonth($file->createDate);
        }
        
        $list = Enumerable::from($sortedFiles)->groupBy('$a==>$a->createMonth')->toList();
        foreach($list as $groupKey => $groupData) {
            $maxItem = Enumerable::from($groupData)->max('$b==>$b->createTime');
            $sortedFiles[$maxItem]->required = TRUE;
        }
        
        // Always retain the last 10 files (This is required if the backup failed)
        $fileCount = count($sortedFiles);
        foreach($sortedFiles as $key => $file) {
            $fileCount -= 1;
            if ($fileCount<10) {
                $file->required=TRUE;
            }
        }
    }
    
    private function lastDayOfMonth($time) {
        $cdate = new \DateTime($time);
        $cdate->setDate($cdate->format("Y"), $cdate->format("n")+1, 1);
        return $cdate->sub(new \DateInterval("P1D"))->format("Y-m-d");
    }

    public function copyLatest($targetDir) {
        $files = scandir($this->basePath);        
        $firstLevelDirs = [];
        foreach ($files as $file) {
            if($file !== "." && $file !=="..") {
                if(is_dir($this->basePath.$file)) {
                    // create first level sub directories for further monitoring
                    $firstLevelDirs[$file] = $this->basePath.$file;
                }
            }
        }
        // Resolve directories at first level
        $requiredFiles = [];
        foreach($firstLevelDirs as $dbname => $dirPath) {
            $files = scandir($dirPath);
            foreach ($files as $file) {
                if($file !== "." && $file !==".." && !is_dir($dirPath.$file)) {
                    // Resolve files at first level only
                    $nameParts = explode("_", $file);
                    $fileInfo = new FileInfo();
                    $fileInfo->fileName = $file;
                    $fileInfo->filePath = $dirPath;
                    $lastPart = count($nameParts) - 1;
                    $fileInfo->createTime = strtotime($nameParts[$lastPart-1].'T'.substr($nameParts[$lastPart], 0, strpos($nameParts[$lastPart], ".")));
                    $fileInfo->createDate = date("Y-m-d H:i", $fileInfo->createTime);
                    if(array_key_exists($dbname, $requiredFiles) && $fileInfo->createTime > $requiredFiles[$dbname]->createTime) {
                        $requiredFiles[$dbname] = $fileInfo;
                    } else {
                        $requiredFiles[$dbname] = $fileInfo;
                    }
                }
            }            
        }
        foreach($requiredFiles as $file) {
            copy($file->filePath.DIRECTORY_SEPARATOR.$file->fileName, $targetDir.DIRECTORY_SEPARATOR.$file->fileName);
        }
        echo "Successfully copied ".count($requiredFiles)." files\n";
    }
}

class FileInfo {
    public $fileName = '';
    public $filePath = '';
    public $createTime;
    public $createDate;
    public $createMonth;
    public $required = FALSE;
    
    public function dump() {
        return $this->fileName.": ".$this->filePath."|".$this->createDate."|".$this->required."\n";
    }
}
