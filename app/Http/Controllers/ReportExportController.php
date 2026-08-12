<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill; // correct enum
use Throwable;

class ReportExportController extends Controller
{
    public function historyExportExcel(Request $request)
    {
        // Trace request
        \Log::info('historyExportExcel hit', [
            'route'      => '/history-export-excel',
            'tenant'     => $request->header('X-Company-DB'),
            'reportName' => $request->input('reportName'),
            'startDate'  => $request->input('startDate'),
            'endDate'    => $request->input('endDate'),
        ]);

        // Validate payload
        $v = Validator::make($request->all(), [
            'branchCode'               => 'sometimes|string|nullable',
            'startDate'                => 'required|date',
            'endDate'                  => 'required|date',
            'reportName'               => 'sometimes|string|nullable',
            'userCode'                 => 'sometimes|string|nullable',
            'jsonSheets'               => 'required|array|min:1',
            'jsonSheets.*.sheetName'   => 'required|string',
            'jsonSheets.*.headers'     => 'required|array|min:1',
            'jsonSheets.*.rows'        => 'required|array',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payload. Expecting branchCode/startDate/endDate/jsonSheets[].',
                'errors'  => $v->errors(),
            ], 422);
        }

        try {
            // Meta
            $branchCode  = $request->input('branchCode');
            $startDate   = date('Y-m-d', strtotime($request->input('startDate')));
            $endDate     = date('Y-m-d', strtotime($request->input('endDate')));
            $reportTitle = $request->input('reportName', 'History Export');
            $userCode    = $request->input('userCode');

            // Normalize jsonSheets -> [['name','headers','rows']]
            $incomingSheets = (array) $request->input('jsonSheets', []);
            $tabs = [];

            foreach ($incomingSheets as $sheet) {
                $name    = $this->sanitizeSheetName((string) ($sheet['sheetName'] ?? 'Sheet'));
                $headers = is_array($sheet['headers'] ?? null) ? $sheet['headers'] : [];
                $rows    = is_array($sheet['rows'] ?? null)    ? $sheet['rows']    : [];

                if (empty($headers)) {
                    \Log::warning("historyExportExcel: Dropping sheet '{$name}' because headers are empty.");
                    continue;
                }

                $norm = [];
                foreach ($rows as $r) {
                    $r = (array) $r; // ensure array
                    $line = [];
                    foreach ($headers as $h) {
                        $line[$h] = array_key_exists($h, $r) ? $r[$h] : null;
                    }
                    $norm[] = $line;
                }

                $tabs[] = ['name' => $name, 'headers' => $headers, 'rows' => $norm];
            }

            if (empty($tabs)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid sheets to export (headers empty or jsonSheets missing).',
                ], 422);
            }

            // Banner info from tenant DB (safe)
            $companyName = $companyAddress = $companyTel = '';
            $extractedBy = 'Extracted By : Admin';
            $branchLine  = $branchCode ? ("Branch : " . $branchCode) : '';

            try {
                $row = DB::connection('tenant')
                    ->table('company')
                    ->selectRaw("TOP 1 comp_name, CONCAT(comp_addr1, comp_addr2, comp_addr3) AS address, tel_no")
                    ->first();

                if ($row) {
                    $companyName    = $row->comp_name ?? '';
                    $companyAddress = !empty($row->address) ? ('Address : ' . $row->address) : '';
                    $companyTel     = !empty($row->tel_no)  ? ('Tel No : ' . $row->tel_no)   : '';
                }
            } catch (Throwable $e) {
                \Log::warning("historyExportExcel: company lookup failed: " . $e->getMessage());
            }

            try {
                if (!empty($userCode)) {
                    $u = DB::connection('tenant')
                        ->table('USERS')
                        ->where('USER_CODE', $userCode)
                        ->select('USER_NAME')
                        ->first();
                    if ($u && !empty($u->USER_NAME)) {
                        $extractedBy = 'Extracted By : ' . $u->USER_NAME;
                    }
                }
            } catch (Throwable $e) {
                \Log::warning("historyExportExcel: user lookup failed: " . $e->getMessage());
            }

            // Multi-sheet export
            $export = new class(
                $tabs, $reportTitle, $startDate, $endDate,
                $companyName, $companyAddress, $companyTel, $extractedBy, $branchLine
            ) implements WithMultipleSheets {

                public function __construct(
                    private array $tabs,
                    private string $reportTitle,
                    private string $startDate,
                    private string $endDate,
                    private string $companyName,
                    private string $companyAddress,
                    private string $companyTel,
                    private string $extractedBy,
                    private string $branchLine
                ) {}

                public function sheets(): array
                {
                    $sheets = [];
                    foreach ($this->tabs as $tab) {
                        $sheets[] = new class(
                            $tab['name'], $tab['headers'], $tab['rows'],
                            $this->reportTitle, $this->startDate, $this->endDate,
                            $this->companyName, $this->companyAddress, $this->companyTel,
                            $this->extractedBy, $this->branchLine
                        ) implements FromArray, WithTitle, WithStyles, WithEvents, WithColumnFormatting {

                            private int $headerRowIndex = 1;
                            private int $colCount = 1;

                            public function __construct(
                                private string $sheetName,
                                private array $headers,
                                private array $rows,
                                private string $reportTitle,
                                private string $startDate,
                                private string $endDate,
                                private string $companyName,
                                private string $companyAddress,
                                private string $companyTel,
                                private string $extractedBy,
                                private string $branchLine
                            ) {}

                            public function title(): string { return $this->sheetName; }

                            public function array(): array
                            {
                                $out = [];
                                $line = 0;

                                if ($this->companyName !== '')    { $out[] = [$this->companyName];    $line++; }
                                if ($this->companyAddress !== '') { $out[] = [$this->companyAddress]; $line++; }
                                if ($this->companyTel !== '')     { $out[] = [$this->companyTel];     $line++; }
                                if ($this->branchLine !== '')     { $out[] = [$this->branchLine];     $line++; }

                                $title = $this->reportTitle . " ({$this->startDate} to {$this->endDate})";
                                $out[] = [$title]; $line++;

                                if ($this->extractedBy !== '')    { $out[] = [$this->extractedBy];    $line++; }
                                $out[] = ['Date & Time: ' . date('Y-m-d H:i:s')]; $line++;
                                $out[] = ['']; $line++; // spacer

                                $this->headerRowIndex = $line + 1;

                                if (empty($this->headers)) {
                                    $out[] = ['No rows'];
                                    $this->colCount = 1;
                                    return $out;
                                }

                                // headers
                                $out[] = $this->headers;
                                $this->colCount = count($this->headers);

                                // rows
                                foreach ($this->rows as $r) {
                                    $rowOut = [];
                                    foreach ($this->headers as $h) {
                                        $v = $r[$h] ?? null;
                                        $rowOut[] = $this->coerceValue($v);
                                    }
                                    $out[] = $rowOut;
                                }

                                return $out;
                            }

                            public function styles(Worksheet $sheet)
                            {
                                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

                                $r = 1;
                                if ($this->companyName !== '') { // company line style
                                    $sheet->getStyle("A{$r}")->applyFromArray([
                                        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0000FF']],
                                    ]);
                                    $r++;
                                }
                                if ($this->companyAddress !== '') $r++;
                                if ($this->companyTel !== '')     $r++;
                                if ($this->branchLine !== '')     $r++;

                                $sheet->getStyle("A{$r}")->applyFromArray([ // title
                                    'font' => ['bold' => true, 'italic' => true, 'color' => ['rgb' => '800080']],
                                ]);

                                // header row style
                                $hdr = $this->headerRowIndex;
                                if ($this->colCount > 0) {
                                    $end = Coordinate::stringFromColumnIndex($this->colCount);
                                    $sheet->getStyle("A{$hdr}:{$end}{$hdr}")->applyFromArray([
                                        'font' => ['bold' => true],
                                        'alignment' => ['horizontal' => 'center'],
                                        'fill' => [
                                            'fillType'   => Fill::FILL_SOLID,
                                            'startColor' => ['rgb' => 'ADD8E6'], // LightBlue
                                        ],
                                    ]);
                                }
                                return [];
                            }

                            public function columnFormats(): array
                            {
                                $formats = [];
                                $sample  = array_slice($this->rows, 0, 50);

                                foreach ($this->headers as $i => $h) {
                                    $isNum = true;
                                    $isDate = true;

                                    foreach ($sample as $r) {
                                        $v = $r[$h] ?? null;
                                        if ($v === null || $v === '') continue;

                                        $sv = is_string($v) ? str_replace([','], [''], $v) : $v;
                                        if (!is_numeric($sv)) $isNum = false;

                                        if (strtotime((string)$v) === false) $isDate = false;

                                        if (!$isNum && !$isDate) break;
                                    }

                                    $col = Coordinate::stringFromColumnIndex($i + 1);
                                    if ($isDate)      { $formats[$col] = NumberFormat::FORMAT_DATE_YYYYMMDD2; }
                                    elseif ($isNum)   { $formats[$col] = NumberFormat::FORMAT_NUMBER_00; }
                                }

                                return $formats;
                            }

                            public function registerEvents(): array
                            {
                                return [
                                    AfterSheet::class => function (AfterSheet $event) {
                                        $sheet = $event->sheet->getDelegate();

                                        // Auto-size
                                        for ($i = 1; $i <= max(1, $this->colCount); $i++) {
                                            $letter = Coordinate::stringFromColumnIndex($i);
                                            $event->sheet->getColumnDimension($letter)->setAutoSize(true);
                                        }

                                        // Auto-filter (header to last row)
                                        $lastRow = $sheet->getHighestRow();
                                        $lastCol = $sheet->getHighestColumn();
                                        if ($this->colCount > 0 && $lastRow >= $this->headerRowIndex) {
                                            $sheet->setAutoFilter("A{$this->headerRowIndex}:{$lastCol}{$lastRow}");
                                        }

                                        // Freeze above header (banner + header visible)
                                        $sheet->freezePane('A' . ($this->headerRowIndex + 1));
                                    },
                                ];
                            }

                            private function coerceValue($v)
                            {
                                if ($v === null) return '';
                                if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d H:i:s');

                                // numeric strings -> numbers (strip commas)
                                $sv = is_string($v) ? str_replace([','], [''], $v) : $v;
                                if (is_numeric($sv)) return 0 + $sv;

                                $ts = strtotime((string)$v);
                                if ($ts !== false) return date('Y-m-d H:i:s', $ts);

                                return (string)$v;
                            }
                        };
                    }
                    return $sheets;
                }
            };

            $fileSafe = preg_replace('/\s+/', '_', $reportTitle ?: 'History');
            $filename = $fileSafe . '_' . date('YmdHis') . '.xlsx';

            return Excel::download($export, $filename);

        } catch (Throwable $e) {
            \Log::error('historyExportExcel failed', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Export failed.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function sanitizeSheetName(string $name): string
    {
        $s = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/', '', $name);
        $s = trim($s);
        if ($s === '') $s = 'Sheet';
        if (mb_strlen($s) > 31) $s = mb_substr($s, 0, 31);
        return $s;
    }
}
