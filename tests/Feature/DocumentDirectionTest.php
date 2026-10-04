<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

class DocumentDirectionTest extends TestCase
{
    public function test_login_page_uses_rtl_when_locale_is_persian(): void
    {
        App::setLocale('fa');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    public function test_login_page_uses_ltr_when_locale_is_english(): void
    {
        App::setLocale('en');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('dir="ltr"', false);
    }
}
