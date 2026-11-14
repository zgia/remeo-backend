<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginCaptchaTest extends TestCase
{
    public function test_image_is_generated_without_exposing_the_answer_or_key(): void
    {
        $response = $this->getJson('/signin/captcha')->assertOk()->assertJsonPath('code', 0);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $response->json('data.img'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertJsonMissingPath('data.key');
        $this->assertTrue(session()->has('captcha.key'));
    }

    public function test_missing_wrong_and_malformed_captcha_are_rejected_before_authentication(): void
    {
        foreach ([null, '', 'wrong', ['aaaa'], str_repeat('a', 11)] as $captcha) {
            $this->issueLoginCaptcha();
            $this->login($captcha)->assertStatus(422)->assertJsonValidationErrors('captcha', 'data.errors');
        }
    }

    public function test_valid_captcha_is_case_insensitive_and_consumed_even_when_password_is_wrong(): void
    {
        $this->issueLoginCaptcha();
        $this->login('AAAA')->assertStatus(401);
        $this->login('aaaa')->assertStatus(422)->assertJsonValidationErrors('captcha', 'data.errors');
    }

    public function test_wrong_answer_consumes_the_captcha(): void
    {
        $this->issueLoginCaptcha();
        $this->login('wrong')->assertStatus(422);
        $this->login('aaaa')->assertStatus(422)->assertJsonValidationErrors('captcha', 'data.errors');
    }

    public function test_expired_captcha_is_rejected(): void
    {
        $this->issueLoginCaptcha();
        $this->travel(61)->seconds();
        $this->login('aaaa')->assertStatus(422)->assertJsonValidationErrors('captcha', 'data.errors');
    }

    public function test_refresh_replaces_the_previous_captcha(): void
    {
        $this->issueLoginCaptcha();
        config(['captcha.characters' => ['b']]);
        $this->getJson('/signin/captcha')->assertOk();
        $this->login('aaaa')->assertStatus(422)->assertJsonValidationErrors('captcha', 'data.errors');
    }

    public function test_captcha_requires_the_issuing_session(): void
    {
        $this->issueLoginCaptcha();
        session()->flush();
        $this->login('aaaa')->assertStatus(422)->assertJsonValidationErrors('captcha', 'data.errors');
    }

    public function test_refresh_does_not_consume_the_login_rate_limit(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->issueLoginCaptcha();
        }

        $this->login('aaaa')->assertStatus(401);
    }

    private function login(mixed $captcha): TestResponse
    {
        return $this->postJson('/signin/dologin', [
            'mobile' => '13800000001', 'password' => 'wrong', 'captcha' => $captcha,
        ]);
    }
}
