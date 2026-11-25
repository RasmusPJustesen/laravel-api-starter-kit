<?php

namespace Tests\Unit;

use App\Support\Auth\UserAgent;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    public function test_agent_metadata_is_best_effort(): void
    {
        $this->assertSame(['device' => null, 'browser' => null, 'platform' => null], UserAgent::describe(null));
        $this->assertSame(['device' => 'Desktop', 'browser' => 'Edge', 'platform' => 'Windows'], UserAgent::describe('Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36 Edg/130.0'));
        $this->assertSame(['device' => 'Mobile', 'browser' => 'Samsung Internet', 'platform' => 'Android'], UserAgent::describe('Mozilla/5.0 (Linux; Android 15) Chrome/130.0 Mobile Safari/537.36 SamsungBrowser/28.0'));
    }
}
