<?php

declare(strict_types=1);

it('allows all ips when whitelist is empty', function () {
    config()->set('prometheus.endpoint.allowed_ips', []);

    $this->get('/metrics')->assertOk();
});

it('blocks ips not in whitelist', function () {
    config()->set('prometheus.endpoint.allowed_ips', ['192.168.1.100']);

    $this->get('/metrics')->assertForbidden();
});

it('allows whitelisted ips', function () {
    config()->set('prometheus.endpoint.allowed_ips', ['127.0.0.1']);

    $this->get('/metrics')->assertOk();
});

it('filters out null values from allowed_ips config', function () {
    config()->set('prometheus.endpoint.allowed_ips', [null, '', false]);

    $this->get('/metrics')->assertOk();
});
