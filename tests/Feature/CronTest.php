<?php

namespace Tests\Feature;

use App\Console\Commands\Cron;
use App\Models\Page;
use App\Models\PageLock;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CronTest extends TestCase
{
    use RefreshDatabase;

    public function test_housekeeping(): void
    {
        $disk = Storage::fake('media');
        $user = User::factory()->editor()->create();
        $expired = PageLock::create(['page_id' => Page::factory()->create()->id, 'user_id' => $user->id, 'expires_at' => now()->subMinute()]);
        $live = PageLock::create(['page_id' => Page::factory()->create()->id, 'user_id' => $user->id, 'expires_at' => now()->addMinute()]);
        $disk->put('photos/kept.png', 'x');
        $disk->put('.thumbs/photos/kept.png', 't');
        $disk->put('.thumbs/photos/gone.png', 't');

        $this->artisan('wicket:cron')
            ->expectsOutputToContain('Expired edit locks removed: 1')
            ->expectsOutputToContain('Orphan thumbnails removed: 1')
            ->assertSuccessful();

        $this->assertNull(PageLock::find($expired->page_id));
        $this->assertNotNull(PageLock::find($live->page_id));
        $this->assertTrue($disk->exists('.thumbs/photos/kept.png'));
        $this->assertFalse($disk->exists('.thumbs/photos/gone.png'));
    }

    public function test_last_run_is_reported_on_up(): void
    {
        Storage::fake('media');
        $this->travelTo(now()->setDateTime(2026, 10, 9, 3, 0, 0));

        $this->getJson('/up')->assertJsonPath('cron', null);
        $this->artisan('wicket:cron')->assertSuccessful();

        $this->assertNotNull(Cache::get(Cron::LAST_RUN_KEY));
        $this->getJson('/up')->assertJsonPath('cron', '2026-10-09T03:00:00+00:00');
    }

    public function test_schedule(): void
    {
        $events = collect(app(Schedule::class)->events())->keyBy(fn ($e) => $e->description ?: $e->command);
        $find = fn (string $needle) => $events->first(fn ($e, $key) => str_contains($key, $needle));

        $this->assertSame('* * * * *', $find('scheduler-heartbeat')->expression);
        $this->assertSame('0 * * * *', $find('wicket:cron')->expression);
        $this->assertTrue($find('wicket:cron')->withoutOverlapping);
        $this->assertSame('30 3 * * *', $find('wicket:reindex')->expression);
        $this->assertTrue($find('wicket:reindex')->withoutOverlapping);
    }
}
