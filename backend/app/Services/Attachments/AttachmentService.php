<?php

namespace App\Services\Attachments;

use App\Support\ChinaTime;
use App\Models\Admin;
use App\Models\Attachment;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AttachmentService
{
    private const MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    private const MAX_IMAGE_SIZE = 2 * 1024 * 1024;

    public function __construct(private readonly AuditLogService $audit) {}

    public function upload(Admin $admin, UploadedFile $file, string $type, int $id): Attachment
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::MIMES, true)) {
            abort(422, '只允许上传图片或 PDF');
        }
        if (str_starts_with($mime, 'image/') && $file->getSize() > self::MAX_IMAGE_SIZE) {
            abort(422, '图片不能超过 2MB');
        }

        $path = $file->store('attachments/'.now('Asia/Shanghai')->format('Ym'), 'local');
        $att = Attachment::query()->create([
            'attachable_type' => $type,
            'attachable_id' => $id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'created_by' => $admin->id,
        ]);

        $this->audit->record($admin, 'attachment.upload', 'attachment', $att->id, null, $this->row($att));

        return $att;
    }

    public function list(string $type, int $id): array
    {
        return Attachment::query()
            ->where('attachable_type', $type)
            ->where('attachable_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Attachment $att): array => $this->row($att))
            ->all();
    }

    public function download(Attachment $att): mixed
    {
        if (! Storage::disk($att->disk)->exists($att->path)) {
            abort(404, '附件不存在');
        }

        return Storage::disk($att->disk)->download($att->path, $att->original_name);
    }

    public function row(Attachment $att): array
    {
        return [
            'id' => $att->id,
            'attachable_type' => $att->attachable_type,
            'attachable_id' => $att->attachable_id,
            'original_name' => $att->original_name,
            'mime' => $att->mime,
            'size' => $att->size,
            'download_url' => "/api/v1/attachments/{$att->id}/download",
            'created_at' => ChinaTime::fmt($att->created_at),
        ];
    }
}
