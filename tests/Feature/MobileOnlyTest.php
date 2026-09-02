<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileOnlyTest extends TestCase
{
    public function test_desktop_browser_is_blocked_from_user_pages(): void
    {
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/151 Safari/537.36',
            'Sec-CH-UA-Mobile' => '?0',
        ])->get('/login')
            ->assertForbidden()
            ->assertSee('รองรับเฉพาะโทรศัพท์มือถือ');
    }

    public function test_mobile_browser_can_open_user_pages(): void
    {
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 Chrome/151 Mobile Safari/537.36',
            'Sec-CH-UA-Mobile' => '?1',
        ])->get('/login')->assertOk();
    }

    public function test_desktop_browser_can_open_admin_pages(): void
    {
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/151 Safari/537.36',
            'Sec-CH-UA-Mobile' => '?0',
        ])->get('/admin/login')->assertOk();
    }
}
