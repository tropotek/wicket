<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoggingTest extends TestCase
{
    public function test_data_channel_writes_dated_files_under_data_logs(): void
    {
        $this->assertSame(base_path('data/logs/wicket.log'), config('logging.channels.data.path'));

        $dir = storage_path('framework/testing/logs-'.uniqid());
        config(['logging.channels.data.path' => $dir.'/wicket.log']);

        Log::channel('data')->error('Disk nearly full');

        $file = $dir.'/wicket-'.now()->format('Y-m-d').'.log';
        $this->assertFileExists($file);
        $this->assertStringContainsString('ERROR: Disk nearly full', File::get($file));

        File::deleteDirectory($dir);
    }
}
