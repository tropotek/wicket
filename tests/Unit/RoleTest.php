<?php

namespace Tests\Unit;

use App\Enums\Role;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    public function test_roles_are_ranked(): void
    {
        $this->assertTrue(Role::Admin->atLeast(Role::Editor));
        $this->assertTrue(Role::Editor->atLeast(Role::Editor));
        $this->assertTrue(Role::Member->atLeast(Role::Guest));
        $this->assertFalse(Role::Member->atLeast(Role::Editor));
        $this->assertFalse(Role::Guest->atLeast(Role::Member));
    }
}
