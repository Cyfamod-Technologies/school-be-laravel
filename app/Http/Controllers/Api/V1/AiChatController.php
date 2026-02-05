<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AiChatLog;
use App\Models\ClassArm;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiChatController extends Controller
{
    public function chat(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->school_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $message = trim($payload['message']);
        $intent = $this->detectIntent($message);
        $isAdmin = $this->isAdmin($user);

        $studentReply = $this->handleStudentFlow($request, $user, $message);
        if ($studentReply) {
            $reply = $studentReply;
        } elseif ($intent === 'delete' && ! $isAdmin) {
            $reply = 'Only school admins can delete. Please contact your admin.';
        } else {
            $reply = $this->callAi($user, $message) ?? $this->buildDefaultReply($user, $message);
        }

        AiChatLog::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'user_message' => $message,
            'assistant_reply' => $reply,
            'intent' => $intent,
        ]);

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'action' => 'ai.chat',
            'description' => sprintf(
                'school_id=%s; intent=%s; message=%s',
                $user->school_id,
                $intent ?? 'none',
                $this->truncate($message, 500),
            ),
        ]);

        return response()->json([
            'reply' => $reply,
            'intent' => $intent,
            'can_delete' => $isAdmin,
            'suggestions' => $this->suggestionsFor($intent),
        ]);
    }

    public function history(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->school_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $limit = (int) ($request->query('limit', 100));
        $limit = max(1, min(200, $limit));
        $scope = $request->query('scope', 'school');
        $isAdmin = $this->isAdmin($user);

        $query = AiChatLog::query()
            ->where('school_id', $user->school_id);

        if (! $isAdmin || $scope !== 'school') {
            $query->where('user_id', $user->id);
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'data' => $logs,
        ]);
    }

    private function detectIntent(string $message): ?string
    {
        if (preg_match('/\b(delete|remove|erase)\b/i', $message)) {
            return 'delete';
        }
        if (preg_match('/\b(create|add|new)\b/i', $message)) {
            return 'create';
        }
        if (preg_match('/\b(edit|update|change)\b/i', $message)) {
            return 'edit';
        }
        return null;
    }

    private function buildDefaultReply(User $user, string $message): string
    {
        $schoolName = $user->school?->name;
        $cleanSchoolName = $this->normalizeText($schoolName ?? '');
        $cleanSchoolName = $cleanSchoolName !== '' ? $cleanSchoolName : null;
        $prefix = $cleanSchoolName
            ? "Hi! I'm your assistant for {$cleanSchoolName}. "
            : "Hi! I'm your school assistant. ";

        $normalized = strtolower($this->normalizeText($message));

        if ($this->isOnboardingQuestion($normalized)) {
            return $prefix . $this->buildOnboardingReply();
        }

        $matched = $this->matchRoute($normalized);
        if ($matched) {
            $link = $this->frontendUrl($matched['path']);
            if ($this->isExplainQuestion($normalized) && ! empty($matched['description'])) {
                return $prefix . sprintf('%s You can manage it here: %s', $matched['description'], $link);
            }
            return $prefix . $this->formatRouteReply($matched['label'], $link);
        }

        return $prefix
            . "Tell me what you need help with (add students, enter results, assign teachers, etc.).";
    }

    private function suggestionsFor(?string $intent): array
    {
        if ($intent === 'create') {
            return [
                'Add a new student',
                'Create a new class',
                'Add a new staff member',
            ];
        }
        if ($intent === 'edit') {
            return [
                'Edit a class teacher assignment',
                'Update a student record',
                'Change the active term',
            ];
        }
        if ($intent === 'delete') {
            return [
                'Contact your admin to approve the delete request',
            ];
        }
        return [
            'How do I add a student?',
            'Show me how to enter term results.',
            'Edit a class teacher assignment.',
        ];
    }

    private function isAdmin(User $user): bool
    {
        $role = strtolower((string) ($user->role ?? ''));
        return in_array($role, ['admin', 'super_admin'], true);
    }

    private function frontendUrl(string $path): string
    {
        $raw = (string) config('app.frontend_url', config('app.frontend_login_url', '/'));
        $base = $raw;
        try {
            $parts = parse_url($raw);
            if (isset($parts['scheme'], $parts['host'])) {
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                $base = $parts['scheme'] . '://' . $parts['host'] . $port;
            }
        } catch (\Throwable $e) {
            // fall back to raw
        }

        $path = '/' . ltrim($path, '/');
        return rtrim($base, '/') . $path;
    }

    private function normalizeText(string $value): string
    {
        $value = str_replace(["<br>", "<br/>", "<br />"], ' ', $value);
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        $value = preg_replace('/\s+/', ' ', $value ?? '');
        return trim((string) $value);
    }

    private function handleStudentFlow(Request $request, User $user, string $message): ?string
    {
        $normalized = strtolower($this->normalizeText($message));
        $pendingKey = $this->pendingKey($user->id, 'create_student');
        $pending = Cache::get($pendingKey);

        if ($pending && is_array($pending)) {
            return $this->handlePendingStudent($request, $user, $normalized, $pending, $pendingKey);
        }

        if (! preg_match('/\b(add|create|register)\s+(a\s+)?student\b/', $normalized)) {
            return null;
        }

        $this->ensurePermission($request, 'students.create');

        $data = $this->extractStudentData($user, $normalized);
        $missing = $this->missingStudentFields($data);

        if ($missing) {
            Cache::put($pendingKey, [
                'type' => 'create_student',
                'status' => 'collecting',
                'data' => $data,
                'missing' => $missing,
            ], now()->addMinutes(30));

            return $this->formatMissingStudentReply($data, $missing);
        }

        Cache::put($pendingKey, [
            'type' => 'create_student',
            'status' => 'awaiting_confirmation',
            'data' => $data,
        ], now()->addMinutes(30));

        return $this->formatConfirmStudentReply($data);
    }

    private function handlePendingStudent(
        Request $request,
        User $user,
        string $normalized,
        array $pending,
        string $pendingKey
    ): string {
        if (($pending['type'] ?? '') !== 'create_student') {
            Cache::forget($pendingKey);
            return $this->buildDefaultReply($user, $normalized);
        }

        if (($pending['status'] ?? '') === 'awaiting_confirmation') {
            if (preg_match('/\b(cancel|no|stop)\b/', $normalized)) {
                Cache::forget($pendingKey);
                return 'Okay, I cancelled that student creation.';
            }
            if (preg_match('/\b(confirm|yes|yep|sure|go ahead|proceed)\b/', $normalized)) {
                $result = $this->createStudentRecord($request, $user, $pending['data'] ?? []);
                Cache::forget($pendingKey);
                return $result;
            }
            return 'Please reply CONFIRM to create the student, or CANCEL to stop.';
        }

        $data = $pending['data'] ?? [];
        $data = $this->fillStudentDataFromMessage($user, $normalized, $data);
        $missing = $this->missingStudentFields($data);

        if ($missing) {
            Cache::put($pendingKey, [
                'type' => 'create_student',
                'status' => 'collecting',
                'data' => $data,
                'missing' => $missing,
            ], now()->addMinutes(30));
            return $this->formatMissingStudentReply($data, $missing);
        }

        Cache::put($pendingKey, [
            'type' => 'create_student',
            'status' => 'awaiting_confirmation',
            'data' => $data,
        ], now()->addMinutes(30));

        return $this->formatConfirmStudentReply($data);
    }

    private function extractStudentData(User $user, string $normalized): array
    {
        $data = $this->fillStudentDataFromMessage($user, $normalized, []);

        $school = $user->school;
        if ($school) {
            $data['current_session_id'] = $school->current_session_id ?? null;
            $data['current_term_id'] = $school->current_term_id ?? null;
        }

        return $data;
    }

    private function fillStudentDataFromMessage(User $user, string $normalized, array $data): array
    {
        $name = $this->extractName($normalized);
        if ($name) {
            $data = array_merge($data, $name);
        }

        $gender = $this->extractGender($normalized);
        if ($gender) {
            $data['gender'] = $gender;
        }

        $dob = $this->extractDate($normalized, ['dob', 'date of birth', 'birth']);
        if ($dob) {
            $data['date_of_birth'] = $dob;
        }

        $admissionDate = $this->extractDate($normalized, ['admission']);
        if ($admissionDate) {
            $data['admission_date'] = $admissionDate;
        }

        if (! isset($data['status'])) {
            $data['status'] = 'active';
        }

        $classMatch = $this->matchClassAndArm($user, $normalized);
        if ($classMatch) {
            $data = array_merge($data, $classMatch);
        }

        return $data;
    }

    private function missingStudentFields(array $data): array
    {
        $required = [
            'first_name',
            'last_name',
            'gender',
            'date_of_birth',
            'current_session_id',
            'current_term_id',
            'school_class_id',
            'class_arm_id',
            'admission_date',
            'status',
        ];

        $missing = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    private function formatMissingStudentReply(array $data, array $missing): string
    {
        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $classLabel = $data['class_label'] ?? null;
        $parts = [];

        if ($name !== '') {
            $parts[] = "Student: {$name}";
        }
        if ($classLabel) {
            $parts[] = "Class: {$classLabel}";
        }

        $missingLabels = array_map(function ($field) {
            return match ($field) {
                'current_session_id' => 'current session (set in School Settings)',
                'current_term_id' => 'current term (set in School Settings)',
                'school_class_id' => 'class',
                'class_arm_id' => 'class arm',
                'date_of_birth' => 'date of birth (YYYY-MM-DD)',
                'admission_date' => 'admission date (YYYY-MM-DD)',
                default => str_replace('_', ' ', $field),
            };
        }, $missing);

        $summary = $parts ? implode(' · ', $parts) . '. ' : '';
        return $summary
            . 'Please provide: '
            . implode(', ', $missingLabels)
            . '.';
    }

    private function formatConfirmStudentReply(array $data): string
    {
        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $classLabel = $data['class_label'] ?? 'the selected class';
        $gender = $data['gender'] ?? '';
        $dob = $data['date_of_birth'] ?? '';
        $admission = $data['admission_date'] ?? '';

        return sprintf(
            'Please CONFIRM to create student %s in %s (gender: %s, DOB: %s, admission date: %s).',
            $name,
            $classLabel,
            $gender,
            $dob,
            $admission,
        );
    }

    private function createStudentRecord(Request $request, User $user, array $data): string
    {
        $this->ensurePermission($request, 'students.create');
        $school = $user->school;
        if (! $school) {
            return 'Your account is not linked to a school.';
        }

        $required = $this->missingStudentFields($data);
        if ($required) {
            return $this->formatMissingStudentReply($data, $required);
        }

        Student::fixLegacyForeignKeys();

        $student = Student::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'],
            'nationality' => $data['nationality'] ?? null,
            'state_of_origin' => $data['state_of_origin'] ?? null,
            'lga_of_origin' => $data['lga_of_origin'] ?? null,
            'house' => $data['house'] ?? null,
            'club' => $data['club'] ?? null,
            'address' => $data['address'] ?? null,
            'medical_information' => $data['medical_information'] ?? null,
            'blood_group_id' => $data['blood_group_id'] ?? null,
            'current_session_id' => $data['current_session_id'],
            'current_term_id' => $data['current_term_id'],
            'school_class_id' => $data['school_class_id'],
            'class_arm_id' => $data['class_arm_id'],
            'class_section_id' => $data['class_section_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'admission_date' => $data['admission_date'],
            'photo_url' => $data['photo_url'] ?? null,
            'status' => strtolower($data['status']),
            'portal_password' => '123456',
        ]);

        $link = $this->frontendUrl('/v14/all-students');
        return "Student created successfully. View students: {$link}";
    }

    private function extractName(string $normalized): ?array
    {
        if (preg_match('/\b(?:name\s+is|his\s+name\s+is|her\s+name\s+is)\s+([a-z\s]+)/', $normalized, $match)) {
            $name = trim($match[1]);
        } else {
            if (preg_match('/\bstudent\b\s*(.+)$/', $normalized, $match)) {
                $name = trim($match[1]);
            } else {
                return null;
            }
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (! $parts || count($parts) < 2) {
            return null;
        }

        $first = array_shift($parts);
        $last = array_pop($parts);
        $middle = $parts ? implode(' ', $parts) : null;

        return [
            'first_name' => ucfirst($first),
            'middle_name' => $middle ? ucwords($middle) : null,
            'last_name' => ucfirst($last),
        ];
    }

    private function extractGender(string $normalized): ?string
    {
        if (preg_match('/\b(male|boy|m)\b/', $normalized)) {
            return 'male';
        }
        if (preg_match('/\b(female|girl|f)\b/', $normalized)) {
            return 'female';
        }
        if (preg_match('/\b(other|others)\b/', $normalized)) {
            return 'other';
        }
        return null;
    }

    private function extractDate(string $normalized, array $hints): ?string
    {
        $hinted = false;
        foreach ($hints as $hint) {
            if (str_contains($normalized, $hint)) {
                $hinted = true;
                break;
            }
        }

        if (! $hinted) {
            return null;
        }

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $normalized, $match)) {
            return $match[1];
        }

        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $normalized, $match)) {
            try {
                $date = Carbon::createFromFormat('d/m/Y', "{$match[1]}/{$match[2]}/{$match[3]}");
                return $date->format('Y-m-d');
            } catch (\Throwable $e) {
                try {
                    $date = Carbon::createFromFormat('m/d/Y', "{$match[1]}/{$match[2]}/{$match[3]}");
                    return $date->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }

        return null;
    }

    private function matchClassAndArm(User $user, string $normalized): ?array
    {
        $schoolId = $user->school_id;
        if (! $schoolId) {
            return null;
        }

        $classes = Cache::remember(
            "ai:classes:{$schoolId}",
            now()->addMinutes(10),
            fn () => SchoolClass::query()
                ->where('school_id', $schoolId)
                ->get(['id', 'name'])
                ->toArray(),
        );

        $arms = Cache::remember(
            "ai:class_arms:{$schoolId}",
            now()->addMinutes(10),
            fn () => ClassArm::query()
                ->whereHas('school_class', function ($query) use ($schoolId) {
                    $query->where('school_id', $schoolId);
                })
                ->get(['id', 'name', 'school_class_id'])
                ->toArray(),
        );

        $class = $this->matchByName($normalized, $classes);
        $arm = $this->matchByName($normalized, $arms);

        if (! $arm && $class) {
            $candidateArms = array_values(array_filter($arms, fn ($item) => $item['school_class_id'] === $class['id']));
            if (count($candidateArms) === 1) {
                $arm = $candidateArms[0];
            }
        }

        if (! $class && $arm && isset($arm['school_class_id'])) {
            foreach ($classes as $candidate) {
                if ($candidate['id'] === $arm['school_class_id']) {
                    $class = $candidate;
                    break;
                }
            }
        }

        if (! $class || ! $arm) {
            return null;
        }

        return [
            'school_class_id' => $class['id'],
            'class_arm_id' => $arm['id'],
            'class_label' => trim($class['name'] . ' ' . $arm['name']),
        ];
    }

    private function matchByName(string $normalized, array $items): ?array
    {
        $best = null;
        $bestLength = 0;

        foreach ($items as $item) {
            $name = strtolower((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (str_contains($normalized, strtolower($name)) && strlen($name) > $bestLength) {
                $best = $item;
                $bestLength = strlen($name);
            }
        }

        return $best;
    }

    private function pendingKey(string $userId, string $type): string
    {
        return "ai:pending:{$type}:{$userId}";
    }

    private function callAi(User $user, string $message): ?string
    {
        $enabled = strtolower((string) config('ai.enabled', 'off'));
        if (! in_array($enabled, ['on', 'true', '1'], true)) {
            return null;
        }

        $apiKey = (string) config('ai.api_key');
        if ($apiKey === '') {
            return null;
        }

        $provider = strtolower((string) config('ai.provider', 'openai'));
        if (! in_array($provider, ['openai', 'openai-compatible'], true)) {
            $provider = 'openai';
        }

        $model = (string) (config('ai.model') ?: 'gpt-4o-mini');
        $baseUrl = (string) (config('ai.base_url') ?: 'https://api.openai.com');
        $baseUrl = rtrim($baseUrl, '/');

        $routes = (array) config('ai.routes', []);
        $routeLines = [];
        foreach ($routes as $route) {
            if (! isset($route['label'], $route['path'])) {
                continue;
            }
            $routeLines[] = sprintf(
                '- %s: %s',
                $route['label'],
                $this->frontendUrl($route['path']),
            );
        }

        $setupSteps = [
            '/v10/profile',
            '/v11/all-sessions',
            '/v11/all-terms',
            '/v12/all-classes',
            '/v16/all-subjects',
            '/v17/assign-subjects',
            '/v15/add-staff',
            '/v17/assign-teachers',
            '/v18/assign-class-teachers',
            '/v19/grade-scales',
            '/v19/skills',
            '/v14/add-student',
        ];
        $setupLines = [];
        foreach ($setupSteps as $index => $path) {
            $setupLines[] = sprintf('%d) %s', $index + 1, $this->frontendUrl($path));
        }

        $appInfo = $this->loadAppInfoGuide();

        $system = implode("\n", [
            'You are a school-scoped dashboard assistant.',
            'Only respond using the routes provided; do not invent URLs.',
            'If the user asks how to do something, give brief steps and include the best matching link.',
            'Use the App Info Guide below as the source of truth for how-to steps and page usage.',
            'Do not discuss architecture, backend logic, or database details; say it is not covered in the guide.',
            'If unclear, ask a clarifying question.',
            'Setup order links: ' . implode(' | ', $setupLines),
            'Routes:',
            implode("\n", $routeLines),
        ]);

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                $appInfo ? ['role' => 'system', 'content' => "App Info Guide:\n" . $appInfo] : null,
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => 0.2,
        ];
        $payload['messages'] = array_values(array_filter($payload['messages']));

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/v1/chat/completions", $payload);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            if (! is_string($content)) {
                return null;
            }
            $content = trim($content);
            return $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function loadAppInfoGuide(): ?string
    {
        $path = base_path('App-info.md');
        if (! is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $content = trim($content);
        $maxLength = 12000;
        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength) . "\n\n(Truncated)";
        }

        return $content;
    }

    /**
     * @return array{label:string,path:string,keywords?:array}|null
     */
    private function matchRoute(string $normalizedMessage): ?array
    {
        $routes = (array) config('ai.routes', []);
        if (! $routes) {
            return null;
        }

        $tokens = preg_split('/\s+/', $normalizedMessage, -1, PREG_SPLIT_NO_EMPTY);
        $tokenSet = $tokens ? array_fill_keys($tokens, true) : [];
        $bestScore = 0;
        $bestRoute = null;

        foreach ($routes as $route) {
            $keywords = $route['keywords'] ?? [];
            if (! $keywords) {
                continue;
            }
            $score = 0;
            foreach ($keywords as $keyword) {
                $kw = strtolower($keyword);
                if (strpos($kw, ' ') !== false) {
                    if (str_contains($normalizedMessage, $kw)) {
                        $score += substr_count($kw, ' ') + 1;
                    }
                } else {
                    if (isset($tokenSet[$kw])) {
                        $score += 1;
                    }
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRoute = $route;
            }
        }

        return $bestScore > 0 ? $bestRoute : null;
    }

    private function formatRouteReply(string $label, string $link): string
    {
        $lower = strtolower($label);
        if (str_starts_with($lower, 'add ')) {
            $action = substr($lower, 4);
            return "Go to {$link} to add {$action}.";
        }
        if (str_starts_with($lower, 'view ')) {
            $action = substr($lower, 5);
            return "Open {$link} to view {$action}.";
        }
        return "Open {$link} for {$label}.";
    }

    private function isOnboardingQuestion(string $normalizedMessage): bool
    {
        $patterns = [
            'setup school',
            'set up school',
            'setting up my school',
            'start setting up',
            'getting started',
            'where do i start',
            'how do i start',
            'setup my school',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($normalizedMessage, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isExplainQuestion(string $normalizedMessage): bool
    {
        $patterns = [
            'what is',
            'what are',
            'explain',
            'meaning of',
            'define',
            'purpose of',
            'used for',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($normalizedMessage, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function buildOnboardingReply(): string
    {
        $steps = [
            [
                'label' => 'School Settings',
                'path' => '/v10/profile',
                'note' => 'Confirm your school profile details.',
            ],
            [
                'label' => 'Sessions',
                'path' => '/v11/all-sessions',
                'note' => 'Create the academic sessions.',
            ],
            [
                'label' => 'Terms',
                'path' => '/v11/all-terms',
                'note' => 'Add terms for the active session.',
            ],
            [
                'label' => 'Classes',
                'path' => '/v12/all-classes',
                'note' => 'Create classes and class arms.',
            ],
            [
                'label' => 'Subjects',
                'path' => '/v16/all-subjects',
                'note' => 'Add the subjects taught in your school.',
            ],
            [
                'label' => 'Assign Subjects',
                'path' => '/v17/assign-subjects',
                'note' => 'Assign subjects to classes.',
            ],
            [
                'label' => 'Add Staff',
                'path' => '/v15/add-staff',
                'note' => 'Create staff accounts.',
            ],
            [
                'label' => 'Assign Teachers',
                'path' => '/v17/assign-teachers',
                'note' => 'Assign teachers to subjects.',
            ],
            [
                'label' => 'Class Teachers',
                'path' => '/v18/assign-class-teachers',
                'note' => 'Assign class teachers.',
            ],
            [
                'label' => 'Grading & Assessment',
                'path' => '/v19/grade-scales',
                'note' => 'Set grading scales, assessment components, and structures.',
            ],
            [
                'label' => 'Skills',
                'path' => '/v19/skills',
                'note' => 'Set up skill categories if needed.',
            ],
            [
                'label' => 'Add Students',
                'path' => '/v14/add-student',
                'note' => 'Enroll students once the setup is ready.',
            ],
        ];

        $lines = ["Here is a good setup order:"];
        foreach ($steps as $index => $step) {
            $link = $this->frontendUrl($step['path']);
            $lines[] = sprintf(
                "%d) %s: %s (%s)",
                $index + 1,
                $step['label'],
                $step['note'],
                $link,
            );
        }

        return implode("\n", $lines);
    }

    private function truncate(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return mb_substr($value, 0, $limit - 3) . '...';
    }
}
