<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Secret;
use App\Models\User;
use App\Policies\SecretPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SecretPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, ?User> */
    private function actors(): array
    {
        return [
            'anon' => null,
            'guest' => User::factory()->guest()->create(),
            'member' => User::factory()->create(),
            'editor' => User::factory()->editor()->create(),
            'admin' => User::factory()->admin()->create(),
        ];
    }

    /** @return array<string, Secret> keyed "visibility/published|unpublished", authored by a stranger */
    private function secrets(): array
    {
        $author = User::factory()->editor()->create();
        $out = [];
        foreach (Secret::visibilities() as $v) {
            foreach ([true, false] as $publish) {
                $out[$v->value.'/'.($publish ? 'published' : 'unpublished')] = Secret::factory()->for($author, 'author')->create(['visibility' => $v, 'publish' => $publish]);
            }
        }

        return $out;
    }

    private function can(?User $user, string $ability, Secret|string $subject): bool
    {
        return Gate::forUser($user)->allows($ability, $subject);
    }

    public function test_policy_is_auto_registered(): void
    {
        $this->assertInstanceOf(SecretPolicy::class, Gate::getPolicyFor(Secret::class));
    }

    public function test_public_is_not_a_secret_visibility(): void
    {
        $this->assertNotContains(Visibility::Public, Secret::visibilities());
    }

    public function test_view_matrix(): void
    {
        $secrets = $this->secrets();
        $expect = [
            'anon' => [],
            'guest' => [],
            'member' => ['members/published'],
            'editor' => ['members/published', 'editors/published'],
            'admin' => array_keys($secrets),
        ];

        foreach ($this->actors() as $label => $user) {
            $allowed = collect($secrets)->filter(fn (Secret $s) => $this->can($user, 'view', $s))->keys()->all();
            $this->assertEqualsCanonicalizing($expect[$label], $allowed, "view as {$label}");

            // The query scope must agree with the policy.
            $listed = Secret::visibleTo($user)->pluck('id')->all();
            $this->assertEqualsCanonicalizing(collect($secrets)->only($allowed)->map->id->values()->all(), $listed, "visibleTo as {$label}");
        }
    }

    public function test_authors_see_and_edit_their_own_whatever_the_setting(): void
    {
        $author = User::factory()->editor()->create();
        $secret = Secret::factory()->for($author, 'author')->create(['visibility' => Visibility::Private, 'publish' => false]);

        $this->assertTrue($this->can($author, 'view', $secret));
        $this->assertTrue($this->can($author, 'update', $secret));
        $this->assertTrue($this->can($author, 'delete', $secret));
        $this->assertSame([$secret->id], Secret::visibleTo($author)->pluck('id')->all());
    }

    public function test_a_demoted_author_can_view_but_not_edit(): void
    {
        $author = User::factory()->create(); // member
        $secret = Secret::factory()->for($author, 'author')->create();

        $this->assertTrue($this->can($author, 'view', $secret));
        $this->assertFalse($this->can($author, 'update', $secret));
    }

    public function test_create(): void
    {
        $expect = ['anon' => false, 'guest' => false, 'member' => false, 'editor' => true, 'admin' => true];

        foreach ($this->actors() as $label => $user) {
            $this->assertSame($expect[$label], $this->can($user, 'create', Secret::class), $label);
        }
    }

    public function test_update_and_delete_matrix(): void
    {
        $secrets = $this->secrets();
        $shared = ['members/published', 'members/unpublished', 'editors/published', 'editors/unpublished'];
        $expect = [
            'anon' => [],
            'guest' => [],
            'member' => [],
            'editor' => $shared,
            'admin' => array_keys($secrets),
        ];

        foreach ($this->actors() as $label => $user) {
            foreach (['update', 'delete'] as $ability) {
                $allowed = collect($secrets)->filter(fn (Secret $s) => $this->can($user, $ability, $s))->keys()->all();
                $this->assertEqualsCanonicalizing($expect[$label], $allowed, "{$ability} as {$label}");
            }
        }
    }
}
