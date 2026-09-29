<?php

namespace Tests\Feature\Auth;

use App\Jobs\Notification\User\SendUserOtpJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MobileOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeLivewire('pages::auth.login');
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_invalid_mobile_is_rejected(): void
    {
        Queue::fake();

        Livewire::test('pages::auth.login')
            ->set('mobile', '08123456789')
            ->call('sendCode')
            ->assertHasErrors(['mobile']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_send_code_creates_user_and_dispatches_otp_job(): void
    {
        Queue::fake();

        Livewire::test('pages::auth.login')
            ->set('mobile', '09123456789')
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertSet('step', 'otp');

        $user = User::query()->where('mobile', '09123456789')->first();

        $this->assertNotNull($user);
        $this->assertDatabaseCount('one_time_passwords', 1);

        Queue::assertPushed(SendUserOtpJob::class, function (SendUserOtpJob $job) use ($user): bool {
            return $job->user->is($user) && strlen($job->code) === 6;
        });
    }

    public function test_resend_is_blocked_while_otp_is_active(): void
    {
        Queue::fake();

        $component = Livewire::test('pages::auth.login')
            ->set('mobile', '09123456789')
            ->call('sendCode')
            ->assertSet('step', 'otp');

        Queue::assertPushed(SendUserOtpJob::class, 1);

        $component->call('resendCode')
            ->assertHasErrors(['code']);

        Queue::assertPushed(SendUserOtpJob::class, 1);
        $this->assertDatabaseCount('one_time_passwords', 1);
    }

    public function test_valid_otp_logs_user_in_and_redirects_to_dashboard(): void
    {
        Queue::fake();

        $component = Livewire::test('pages::auth.login')
            ->set('mobile', '09123456789')
            ->call('sendCode');

        $user = User::query()->where('mobile', '09123456789')->firstOrFail();
        $code = $user->oneTimePasswords()->firstOrFail()->password;

        $component
            ->set('code', $code)
            ->call('verify')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_otp_does_not_log_user_in(): void
    {
        Queue::fake();

        Livewire::test('pages::auth.login')
            ->set('mobile', '09123456789')
            ->call('sendCode')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code']);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire('pages::dashboard.index');
    }

    public function test_otp_step_displays_masked_mobile_number(): void
    {
        Queue::fake();

        Livewire::test('pages::auth.login')
            ->set('mobile', '09177886099')
            ->call('sendCode')
            ->assertSet('step', 'otp')
            ->assertSee('0917***6099')
            ->assertDontSee('09177886099');
    }

    public function test_back_to_mobile_resets_otp_state(): void
    {
        Queue::fake();

        Livewire::test('pages::auth.login')
            ->set('mobile', '09123456789')
            ->call('sendCode')
            ->assertSet('step', 'otp')
            ->set('code', '123456')
            ->call('backToMobile')
            ->assertSet('step', 'mobile')
            ->assertSet('code', '')
            ->assertSet('resendAvailableAt', null)
            ->assertSet('debugOtpCode', null);
    }

    public function test_dev_otp_helper_populates_debug_code_in_local_environment(): void
    {
        Queue::fake();

        $component = Livewire::test('pages::auth.login')
            ->set('mobile', '09123456789')
            ->call('sendCode');

        $user = User::query()->where('mobile', '09123456789')->firstOrFail();
        $code = $user->oneTimePasswords()->firstOrFail()->password;

        $component
            ->assertSet('debugOtpCode', $code)
            ->assertSee($code);
    }
}
