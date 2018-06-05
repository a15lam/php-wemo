<?php
namespace openWebX\PhpWemo\Contracts;

interface DeviceInterface{
    public function On();

    public function Off();
    
    public function state();

    public function isDimmable();
}