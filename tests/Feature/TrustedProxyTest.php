<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_forwarded_https_is_used_for_generated_urls(): void
    {
        Route::get('/_proxy-url-test', fn (): string => url('/asset.css'));

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '172.19.0.2'])
            ->withHeaders([
                'X-Forwarded-Host' => 'panel.barangbumi.my',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/_proxy-url-test');

        $response
            ->assertOk()
            ->assertContent('https://panel.barangbumi.my/asset.css');
    }
}
