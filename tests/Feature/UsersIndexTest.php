<?php

namespace Tests\Feature;

use App\Ai\Agents\UserAssistant;
use App\Ai\Tools\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;
use Tests\TestCase;

class UsersIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_users_page_with_list(): void
    {
        $viewer = User::factory()->create([
            'mobile' => '09121111111',
        ]);
        $listed = User::factory()->create([
            'mobile' => '09171234567',
        ]);

        $this->actingAs($viewer)
            ->get(route('system.users.index'))
            ->assertOk()
            ->assertSee(__('general.users'))
            ->assertSee(__('general.ai_prompt'))
            ->assertSee($listed->mobile)
            ->assertDontSee(__('general.users_page_placeholder'));
    }

    public function test_users_list_can_be_searched_by_mobile(): void
    {
        $viewer = User::factory()->create([
            'mobile' => '09121111111',
        ]);
        $match = User::factory()->create([
            'mobile' => '09171234567',
        ]);
        $other = User::factory()->create([
            'mobile' => '09351234567',
        ]);

        Livewire::actingAs($viewer)
            ->test('pages::user.index')
            ->set('search', '0917')
            ->assertSee($match->mobile)
            ->assertDontSee($other->mobile);
    }

    public function test_empty_prompt_is_rejected(): void
    {
        $viewer = User::factory()->create();

        Livewire::actingAs($viewer)
            ->test('pages::user.index')
            ->set('prompt', '')
            ->call('sendPrompt')
            ->assertHasErrors(['prompt']);
    }

    public function test_send_prompt_uses_user_assistant_agent(): void
    {
        UserAssistant::fake([
            __('general.user_created', ['mobile' => '09171234567']),
        ]);

        $viewer = User::factory()->create([
            'mobile' => '09121111111',
        ]);

        Livewire::actingAs($viewer)
            ->test('pages::user.index')
            ->set('prompt', 'یک کاربر با شماره موبایل 09171234567 اضافه کن')
            ->call('sendPrompt')
            ->assertHasNoErrors()
            ->assertSet('assistantReply', __('general.user_created', ['mobile' => '09171234567']))
            ->assertSet('prompt', '');

        UserAssistant::assertPrompted('یک کاربر با شماره موبایل 09171234567 اضافه کن');
    }

    public function test_create_user_tool_creates_user_by_mobile(): void
    {
        $result = (string) (new CreateUser)->handle(new Request([
            'mobile' => '09171234567',
        ]));

        $this->assertSame(
            __('general.user_created', ['mobile' => '09171234567']),
            $result,
        );
        $this->assertDatabaseHas('users', [
            'mobile' => '09171234567',
        ]);
    }
}
