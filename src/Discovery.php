<?php
namespace a15lam\PhpWemo;

use a15lam\PhpWemo\Devices\Bridge;
use a15lam\PhpWemo\Devices\LightSwitch;
use a15lam\PhpWemo\Devices\InsightSwitch;
use a15lam\PhpWemo\Devices\WemoBulb;
use a15lam\PhpWemo\Devices\WemoSwitch;
use a15lam\PhpWemo\Workspace as WS;
use Clue\React\Ssdp\Client;
use React\EventLoop\Factory;

/**
 * Class Discovery
 *
 * Discovers all Wemo devices in the network
 * and caches them in a file in json.
 *
 * @package a15lam\PhpWemo
 */
class Discovery
{
    /** @type array */
    protected static $output = [];

    public static $deviceFile = null;

    /**
     * Retrieves devices from cache. If not devices are found in cache
     * then finds/discovers Wemo devices in the network and returns them.
     * Caches found devices in a file.
     *
     * @param bool $refresh Set this to true to force device discovery.
     *
     * @return array|mixed|null
     */
    public static function find($refresh = false)
    {
        // If not refreshing then look in cache first.
        if ($refresh === false) {
            $devices = static::getDevicesFromStorage();
            if (!empty($devices)) {
                return $devices;
            } else {
                // No devices found in cache. Force refresh.
                $refresh = true;
            }
        }

        // Discover devices in the network
        if ($refresh) {
            $loop = Factory::create();
            $client = new Client($loop);
            $client->search('urn:Belkin:service:basicevent:1', 2)->then(
                function (){
                    if (WS::config()->get('debug') === true) {
                        echo 'Search completed' . PHP_EOL;
                    }
                },
                function ($e){
                    throw new \Exception('Device discovery failed: ' . $e);
                },
                function ($progress){
                    if (WS::config()->get('debug') === true) {
                        echo "found one!" . PHP_EOL;
                    }
                    static::$output[] = $progress;
                }
            );
            $loop->run();
        }

        // Get additional device info.
        $devices = static::getDeviceInfo(static::$output);
        // Cache found devices.
        static::setDevicesInStorage($devices);

        return $devices;
    }

    /**
     * Finds a device by its name
     *
     * @param $name string
     *
     * @return mixed
     * @throws \Exception
     */
    public static function getDeviceByName($name)
    {
        $id = str_replace(' ', '_', strtolower($name));
        return static::getDeviceById($id);
    }

    public static function getDeviceById($id)
    {
        $device = static::lookupDevice('id', $id);
        if (!empty($device) && isset($device['class_name'])) {
            $class = $device['class_name'];

            return new $class($id);
        }

        // Search device in wemo link
        $bridge = new Bridge('wemo_link');
        $devices = $bridge->getPairedDevices();

        foreach ($devices as $d) {
            if ($id === $d['id']) {
                if ($d['productName'] === 'Lighting') {
                    return new WemoBulb('wemo_link', $id);
                }
            }
        }

        throw new \Exception('Invalid device id supplied. No base device found by id ' . $id);
    }

    /**
     * Lookup a device by key - value
     *
     * @param $key   string
     * @param $value mixed
     *
     * @return mixed
     * @throws \Exception
     */
    public static function lookupDevice($key, $value)
    {
        $devices = static::find();

        foreach ($devices as $device) {
            if ($value === $device[$key]) {
                return $device;
            }
        }

        return null;
    }

    /**
     * Fetches device details
     *
     * @param $devices
     *
     * @return array
     */
    protected static function getDeviceInfo($devices)
    {
        $infos = [];
        foreach ($devices as $device) {
            $sender = $device['_sender'];
            $ip = substr($sender, 0, strpos($sender, ':'));
            $wc = new WemoClient($ip);
            $info = $wc->info('setup.xml');
            $info = $info['root']['device'];
            $id = str_replace(' ', '_', strtolower($info['friendlyName']));
            $data = [
                'id'           => $id,
                'ip'           => $ip,
                'deviceType'   => $info['deviceType'],
                'friendlyName' => $info['friendlyName'],
                'modelName'    => $info['modelName'],
                'UDN'          => $info['UDN']
            ];

            if (static::isBridge($info['UDN'])) {
                $bridge = new Bridge($ip);
                $bridgeDevices = $bridge->getPairedDevices(true);

                foreach ($bridgeDevices as $i => $bridgeDevice) {
                    $bridgeDevice['id'] = str_replace(' ', '_', strtolower($bridgeDevice['FriendlyName']));
                    $bridgeDevices[$i] = $bridgeDevice;
                }

                $data['class_name'] = Bridge::class;
                $data['device'] = $bridgeDevices;
            } else if (static::isLightSwitch($info['UDN'])) {
                $data['class_name'] = LightSwitch::class;
            } else if (static::isWemoSwitch($info['UDN'])) {
                $data['class_name'] = WemoSwitch::class;
            } else if (static::isInsightSwitch($info['UDN'])) {
                $data['class_name'] = InsightSwitch::class;
            }

            $infos[] = $data;
        }

        return $infos;
    }

    /**
     * Caches devices in file.
     *
     * @param $devices array
     *
     * @return bool
     */
    protected static function setDevicesInStorage($devices)
    {
        try {
            $file = (static::$deviceFile !== null) ? static::$deviceFile : WS::config()->get('device_storage');
            $json = json_encode($devices, JSON_UNESCAPED_SLASHES);
            @file_put_contents($file, $json);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Retrieves devices from cache
     *
     * @return mixed|null
     */
    protected static function getDevicesFromStorage()
    {
        try {
            $file = (static::$deviceFile !== null) ? static::$deviceFile : WS::config()->get('device_storage');
            $content = @file_get_contents($file);
            if (!empty($content)) {
                $devices = json_decode($content, true);

                return $devices;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Checks to see if UDN is for a bridge device.
     *
     * @param $udn string
     *
     * @return bool
     */
    protected static function isBridge($udn)
    {
        if (strpos($udn, 'uuid:Bridge-1') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if UDN is for a LightSwitch device.
     *
     * @param $udn string
     *
     * @return bool
     */
    protected static function isLightSwitch($udn)
    {
        if (strpos($udn, 'uuid:Lightswitch-1') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if UDN is for a InsightSwitch device.
     *
     * @param $udn string
     *
     * @return bool
     */
    protected static function isInsightSwitch($udn)
    {
        if (strpos($udn, 'uuid:Insight-1') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if UDN is for a WemoSwitch device.
     *
     * @param $udn string
     *
     * @return bool
     */
    protected static function isWemoSwitch($udn)
    {
        if (strpos($udn, 'uuid:Socket-1') !== false) {
            return true;
        }

        return false;
    }
}