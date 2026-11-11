<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarnPageTest extends TestCase
{
    use RefreshDatabase;

    private function link(string $userId): string
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/moonetix-vectors.json'), true)['links'][$userId];
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/earn')->assertRedirect('/login');
    }

    public function test_no_link_before_the_user_confirms_16_or_older(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/earn')
            ->assertOk()
            ->assertSee('I am 16 or older')
            ->assertDontSee('wall.moonetix.com');

        $this->actingAs($user)->post('/earn/age', [])->assertSessionHasErrors('age_16_or_older');
        $this->assertNull($user->fresh()->age_confirmed_at);
    }

    public function test_signed_link_for_the_signed_in_user_only(): void
    {
        $user = User::factory()->create(); // id 1
        User::factory()->create(); // id 2

        $this->actingAs($user)->post('/earn/age', ['age_16_or_older' => '1'])->assertRedirect('/earn');

        // A user ID sent by the client is ignored.
        $response = $this->actingAs($user)->get('/earn?user_id=2&user=2')->assertOk();

        $response->assertSee('src="'.e($this->link('1')).'"', false);
        $response->assertDontSee('user_id=2', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));

        $html = $response->getContent();
        $this->assertStringNotContainsString('test_link_salt_not_secret', $html);
        $this->assertStringNotContainsString('test_postback_key_not_secret', $html);
    }

    public function test_balance_and_recent_rewards_are_shown(): void
    {
        $user = User::factory()->create();
        $pb = json_decode(file_get_contents(__DIR__.'/../Fixtures/moonetix-vectors.json'), true)['postbacks'];
        $this->get($pb['credit_150'])->assertOk();
        $this->get($pb['credit_decimal'])->assertOk();

        $this->actingAs($user->fresh())->get('/earn')
            ->assertOk()
            ->assertSee('150.75 coins')
            ->assertSee('Bigo Live &amp; &quot;Friends&quot;', false);
    }

    public function test_demo_sign_in_works(): void
    {
        User::factory()->create(['email' => 'demo@example.com', 'password' => 'right-password']);

        $this->post('/login', ['email' => 'demo@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'demo@example.com', 'password' => 'right-password'])->assertRedirect('/earn');
        $this->assertAuthenticated();
    }
}
