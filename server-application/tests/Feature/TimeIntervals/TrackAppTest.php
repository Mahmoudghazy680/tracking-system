<?php

namespace Tests\Feature\TimeIntervals;

use App\Models\User;
use Tests\Facades\UserFactory;
use Tests\TestCase;

class TrackAppTest extends TestCase
{
    private const URI = 'time-intervals/app';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserFactory::refresh()->asUser()->withTokens()->create();
    }

    public function test_track_app_sanitizes_arabic_and_bidi_marks(): void
    {
        $payload = [
            'title' => "\u{200F}اختبار\u{202E} عنوان",
            'executable' => "\u{200E}chrome\u{2069}.exe",
            'url' => "https://\u{200F}example.com/\u{202E}watch",
        ];

        $response = $this->actingAs($this->user)->putJson(self::URI, $payload);

        $response->assertOk();

        $this->assertDatabaseHas('tracked_applications', [
            'user_id' => $this->user->id,
            'title' => 'اختبار عنوان',
            'executable' => 'chrome.exe',
            'url' => 'https://example.com/watch',
        ]);
    }

    public function test_track_app_falls_back_to_title_if_executable_empty_after_sanitization(): void
    {
        $payload = [
            'title' => "\u{200F}اختبار التطبيق\u{2069}",
            'executable' => "\u{200E}\u{200F}\u{202E}",
        ];

        $response = $this->actingAs($this->user)->putJson(self::URI, $payload);

        $response->assertOk();

        $this->assertDatabaseHas('tracked_applications', [
            'user_id' => $this->user->id,
            'title' => 'اختبار التطبيق',
            'executable' => 'اختبار التطبيق',
            'url' => null,
        ]);
    }

    public function test_track_app_unauthorized(): void
    {
        $response = $this->putJson(self::URI, [
            'title' => 'اختبار',
            'executable' => 'chrome.exe',
        ]);

        $response->assertUnauthorized();
    }
}