<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Page;
use App\Models\User;
use App\Policies\PagePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PagePolicyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, ?User> actor kinds keyed by label; null = logged out */
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

    private function can(?User $user, string $ability, Page|string $subject): bool
    {
        return Gate::forUser($user)->allows($ability, $subject);
    }

    /** Pages authored by someone other than the actors. */
    private function pages(): array
    {
        $author = User::factory()->editor()->create();

        return collect(Visibility::cases())
            ->mapWithKeys(fn (Visibility $v) => [$v->value => Page::factory()->for($author, 'author')->create(['visibility' => $v])])
            ->all();
    }

    public function test_policy_is_auto_registered(): void
    {
        $this->assertInstanceOf(PagePolicy::class, Gate::getPolicyFor(Page::class));
    }

    public function test_new_pages_default_to_private(): void
    {
        $page = Page::create(['title' => 'T', 'slug' => 't']);

        $this->assertSame(Visibility::Private, $page->visibility);
        $this->assertSame(Visibility::Private, $page->fresh()->visibility);
    }

    public function test_view_matrix_for_non_authors(): void
    {
        $expected = [
            //          public members editors private
            'anon' => [true,  false,  false,  false],
            'guest' => [true,  false,  false,  false],
            'member' => [true,  true,   false,  false],
            'editor' => [true,  true,   true,   false],
            'admin' => [true,  true,   true,   true],
        ];

        $pages = $this->pages();

        foreach ($this->actors() as $label => $user) {
            foreach (array_values($pages) as $i => $page) {
                $this->assertSame(
                    $expected[$label][$i],
                    $this->can($user, 'view', $page),
                    "{$label} viewing {$page->visibility->value}",
                );
            }
        }
    }

    public function test_authors_always_view_their_own_pages(): void
    {
        $authors = [
            User::factory()->guest()->create(),
            User::factory()->create(),
            User::factory()->editor()->create(),
        ];

        foreach ($authors as $author) {
            $page = Page::factory()->for($author, 'author')->create(['visibility' => Visibility::Private]);

            $this->assertTrue($this->can($author, 'view', $page), $author->role->value);
        }
    }

    public function test_create_matrix(): void
    {
        $expected = ['anon' => false, 'guest' => false, 'member' => false, 'editor' => true, 'admin' => true];

        foreach ($this->actors() as $label => $user) {
            $this->assertSame($expected[$label], $this->can($user, 'create', Page::class), $label);
        }
    }

    public function test_update_matrix_for_non_authors(): void
    {
        $expected = [
            //          public members editors private
            'anon' => [false, false,  false,  false],
            'guest' => [false, false,  false,  false],
            'member' => [false, false,  false,  false],
            'editor' => [true,  true,   true,   false],
            'admin' => [true,  true,   true,   true],
        ];

        $pages = $this->pages();

        foreach ($this->actors() as $label => $user) {
            foreach (array_values($pages) as $i => $page) {
                $this->assertSame(
                    $expected[$label][$i],
                    $this->can($user, 'update', $page),
                    "{$label} updating {$page->visibility->value}",
                );
            }
        }
    }

    public function test_editor_can_update_own_private_page_but_member_author_cannot(): void
    {
        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();

        $this->assertTrue($this->can($editor, 'update', Page::factory()->for($editor, 'author')->create()));
        $this->assertFalse($this->can($member, 'update', Page::factory()->for($member, 'author')->create()));
    }

    public function test_delete_matrix(): void
    {
        $editor = User::factory()->editor()->create();
        $own = Page::factory()->for($editor, 'author')->create(['visibility' => Visibility::Public]);
        $others = Page::factory()->create(['visibility' => Visibility::Public]);

        $this->assertTrue($this->can($editor, 'delete', $own));
        $this->assertFalse($this->can($editor, 'delete', $others));
        $this->assertTrue($this->can(User::factory()->admin()->create(), 'delete', $others));
        $this->assertFalse($this->can(User::factory()->create(), 'delete', $others));
        $this->assertFalse($this->can(null, 'delete', $others));
    }

    public function test_visible_to_scope_agrees_with_policy(): void
    {
        $this->pages();
        $actors = $this->actors();
        foreach ($actors as $user) {
            if ($user) {
                Page::factory()->for($user, 'author')->create(['visibility' => Visibility::Private]);
            }
        }

        $all = Page::all();

        foreach ($actors as $label => $user) {
            $expected = $all->filter(fn (Page $p) => $this->can($user, 'view', $p))->pluck('id')->sort()->values();
            $actual = Page::visibleTo($user)->pluck('id')->sort()->values();

            $this->assertEquals($expected, $actual, $label);
        }
    }

    public function test_restore_and_force_delete_are_admin_only(): void
    {
        $page = Page::factory()->create();
        $author = $page->author;

        foreach (['restore', 'forceDelete'] as $ability) {
            $this->assertTrue($this->can(User::factory()->admin()->create(), $ability, $page), $ability);
            $this->assertFalse($this->can($author, $ability, $page), $ability);
            $this->assertFalse($this->can(User::factory()->editor()->create(), $ability, $page), $ability);
        }
    }
}
