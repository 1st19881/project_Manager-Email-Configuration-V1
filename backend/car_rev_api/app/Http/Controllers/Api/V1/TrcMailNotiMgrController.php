<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrcMailNotiMgrRequest;
use App\Http\Requests\UpdateTrcMailNotiMgrRequest;
use App\Http\Resources\TrcMailNotiMgrResource;
use App\Models\TrcMailNotiMgr;
use App\Support\LegacyText;
use App\Support\MailNotificationManagerSpreadsheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrcMailNotiMgrController extends Controller
{
    public function downloadImportTemplate(MailNotificationManagerSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'mail-notification-template-');

        if ($path === false) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to create template.');
        }

        $spreadsheet->createTemplate($path);

        return response()->download(
            $path,
            'mail-notification-managers-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }

    public function import(Request $request, MailNotificationManagerSpreadsheet $spreadsheet): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        try {
            $rows = $spreadsheet->read($request->file('file'));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'The import file could not be read.',
                'errors' => ['file' => [$exception->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($rows === []) {
            return response()->json([
                'message' => 'No data rows were found.',
                'errors' => ['file' => ['Add at least one recipient below the header row.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (count($rows) > 1000) {
            return response()->json([
                'message' => 'The file contains too many rows.',
                'errors' => ['file' => ['A maximum of 1,000 rows can be imported at a time.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $errors = [];
        $duplicates = [];
        $emails = [];
        $existingEmails = TrcMailNotiMgr::query()
            ->select('EMAIL')
            ->get()
            ->mapWithKeys(fn (TrcMailNotiMgr $manager): array => [strtolower((string) $manager->email) => true])
            ->all();

        foreach ($rows as $index => &$row) {
            $rowNumber = $index + 2;
            $row['active_flag'] = strtoupper($row['active_flag'] ?? 'Y');
            $email = strtolower((string) ($row['email'] ?? ''));
            $validator = Validator::make($row, [
                'employee_code' => ['required', 'string', 'max:50'],
                'plant' => ['required', 'string', 'max:20'],
                'position_name' => ['nullable', 'string', 'max:200'],
                'department_name' => ['nullable', 'string', 'max:200'],
                'name_th' => ['nullable', 'string', 'max:250'],
                'name_en' => ['nullable', 'string', 'max:250'],
                'email' => ['required', 'email', 'max:255'],
                'active_flag' => ['required', Rule::in(['Y', 'N'])],
            ]);

            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Row {$rowNumber}: {$message}";
            }

            if ($email !== '') {
                if (isset($existingEmails[$email])) {
                    $duplicates[] = "แถว {$rowNumber}: {$email} มีอยู่ในระบบแล้ว";
                }

                if (isset($emails[$email])) {
                    $duplicates[] = "แถว {$rowNumber}: {$email} ซ้ำกับแถว {$emails[$email]} ในไฟล์";
                }

                $emails[$email] ??= $rowNumber;
            }
        }
        unset($row);

        if ($errors !== [] || $duplicates !== []) {
            return response()->json([
                'message' => 'Some rows contain invalid data. No records were imported.',
                'errors' => ['file' => [...$duplicates, ...$errors]],
                'duplicates' => $duplicates,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::connection((new TrcMailNotiMgr)->getConnectionName())->transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                TrcMailNotiMgr::query()->create([
                    ...$row,
                    'noti_id' => (string) Str::uuid(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Import completed successfully.',
            'imported' => count($rows),
        ], Response::HTTP_CREATED);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'plant' => ['nullable', 'string', 'max:20'],
            'active_flag' => ['nullable', 'in:Y,N'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $managers = TrcMailNotiMgr::query()
            ->when($validated['plant'] ?? null, fn ($query, $plant) => $query->where('PLANT', $plant))
            ->when($validated['active_flag'] ?? null, fn ($query, $activeFlag) => $query->where('ACTIVE_FLAG', $activeFlag))
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    // The columns hold TIS-620, so the pattern has to be encoded the same way.
                    $pattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], LegacyText::toLegacy($search)).'%';

                    $query->where('EMPLOYEE_CODE', 'like', $pattern)
                        ->orWhere('POSITION_NAME', 'like', $pattern)
                        ->orWhere('DEPARTMENT_NAME', 'like', $pattern)
                        ->orWhere('NAME_TH', 'like', $pattern)
                        ->orWhere('NAME_EN', 'like', $pattern)
                        ->orWhere('EMAIL', 'like', $pattern);
                });
            })
            ->orderByDesc('CREATED_AT')
            ->orderBy('NOTI_ID')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return TrcMailNotiMgrResource::collection($managers);
    }

    public function store(StoreTrcMailNotiMgrRequest $request): JsonResponse
    {
        $data = $this->toDatabaseAttributes($request->validated());
        $data['noti_id'] = (string) Str::uuid();
        $data['active_flag'] ??= 'Y';

        $manager = TrcMailNotiMgr::query()->create($data);

        return (new TrcMailNotiMgrResource($manager))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TrcMailNotiMgr $mailNotificationManager): TrcMailNotiMgrResource
    {
        return new TrcMailNotiMgrResource($mailNotificationManager);
    }

    public function update(
        UpdateTrcMailNotiMgrRequest $request,
        TrcMailNotiMgr $mailNotificationManager,
    ): TrcMailNotiMgrResource {
        $mailNotificationManager->update($this->toDatabaseAttributes($request->validated()));

        return new TrcMailNotiMgrResource($mailNotificationManager->refresh());
    }

    public function destroy(TrcMailNotiMgr $mailNotificationManager): Response
    {
        $mailNotificationManager->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function toDatabaseAttributes(array $attributes): array
    {
        return $attributes;
    }
}
