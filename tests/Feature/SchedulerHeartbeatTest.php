<?php

namespace Tests\Feature;

use App\Support\HealthStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SchedulerHeartbeatTest extends TestCase
{
    public function test_heartbeat_task_is_scheduled_every_minute_and_writes_cache(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => $e->description === 'scheduler-heartbeat');

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);

        $event->run(app());
        $this->assertNotNull(Cache::get(HealthStatus::HEARTBEAT_KEY));
    }
}
