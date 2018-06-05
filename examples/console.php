<?php
require __DIR__ . '/../vendor/autoload.php';

echo "\n\n-------------------------------------------------" . PHP_EOL;
echo "| PHP-WEMO Console " . PHP_EOL;
echo "-------------------------------------------------" . PHP_EOL;

$refresh = (isset($argv[1])) ? $argv[1] : false;

echo "Looking up your Wemo devices...\n" . PHP_EOL;

$devices = \openWebX\PhpWemo\Discovery::find($refresh);

if (count($devices) > 0) {
    while (true) {
        $list = [];
        foreach ($devices as $device) {
            if ($device['deviceType'] === 'urn:Belkin:device:bridge:1') {
                foreach ($device['device'] as $d) {
                    if ($d['productName'] === 'Lighting') {
                        $di = new \openWebX\PhpWemo\Devices\WemoBulb($device['id'], $d['id']);
                        $list[] = [$device['id'] . '.' . $d['id'], $di->state(), $di->dimState()];
                    }
                }
            } else {
                if (isset($device['class_name'])) {
                    $client = new \openWebX\PhpWemo\WemoClient($device['ip'], $device['port']);
                    $dc = $device['class_name'];
                    /** @var \openWebX\PhpWemo\Contracts\DeviceInterface $di */
                    $di = new $dc($device['id'], $client);
                    $list[] = [$device['id'], $di->state()];
                }
            }
        }

        $list[] = '[QUIT]';

        foreach ($list as $i => $l) {
            if('[QUIT]' !== $l) {
                $dimState = (isset($l[2])) ? ':' . $l[2] : '';
                $state = (boolval($l[1]) === true) ? 'On' : 'Off';
                echo "[$i] [$state" . "$dimState] $l[0]" . PHP_EOL;
            } else {
                echo "[$i] $l";
            }
        }

        $choice = -1;

        while (!isset($list[$choice])) {
            if ($choice !== -1) {
                echo "Invalid choice. Please select from 0 to " . (count($list) - 1) . PHP_EOL;
            }
            $choice =
                trim(readline("\nPlease select a Wemo device to control (0..." .
                    (count($list) - 1) .
                    "): "));
        }

        if((int) $choice === (count($list)-1)){
            echo 'Bye...' . PHP_EOL;
            exit();
        }

        $chosen = explode('.', $list[$choice][0]);
        $device = \openWebX\PhpWemo\Discovery::lookupDevice('id', $chosen[0]);
        $deviceClass = $device['class_name'];
        $myDevice = null;

        if ($deviceClass === \openWebX\PhpWemo\Devices\Bridge::class) {
            $bridgeDevices = $device['device'];
            $bridgeDevice = [];
            foreach ($bridgeDevices as $bd) {
                if ($bd['id'] === $chosen[1]) {
                    $bridgeDevice = $bd;
                    break;
                }
            }

            $deviceType = $bridgeDevice['productName'];
            if ('Lighting' === $deviceType) {
                $myDevice = new \openWebX\PhpWemo\Devices\WemoBulb($chosen[0], $chosen[1]);
            } else {
                echo "\nYour Wemo Link device $chosen[1] is not currently supported by PHP-WEMO" . PHP_EOL;
                exit();
            }

            echo "\nOptions..." . PHP_EOL;

            echo "[On]" . PHP_EOL;
            echo "[Off]" . PHP_EOL;
            echo "[Dim:%]" . PHP_EOL;

            $success = false;
            while (!$success) {
                $operation = explode(':', trim(strtolower(readline("\nTurn on/off/dim $chosen[1]? "))));

                if ('on' === $operation[0]) {
                    $success = ($myDevice->On() === false) ? false : true;
                } else if ('off' === $operation[0]) {
                    $success = ($myDevice->Off() === false) ? false : true;
                } else if ('dim' === $operation[0] && isset($operation[1])) {
                    $success = $myDevice->dim($operation[1]);
                }
            }
        } else {
            $client = new \openWebX\PhpWemo\WemoClient($device['ip'], $device['port']);
            /** @type \openWebX\PhpWemo\Contracts\DeviceInterface $myDevice */
            $myDevice = new $deviceClass($chosen[0], $client);

            echo "\nOptions..." . PHP_EOL;

            echo "[On]" . PHP_EOL;
            echo "[Off]" . PHP_EOL;

            $success = false;
            while (!$success) {
                $operation = trim(strtolower(readline("\nTurn on/off $chosen[0]? ")));

                if ('on' === $operation) {
                    $success = ($myDevice->On() === false) ? false : true;
                } else if ('off' === $operation) {
                    $success = ($myDevice->Off() === false) ? false : true;
                }
            }
        }

        echo "\nOperation successful." . PHP_EOL;
        echo "-------------------------------------------------\n" . PHP_EOL;
    }
} else {
    echo "Could not find any Wemo devices in your network. You may try again.";
}
