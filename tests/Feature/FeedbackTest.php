<?php

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Models\FeedbackSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_and_service_policies_are_public(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('FAQ and service policies')
            ->assertSee('No backups or recovery promise')
            ->assertSee('No feature roadmap promise');
    }

    public function test_feedback_requires_an_authenticated_user(): void
    {
        $this->get(route('feedback.index'))->assertRedirect(route('login'));
        $this->post(route('feedback.store'))->assertRedirect(route('login'));
    }

    public function test_a_user_can_submit_feedback_and_only_view_their_own_submissions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        FeedbackSubmission::query()->create([
            'user_id' => $other->id,
            'type' => 'general',
            'subject' => 'Private feedback from someone else',
            'message' => 'This should not appear for the signed-in user.',
            'status' => FeedbackStatus::New,
        ]);

        $this->actingAs($user)->post(route('feedback.store'), [
            'type' => 'suggestion',
            'subject' => 'Make expiry clearer',
            'message' => 'Please show the remaining access time on every project page.',
            'status' => 'closed',
            'user_id' => $other->id,
        ])->assertRedirect(route('feedback.index'));

        $submission = FeedbackSubmission::query()->whereBelongsTo($user)->sole();
        $this->assertSame(FeedbackStatus::New, $submission->status);

        $this->actingAs($user)->get(route('feedback.index'))
            ->assertOk()
            ->assertSee('Make expiry clearer')
            ->assertDontSee('Private feedback from someone else');
    }

    public function test_feedback_is_validated_and_available_to_administrators(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('feedback.store'), [
            'type' => 'unknown',
            'subject' => '',
            'message' => 'short',
        ])->assertSessionHasErrors(['type', 'subject', 'message']);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get('/admin/feedback-submissions')
            ->assertOk()
            ->assertSee('User feedback');
    }
}
