<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\LegacyText;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlantController extends Controller
{
    public function index(): JsonResponse
    {
        $plants = DB::connection('intra01')
            ->table('WEB.RECRU_PLANT')
            ->select(['PLANT_ID', 'PLANT_CODE', 'PLANT_NAME'])
            ->whereNotNull('PLANT_ID')
            ->whereNotNull('PLANT_NAME')
            ->orderBy('PLANT_ID')
            ->get()
            ->map(fn (object $plant): array => [
                'id' => trim((string) $plant->plant_id),
                'code' => trim((string) $plant->plant_code),
                'name' => LegacyText::toUtf8(trim((string) $plant->plant_name)),
            ])
            ->filter(fn (array $plant): bool => $plant['id'] !== '' && $plant['name'] !== '')
            ->values();

        return response()->json(['data' => $plants]);
    }
}
