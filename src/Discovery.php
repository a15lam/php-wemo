<?php
namespace openWebX\PhpWemo;

use openWebX\PhpWemo\Contracts\ClientInterface;
use openWebX\PhpWemo\Contracts\DeviceInterface;
use openWebX\PhpWemo\Devices\Bridge;
use openWebX\PhpWemo\Devices\LightSwitch;
use openWebX\PhpWemo\Devices\InsightSwitch;
use openWebX\PhpWemo\Devices\WemoBulb;
use openWebX\PhpWemo\Devices\WemoSwitch;
use openWebX\PhpWemo\Workspace as WS;
use Clue\React\Ssdp\Client;
use React\EventLoop\Factory;

/**
 * Class Discovery
 *
 * Discovers all Wemo devices in the network
 * and caches them in a file in json.
 *
 * @package openWebX\PhpWemo
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
            static::findAllDevices();
        }

        // Get additional device info.
        $devices = static::getDeviceInfo(static::$output);
        // Cache found devices.
        static::setDevicesInStorage($devices);

        return $devices;
    }

    /**
     * Discover all devices in network.
     */
    protected static function findAllDevices()
    {
        static::findBelkinWemo();
    }

    /**
     * Discover all Belkin Wemo devices in network.
     */
    protected static function findBelkinWemo()
    {
        $loop = Factory::create();
        $client = new Client($loop);
        $client->search('urn:Belkin:device:**', 2)->then(
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

    /**
     * @param $id
     *
     * @return \openWebX\PhpWemo\Devices\WemoBulb
     * @throws \Exception
     */
    public static function getDeviceById($id)
    {
        $device = static::lookupDevice('id', $id);
        if (!empty($device) && isset($device['class_name'])) {
            /** @var DeviceInterface $class */
            $class = $device['class_name'];
            $client = static::getClientByDevice($device);
            return new $class($id, $client);
        }

        // Search device in wemo link
        $bridge = new Bridge('wemo_link', new WemoClient($device['ip'], $device['port']));
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
     * @param $device
     *
     * @return \openWebX\PhpWemo\WemoClient
     */
    protected static function getClientByDevice($device)
    {
        $ip = static::getIpFromDevice($device);
        $port = static::getPortFromDevice($device);
        $client = new WemoClient($ip, $port);

        return $client;
    }

    /**
     * @param $device
     *
     * @return string
     */
    protected static function getIpFromDevice($device)
    {
        if(isset($device['ip'])){
            return $device['ip'];
        }
        if(isset($device['_sender'])){
            $sender = $device['_sender'];
            $ip = substr($sender, 0, strpos($sender, ':'));
            return $ip;
        }

        throw new \RuntimeException('No IP found for device ' . implode(', ', $device));
    }

    /**
     * @param $device
     *
     * @return string
     */
    protected static function getPortFromDevice($device)
    {
        if(isset($device['port'])){
            return $device['port'];
        }
        if(isset($device['data'])){
            return static::getPort($device['data']);
        }

        throw new \RuntimeException('No Port found for device ' . implode(', ', $device));
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
    protected static function getDeviceInfo(array $devices): array
    {
        $infos = [];
        foreach ($devices as $device) {
            $sender = $device['_sender'];
            $port = static::getPort($device['data']);
            $ip = substr($sender, 0, strpos($sender, ':'));
            $client = static::getClientByDevice($device);
            $info = static::getClientInfo($client);

            if (isset($info['root']) && isset($info['root']['device'])) {

                $info = $info['root']['device'];

                // Skipping emulated wemo switch by fauxmo.
                if($info['deviceType'] !== 'urn:MakerMusingsArif:device:controllee:1') {
                    $id = str_replace(' ', '_', strtolower($info['friendlyName']));
                    $data = [
                        'id'           => $id,
                        'ip'           => $ip,
                        'port'         => $port,
                        'deviceType'   => $info['deviceType'],
                        'friendlyName' => $info['friendlyName'],
                        'modelName'    => $info['modelName'],
                        'UDN'          => $info['UDN']
                    ];

                    if (static::isBridge($info['modelName'])) {
                        $bridge = new Bridge($ip, $client);
                        $bridgeDevices = $bridge->getPairedDevices(true);

                        foreach ($bridgeDevices as $i => $bridgeDevice) {
                            $bridgeDevice['id'] = str_replace(' ', '_', strtolower($bridgeDevice['FriendlyName']));
                            $bridgeDevices[$i] = $bridgeDevice;
                        }

                        $data['class_name'] = Bridge::class;
                        $data['device'] = $bridgeDevices;
                    } else if (static::isLightSwitch($info['modelName'])) {
                        $data['class_name'] = LightSwitch::class;
                    } else if (static::isWemoSwitch($info['modelName'])) {
                        $data['class_name'] = WemoSwitch::class;
                    } else if (static::isInsightSwitch($info['modelName'])) {
                        $data['class_name'] = InsightSwitch::class;
                    } else if (static::isEmulatedWemoSwitch($info['modelName'])) {
                        $data['class_name'] = WemoSwitch::class;
                    } else {
                        static::resolveOtherDevices($data, $info, $device);
                    }

                    $infos[] = $data;
                }
            }
        }

        return $infos;
    }

    /**
     * @param array $data
     * @param array $info
     * @param array $device
     */
    protected static function resolveOtherDevices(& $data, $info, $device)
    {
        // Overwrite this class for adding other devices.
    }

    /**
     * @param ClientInterface $client
     *
     * @return mixed
     */
    protected static function getClientInfo(ClientInterface $client)
    {
        return $client->info('setup.xml');
    }

    /**
     * @param $data
     *
     * @return string
     */
    protected static function getPort(string $data) : string
    {
        $pieces = explode('LOCATION:', $data);
        $location = substr($pieces[1], 0, strpos($pieces[1],  "\n"));
        $pieces = explode(':', $location);
        $port = trim(substr($pieces[2], 0, strpos($pieces[2], '/')));

        return $port;
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
            $data = [];
            $file = (static::$deviceFile !== null) ? static::$deviceFile : WS::config()->get('device_storage');
            $content = @file_get_contents($file);
            if (!empty($content)) {
                $data = json_decode($content, true);
            }
            if(!isset($data['state'])){
                $data = ['state' => [], 'device' => []];
            }
            $data['device'] = $devices;
            $json = json_encode($data, JSON_UNESCAPED_SLASHES);
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
                if(isset($devices['device'])){
                    return $devices['device'];
                }

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
     * @param $modelName string
     *
     * @return bool
     */
    protected static function isBridge($modelName)
    {
        if ($modelName === Bridge::MODEL_NAME) {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if UDN is for a LightSwitch device.
     *
     * @param $modelName string
     *
     * @return bool
     */
    protected static function isLightSwitch($modelName)
    {
        if ($modelName === LightSwitch::MODEL_NAME) {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if UDN is for a InsightSwitch device.
     *
     * @param $modelName string
     *
     * @return bool
     */
    protected static function isInsightSwitch($modelName)
    {
        if ($modelName === InsightSwitch::MODEL_NAME) {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if UDN is for a WemoSwitch device.
     *
     * @param $modelName string
     *
     * @return bool
     */
    protected static function isWemoSwitch($modelName)
    {
        if ($modelName === WemoSwitch::MODEL_NAME) {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if UDN is for a Emulated WemoSwitch device.
     *
     * @param $modelName string
     *
     * @return bool
     */
    protected static function isEmulatedWemoSwitch($modelName)
    {
        if ($modelName === WemoSwitch::EMULATED_NAME) {
            return true;
        }

        return false;
    }
}