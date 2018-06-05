<?php

namespace openWebX\PhpWemo\Contracts;

interface ClientInterface
{
    public function info(string $url);

    public function request(string $controlUrl, ?string $service = null, ?string $method = null, array $arguments = []);
}