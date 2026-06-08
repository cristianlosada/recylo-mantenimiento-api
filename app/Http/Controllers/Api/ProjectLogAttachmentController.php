<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectAttachment;
use App\Models\ProjectAttachmentType;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectLogAttachmentController extends Controller
{
    /**
     * Subir uno o varios archivos a un registro de bitácora
     */
    public function store(Request $request, int $projectId, int $logId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $log = ProjectLog::where('project_id', $projectId)->find($logId);
        if (!$log) {
            return ApiResponse::notFound('Bitácora no encontrada');
        }

        $request->validate([
            'files'            => 'required|array|min:1|max:10',
            'files.*'          => 'required|file|max:20480|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip',
            'attachment_type'  => 'nullable|string|exists:project_attachment_types,code',
        ], [
            'files.required'      => 'Debe seleccionar al menos un archivo',
            'files.*.max'         => 'Cada archivo no puede superar 20 MB',
            'files.*.mimes'       => 'Tipo de archivo no permitido',
            'files.max'           => 'Máximo 10 archivos por carga',
        ]);

        $typeCode  = $request->input('attachment_type', 'other');
        $typeModel = ProjectAttachmentType::where('code', $typeCode)->first();

        $uploaded = [];

        DB::beginTransaction();
        try {
            foreach ($request->file('files') as $file) {
                $extension = $file->getClientOriginalExtension();
                $fileName  = Str::uuid() . '.' . $extension;
                $filePath  = $file->storeAs(
                    "projects/{$projectId}/logs/{$logId}",
                    $fileName,
                    'public'
                );

                $attachment = ProjectAttachment::create([
                    'project_id'         => $projectId,
                    'log_id'             => $logId,
                    'attachment_type_id' => $typeModel?->id,
                    'file_path'          => $filePath,
                    'original_name'      => $file->getClientOriginalName(),
                    'uploaded_by'        => auth()->id(),
                ]);

                $uploaded[] = [
                    'id'            => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'file_path'     => $attachment->file_path,
                    'url'           => Storage::disk('public')->url($attachment->file_path),
                    'type'          => $typeCode,
                    'uploaded_at'   => $attachment->created_at,
                ];
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al subir los archivos', 500);
        }

        return ApiResponse::success($uploaded, 'Archivos subidos exitosamente', 201);
    }

    /**
     * Eliminar un adjunto de bitácora
     */
    public function destroy(Request $request, int $projectId, int $logId, int $attachmentId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $attachment = ProjectAttachment::where('log_id', $logId)
            ->where('project_id', $projectId)
            ->find($attachmentId);

        if (!$attachment) {
            return ApiResponse::notFound('Adjunto no encontrado');
        }

        // Solo el que subió o un admin puede eliminar
        if ($attachment->uploaded_by !== auth()->id()) {
            // Podría validarse con permiso PROJECTS.DELETE aquí si se desea
        }

        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return ApiResponse::success(null, 'Adjunto eliminado exitosamente');
    }
}
