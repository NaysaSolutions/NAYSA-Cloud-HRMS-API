<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PrintingController extends Controller
{
     
    
    private function tenantCreds(Request $request): array
    {
        $creds = $request->attributes->get('tenant.db');
        if (!$creds && ($conn = $request->attributes->get('tenant.connection'))) {
            $creds = [
                'host'     => config("database.connections.$conn.host"),
                'database' => config("database.connections.$conn.database"),
                'username' => config("database.connections.$conn.username"),
                'password' => config("database.connections.$conn.password"),
            ];
        }
        if (!$creds) {
            $default = config('database.default', 'sqlsrv');
            $creds = [
                'host'     => config("database.connections.$default.host"),
                'database' => config("database.connections.$default.database"),
                'username' => config("database.connections.$default.username"),
                'password' => config("database.connections.$default.password"),
            ];
        }

        return $creds ?? [];
    }




    public function getID(Request $request) {
        try {
            $results = DB::select(
                'EXEC sproc_PHP_Printing @mode = ?',
                ['GenerateID'] 
            );
    
            if (empty($results)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No ID generated',
                ], 404);
            }
    
            return response()->json([
                'success' => true,
                'generatedID' => $results[0]->generatedID,
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }



public function PrintFormResult(Request $request) {
    $jsonData = $request->all();
    $generatedID = $jsonData['json_data']['generatedID'] ?? null;

    if (!$generatedID) {
        return response()->json([
            'success' => false,
            'message' => 'Missing generatedID parameter',
        ], 400);
    }

    try {
        $results = DB::select(
            'EXEC sproc_PHP_Printing @mode = ?, @params = ?',
            ['PrintFormResult', json_encode(['json_data' => ['generatedID' => $generatedID]])]
        );

        if (empty($results)) {
            Log::error("PrintFormResult - No data found for ID: $generatedID");
            return response()->json([
                'success' => false,
                'message' => 'Document not found or not yet generated',
            ], 404);
        }

        $pdfData = $results[0]->result ?? null;

        if (!$pdfData) {
            Log::error("PrintFormResult - PDF data null for ID: $generatedID");
            return response()->json([
                'success' => false,
                'message' => 'PDF generation not complete',
            ], 404);
        }

        return response($pdfData)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="document.pdf"');
    } catch (\Exception $e) {
        Log::error("PrintFormResult Error: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}




public function printForm(Request $request)
{  
    try {
        $validated = $request->validate([
            'tranId'    => 'required',
            'formName'  => 'required|string',
            'docCode'   => 'nullable|string',
            'printMode' => 'nullable|string',
        ]);

        $baseUrl = rtrim(config('services.crystal.base'), '/');
        $apiUrl  = $baseUrl . config('services.crystal.form_generate');
        $creds = $this->tenantCreds($request);


        $TranID  = $request->input('tranId');
        $DocCode  = $request->input('docCode');
        $PrintMode = $request->input('printMode');
        $UserCode = $request->input('userCode');
        


        DB::statement('EXEC sproc_PHP_DocTrail @_mode = :mode, @_tranId = :tranId, @_docCode = :docCode, @_activity = :activity, @_userCode = :userCode',
            [
                'mode'     => 'Upsert',
                'tranId'   => $TranID,
                'docCode'  => $DocCode ?? '',
                'activity' => $PrintMode ?? 'Inline',
                'userCode' => $UserCode,
            ]
        );


        foreach (['host','database','username','password'] as $k) {
            if (empty($creds[$k])) {
                Log::warning('printForm: missing DB credential', ['missing' => $k]);
                return response()->json([
                    'status'  => 'error',
                    'message' => "Missing database credential: $k",
                ], 400);
            }
        }

        $payload = [
            'ReportName'    => $validated['formName'],
            'ServerName'    => $creds['host']     ?? '',
            'DatabaseName'  => $creds['database'] ?? '',
            'UserId'        => $creds['username'] ?? '',
            'Password'      => $creds['password'] ?? '',
            'TransactionId' => $validated['tranId'],
            'DocCode'       => $validated['docCode']   ?? '',
            'PrintMode'     => $validated['printMode'] ?? 'Inline',
        ];

        $response = Http::withHeaders(['Accept' => 'application/pdf'])->post($apiUrl, $payload);
        if ($response->successful()) {
            return response($response->body(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="report.pdf"');
        }



        return response()->json([
            'status'  => 'error',
            'message' => 'Failed to generate report',
            'code'    => $response->status(),
            'details' => $response->body(),
            'payload' => collect($payload)->except('Password'),
        ], 500);

    } catch (\Throwable $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Error calling Crystal Report API.',
            'details' => $e->getMessage(),
        ], 500);
    }

}



public function printQuery(Request $request)
{  
    try {
        $validated = $request->validate([
            'formName'  => 'required|string',
            'userCode'   => 'nullable|string',
            'params'   => 'nullable|string',
        ]);

        $baseUrl = rtrim(config('services.crystal.base'), '/');
        $apiUrl  = $baseUrl . config('services.crystal.query_generate_pdf');
        $creds = $this->tenantCreds($request);




        foreach (['host','database','username','password'] as $k) {
            if (empty($creds[$k])) {
                Log::warning('printForm: missing DB credential', ['missing' => $k]);
                return response()->json([
                    'status'  => 'error',
                    'message' => "Missing database credential: $k",
                ], 400);
            }
        }

        $payload = [
            'ReportName'    => $validated['formName'],
            'ServerName'    => $creds['host']     ?? '',
            'DatabaseName'  => $creds['database'] ?? '',
            'UserId'        => $creds['username'] ?? '',
            'Password'      => $creds['password'] ?? '',
            'UserCode'      => $validated['userCode'],
            'Params'      => $validated['params'],
        ];

        $response = Http::withHeaders(['Accept' => 'application/pdf'])->post($apiUrl, $payload);
        if ($response->successful()) {
            return response($response->body(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="report.pdf"');
        }



        return response()->json([
            'status'  => 'error',
            'message' => 'Failed to generate report',
            'code'    => $response->status(),
            'details' => $response->body(),
            'payload' => collect($payload)->except('Password'),
        ], 500);

    } catch (\Throwable $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Error calling Crystal Report API.',
            'details' => $e->getMessage(),
        ], 500);
    }

}




public function printARReport(Request $request)
{
    $baseUrl   = rtrim(config('services.crystal.base'), '/');
    $apiUrl    = $baseUrl . config('services.crystal.ar_export_excel');
    $apiUrlPdf = $baseUrl . config('services.crystal.ar_generate_pdf');


    $creds = $this->tenantCreds($request);
    foreach (['host','database','username','password'] as $k) {
        if (empty($creds[$k])) {
            Log::warning('Tenant DB creds missing', ['have' => array_keys(array_filter($creds)), 'missing' => $k]);
            return response()->json([
                'status'  => 'error',
                'message' => "Missing database credential: $k (tenant middleware not set or connection misconfigured).",
            ], 400);
        }
    }


    $SprocMode  = $request->input('sprocMode');
    $SprocName  = $request->input('sprocName');
    $Export     = $request->input('export', 'N');
    $ReportName = $request->input('reportName');
    $UserCode   = $request->input('userCode');
    $Branch     = $request->input('branchCode');
    $StartDate  = date('Y-m-d', strtotime($request->input('startDate')));
    $EndDate    = date('Y-m-d', strtotime($request->input('endDate')));
    $SCustomer  = $request->input('sCustCode');
    $ECustomer  = $request->input('eCustCode');
    $formName   = $request->input('formName');


    $formPath     = base_path('scripts' . DIRECTORY_SEPARATOR . 'Reports');
    $libraryPath  = base_path('scripts' . DIRECTORY_SEPARATOR . 'Library');
    $formFullPath = $formName ? ($formPath . DIRECTORY_SEPARATOR . $formName) : '';
    $timestamp    = now()->format('Ymd_His');
    $safeName     = Str::of($ReportName)->replace(['/', '\\', '"', "'"], '_');
    $ExportFileName = storage_path('app/' . $safeName . " ($timestamp).xlsx");


    $params = [
        'SprocMode'       => $SprocMode,
        'SprocName'       => $SprocName,
        'Export'          => $Export,
        'ExportFileName'  => $ExportFileName,
        'ReportName'      => $ReportName,
        'UserCode'        => $UserCode,
        'LibraryPath'     => $libraryPath,
        'Branch'          => $Branch,
        'StartDate'       => $StartDate,
        'EndDate'         => $EndDate,
        'SCustomer'       => $SCustomer,
        'ECustomer'       => $ECustomer,
        'ReportPath'      => $formFullPath,
        'DbServer'        => $creds['host'],
        'DbName'          => $creds['database'],
        'DbUser'          => $creds['username'],
        'DbPassword'      => $creds['password'],
    ];

    try {
        if (strtoupper($Export) !== 'Y') {
            // PDF mode
            $response = Http::accept('application/pdf')
                ->asJson()
                ->timeout(120)
                ->post($apiUrlPdf, $params);

            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="report.pdf"');
            }

            Log::warning('AR PDF generation failed', [
                'status'  => $response->status(),
                'ct'      => $response->header('Content-Type'),
                'payload' => collect($params)->except('DbPassword'),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to generate report (PDF).',
                'code'    => $response->status(),
                'details' => $response->body(),
            ], 500);
        }

        // Excel mode
        $response = Http::accept('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->asJson()
            ->timeout(180)
            ->post($apiUrl, $params);

        if ($response->successful()) {
            $fileName = ($safeName ?: 'ARReport') . '_' . $timestamp . '.xlsx';
            return response($response->body(), 200)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        }

        Log::warning('AR Excel export failed', [
            'status'  => $response->status(),
            'ct'      => $response->header('Content-Type'),
            'payload' => collect($params)->except('DbPassword'),
        ]);

        return response()->json([
            'status'  => 'error',
            'message' => 'Failed to generate report (Excel).',
            'code'    => $response->status(),
            'details' => $response->body(),
        ], 500);

    } catch (\Throwable $e) {
        Log::error('AR report API error', ['ex' => $e]);
        return response()->json([
            'status'  => 'error',
            'message' => 'Error calling Web API.',
            'details' => $e->getMessage(),
        ], 500);
    }
}








public function printAPReport(Request $request)
{
    $baseUrl   = rtrim(config('services.crystal.base'), '/');
    $apiUrl    = $baseUrl . config('services.crystal.ap_export_excel');
    $apiUrlPdf = $baseUrl . config('services.crystal.ap_generate_pdf');


    $creds = $this->tenantCreds($request);
    foreach (['host','database','username','password'] as $k) {
        if (empty($creds[$k])) {
            Log::warning('Tenant DB creds missing', ['have' => array_keys(array_filter($creds)), 'missing' => $k]);
            return response()->json([
                'status'  => 'error',
                'message' => "Missing database credential: $k (tenant middleware not set or connection misconfigured).",
            ], 400);
        }
    }


    $SprocMode  = $request->input('sprocMode');
    $SprocName  = $request->input('sprocName');
    $Export     = $request->input('export', 'N');
    $ReportName = $request->input('reportName');
    $UserCode   = $request->input('userCode');
    $Branch     = $request->input('branchCode');
    $StartDate  = date('Y-m-d', strtotime($request->input('startDate')));
    $EndDate    = date('Y-m-d', strtotime($request->input('endDate')));
    $SPayee  = $request->input('sPayee');
    $EPayee  = $request->input('ePayee');
    $formName   = $request->input('formName');


    $formPath     = base_path('scripts' . DIRECTORY_SEPARATOR . 'Reports');
    $libraryPath  = base_path('scripts' . DIRECTORY_SEPARATOR . 'Library');
    $formFullPath = $formName ? ($formPath . DIRECTORY_SEPARATOR . $formName) : '';
    $timestamp    = now()->format('Ymd_His');
    $safeName     = Str::of($ReportName)->replace(['/', '\\', '"', "'"], '_');
    $ExportFileName = storage_path('app/' . $safeName . " ($timestamp).xlsx");


    $params = [
        'SprocMode'       => $SprocMode,
        'SprocName'       => $SprocName,
        'Export'          => $Export,
        'ExportFileName'  => $ExportFileName,
        'ReportName'      => $ReportName,
        'UserCode'        => $UserCode,
        'LibraryPath'     => $libraryPath,
        'Branch'          => $Branch,
        'StartDate'       => $StartDate,
        'EndDate'         => $EndDate,
        'SPayee'          => $SPayee,
        'EPayee'          => $EPayee,
        'ReportPath'      => $formFullPath,
        'DbServer'        => $creds['host'],
        'DbName'          => $creds['database'],
        'DbUser'          => $creds['username'],
        'DbPassword'      => $creds['password'],
    ];

    try {
        if (strtoupper($Export) !== 'Y') {
            // PDF mode
            $response = Http::accept('application/pdf')
                ->asJson()
                ->timeout(120)
                ->post($apiUrlPdf, $params);

            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="report.pdf"');
            }

            Log::warning('AP PDF generation failed', [
                'status'  => $response->status(),
                'ct'      => $response->header('Content-Type'),
                'payload' => collect($params)->except('DbPassword'),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to generate report (PDF).',
                'code'    => $response->status(),
                'details' => $response->body(),
            ], 500);
        }

        // Excel mode
        $response = Http::accept('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->asJson()
            ->timeout(180)
            ->post($apiUrl, $params);

        if ($response->successful()) {
            $fileName = ($safeName ?: 'APReport') . '_' . $timestamp . '.xlsx';
            return response($response->body(), 200)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        }

        Log::warning('AP Excel export failed', [
            'status'  => $response->status(),
            'ct'      => $response->header('Content-Type'),
            'payload' => collect($params)->except('DbPassword'),
        ]);

        return response()->json([
            'status'  => 'error',
            'message' => 'Failed to generate report (Excel).',
            'code'    => $response->status(),
            'details' => $response->body(),
        ], 500);

    } catch (\Throwable $e) {
        Log::error('AP report API error', ['ex' => $e]);
        return response()->json([
            'status'  => 'error',
            'message' => 'Error calling Web API.',
            'details' => $e->getMessage(),
        ], 500);
    }
}







public function printGLReport(Request $request)
{
    $baseUrl   = rtrim(config('services.crystal.base'), '/');
    $apiUrl    = $baseUrl . config('services.crystal.gl_export_excel');
    $apiUrlPdf = $baseUrl . config('services.crystal.gl_generate_pdf');


    $creds = $this->tenantCreds($request);
    foreach (['host','database','username','password'] as $k) {
        if (empty($creds[$k])) {
            Log::warning('Tenant DB creds missing', ['have' => array_keys(array_filter($creds)), 'missing' => $k]);
            return response()->json([
                'status'  => 'error',
                'message' => "Missing database credential: $k (tenant middleware not set or connection misconfigured).",
            ], 400);
        }
    }


    $SprocMode  = $request->input('sprocMode');
    $SprocName  = $request->input('sprocName');
    $Export     = $request->input('export', 'N');
    $ReportName = $request->input('reportName');
    $UserCode   = $request->input('userCode');
    $Branch     = $request->input('branchCode');
    $StartDate  = date('Y-m-d', strtotime($request->input('startDate')));
    $EndDate    = date('Y-m-d', strtotime($request->input('endDate')));
    $SGL  = $request->input('sGL');
    $EGL  = $request->input('eGL');
    $SSL  = $request->input('sSL');
    $ESL  = $request->input('eSL');
    $SRC  = $request->input('sRC');
    $ERC  = $request->input('eRC');
    $formName   = $request->input('formName');


    $formPath     = base_path('scripts' . DIRECTORY_SEPARATOR . 'Reports');
    $libraryPath  = base_path('scripts' . DIRECTORY_SEPARATOR . 'Library');
    $formFullPath = $formName ? ($formPath . DIRECTORY_SEPARATOR . $formName) : '';
    $timestamp    = now()->format('Ymd_His');
    $safeName     = Str::of($ReportName)->replace(['/', '\\', '"', "'"], '_');
    $ExportFileName = storage_path('app/' . $safeName . " ($timestamp).xlsx");


    $params = [
        'SprocMode'       => $SprocMode,
        'SprocName'       => $SprocName,
        'Export'          => $Export,
        'ExportFileName'  => $ExportFileName,
        'ReportName'      => $ReportName,
        'UserCode'        => $UserCode,
        'LibraryPath'     => $libraryPath,
        'Branch'          => $Branch,
        'StartDate'       => $StartDate,
        'EndDate'         => $EndDate,
        'SGL'             => $SGL,
        'EGL'             => $EGL,
        'SSL'             => $SSL,
        'ESL'             => $ESL,
        'SRC'             => $SRC,
        'ERC'             => $ERC,
        'ReportPath'      => $formFullPath,
        'DbServer'        => $creds['host'],
        'DbName'          => $creds['database'],
        'DbUser'          => $creds['username'],
        'DbPassword'      => $creds['password'],
    ];

    try {
        if (strtoupper($Export) !== 'Y') {
            // PDF mode
            $response = Http::accept('application/pdf')
                ->asJson()
                ->timeout(120)
                ->post($apiUrlPdf, $params);

            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="report.pdf"');
            }

            Log::warning('GL PDF generation failed', [
                'status'  => $response->status(),
                'ct'      => $response->header('Content-Type'),
                'payload' => collect($params)->except('DbPassword'),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to generate report (PDF).',
                'code'    => $response->status(),
                'details' => $response->body(),
            ], 500);
        }

        // Excel mode
        $response = Http::accept('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->asJson()
            ->timeout(180)
            ->post($apiUrl, $params);

        if ($response->successful()) {
            $fileName = ($safeName ?: 'APReport') . '_' . $timestamp . '.xlsx';
            return response($response->body(), 200)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        }

        Log::warning('GL Excel export failed', [
            'status'  => $response->status(),
            'ct'      => $response->header('Content-Type'),
            'payload' => collect($params)->except('DbPassword'),
        ]);

        return response()->json([
            'status'  => 'error',
            'message' => 'Failed to generate report (Excel).',
            'code'    => $response->status(),
            'details' => $response->body(),
        ], 500);

    } catch (\Throwable $e) {
        Log::error('GL report API error', ['ex' => $e]);
        return response()->json([
            'status'  => 'error',
            'message' => 'Error calling Web API.',
            'details' => $e->getMessage(),
        ], 500);
    }
}







// public function exportHistoryReport(Request $request)
// {
//    // ----- .NET endpoint (JSON → Excel) -----
//     $baseUrl         = rtrim(config('services.crystal.base'), '/');
//     $apiUrlExcelJson = $baseUrl . config('services.crystal.history_export_excel'); // e.g. /api/report/ap-export-excel-json


//     // ----- tenant DB creds (for company/user lookups in .NET) -----
//     $creds = $this->tenantCreds($request);
//     foreach (['host','database','username','password'] as $k) {
//         if (empty($creds[$k])) {
//             Log::warning('Tenant DB creds missing', ['have' => array_keys(array_filter($creds)), 'missing' => $k]);
//             return response()->json([
//                 'status'  => 'error',
//                 'message' => "Missing database credential: $k.",
//             ], 400);
//         }
//     }

//     // ----- inputs from FE -----
//     $reportName = $request->input('reportName', 'Report');
//     $userCode   = $request->input('userCode');
//     $branch     = $request->input('branchCode');
//     $startDate  = $request->filled('startDate') ? date('Y-m-d', strtotime($request->input('startDate'))) : null;
//     $endDate    = $request->filled('endDate')   ? date('Y-m-d', strtotime($request->input('endDate')))   : null;

//     // Array of tabs from SVIHistory.jsx:
//     // [{ sheetName, headers: [...], rows: [ {col:val,...}, ... ] }, ...]
//     $jsonSheets = $request->input('jsonSheets', []);

//     if (!is_array($jsonSheets) || count($jsonSheets) === 0) {
//         return response()->json([
//             'status'  => 'error',
//             'message' => 'jsonSheets is required and must be a non-empty array for Excel export.',
//         ], 422);
//     }

//     // ----- filename for download -----
//     $timestamp    = now()->format('Ymd_His');
//     $safeName     = Str::of($reportName)->replace(['/', '\\', '"', "'"], '_');
//     $downloadName = ($safeName ?: 'Report') . '_' . $timestamp . '.xlsx';

//     // ----- payload for .NET -----
//     $paramsExcel = [
//         'ReportName' => $reportName,
//         'UserCode'   => $userCode,
//         'Branch'     => $branch,
//         'StartDate'  => $startDate,
//         'EndDate'    => $endDate,

//         // tabbed JSON data coming from FE
//         'JsonData'   => $jsonSheets,

//         // DB creds so .NET can fetch company/user info
//         'DbServer'   => $creds['host'],
//         'DbName'     => $creds['database'],
//         'DbUser'     => $creds['username'],
//         'DbPassword' => $creds['password'],
//     ];

//     try {
//         $response = Http::accept('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
//             ->asJson()
//             ->timeout(180)
//             ->post($apiUrlExcelJson, $paramsExcel);

//         if ($response->successful()) {
//             return response($response->body(), 200)
//                 ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
//                 ->header('Content-Disposition', 'attachment; filename="'.$downloadName.'"');
//         }

//         Log::warning('GL Excel export (JSON) failed', [
//             'status'  => $response->status(),
//             'ct'      => $response->header('Content-Type'),
//             'payload' => collect($paramsExcel)->except('DbPassword'),
//         ]);

//         return response()->json([
//             'status'  => 'error',
//             'message' => 'Failed to generate report (Excel).',
//             'code'    => $response->status(),
//             'details' => $response->body(),
//         ], 500);

//     } catch (\Throwable $e) {
//         Log::error('GL Excel export (JSON) exception', ['ex' => $e]);
//         return response()->json([
//             'status'  => 'error',
//             'message' => 'Error calling Web API.',
//             'details' => $e->getMessage(),
//         ], 500);
//     }
// }




public function exportHistoryReport(Request $request)
{
    // 0) Target URL
    $baseUrl = rtrim(config('services.crystal.base'), '/');                 // e.g. https://dotnet-host
    $path    = ltrim(config('services.crystal.history_export_excel'), '/'); // e.g. api/report/history-export-excel
    $apiUrl  = $baseUrl . '/' . $path;

    // 1) Validate FE inputs (legacy keys this action expects)
    $request->validate([
        'Branch' => 'required|string',
        'StartDate'  => 'nullable|date',
        'EndDate'    => 'nullable|date',
        'ReportName' => 'required|string',
        'UserCode'   => 'nullable|string',
        'JsonData' => 'required', // can be array-of-tabs OR {Meta,Data}
    ]);

    $branch    = (string) $request->input('Branch');
    $startDate = $request->filled('StartDate') ? date('Y-m-d', strtotime($request->input('StartDate'))) : null;
    $endDate   = $request->filled('EndDate')   ? date('Y-m-d', strtotime($request->input('EndDate')))   : null;
    $report    = (string) $request->input('ReportName');
    $userCode  = $request->input('UserCode');

    // 2) Normalize jsonSheets into canonical { Meta, Data }
    // Accept:
    //   A) array-of-tabs: [{ sheetName, rows }, ...]
    //   B) wrapped: { Meta:{...}, Data:{ "Tab": [ ... ] } }
    $jsonSheets = $request->input('JsonData');

    $normalized = [
        'Meta' => [
            'ReportName' => $report,
            'StartDate'  => $startDate,
            'EndDate'    => $endDate,
        ],
        'Data' => [],
    ];

    if (is_array($jsonSheets)) {
        $isList = array_is_list($jsonSheets);

        if ($isList) {
            // A) tabs array
            foreach ($jsonSheets as $tab) {
                $name = isset($tab['sheetName']) && $tab['sheetName'] !== '' ? $tab['sheetName'] : 'Sheet';
                $rows = isset($tab['rows']) && is_array($tab['rows']) ? $tab['rows'] : [];
                $normalized['Data'][$name] = $rows;
            }
        } else {
            // B) wrapped-like (tolerate inner casing)
            $meta = $jsonSheets['Meta'] ?? $jsonSheets['meta'] ?? [];
            $data = $jsonSheets['Data'] ?? $jsonSheets['data'] ?? [];

            if (is_array($meta)) {
                $normalized['Meta']['ReportName'] = $meta['ReportName'] ?? $meta['reportName'] ?? $normalized['Meta']['ReportName'];
                $normalized['Meta']['StartDate']  = $meta['StartDate']  ?? $meta['startDate']  ?? $normalized['Meta']['StartDate'];
                $normalized['Meta']['EndDate']    = $meta['EndDate']    ?? $meta['endDate']    ?? $normalized['Meta']['EndDate'];
            }

            if (is_array($data)) {
                foreach ($data as $tabName => $rows) {
                    $tabKey = $tabName ?: 'Sheet';
                    $normalized['Data'][$tabKey] = is_array($rows) ? $rows : [];
                }
            }
        }
    }

    // Guard: must have at least one tab key (even if rows are empty arrays)
    if (empty($normalized['Data']) || !is_array($normalized['Data'])) {
        return response()->json([
            'status'  => 'error',
            'message' => 'No sheets found to export.',
            'code'    => 400,
            'details' => 'Normalized.Data is empty.',
        ], 400);
    }

    // 3) Multi-tenant DB creds
    $creds = $this->tenantCreds($request); // implement per your tenancy
    foreach (['host','database','username','password'] as $k) {
        if (empty($creds[$k])) {
            \Log::warning('Tenant DB creds missing', ['missing' => $k]);
            return response()->json(['status' => 'error', 'message' => "Missing DB credential: $k"], 400);
        }
    }

    // 4) Build payload for .NET — be **very** compatible
    $jsonDataPascal = $normalized; // { Meta, Data }
    $jsonDataSnake  = [
        'meta' => [
            'report_name' => $jsonDataPascal['Meta']['ReportName'],
            'start_date'  => $jsonDataPascal['Meta']['StartDate'],
            'end_date'    => $jsonDataPascal['Meta']['EndDate'],
        ],
        'data' => [], // keep as array; JSON encodes to object with tab keys
    ];
    foreach ($jsonDataPascal['Data'] as $tab => $rows) {
        $jsonDataSnake['data'][$tab] = $rows;
    }

    $paramsExcel = [
        'ReportName' => $report,
        'UserCode'   => $userCode,
        'Branch'     => $branch,
        'StartDate'  => $startDate,
        'EndDate'    => $endDate,

        // Main (PascalCase) — typical ASP.NET binders prefer this
        'JsonData'   => $jsonDataPascal,

        // Fallback (snake_case) — for controllers expecting snake
        'json_data'  => $jsonDataSnake,

        // Tenant info
        'DbServer'   => $creds['host'],
        'DbName'     => $creds['database'],
        'DbUser'     => $creds['username'],
        'DbPassword' => $creds['password'],
    ];

    // Extra safety for legacy servers that look at top-level Data/Meta
    $paramsExcel['Data'] = $jsonDataPascal['Data'];
    $paramsExcel['Meta'] = $jsonDataPascal['Meta'];

    // 5) Filename
    $stamp        = now()->format('Ymd_His');
    $safeBaseName = \Illuminate\Support\Str::of($report)->replace(['/', '\\', '"', "'"], '_')->value() ?: 'Report';
    $downloadName = "{$safeBaseName}_{$stamp}.xlsx";

    // 6) Call .NET and stream back
    try {
        \Log::info('HistoryExport -> POSTing to .NET', [
            'url'     => $apiUrl,
            'payload' => collect($paramsExcel)->except('DbPassword'),
        ]);

        $dotnet = \Illuminate\Support\Facades\Http::withHeaders([
                'Accept'       => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(180)
            ->withBody(json_encode($paramsExcel, JSON_UNESCAPED_UNICODE), 'application/json')
            ->post($apiUrl);

        $ct = $dotnet->header('Content-Type', '');

        if ($dotnet->failed()) {
            $body = $dotnet->body();
            \Log::warning('History Excel export failed', [
                'status'  => $dotnet->status(),
                'ct'      => $ct,
                'payload' => collect($paramsExcel)->except('DbPassword'),
                'body'    => $body,
            ]);

            // Bubble up .NET’s exact message/status to the FE
            return response($body ?: json_encode([
                'status'  => 'error',
                'message' => 'Failed to generate Excel.',
            ]), $dotnet->status(), [
                'Content-Type' => $ct ?: 'application/json',
            ]);
        }

        // Success: stream XLSX
        return response($dotnet->body(), 200)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="'.$downloadName.'"');

    } catch (\Throwable $e) {
        \Log::error('History Excel export exception', ['ex' => $e]);
        return response()->json([
            'status'  => 'error',
            'message' => 'Error calling Web API.',
            'details' => $e->getMessage(),
        ], 500);
    }
}





public function upsertDocSign(Request $request)

    {   
        $documentID = $request->input('documentID');
        $checkedBy = $request->input('checkedBy');
        $notedBy = $request->input('notedBy');
        $approvedBy =$request->input('approvedBy');

    try {
        DB::statement(
            'EXEC sproc_PHP_DocSign @_mode = ?, @_tranId = ?, @_checkedBy = ?, @_notedBy = ?, @_approvedBy = ?',
            ['Upsert', $documentID, $checkedBy, $notedBy, $approvedBy]
        );

        return response()->json([
            'success' => true,
            'message' => "Docsign updated successfully"
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
    }









    public function getDocSign(Request $request)
    { 
     try {
        $id = $request->input('documentID');

        $results = DB::select(
            'EXEC sproc_PHP_DocSign @_mode = ?,@_tranId = ?',
            ['Get', $id] 
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
    }







    public function getAR_Report(Request $request) {
    // 1. Get the raw JSON and decode it
    $params = json_decode($request->input('PARAMS'), true);

    try {
        // 2. Map the JSON keys to the SPROC parameters
        // We use null coalescing (??) to handle missing filters safely
        $results = DB::select(
            'EXEC sproc_PHP_ExportReport_AR 
                @mode = ?, 
                @branchcode = ?, 
                @startdate = ?, 
                @enddate = ?, 
                @scustomer = ?, 
                @ecustomer = ?',
            [
                $params['mode'] ?? 'SVIListing',
                $params['branchcode'] ?? null,
                $params['startdate'] ?? null,
                $params['enddate'] ?? null,
                $params['scustomer'] ?? null,
                $params['ecustomer'] ?? null
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
    }





    public function getGL_Report(Request $request) {
    // 1. Decode the JSON string from the PARAMS input
    $params = json_decode($request->input('PARAMS'), true);

    try {
        // 2. Map all 10 parameters required by sproc_PHP_ExportReport_GL
        $results = DB::select(
            'EXEC sproc_PHP_ExportReport_GL 
                @mode = ?, 
                @branchcode = ?, 
                @startdate = ?, 
                @enddate = ?, 
                @sGL = ?, 
                @eGL = ?, 
                @sSL = ?, 
                @eSL = ?, 
                @sRC = ?, 
                @eRC = ?',
            [
                $params['mode'] ?? 'GLListing',
                $params['branchcode'] ?? null,
                $params['startDate'] ?? null,
                $params['endDate'] ?? null,
                $params['sGL'] ?? null,
                $params['eGL'] ?? null,
                $params['sSL'] ?? null,
                $params['eSL'] ?? null,
                $params['sRC'] ?? null,
                $params['eRC'] ?? null
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error generating GL Report: ' . $e->getMessage(),
        ], 500);
    }
    }





    public function getAP_Report(Request $request) {
    // 1. Get the raw JSON and decode it
    $params = json_decode($request->input('PARAMS'), true);

    try {
        $results = DB::select(
            'EXEC sproc_PHP_ExportReport_AR 
                @mode = ?, 
                @branchcode = ?, 
                @startdate = ?, 
                @enddate = ?, 
                @spayeeCode = ?, 
                @epayeeCode = ?',
            [
                $params['mode'] ?? 'SVIListing',
                $params['branchcode'] ?? null,
                $params['startdate'] ?? null,
                $params['enddate'] ?? null,
                $params['spayeeCode'] ?? null, 
                $params['epayeeCode'] ?? null
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

}

