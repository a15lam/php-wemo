<?php

namespace openWebX\PhpWemo\Devices;

use openWebX\PhpWemo\Contracts\DeviceInterface;

/**
 * Class LightSwitch
 *
 * @package openWebX\PhpWemo\Devices
 */
class LightSwitch extends BaseDevice implements DeviceInterface
{
    const MODEL_NAME = 'LightSwitch';

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
    public function state()
    {
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