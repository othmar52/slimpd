<?php
namespace Slimpd;
class ExecutionTime
{
     private $startTime;
     private $endTime;

     public function Start(){
         $this->startTime = getrusage();
     }

     public function End(){
         $this->endTime = getrusage();
     }

     private function runTime($ru, $rus, $index) {
         return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
     -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
     }    

     public function __toString(){
         return "This process used " . $this->runTime($this->endTime, $this->startTime, "utime") .
        " ms for its computations\nIt spent " . $this->runTime($this->endTime, $this->startTime, "stime") .
        " ms in system calls\n";
     }
}