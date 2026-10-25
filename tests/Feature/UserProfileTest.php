<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_with_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $business = Business::factory()->for($user, 'owner')->create();
        $user->forceFill(['current_business_id' => $business->id])->save();

        Livewire::actingAs($user)
            ->test('pages::panel.user.dashboard.index')
            ->set('form.first_name', 'علی')
            ->set('form.last_name', 'رضایی')
            ->set('form.email', 'ali@example.com')
            ->set('form.gender', Gender::Male->value)
            ->set('form.birth_date', '1990-05-15')
            ->set('form.avatar', UploadedFile::fake()->image('avatar.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('علی', $user->first_name);
        $this->assertSame('رضایی', $user->last_name);
        $this->assertSame('ali@example.com', $user->email);
        $this->assertSame(Gender::Male, $user->gender);
        $this->assertSame('1990-05-15', $user->birth_date?->format('Y-m-d'));
        $this->assertNotNull($user->avatarUrl());
        $this->assertSame('علی رضایی', $user->fullName());
    }
}
