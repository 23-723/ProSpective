<?php

namespace App\Http\Controllers;

use App\Services\AcademicAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicDashboardController extends Controller
{
    public function index(Request $request, AcademicAnalyticsService $analytics): View
    {
        return view('dashboard', ['dashboard' => $analytics->dashboard($request->query())]);
    }

    public function data(Request $request, AcademicAnalyticsService $analytics): JsonResponse
    {
        return response()->json($analytics->dashboard($request->query()))->header('Cache-Control', 'no-store');
    }

    public function table(Request $request, AcademicAnalyticsService $analytics): JsonResponse
    {
        $query = $request->query();
        $records = $analytics->select($analytics->repository->records(), $analytics->normalize($query));

        return response()->json($analytics->table($records, $query))->header('Cache-Control', 'no-store');
    }
}
