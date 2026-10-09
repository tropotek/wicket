<?php

use App\Html\Passes\ContentImages;
use App\Html\Passes\ExternalLinks;
use App\Html\Passes\InternalLinks;
use App\Html\Passes\SecretPlaceholders;

return [

    /*
    | Allow public self-registration at /register. When off, users are created
    | with `php artisan wicket:create-user` (or by an admin).
    */
    'registration' => (bool) env('WICKET_REGISTRATION', false),

    /*
    | The secrets module: stored credentials embedded in pages. Off hides the manager
    | and the editor button.
    */
    'secrets' => (bool) env('WICKET_SECRETS', true),

    /*
    | Failed logins allowed per account (or unknown identifier) + IP before
    | lockout, and how long the lockout lasts.
    */
    /*
    | How long a page edit lock lasts without a heartbeat. The editor refreshes
    | it every half-lifetime while open.
    */
    'page_lock_seconds' => (int) env('WICKET_PAGE_LOCK_SECONDS', 120),

    /*
    | Largest image upload accepted, in KB. php.ini's upload_max_filesize must allow it.
    */
    'upload_max_kb' => (int) env('WICKET_UPLOAD_MAX_KB', 10240),

    /*
    | File types the media browser accepts. Media is served as static files, so keep
    | anything a browser or server might execute (svg, html, js, php…) off this list.
    */
    'media_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'txt', 'csv', 'zip',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
    ],

    /*
    | Cache rendered page content for guests (App\Support\RenderCache). Signed-in views are
    | never cached. Busted on any page, revision or redirect write.
    */
    'render_cache' => (bool) env('WICKET_RENDER_CACHE', true),

    /*
    | Render passes run over page HTML before display, in this order (App\Html\Pipeline).
    */
    'html_passes' => [
        ExternalLinks::class,
        InternalLinks::class,
        SecretPlaceholders::class,
        ContentImages::class,
    ],

    /*
    | Hosts an <iframe> in page content may show (https only). Everything else is stripped on save.
    */
    'embed_hosts' => ['www.youtube.com', 'www.youtube-nocookie.com', 'youtube.com', 'player.vimeo.com'],

    /*
    | First admin, created by the container entrypoint (wicket:first-admin) when no admin exists.
    | Leave the password empty to have one generated and printed once in the log.
    */
    'first_admin' => [
        'username' => env('WICKET_ADMIN_USERNAME', 'admin'),
        'email' => env('WICKET_ADMIN_EMAIL'),
        'password' => env('WICKET_ADMIN_PASSWORD'),
    ],

    'login' => [
        'max_attempts' => (int) env('WICKET_LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('WICKET_LOGIN_LOCKOUT_MINUTES', 15),
    ],

];
