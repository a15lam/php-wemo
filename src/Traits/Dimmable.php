<?php

namespace openWebX\PhpWemo\Traits;

/**
 * Class Dimmable
 *
 * Dimmable trait for Wemo Bulbs
 *
 * @package openWebX\PhpWemo\Traits
 */
trait Dimmable{
    /**
     * Dims Wemo bulb to specified percentage
     *
     * @param $percent integer
     *
     * @return mixed
     */
    public function dim($percent)
    {
        if($percent < 0){
            $percent = 0;
        } else if($percent > 100){
            $percent = 100;
        }

        $level = round((255*$percent)/100);

        return $this->bridge->setDeviceStatus($this->deviceId, null, $level);
    }

    /**
     * Returns dim level
     * 
     * @return mixed
     */
    public function dimState()
    {
        $currentState = $this->bridge->getBulbState($this->deviceId);
        $level = explode(':', $currentState[1])[0];
        $percent = round(($level*100)/255);

        return $percent;
    }
}