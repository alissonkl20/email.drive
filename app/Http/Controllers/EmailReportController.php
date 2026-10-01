<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailReportRequest;
use App\Services\Email\EmailReportService;
use Illuminate\Http\JsonResponse;

class EmailReportController extends Controller
{
    public function __invoke(EmailReportRequest $request, EmailReportService $reports): JsonResponse
    {
        return response()->json($reports->run($request->validated()));
    }
}
