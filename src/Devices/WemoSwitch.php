<?php

namespace openWebX\PhpWemo\Devices;

use openWebX\PhpWemo\Contracts\DeviceInterface;

/**
 * Class WemoSwitch
 *
 * @package openWebX\PhpWemo\Devices
 */
class WemoSwitch extends BaseDevice implements DeviceInterface
{
    const MODEL_NAME = 'Socket';

    const EMULATED_NAME = 'Emulated Socket';

    protected $services = [
        'BridgeService' => [
            'serviceType' => 'urn:Belkin:service:basicevent:1',
            'serviceId'   => 'urn:Belkin:serviceId:basicevent1',
            'controlURL'  => '/upnp/control/basicevent1',
            'eventSubURL' => '/upnp/event/basicevent1',
            'SCPDURL'     => '/eventservice.xml'
        ]
    ];

    /**
     * Turns on switch
     *
     * @return bool|string
     * @throws \Exception
     */
    public function On()
    {
        return ($this->setBinaryState(1))? '1' : false;
    }

    /**
     * Turns off switch
     *
     * @return bool|string
     * @throws \Exception
     */
    public function Off()
    {
        return ($this->setBinaryState(0))? '0' : false;
    }

    /**
     * Returns switch state
     * 
     * @return mixed
     * @throws \Exception
     */
    public function state(){
        return $this->getBinaryState();
    }

    /**
     * Indicates if device is dimmable or not.
     * 
     * @return bool
     */
    public function isDimmable()
    {
        return false;
    }
}