<?php

namespace Database\Seeders;

use App\Enums\Visibility;
use App\Models\Content;
use App\Models\Page;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One dev page per visibility level, authored by the seeded editor. The public page also shows
 * the kinds of link, the private page a few example secrets, and a second private page tiles them.
 */
class DevPageSeeder extends Seeder
{
    public function run(): void
    {
        $editor = User::where('username', 'editor')->firstOrFail();

        $pages = [
            ['public-page', 'Public page', Visibility::Public],
            ['members-page', 'Members page', Visibility::Members],
            ['editors-page', 'Editors page', Visibility::Editors],
            ['private-page', 'Private page', Visibility::Private],
        ];

        $secrets = $this->secrets($editor);

        foreach ($pages as [$slug, $title, $visibility]) {
            $page = Page::factory()->for($editor, 'author')->create([
                'slug' => $slug,
                'title' => $title,
                'visibility' => $visibility,
            ]);

            $html = "<p>This page is <strong>{$visibility->label()}</strong>.</p>\n<p>".fake()->paragraph().'</p>';

            if ($visibility === Visibility::Public) {
                $html .= "\n".$this->links();
            }

            if ($visibility === Visibility::Private) {
                $html .= "\n<h2>Secrets</h2>\n<p>".implode("</p>\n<p>", $secrets).'</p>';
            }

            Content::factory()->for($page)->for($editor)->create([
                'html' => $html,
                'description' => "Seeded {$visibility->value} page",
            ]);
        }

        $tiles = Page::factory()->for($editor, 'author')->create([
            'slug' => 'secret-tiles',
            'title' => 'Secret tiles',
            'visibility' => Visibility::Private,
        ]);

        Content::factory()->for($tiles)->for($editor)->create([
            'html' => $this->tiles($secrets),
            'description' => 'Seeded secret layouts',
        ]);
    }

    /**
     * Each kind of link: other sites get the external-link icon unless shown as a button or image;
     * links within the site get neither.
     */
    private function links(): string
    {
        return <<<'HTML'
            <h2>Links</h2>
            <p>Other sites: <a href="https://laravel.com/docs">Laravel docs</a>, <a href="https://www.php.net/">PHP</a> and <a href="http://example.com">plain http</a>, in a sentence.</p>
            <ul>
            <li><a href="https://github.com/tropotek/wicket">Wicket on GitHub</a></li>
            <li><a href="https://getbootstrap.com/docs/5.3/">Bootstrap 5.3</a></li>
            </ul>
            <p><a class="btn btn-primary" href="https://github.com/tropotek/wicket">A button to another site</a></p>
            <p>Within the site: <a href="/members-page">Members page</a> and <a href="/">home</a>.</p>
            HTML;
    }

    /**
     * The two ways secrets sit side by side: loose in a paragraph, each as wide as its text, and
     * one to a Bootstrap column, all the same size.
     *
     * @param  list<string>  $secrets
     */
    private function tiles(array $secrets): string
    {
        $columns = array_map(fn (string $secret) => "<div class=\"col-md-4\">{$secret}</div>", $secrets);

        return "<h2>One paragraph</h2>\n<p>".implode('&nbsp;', $secrets)."</p>\n"
            ."<h2>Row of columns</h2>\n<div class=\"row\">\n".implode("\n", $columns)."\n</div>";
    }

    /**
     * Example secrets as the editor embeds them: a full one, a plain login and one with no login.
     *
     * @return list<string>
     */
    private function secrets(User $editor): array
    {
        $secrets = [
            [
                'name' => 'Example Bank',
                'url' => 'https://bank.example.com/login',
                'username' => 'treasurer',
                'password' => 'correct-horse-battery',
                'otp' => 'JBSWY3DPEHPK3PXP',
                'keys' => "recovery-1: 4f8a-22c1-9b07\nrecovery-2: 7d3e-51aa-c6f4",
                'notes' => 'Two signatures are needed for transfers over $500.',
            ],
            [
                'name' => 'Example Mail',
                'url' => 'https://mail.example.com',
                'username' => 'club@example.com',
                'password' => 'staple-orbit-lantern',
            ],
            [
                'name' => 'Shed alarm',
                'url' => null,
                'username' => null,
                'password' => '4821',
                'notes' => 'Keypad is inside the side door. Arm it with the same code.',
            ],
        ];

        return array_map(function (array $attributes) use ($editor) {
            $secret = Secret::factory()->for($editor, 'author')->create($attributes);

            return "<wk-secret data-id=\"{$secret->id}\" contenteditable=\"false\">".e($secret->name).'</wk-secret>';
        }, $secrets);
    }
}
