<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz redirige al login (la app no tiene página pública).
     */
    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
