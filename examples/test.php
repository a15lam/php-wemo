<?php
require __DIR__ . '/../vendor/autoload.php';

//Run Discovery::find() to get device info. Use id to init devices.
//
//$bulb1 = new \openWebX\PhpWemo\Devices\WemoBulb('wemo_link', 'media_room_1');
//$bulb2 = new \openWebX\PhpWemo\Devices\WemoBulb('192.168.1.69', 'media_room_2');
////
//$bulb1->dim(10);
//sleep(1);
//echo "Dim 10% ".print_r($bulb1->state(), true).PHP_EOL;
//sleep(1);
//
//$bulb1->dim(20);
//sleep(1);
//echo "Dim 20% ".print_r($bulb1->state(), true).PHP_EOL;
//
////$bulb1->dim(50);
////sleep(1);
////$bulb1->dim(80);
//sleep(1);
//$bulb1->dim(100);
//echo "Dim 100% ".print_r($bulb1->state(), true).PHP_EOL;
//sleep(1);
//
//$bulb1->Off();
//sleep(1);
//echo "Off: ".print_r($bulb1->state(), true).PHP_EOL;


//
//$switch = \openWebX\PhpWemo\Discovery::getBaseDeviceByName('Foyer Light');
////$switch = new \openWebX\PhpWemo\Devices\LightSwitch('192.168.1.68');
//$switch = new \openWebX\PhpWemo\Devices\LightSwitch('foyer_light');
//echo $switch->state();
//$switch->On();
//sleep(2);
//$switch->Off();
//
//$switch = new \openWebX\PhpWemo\Devices\WemoSwitch('192.168.1.71');
//$switch->On();
//sleep(2);
//$switch->Off();
//sleep(2);
//$switch->On();
//print_r($switch->state());
//
//$wb = \openWebX\PhpWemo\Discovery::getDeviceByName('media room');
//$wb->on();
//sleep(2);
//echo "state:".$wb->state().PHP_EOL;
//sleep(2);
//$wb->off();
//sleep(1);
//echo "state:".$wb->state().PHP_EOL;

//$b = new \openWebX\PhpWemo\WemoClient('192.168.1.68');
//echo "here".PHP_EOL;
//print_r($b->info('setup.xml'));

$devices = \openWebX\PhpWemo\Discovery::find(true);
print_r($devices);