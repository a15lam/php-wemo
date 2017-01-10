<?php

namespace a15lam\PhpWemo\Devices;

use a15lam\PhpWemo\Contracts\DeviceInterface;

/**
 * Class WemoSwitch
 *
 * @package a15lam\PhpWemo\Devices
 */
class InsightSwitch extends WemoSwitch implements DeviceInterface
{
    protected $services = [
        'BridgeService' => [
            'serviceType' => 'urn:Belkin:service:basicevent:1',
            'serviceId'   => 'urn:Belkin:serviceId:basicevent1',
            'controlURL'  => '/upnp/control/basicevent1',
            'eventSubURL' => '/upnp/event/basicevent1',
            'SCPDURL'     => '/eventservice.xml'
        ],
        'InsightService' => [
            'serviceType' => 'urn:Belkin:service:insight:1',
            'serviceId'   => 'urn:Belkin:serviceId:insight1',
            'controlURL'  => '/upnp/control/insight1',
            'eventSubURL' => '/upnp/event/insight1',
            'SCPDURL'     => '/insightservice.xml'
        ]
    ];

    /**
     * Returns insight params
     * 
     * @return mixed
     * @throws \Exception
     */
    public function getParams(){
        $service = $this->services['InsightService']['serviceType'];
        $controlUrl = $this->services['InsightService']['controlURL'];
        $method = 'GetInsightParams';

        $rs = $this->client->request($controlUrl, $service, $method);
        $rs = $this->unwrapResponse($rs);

        if (isset($rs['s:Fault'])) {
            throw new \Exception('Failed to get insight params. ' . print_r($rs, true));
        }

        return $rs['u:GetInsightParamsResponse']['InsightParams'];
    }
}