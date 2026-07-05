<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Services\Attachments\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function index(Request $req, AttachmentService $service): JsonResponse
    {
        $data = $req->validate([
            'attachable_type' => ['required', 'string', 'max:80'],
            'attachable_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(['items' => $service->list($data['attachable_type'], (int) $data['attachable_id'])]);
    }

    public function store(Request $req, AttachmentService $service): JsonResponse
    {
        $data = $req->validate([
            'attachable_type' => ['required', 'string', 'max:80'],
            'attachable_id' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $att = $service->upload($req->user(), $data['file'], $data['attachable_type'], (int) $data['attachable_id']);

        return response()->json(['attachment' => $service->row($att), 'message' => '附件已上传'], 201);
    }

    public function download(Attachment $attachment, AttachmentService $service): mixed
    {
        return $service->download($attachment);
    }
}
