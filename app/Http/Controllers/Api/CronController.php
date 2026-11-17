<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CronController extends Controller
{
    /**
     * Point d'accès externe pour la tâche planifiée :
     * curl -X POST -H "Authorization: Bearer $CRON_SECRET" https://.../api/cron/tick
     */
    public function tick(Request $request, SchedulerService $scheduler): JsonResponse
    {
        $authHeader = (string) $request->header('Authorization', '');
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

        $cronSecret = config('services.cron_secret', env('CRON_SECRET'));

        if (!$token || !$cronSecret || !hash_equals(hash('sha256', $token), hash('sha256', $cronSecret))) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $report = $scheduler->runTick();
        return response()->json($report);
    }
}
