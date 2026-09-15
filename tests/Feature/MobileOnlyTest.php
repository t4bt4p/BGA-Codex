<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileOnlyTest extends TestCase
{
    public function test_desktop_browser_is_blocked_from_user_pages(): void
    {
        config(['user_access.mobile_only' => true]);
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/151 Safari/537.36',
            'Sec-CH-UA-Mobile' => '?0',
        ])->get('/login')
            ->assertForbidden()
            ->assertSee('รองรับเฉพาะโทรศัพท์มือถือ');
    }

    public function test_mobile_browser_can_open_user_pages(): void
    {
        config(['user_access.mobile_only' => true]);
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 Chrome/151 Mobile Safari/537.36',
            'Sec-CH-UA-Mobile' => '?1',
        ])->get('/login')->assertOk();
    }

    public function test_desktop_browser_can_open_admin_pages(): void
    {
        config(['user_access.mobile_only' => true]);
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/151 Safari/537.36',
            'Sec-CH-UA-Mobile' => '?0',
        ])->get('/admin/login')->assertOk();
    }

    public function test_desktop_can_open_all_user_routes_when_mobile_restriction_is_paused(): void
    {
        config(['user_access.mobile_only' => false]);
        foreach (['/', '/login', '/register', '/mygames', '/history', '/profile', '/account-suspended'] as $path) {
            $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Sec-CH-UA-Mobile' => '?0'])
                ->get($path)->assertOk();
        }
    }
}
