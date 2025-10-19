<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BulkUploadValidationException;
use App\Http\Controllers\Controller;
use App\Models\BulkUploadBatch;
use App\Services\BulkUpload\StudentBulkUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class StudentBulkUploadController extends Controller
{
    public function __construct(private readonly StudentBulkUploadService $service)
    {
    }

    public function template(Request $request)
    {
        $school = $request->user()->school;
        $csv = $this->service->generateTemplate($school);

        $fileName = 'student-bulk-upload-template-' . now()->format('Ymd_His') . '.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        try {
            $result = $this->service->validateAndPrepare(
                $request->user()->school,
                $request->file('file'),
                $request->user()
            );
        } catch (BulkUploadValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
                'error_csv' => $exception->errorCsv() ? base64_encode($exception->errorCsv()) : null,
            ], 422);
        }

        /** @var BulkUploadBatch $batch */
        $batch = $result['batch'];

        return response()->json([
            'message' => 'File validated successfully. Review the preview and confirm to create students.',
            'batch_id' => $batch->id,
            'expires_at' => optional($batch->expires_at)->toIso8601String(),
            'summary' => $result['summary'],
            'preview_rows' => $result['preview_rows'],
        ]);
    }

    public function commit(Request $request, BulkUploadBatch $batch): JsonResponse
    {
        $user = $request->user();

        if ($batch->school_id !== $user->school_id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $result = $this->service->commit($batch);

        return response()->json([
            'message' => 'Students uploaded successfully.',
            'summary' => [
                'total_processed' => $result['processed'],
                'parents_created' => $result['parents_created'],
                'failed' => $result['failed'],
            ],
        ]);
    }
}
