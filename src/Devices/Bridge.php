<?php

namespace openWebX\PhpWemo\Devices;

use openWebX\PhpWemo\Discovery;
use openWebX\PhpWemo\WemoClient;

/**
 * Class Bridge
 *
 * @package openWebX\PhpWemo\Devices
 */
class Bridge extends BaseDevice
{
    const MODEL_NAME = 'Bridge';

    protected $services = [
        'BridgeService' => [
            'serviceType' => 'urn:Belkin:service:bridge:1',
            'serviceId'   => 'urn:Belkin:serviceId:bridge1',
            'controlURL'  => '/upnp/control/bridge1',
            'eventSubURL' => '/upnp/event/bridge1',
            'SCPDURL'     => '/bridgeservice.xml'
        ]
    ];

    /**
     * Retrieves all paired bridge devices.
     *
     * @param bool $refresh Set true to force device discovery
     *
     * @return mixed
     * @throws \Exception
     */
    public function getPairedDevices($refresh = false)
    {
        if ($refresh === false) {
            $device = Discovery::lookupDevice('ip', $this->ip);
            if (isset($device['device']) && is_array($device['device'])) {
                return $device['device'];
            }
        }

        $service = $this->services['BridgeService']['serviceType'];
        $controlUrl = $this->services['BridgeService']['controlURL'];
        $method = 'GetEndDevices';
        $arguments = [
            'DevUDN'      => $this->getUDN($refresh),
            'ReqListType' => 'PAIRED_LIST'
        ];

        $rs = $this->client->request($controlUrl, $service, $method, $arguments);
        $rs = $this->unwrapResponse($rs);
        $rs = WemoClient::xmlToArray($rs['u:GetEndDevicesResponse']['DeviceLists']);

        // Standalone devices.
        $devices = $rs['DeviceLists']['DeviceList']['DeviceInfos'];
        if(static::isArrayAssoc($devices)) {
            $devices = $rs['DeviceLists']['DeviceList']['DeviceInfos']['DeviceInfo'];
        }
        foreach ($devices as $k => $d) {
            $d['IsGroupAction'] = 'NO';
            $devices[$k] = $d;
        }
        // Grouped devices.
        $groupedDeviceList = [];
        $groupedDevices = $rs['DeviceLists']['DeviceList']['GroupInfos'];
        if(static::isArrayAssoc($groupedDevices)) {
            $groupedDeviceList[] = $rs['DeviceLists']['DeviceList']['GroupInfos']['GroupInfo'];
        }
        foreach ($groupedDeviceList as $gd) {
            if(!empty($gd['GroupID']) && !empty($gd['GroupName']) && !empty($gd['GroupCapabilityValues'])) {
                $devices[] = [
                    'DeviceID'      => $gd['GroupID'],
                    'FriendlyName'  => $gd['GroupName'],
                    'CurrentState'  => $gd['GroupCapabilityValues'],
                    'productName'   => $gd['DeviceInfos']['DeviceInfo'][0]['productName'],
                    'IsGroupAction' => 'YES'
                ];
                if(isset($gd['DeviceInfos']) && isset($gd['DeviceInfos']['DeviceInfo'])){
                    foreach($gd['DeviceInfos']['DeviceInfo'] as $gdi){
                        $gdi['IsGroupAction'] = 'NO';
                        $devices[] = $gdi;
                    }
                }
            }
        }
        return $devices;
    }

    /**
     * Looks up bridge deviceId by its discovery id.
     *
     * @param $id string
     *
     * @return null
     */
    public function getDeviceIdByCustomId($id)
    {
        $devices = $this->getPairedDevices();

        foreach ($devices as $device) {
            if (strtolower($device['id']) === strtolower($id)) {
                return $device['DeviceID'];
            }
        }

        return null;
    }

    /**
     * Turns on a Wemo bulb
     *
     * @param $deviceId string
     *
     * @return bool|string
     * @throws \Exception
     */
    public function bulbOn($deviceId)
    {
        return ($this->setDeviceStatus($deviceId, 1)) ? '1' : false;
    }

    /**
     * Turns off a Wemo bulb
     *
     * @param $deviceId
     *
     * @return bool|string
     * @throws \Exception
     */
    public function bulbOff($deviceId)
    {
        return ($this->setDeviceStatus($deviceId, 0)) ? '0' : false;
    }

    /**
     * Sets bridge device status
     *
     * @param $deviceId
     * @param $state
     * @param $level
     *
     * @return bool
     * @throws \Exception
     */
    public function setDeviceStatus($deviceId, $state = null, $level = null)
    {
        $groupAction = 'NO';
        $devices = $this->getPairedDevices();
        foreach ($devices as $d) {
            if ($deviceId === $d['DeviceID']) {
                $groupAction = $d['IsGroupAction'];
            }
        }

        if (intval($level) > 0 && $state === 0) {
            $state = '1';
        }

        $capids = [];
        $capval = [];
        if ($state !== null) {
            $capids[] = '10006';
            $capval[] = $state;
        }
        if ($level !== null) {
            $capids[] = '10008';
            $capval[] = $level . ':0';
        }

        $capIdsString = implode(',', $capids);
        $capValString = implode(',', $capval);

        $service = $this->services['BridgeService']['serviceType'];
        $controlUrl = $this->services['BridgeService']['controlURL'];
        $method = 'SetDeviceStatus';
        $arguments = [
            'DeviceStatusList' => '&lt;?xml version=&quot;1.0&quot; encoding=&quot;utf-8&quot;?&gt;&lt;DeviceStatusList&gt;&lt;DeviceStatus&gt;&lt;IsGroupAction&gt;' .
                $groupAction .
                '&lt;/IsGroupAction&gt;&lt;DeviceID available=&quot;YES&quot;&gt;' .
                $deviceId .
                '&lt;/DeviceID&gt;&lt;CapabilityID&gt;' .
                $capIdsString .
                '&lt;/CapabilityID&gt;&lt;CapabilityValue&gt;' .
                $capValString .
                '&lt;/CapabilityValue&gt;&lt;LastEventTimeStamp&gt;0&lt;/LastEventTimeStamp&gt;&lt;/DeviceStatus&gt;&lt;/DeviceStatusList&gt;'
        ];

        $rs = $this->client->request($controlUrl, $service, $method, $arguments);
        $rs = $this->unwrapResponse($rs);

        if (isset($rs['s:Fault'])) {
            throw new \Exception('Failed to change bulb state. ' . print_r($rs, true));
        }

        return true;
    }

    /**
     * @param $deviceId
     *
     * @return bool
     */
    public function getBulbState($deviceId)
    {
        $devices = $this->getPairedDevices(true);
        foreach ($devices as $device) {
            if ($device['DeviceID'] === $deviceId) {
                $curstate = explode(',', $device['CurrentState']);

                return $curstate;
            }
        }

        return false;
    }

    public static function isArrayAssoc(array $array)
    {
        $keys = array_keys($array);

        return array_keys($keys) !== $keys;
    }
}