<?php

namespace Tests\Feature;

use App\Facades\Settings;
use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'mail.from.address' => 'wiki@example.org']);
        Mail::fake();
    }

    private function form(array $overrides = [], int $secondsAgo = 10): array
    {
        return $overrides + [
            'name' => 'Sam Visitor',
            'email' => 'sam@example.net',
            'phone' => '0400 000 000',
            'message' => 'Hello there, the printer page is out of date.',
            'website' => '',
            'stamp' => Crypt::encryptString((string) (now()->timestamp - $secondsAgo)),
        ];
    }

    public function test_form_renders_with_footer_link(): void
    {
        $this->get('/contact')->assertOk()->assertSee('name="website"', false)->assertSee('name="stamp"', false);
        $this->get('/login')->assertSee(route('contact'));
    }

    public function test_message_goes_to_the_site_with_reply_to_the_visitor(): void
    {
        $this->post('/contact', $this->form())->assertRedirect(route('contact'))->assertSessionHas('status');

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            return $mail->hasTo('wiki@example.org')
                && ! $mail->hasTo('sam@example.net')
                && $mail->hasReplyTo('sam@example.net')
                && str_contains($mail->render(), 'printer page is out of date');
        });
    }

    public function test_validation(): void
    {
        $this->post('/contact', $this->form(['name' => '', 'email' => 'nope', 'message' => '']))
            ->assertSessionHasErrors(['name', 'email', 'message']);

        Mail::assertNothingSent();
    }

    public function test_bots_look_successful_but_send_nothing(): void
    {
        $this->post('/contact', $this->form(['website' => 'http://spam.example']))->assertSessionHas('status');
        $this->post('/contact', $this->form([], secondsAgo: 1))->assertSessionHas('status');
        $this->post('/contact', $this->form([], secondsAgo: 9000))->assertSessionHas('status');
        $this->post('/contact', $this->form(['stamp' => 'forged']))->assertSessionHas('status');

        Mail::assertNothingSent();
    }

    public function test_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/contact', $this->form(['email' => "sam{$i}@example.net"]))->assertSessionHasNoErrors();
        }
        $this->post('/contact', $this->form(['email' => 'other@example.net']))->assertSessionHasErrors('message');

        Mail::assertSentCount(5);
    }

    public function test_subject_cannot_carry_extra_lines(): void
    {
        $this->post('/contact', $this->form(['name' => "Sam\r\nBcc: victim@example.com"]));

        Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail) => ! str_contains($mail->envelope()->subject, "\n"));
    }

    public function test_switch_off(): void
    {
        Settings::set(['wiki.contact' => false]);

        $this->get('/contact')->assertNotFound();
        $this->post('/contact', $this->form())->assertNotFound();
        $this->get('/login')->assertDontSee(route('contact'));
    }
}
