<?php

namespace Tests\Feature;

use App\Ai\Agents\UserAssistant;
use App\Ai\Tools\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Transcription;
use Livewire\Livewire;
use Tests\Concerns\GrantsAdministratorAccess;
use Tests\TestCase;

class UsersIndexTest extends TestCase
{
    use GrantsAdministratorAccess;
    use RefreshDatabase;

    public function test_authenticated_user_can_view_users_page_with_list(): void
    {
        $viewer = $this->grantAdministratorAccess(User::factory()->create([
            'mobile' => '09121111111',
        ]));
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
        $viewer = $this->grantAdministratorAccess(User::factory()->create([
            'mobile' => '09121111111',
        ]));
        $match = User::factory()->create([
            'mobile' => '09171234567',
        ]);
        $other = User::factory()->create([
            'mobile' => '09351234567',
        ]);

        Livewire::actingAs($viewer)
            ->test('pages::panel.administrator.user-management.user.index')
            ->set('search', '0917')
            ->assertSee($match->mobile)
            ->assertDontSee($other->mobile);
    }

    public function test_empty_prompt_is_rejected(): void
    {
        $viewer = $this->grantAdministratorAccess(User::factory()->create());

        Livewire::actingAs($viewer)
            ->test('pages::panel.administrator.user-management.user.index')
            ->set('prompt', '')
            ->call('sendPrompt')
            ->assertHasErrors(['prompt']);
    }

    public function test_send_prompt_uses_user_assistant_agent(): void
    {
        UserAssistant::fake([
            __('general.user_created', ['mobile' => '09171234567']),
        ]);

        $viewer = $this->grantAdministratorAccess(User::factory()->create([
            'mobile' => '09121111111',
        ]));

        Livewire::actingAs($viewer)
            ->test('pages::panel.administrator.user-management.user.index')
            ->set('prompt', 'یک کاربر با شماره موبایل 09171234567 اضافه کن')
            ->call('sendPrompt')
            ->assertHasNoErrors()
            ->assertSet('assistantReply', __('general.user_created', ['mobile' => '09171234567']))
            ->assertSet('prompt', '');

        UserAssistant::assertPrompted('یک کاربر با شماره موبایل 09171234567 اضافه کن');
    }

    public function test_audio_upload_transcribes_into_prompt_without_running_agent(): void
    {
        Transcription::fake([
            'یک کاربر با شماره موبایل 09171234567 اضافه کن',
        ]);

        UserAssistant::fake([
            __('general.user_created', ['mobile' => '09171234567']),
        ]);

        $viewer = $this->grantAdministratorAccess(User::factory()->create([
            'mobile' => '09121111111',
        ]));

        $audio = UploadedFile::fake()->createWithContent(
            'voice.webm',
            str_repeat('audio', 256),
            'application/octet-stream',
        );

        Livewire::actingAs($viewer)
            ->test('pages::panel.administrator.user-management.user.index')
            ->set('audio', $audio)
            ->assertHasNoErrors()
            ->assertSet('prompt', 'یک کاربر با شماره موبایل 09171234567 اضافه کن')
            ->assertSet('audio', null)
            ->assertSet('assistantReply', '')
            ->assertSet('isTranscribing', false);

        Transcription::assertGenerated(fn () => true);
        UserAssistant::assertNeverPrompted();
    }

    public function test_recorded_m4a_transcribes_then_send_runs_agent(): void
    {
        Transcription::fake([
            'یک کاربر با شماره موبایل 09171234567 اضافه کن',
        ]);

        UserAssistant::fake([
            __('general.user_created', ['mobile' => '09171234567']),
        ]);

        $viewer = $this->grantAdministratorAccess(User::factory()->create([
            'mobile' => '09121111111',
        ]));

        $audio = UploadedFile::fake()->createWithContent(
            'voice.m4a',
            str_repeat('audio', 256),
            'audio/mp4',
        );

        Livewire::actingAs($viewer)
            ->test('pages::panel.administrator.user-management.user.index')
            ->set('audio', $audio)
            ->assertHasNoErrors()
            ->assertSet('prompt', 'یک کاربر با شماره موبایل 09171234567 اضافه کن')
            ->assertSet('audio', null)
            ->call('sendPrompt')
            ->assertHasNoErrors()
            ->assertSet('assistantReply', __('general.user_created', ['mobile' => '09171234567']))
            ->assertSet('prompt', '');

        UserAssistant::assertPrompted('یک کاربر با شماره موبایل 09171234567 اضافه کن');
        Transcription::assertGenerated(fn () => true);
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
