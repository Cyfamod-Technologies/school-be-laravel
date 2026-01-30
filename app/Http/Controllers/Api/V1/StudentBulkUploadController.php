<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BulkUploadValidationException;
use App\Http\Controllers\Controller;
use App\Models\BulkUploadBatch;
use App\Services\BulkUpload\StudentBulkUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * @OA\Tag(
 *     name="school-v2.0",
 *     description="v2.0 – Rollover, Promotions, Attendance, Fees, Roles"
 * )
 */
class StudentBulkUploadController extends Controller
{
    public function __construct(private readonly StudentBulkUploadService $service)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/students/bulk/template",
     *     tags={"school-v2.0"},
     *     summary="Download student bulk upload template",
     *     @OA\Parameter(name="session_id", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="class_id", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="class_arm_id", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="CSV template")
     * )
     */
    public function template(Request $request)
    {
        $school = $request->user()->school;
        
        // Get preselected context from query params
        $preselected = [
            'session_id' => $request->query('session_id'),
            'class_id' => $request->query('class_id'),
            'class_arm_id' => $request->query('class_arm_id'),
        ];
        
        $csv = $this->service->generateTemplate($school, $preselected);

        $fileName = 'student-bulk-upload-template-' . now()->format('Ymd_His') . '.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/students/bulk/preview",
     *     tags={"school-v2.0"},
     *     summary="Preview student bulk upload",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="session_id", type="string"),
     *                 @OA\Property(property="class_id", type="string"),
     *                 @OA\Property(property="class_arm_id", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Preview generated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'session_id' => ['nullable', 'string'],
            'class_id' => ['nullable', 'string'],
            'class_arm_id' => ['nullable', 'string'],
        ]);

        // Get preselected context from form data
        $preselected = [
            'session_id' => $request->input('session_id'),
            'class_id' => $request->input('class_id'),
            'class_arm_id' => $request->input('class_arm_id'),
        ];

        try {
            $result = $this->service->validateAndPrepare(
                $request->user()->school,
                $request->file('file'),
                $request->user(),
                $preselected
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

    /**
     * @OA\Post(
     *     path="/api/v1/students/bulk/{batch}/commit",
     *     tags={"school-v2.0"},
     *     summary="Commit student bulk upload",
     *     @OA\Parameter(name="batch", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Upload committed"),
     *     @OA\Response(response=404, description="Batch not found")
     * )
     */
    public function commit(Request $request, BulkUploadBatch $batch): JsonResponse
    {
        $user = $request->user();

        if ($batch->school_id !== $user->school_id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $decisions = $request->input('decisions', []);
        if (! is_array($decisions)) {
            $decisions = [];
        }

        $result = $this->service->commit($batch, $decisions);

        return response()->json([
            'message' => 'Students uploaded successfully.',
            'summary' => [
                'total_processed' => $result['processed'],
                'created' => $result['created'] ?? 0,
                'updated' => $result['updated'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'parents_created' => $result['parents_created'],
                'failed' => $result['failed'],
            ],
        ]);
    }
}
