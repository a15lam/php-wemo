<?php
namespace a15lam\PhpWemo;

use a15lam\PhpWemo\Contracts\ClientInterface;
use a15lam\PhpWemo\Workspace as WS;

/**
 * Class WemoClient
 *
 * This class makes various HTTP requests to Wemo devices.
 *
 * @package a15lam\PhpWemo
 */
class WemoClient extends BaseClient implements ClientInterface
{
    /**
     * Makes requests to devices.
     *
     * @param string $controlUrl
     * @param string $service
     * @param string $method
     * @param array  $arguments
     *
     * @return array|string
     * @throws \Exception
     */
    public function request($controlUrl, ?string $service = null, ?string $method = null, array $arguments = [])
    {
        $controlUrl = ltrim($controlUrl, '/');
        $url = 'http://' . $this->ip . '/' . $controlUrl;
        $action = $service . '#' . $method;

        $xmlHeader = '<?xml version="1.0" encoding="utf-8"?>
                      <s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
                      <s:Body>
                      <u:' . $method . ' xmlns:u="' . $service . '">';

        $xmlFooter = '</u:' . $method . '></s:Body></s:Envelope>';

        $xmlBody = '';

        foreach ($arguments as $key => $value) {
            $xmlBody .= '<' . $key . '>' . $value . '</' . $key . '>';
        }

        $xml = $xmlHeader . $xmlBody . $xmlFooter;

        try {
            $options = [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_PORT           => $this->port,
                CURLOPT_POSTFIELDS     => $xml,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_VERBOSE        => WS::config()->get('debug', false),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type:text/xml',
                    'SOAPACTION:"' . $action . '"'
                ]
            ];

            $ch = curl_init();
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
        } catch (\Exception $e) {
            throw $e;
        }

        return $this->formatResponse($response);
    }
}