<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_header_logo_link_is_well_formed(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();

        $html = $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->getContent();

        // Hồi quy: từng mất dấu `>` ở thẻ <a> logo mobile khiến trình duyệt
        // phục hồi DOM sai (adoption agency), content bị đẩy sang phải và
        // Alpine báo `drawer is not defined` ở mọi trang.
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*" wire:navigate\.hover class="flex items-center">/',
            $html,
            'Link logo mobile phải là thẻ <a> đóng đúng.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<a\b[^>]*</',
            $html,
            'Không được có thẻ <a> nào nuốt markup khác (thiếu dấu đóng >).',
        );
    }

    public function test_layout_has_no_unclosed_anchor_before_logo(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();

        $html = $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->getContent();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $errors = array_filter(
            libxml_get_errors(),
            fn ($e): bool => $e->level === LIBXML_ERR_FATAL,
        );
        libxml_clear_errors();

        $this->assertCount(0, $errors, 'HTML layout có lỗi fatal.');
    }
}
