# php-wemo
PHP library to control Wemo devices.

Currently supports...

1. Wemo light bulb - on/off/dimming 
2. Wemo light switch - on/off
3. Wemo switch (socket) - on/off
4. Wemo insight switch - on/off/params
5. Device discovery
6. Grouped devices under wemo bridge


## Getting started:

<pre>
git clone https://github.com/a15lam/php-wemo.git
cd php-wemo
composer update
php example/console.php   // An example command line app to control your wemo devices.
</pre>

## Usage:

<pre>
$lightSwitch = \a15lam\PhpWemo\Discovery::getDeviceByName('Bed Room Light'); // Use your wemo device name as they show on your wemo app. Supports grouped devices
$lightSwitch->On();
sleep(2); // Allow a moment to see the light turning on.
$lightSwitch->Off();
// Get switch status
echo $lightSwitch->status();
</pre>

Check the example directory for more usage. Run example/console.php from command line to control your devices.
