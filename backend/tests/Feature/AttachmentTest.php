<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_and_download_private_attachment(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/demo.txt', 'demo');
        $seed = Attachment::query()->create([
            'attachable_type' => 'operation_expense',
            'attachable_id' => 1,
            'disk' => 'local',
            'path' => 'attachments/demo.txt',
            'original_name' => 'demo.txt',
            'mime' => 'text/plain',
            'size' => 4,
        ]);
        $this->getJson("/api/v1/attachments/{$seed->id}/download")->assertUnauthorized();

        $admin = $this->admin();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $res = $this->withToken($token)->postJson('/api/v1/attachments', [
            'attachable_type' => 'operation_expense',
            'attachable_id' => 1,
            'file' => UploadedFile::fake()->image('proof.png'),
        ]);

        $res->assertCreated()->assertJsonPath('attachment.original_name', 'proof.png');
        $att = Attachment::query()->latest('id')->firstOrFail();
        Storage::disk('local')->assertExists($att->path);

        $this->withToken($token)->get("/api/v1/attachments/{$att->id}/download")->assertOk();
    }

    public function test_executable_file_is_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/attachments', [
                'attachable_type' => 'operation_expense',
                'attachable_id' => 1,
                'file' => UploadedFile::fake()->create('run.exe', 4, 'application/x-msdownload'),
            ])->assertStatus(422);
    }

    public function test_image_larger_than_two_megabytes_is_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->withToken($admin->createToken('admin-token')->plainTextToken)
            ->postJson('/api/v1/attachments', [
                'attachable_type' => 'operation_expense',
                'attachable_id' => 1,
                'file' => UploadedFile::fake()->image('large.png')->size(2049),
            ])->assertStatus(422)
            ->assertJsonPath('message', '图片不能超过 2MB');
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'status' => Admin::STATUS_ACTIVE,
        ]);
    }
}
