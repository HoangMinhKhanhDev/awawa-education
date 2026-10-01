<?php

namespace Tests\Feature;

use App\Livewire\Info;
use App\Livewire\Profile\Show as ProfileShow;
use App\Models\Subject;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_profile(): void
    {
        $subject = Subject::factory()->create();
        $student = User::factory()->student($subject)->create(['name' => 'Tên cũ']);

        $this->actingAs($student);

        Livewire::test(ProfileShow::class)
            ->set('name', 'Tên mới')
            ->set('phone', '0912345678')
            ->set('className', '11A1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Tên mới', $student->fresh()->name);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'phone' => '0912345678',
            'class_name' => '11A1',
        ]);
    }

    public function test_profile_page_renders(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('profile'))->assertOk();
    }

    public function test_admin_can_open_profile_page(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('profile'))->assertOk();
    }

    public function test_selecting_avatar_saves_immediately_without_pressing_save(): void
    {
        Storage::fake('public');

        $student = User::factory()->student()->create();

        $this->actingAs($student);

        Livewire::test(ProfileShow::class)
            ->set('avatar', UploadedFile::fake()->create('anh.jpg', 200, 'image/jpeg'))
            ->assertHasNoErrors();

        $path = $student->fresh()->avatar;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_avatar_deletes_the_old_file(): void
    {
        Storage::fake('public');

        $student = User::factory()->student()->create();

        $this->actingAs($student);

        $component = Livewire::test(ProfileShow::class)
            ->set('avatar', UploadedFile::fake()->create('cu.jpg', 200, 'image/jpeg'));

        $old = $student->fresh()->avatar;
        Storage::disk('public')->assertExists($old);

        $component->set('avatar', UploadedFile::fake()->create('moi.jpg', 200, 'image/jpeg'));

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($student->fresh()->avatar);
    }

    public function test_avatar_rejects_oversized_files(): void
    {
        Storage::fake('public');

        $student = User::factory()->student()->create();

        $this->actingAs($student);

        // Livewire chặn file không xem trước được ngay lúc tải lên, nên chỉ còn
        // trường hợp quá 5MB lọt tới validate.
        Livewire::test(ProfileShow::class)
            ->set('avatar', UploadedFile::fake()->create('nang.jpg', 6 * 1024, 'image/jpeg'))
            ->assertHasErrors(['avatar']);

        $this->assertNull($student->fresh()->avatar);
    }

    public function test_avatar_is_changed_by_clicking_the_photo_itself(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student);

        Livewire::test(ProfileShow::class)
            ->assertSee('Bấm vào ảnh để đổi', escape: false)
            ->assertSee('JPG, PNG, WebP — tối đa 5MB', escape: false);
    }

    public function test_student_can_save_a_personal_note(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student);

        Livewire::test(ProfileShow::class)
            ->set('note', 'Mục tiêu 9+ Toán')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Mục tiêu 9+ Toán', $student->fresh()->note);
    }

    public function test_personal_note_shows_next_to_the_student_on_the_leaderboard(): void
    {
        $subject = Subject::factory()->create();
        $student = User::factory()->student($subject)->create(['note' => 'Đang ôn bất đẳng thức']);

        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $viewer = User::factory()->student($subject)->create();

        TeamMembership::query()->create([
            'subject_id' => $subject->id,
            'student_id' => $viewer->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer);
        app(SubjectContext::class)->set($subject->id);

        Livewire::test(Info::class)
            ->assertSee('Đang ôn bất đẳng thức');
    }

    public function test_teacher_can_pick_an_accent_color(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();
        $this->actingAs($teacher);

        Livewire::test(ProfileShow::class)
            ->call('saveAccent', 'violet')
            ->assertHasNoErrors();

        $this->assertSame('violet', $teacher->fresh()->accent);
    }

    public function test_teacher_can_pick_a_custom_hex_color(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();
        $this->actingAs($teacher);

        Livewire::test(ProfileShow::class)
            ->call('saveAccent', '#A3B59A')
            ->assertHasNoErrors();

        $this->assertSame('#a3b59a', $teacher->fresh()->accent);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('--color-brand-500: #a3b59a', $html);
        $this->assertStringContainsString('--color-brand-600:', $html);
    }

    public function test_teacher_can_reset_accent_to_default(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create(['accent' => 'rose']);
        $this->actingAs($teacher);

        Livewire::test(ProfileShow::class)
            ->call('saveAccent', '')
            ->assertHasNoErrors();

        $this->assertNull($teacher->fresh()->accent);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('--color-brand-600:', $html);
    }

    public function test_accent_color_rejects_unknown_values(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();
        $this->actingAs($teacher);

        Livewire::test(ProfileShow::class)
            ->call('saveAccent', 'hong-neon')
            ->assertHasErrors('accent');

        $this->assertNull($teacher->fresh()->accent);
    }

    public function test_layout_emits_brand_overrides_for_custom_accent(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create(['accent' => 'emerald']);
        $this->actingAs($teacher);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('--color-brand-600: #059669', $html);
        // Ghi đè nằm thẳng trên thẻ <html> để tồn tại xuyên suốt wire:navigate.
        $this->assertStringContainsString('<html lang="vi" class="h-full" style="--color-brand-', $html);
    }

    public function test_layout_emits_no_override_for_default_accent(): void
    {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher($subject)->create();
        $this->actingAs($teacher);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('--color-brand-600:', $html);
    }
}
