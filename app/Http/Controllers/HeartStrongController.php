<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HeartStrongController extends Controller
{
    private const OPTION_TABLE = 'HS_OPTION';
    private const DROPDOWN_TABLE = 'HS_DROPDOWN';
    private const DOC_TABLE = 'HS_DOC';
    private const MENU_TABLE = 'HS_MENU';
    private const MODULE_SNAPSHOT_DIRECTORY = 'app/heartstrong/modules';

    private const OPTION_LABELS = [
        'SO_DISCMODE' => 'SO Discount Mode',
        'SO_DISCLEVEL' => 'SO Discount Level',
        'SO_OPRICEMODE' => 'SO UPrice Mode',
        'SO_CPRICEMODE' => 'Posted SO UPrice Mode',
        'SO_OTHPAGE' => 'SO Other Info Page',
        'SO_DUPITEM' => 'SO - Allow Duplicate Item',
        'SO_APPLEVEL' => 'SO Approver Level',
        'SI_DISCMODE' => 'SI Discount Mode',
        'SI_DISCLEVEL' => 'SI Discount Level',
        'SI_WSOMODE' => 'SI Uprice Mode (With SO)',
        'SI_WOSOMODE' => 'SI Uprice Mode (Without SO)',
        'SI_BUYERWT' => 'SI Buyer\'s Weight',
        'SI_GLMODE' => 'GL - SI',
        'FGINV_RRMODE' => 'FGINV - RR',
        'RMINV_RRMODE' => 'RMINV - RR',
        'MSINV_RRMODE' => 'MSINV - RR',
        'FGINV_COSTING' => 'FG Inventory Cost',
        'RMINV_COSTING' => 'RM Inventory Cost',
        'MSINV_COSTING' => 'MS Inventory Cost',
        'FGINV_GLMODE' => 'GL - FGINV',
        'RMINV_GLMODE' => 'GL - RMINV',
        'MSINV_GLMODE' => 'GL - MSINV',
        'ITEM_DECQTY_PUR' => 'PUR Qty Decimal',
        'ITEM_DECQTY_MS' => 'MSINV Qty Decimal',
        'ITEM_DECUCOST_MS' => 'MSINV Ucost Decimal',
        'ITEM_DECQTY_RM' => 'RMINV Qty Decimal',
        'ITEM_DECUCOST_RM' => 'RMINV Ucost Decimal',
        'ITEM_DECQTY_FG' => 'FGINV Qty Decimal',
        'ITEM_DECUCOST_FG' => 'FGINV Ucost Decimal',
        'ITEM_DECSELLPRICE' => 'Item Decimal Sell Price',
        'PUR_DECUPRICE' => 'PUR Uprice Decimal',
        'RRAPP_AUTOEMAIL' => 'RR APP Auto Email',
        'PRAPP_AUTOEMAIL' => 'PR APP Auto Email',
        'POAPP_AUTOEMAIL' => 'PO APP Auto Email',
        'JOAPP_AUTOEMAIL' => 'JO APP Auto Email',
        'CVAPP_AUTOEMAIL' => 'CV APP Auto Email',
        'DR_OTHPAGE' => 'DR Other Info Page',
        'SI_OTHPAGE' => 'SI Other Info Page',
        'SOAPP_AUTOEMAIL' => 'SO APP Auto Email',
        'GL_CURRGLOBAL1' => 'GL Currency Global 1',
        'GL_CURRGLOBAL2' => 'GL Currency Global 2',
        'GL_CURRGLOBAL3' => 'GL Currency Global 3',
        'GL_CURRMODE' => 'GL Currency Mode',
        'PR_APPLEVEL' => 'PR Approval Level',
        'PO_APPLEVEL' => 'PO Approval Level',
        'JO_APPLEVEL' => 'JO Approval Level',
        'PURUOM2_MODE' => 'PUR UOM2',
        'INVUOM2_MODE' => 'INV UOMS2',
        'PATH_PRINTING' => 'Path Printing',
        'GL_CURRDEFAULT' => 'GL Currency Default',
        'MONTH13_MODE' => '13th Month Mode',
        'MONTH13_CODE' => '13th Month Code',
    ];

    public function options()
    {
        $connection = DB::connection('tenant');
        $row = $connection->table(self::OPTION_TABLE)->first();

        if (!$row) {
            return response()->json([
                'success' => true,
                'data' => ['fields' => []],
            ]);
        }

        $columns = collect($this->tableColumns(self::OPTION_TABLE))
            ->keyBy('name');

        $fields = [];

        foreach ((array) $row as $name => $value) {
            $column = $columns->get($name);

            /*
             * Do not expose the HS_OPTION identity/primary-key field as
             * an editable setup value.
             */
            if (
                !$column ||
                $column['isIdentity'] ||
                $column['isPrimaryKey'] ||
                strtoupper($name) === 'ID'
            ) {
                continue;
            }

            $isSwitch = $this->isSwitchOption($name, $column, $value);
            $switchValues = $isSwitch
                ? $this->switchPair($column, $value)
                : [null, null];

            $fields[] = [
                'name' => $name,
                'label' => self::OPTION_LABELS[$name]
                    ?? Str::of($name)
                        ->replace('_', ' ')
                        ->lower()
                        ->title()
                        ->toString(),
                'controlType' => $isSwitch
                    ? 'switch'
                    : $this->optionControlType($column),
                'enabled' => $isSwitch
                    ? $this->decodeSwitchValue($value)
                    : null,
                'enabledValue' => $switchValues[0],
                'disabledValue' => $switchValues[1],
                'value' => $value,
                'originalValue' => $value,
                'dataType' => $column['dataType'],
                'nullable' => $column['nullable'],
                'maxLength' => $column['maxLength'],
                'numberStep' => $this->optionNumberStep($column),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'fields' => $fields,
                'rowId' => ((array) $row)['ID'] ?? null,
            ],
        ]);
    }

    public function updateOption(Request $request)
    {
        $request->validate([
            'field' => ['required', 'string'],
            'enabled' => ['sometimes', 'boolean'],
            'value' => ['sometimes', 'nullable'],
        ]);

        $field = (string) $request->input('field');

        $columns = collect($this->tableColumns(self::OPTION_TABLE))
            ->keyBy('name');

        $column = $columns->get($field);

        if (
            !$column ||
            $column['isIdentity'] ||
            $column['isPrimaryKey'] ||
            strtoupper($field) === 'ID'
        ) {
            throw ValidationException::withMessages([
                'field' => 'The selected HS_OPTION field cannot be updated.',
            ]);
        }

        $connection = DB::connection('tenant');
        $row = $connection->table(self::OPTION_TABLE)->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'HS_OPTION does not contain a setup row.',
            ], 404);
        }

        $rowData = (array) $row;
        $currentValue = $rowData[$field] ?? null;
        $payload = $request->all();
        $isSwitch = $this->isSwitchOption(
            $field,
            $column,
            $currentValue
        );

        if (array_key_exists('enabled', $payload)) {
            if (!$isSwitch) {
                throw ValidationException::withMessages([
                    'field' => 'The selected HS_OPTION field is not a binary switch.',
                ]);
            }

            $enabled = (bool) $request->boolean('enabled');
            $nextValue = $this->encodeSwitchValue(
                $currentValue,
                $column['dataType'],
                $enabled
            );
        } elseif (array_key_exists('value', $payload)) {
            if ($isSwitch) {
                throw ValidationException::withMessages([
                    'value' => 'Use the enabled property when updating a binary HS_OPTION field.',
                ]);
            }

            $enabled = null;
            $nextValue = $this->normalizeOptionValue(
                $field,
                $column,
                $request->input('value')
            );
        } else {
            throw ValidationException::withMessages([
                'value' => 'Supply enabled for a switch or value for another HS_OPTION setting.',
            ]);
        }

        $query = $connection->table(self::OPTION_TABLE);
        $primaryKeys = $columns
            ->filter(fn ($item) => $item['isPrimaryKey'])
            ->keys()
            ->values();

        if ($primaryKeys->isNotEmpty()) {
            foreach ($primaryKeys as $key) {
                $keyValue = $rowData[$key] ?? null;

                $keyValue === null
                    ? $query->whereNull($key)
                    : $query->where($key, $keyValue);
            }
        } elseif (array_key_exists('ID', $rowData)) {
            $query->where('ID', $rowData['ID']);
        }

        $updated = $query->update([$field => $nextValue]);

        return response()->json([
            'success' => true,
            'message' => 'HS_OPTION updated.',
            'data' => [
                'field' => $field,
                'enabled' => $enabled,
                'storedValue' => $nextValue,
                'updatedRows' => $updated,
            ],
        ]);
    }


    /**
     * Return HS_DOC records and dynamic column metadata.
     *
     * The UI is schema-driven. New HS_DOC columns automatically appear
     * in the table and editor unless they are identity columns.
     */
    public function documents(Request $request)
    {
        $columns = collect($this->tableColumns(self::DOC_TABLE));
        $columnNames = $columns->pluck('name')->all();

        $docCodeColumn = $this->firstExistingColumn(
            $columnNames,
            ['DOC_CODE']
        );

        $moduleCodeColumn = $this->firstExistingColumn(
            $columnNames,
            ['MODULE_CODE']
        );

        $statusColumn = $this->firstExistingColumn(
            $columnNames,
            ['DOC_STAT']
        );

        if (!$docCodeColumn) {
            return response()->json([
                'success' => false,
                'message' => 'HS_DOC requires the DOC_CODE column.',
                'code' => 'HS_DOC_DOC_CODE_MISSING',
            ], 422);
        }

        $primaryKeys = $columns
            ->filter(fn ($column) => $column['isPrimaryKey'])
            ->pluck('name')
            ->values()
            ->all();

        $docIdColumn = $this->firstExistingColumn(
            $columnNames,
            ['DOC_ID']
        );

        $keyColumns = $primaryKeys;

        if (empty($keyColumns) && $docIdColumn) {
            $keyColumns = [$docIdColumn];
        }

        if (empty($keyColumns)) {
            $keyColumns = [$docCodeColumn];
        }

        $selectedModuleCode = trim(
            (string) $request->query('moduleCode', '')
        );

        $selectedStatus = trim(
            (string) $request->query('docStatus', '')
        );

        $connection = DB::connection('tenant');

        $query = $connection->table(self::DOC_TABLE);

        if ($moduleCodeColumn && $selectedModuleCode !== '') {
            $query->where(
                $moduleCodeColumn,
                $selectedModuleCode
            );
        }

        if ($statusColumn && $selectedStatus !== '') {
            $query->where(
                $statusColumn,
                $selectedStatus
            );
        }

        $totalRows = (clone $query)->count();

        if ($moduleCodeColumn) {
            $query->orderBy($moduleCodeColumn);
        }

        $query->orderBy($docCodeColumn);

        $rows = $query
            ->limit(5000)
            ->get();

        $moduleCodes = $moduleCodeColumn
            ? $connection
                ->table(self::DOC_TABLE)
                ->select($moduleCodeColumn)
                ->whereNotNull($moduleCodeColumn)
                ->whereRaw(
                    'LTRIM(RTRIM('
                    . $connection->getQueryGrammar()->wrap(
                        $moduleCodeColumn
                    )
                    . ")) <> ''"
                )
                ->distinct()
                ->orderBy($moduleCodeColumn)
                ->pluck($moduleCodeColumn)
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values()
            : collect();

        $statuses = $statusColumn
            ? $connection
                ->table(self::DOC_TABLE)
                ->select($statusColumn)
                ->whereNotNull($statusColumn)
                ->whereRaw(
                    'LTRIM(RTRIM('
                    . $connection->getQueryGrammar()->wrap(
                        $statusColumn
                    )
                    . ")) <> ''"
                )
                ->distinct()
                ->orderBy($statusColumn)
                ->pluck($statusColumn)
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values()
            : collect();

        $labels = [
            'DOC_ID' => 'Document ID',
            'DOC_CODE' => 'Document Code',
            'MODULE_CODE' => 'Module Code',
            'DOC_NAME' => 'Document Name',
            'DOC_SERIES' => 'Document Series',
            'DOC_STAT' => 'Document Status',
            'DOC_CENTRAL' => 'Centralized',
            'DOC_APP' => 'Approval Required',
            'DOC_UPLOAD' => 'Upload Enabled',
            'DOC_LENGTH' => 'Document Length',
            'FORM_NAME' => 'Form Name',
            'ISO_NO' => 'ISO Number',
            'WTBUSTYLE' => 'Business Style',
            'FORM_TITLE' => 'Form Title',
        ];

        $yesNoColumns = [
            'DOC_CENTRAL',
            'DOC_APP',
            'DOC_UPLOAD',
        ];

        $responseColumns = $columns
            ->map(function ($column) use (
                $labels,
                $yesNoColumns
            ) {
                $name = strtoupper(
                    (string) $column['name']
                );

                $inputType = in_array(
                    $column['dataType'],
                    [
                        'tinyint',
                        'smallint',
                        'int',
                        'bigint',
                        'decimal',
                        'numeric',
                        'float',
                        'real',
                        'money',
                        'smallmoney',
                    ],
                    true
                ) ? 'number' : 'text';

                $options = [];

                if (in_array($name, $yesNoColumns, true)) {
                    $inputType = 'select';
                    $options = [
                        ['value' => 'Y', 'label' => 'Yes'],
                        ['value' => 'N', 'label' => 'No'],
                    ];
                } elseif ($name === 'DOC_STAT') {
                    $inputType = 'select';
                    $options = [
                        ['value' => 'Active', 'label' => 'Active'],
                        ['value' => 'Inactive', 'label' => 'Inactive'],
                    ];
                } elseif ($name === 'DOC_SERIES') {
                    $inputType = 'select';
                    $options = [
                        ['value' => 'Auto', 'label' => 'Auto'],
                        ['value' => 'System', 'label' => 'System'],
                        ['value' => 'Manual', 'label' => 'Manual'],
                    ];
                }

                return [
                    ...$column,
                    'label' => $labels[$name]
                        ?? Str::of($column['name'])
                            ->replace('_', ' ')
                            ->lower()
                            ->title()
                            ->toString(),
                    'inputType' => $inputType,
                    'options' => $options,
                    'allowBlank' => $name !== 'DOC_SERIES',
                    'defaultValue' => $name === 'DOC_SERIES'
                        ? 'Auto'
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'columns' => $responseColumns,
                'keyColumns' => $keyColumns,
                'docCodeColumn' => $docCodeColumn,
                'moduleCodeColumn' => $moduleCodeColumn,
                'statusColumn' => $statusColumn,
                'moduleCodes' => $moduleCodes,
                'statuses' => $statuses,
                'selectedModuleCode' => $selectedModuleCode,
                'selectedStatus' => $selectedStatus,
                'totalRows' => $totalRows,
                'rows' => $rows,
            ],
        ]);
    }

    public function saveDocument(Request $request)
    {
        $validated = $request->validate([
            'keys' => ['nullable', 'array'],
            'values' => ['required', 'array', 'min:1'],
        ]);

        $columns = collect(
            $this->tableColumns(self::DOC_TABLE)
        )->keyBy('name');

        $values = collect($validated['values'])
            ->filter(
                fn ($value, $key) =>
                    $columns->has($key)
            )
            ->reject(
                fn ($value, $key) =>
                    $columns[$key]['isIdentity']
            )
            ->map(function ($value, $key) use ($columns) {
                if (
                    $value === ''
                    && $columns[$key]['nullable']
                ) {
                    return null;
                }

                return $value;
            })
            ->all();

        if (empty($values)) {
            throw ValidationException::withMessages([
                'values' => 'No valid HS_DOC values were supplied.',
            ]);
        }

        /*
         * Document Series is restricted to Auto, System, or Manual.
         * New/blank values default to Auto.
         */
        $docSeriesColumn = $columns
            ->keys()
            ->first(
                fn ($columnName) =>
                    strtoupper((string) $columnName)
                    === 'DOC_SERIES'
            );

        if ($docSeriesColumn) {
            $series = trim(
                (string) (
                    $values[$docSeriesColumn] ?? ''
                )
            );

            if ($series === '') {
                $series = 'Auto';
            }

            $seriesMap = [
                'auto' => 'Auto',
                'system' => 'System',
                'manual' => 'Manual',
            ];

            $normalizedSeries = strtolower($series);

            if (!array_key_exists(
                $normalizedSeries,
                $seriesMap
            )) {
                throw ValidationException::withMessages([
                    'values.DOC_SERIES' =>
                        'Document Series must be Auto, System, or Manual.',
                ]);
            }

            $values[$docSeriesColumn] =
                $seriesMap[$normalizedSeries];
        }

        $connection = DB::connection('tenant');
        $keys = $validated['keys'] ?? null;

        if (is_array($keys) && !empty($keys)) {
            $query = $connection->table(self::DOC_TABLE);
            $this->applyKeys($query, $keys, $columns);

            $updated = $query->update($values);

            return response()->json([
                'success' => true,
                'message' => 'Document setup updated.',
                'data' => [
                    'updatedRows' => $updated,
                ],
            ]);
        }

        $connection
            ->table(self::DOC_TABLE)
            ->insert($values);

        return response()->json([
            'success' => true,
            'message' => 'Document setup added.',
        ]);
    }

    public function documentDropdowns(Request $request)
    {
        $columns = $this->tableColumns(self::DROPDOWN_TABLE);
        $columnCollection = collect($columns);

        $docCodeColumn = $columnCollection
            ->first(
                fn ($column) =>
                    strtolower((string) $column['name']) === 'doc_code'
            )['name'] ?? null;

        if (!$docCodeColumn) {
            return response()->json([
                'success' => false,
                'message' => 'HS_DROPDOWN requires the DOC_CODE column.',
                'code' => 'HS_DROPDOWN_DOC_CODE_MISSING',
            ], 422);
        }

        $primaryKeys = $columnCollection
            ->filter(fn ($column) => $column['isPrimaryKey'])
            ->pluck('name')
            ->values()
            ->all();

        $keyColumns = $primaryKeys ?: $columnCollection
            ->pluck('name')
            ->values()
            ->all();

        $selectedDocCode = trim(
            (string) $request->query('docCode', '')
        );

        $connection = DB::connection('tenant');

        $docCodes = $connection
            ->table(self::DROPDOWN_TABLE)
            ->select($docCodeColumn)
            ->whereNotNull($docCodeColumn)
            ->whereRaw(
                'LTRIM(RTRIM('
                . $connection->getQueryGrammar()->wrap($docCodeColumn)
                . ")) <> ''"
            )
            ->distinct()
            ->orderBy($docCodeColumn)
            ->pluck($docCodeColumn)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        $rowsQuery = $connection
            ->table(self::DROPDOWN_TABLE);

        if ($selectedDocCode !== '') {
            $rowsQuery->where(
                $docCodeColumn,
                $selectedDocCode
            );
        }

        $totalRows = (clone $rowsQuery)->count();

        $orderColumns = [
            $docCodeColumn,
            $columnCollection
                ->first(
                    fn ($column) =>
                        strtolower((string) $column['name'])
                        === 'dropdown_column'
                )['name'] ?? null,
            $columnCollection
                ->first(
                    fn ($column) =>
                        strtolower((string) $column['name'])
                        === 'dropdown_code'
                )['name'] ?? null,
        ];

        foreach (array_filter(array_unique($orderColumns)) as $orderColumn) {
            $rowsQuery->orderBy($orderColumn);
        }

        $rows = $rowsQuery
            ->limit(2000)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'columns' => $columnCollection
                    ->map(function ($column) {
                        return [
                            ...$column,
                            'label' => Str::of($column['name'])
                                ->replace('_', ' ')
                                ->lower()
                                ->title()
                                ->toString(),
                        ];
                    })
                    ->values(),
                'keyColumns' => $keyColumns,
                'docCodeColumn' => $docCodeColumn,
                'docCodes' => $docCodes,
                'selectedDocCode' => $selectedDocCode,
                'totalRows' => $totalRows,
                'rows' => $rows,
            ],
        ]);
    }

    public function saveDocumentDropdown(Request $request)
    {
        $validated = $request->validate([
            'keys' => ['nullable', 'array'],
            'values' => ['required', 'array'],
        ]);

        $columns = collect($this->tableColumns(self::DROPDOWN_TABLE))
            ->keyBy('name');

        $values = collect($validated['values'])
            ->filter(fn ($value, $key) => $columns->has($key))
            ->reject(fn ($value, $key) => $columns[$key]['isIdentity'])
            ->map(function ($value, $key) use ($columns) {
                if ($value === '' && $columns[$key]['nullable']) {
                    return null;
                }

                return $value;
            })
            ->all();

        if (empty($values)) {
            throw ValidationException::withMessages([
                'values' => 'No valid HS_DROPDOWN values were supplied.',
            ]);
        }

        $connection = DB::connection('tenant');
        $keys = $validated['keys'] ?? null;

        if (is_array($keys) && !empty($keys)) {
            $query = $connection->table(self::DROPDOWN_TABLE);
            $this->applyKeys($query, $keys, $columns);
            $query->update($values);
            $message = 'Document dropdown updated.';
        } else {
            $connection->table(self::DROPDOWN_TABLE)->insert($values);
            $message = 'Document dropdown added.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function deleteDocumentDropdown(Request $request)
    {
        $validated = $request->validate([
            'keys' => ['required', 'array', 'min:1'],
        ]);

        $columns = collect($this->tableColumns(self::DROPDOWN_TABLE))
            ->keyBy('name');

        $query = DB::connection('tenant')->table(self::DROPDOWN_TABLE);
        $this->applyKeys($query, $validated['keys'], $columns);

        $deleted = $query->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'The HS_DROPDOWN record was not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document dropdown deleted.',
        ]);
    }


    /**
     * Return the permanent HS_MENU master stored in a tenant-specific JSON
     * file and compare it with the rows currently installed in HS_MENU.
     *
     * A row that exists in HS_MENU is licensed/installed.
     * A row missing from HS_MENU is unlicensed/removed but can be restored
     * from the JSON master.
     */
    public function modules()
    {
        try {
            $schemaColumns = collect(
                $this->tableColumns(self::MENU_TABLE)
            );

            if ($schemaColumns->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'HS_MENU was not found in the selected tenant database.',
                    'code' => 'HS_MENU_NOT_FOUND',
                ], 404);
            }

            $columnNames = $schemaColumns
                ->pluck('name')
                ->values()
                ->all();

            $moduleCodeColumn = $this->firstExistingColumn(
                $columnNames,
                ['MODULE_CODE']
            );

            $moduleNameColumn = $this->firstExistingColumn(
                $columnNames,
                ['MODULE']
            );

            $menuCodeColumn = $this->firstExistingColumn(
                $columnNames,
                ['MENU_CODE']
            );

            $menuNameColumn = $this->firstExistingColumn(
                $columnNames,
                ['MENU_NAME']
            );

            $requiredColumns = [
                'MODULE_CODE' => $moduleCodeColumn,
                'MODULE' => $moduleNameColumn,
                'MENU_CODE' => $menuCodeColumn,
                'MENU_NAME' => $menuNameColumn,
            ];

            $missingColumns = collect($requiredColumns)
                ->filter(fn ($column) => !$column)
                ->keys()
                ->values();

            if ($missingColumns->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'HS_MENU is missing required columns: '
                        . $missingColumns->implode(', ')
                        . '.',
                    'code' => 'HS_MENU_COLUMN_MISSING',
                ], 422);
            }

            $idColumn = $this->firstExistingColumn(
                $columnNames,
                ['ID']
            );

            $subMenuColumn = $this->firstExistingColumn(
                $columnNames,
                ['SUB_MENU']
            );

            $pathColumn = $this->firstExistingColumn(
                $columnNames,
                ['PATH']
            );

            $componentKeyColumn = $this->firstExistingColumn(
                $columnNames,
                ['COMPONENT_KEY']
            );

            $visibilityColumn = $this->firstExistingColumn(
                $columnNames,
                ['IS_VISIBLE']
            );

            $modalColumn = $this->firstExistingColumn(
                $columnNames,
                ['IS_MODAL']
            );

            $sortModuleColumn = $this->firstExistingColumn(
                $columnNames,
                ['SORT_MODULE']
            );

            $sortSubmenuColumn = $this->firstExistingColumn(
                $columnNames,
                ['SORT_SUBMENU']
            );

            $sortItemColumn = $this->firstExistingColumn(
                $columnNames,
                ['SORT_ITEM']
            );

            $snapshotKeyColumns = $this->moduleSnapshotKeyColumns(
                $columnNames
            );

            $currentRows = $this->currentModuleRows();

            $snapshot = $this->ensureModuleSnapshot(
                $schemaColumns,
                $currentRows,
                $snapshotKeyColumns
            );

            $snapshotRows = collect($snapshot['rows'] ?? [])
                ->map(
                    fn ($row) =>
                        $this->sanitizeModuleSnapshotRow(
                            (array) $row
                        )
                )
                ->values();

            $this->assertUniqueModuleKeys(
                $snapshotRows,
                $snapshotKeyColumns,
                'JSON master'
            );

            $currentBySnapshotKey = $currentRows
                ->keyBy(
                    fn ($row) =>
                        $this->moduleRowKey(
                            (array) $row,
                            $snapshotKeyColumns
                        )
                );

            $modules = $snapshotRows
                ->filter(function ($row) use ($moduleCodeColumn) {
                    return trim(
                        (string) (
                            $row[$moduleCodeColumn] ?? ''
                        )
                    ) !== '';
                })
                ->groupBy(function ($row) use (
                    $moduleCodeColumn,
                    $moduleNameColumn
                ) {
                    $moduleCode = trim(
                        (string) (
                            $row[$moduleCodeColumn] ?? ''
                        )
                    );

                    $moduleName = trim(
                        (string) (
                            $row[$moduleNameColumn] ?? ''
                        )
                    );

                    return $this->moduleGroupKey(
                        $moduleCode,
                        $moduleName
                    );
                })
                ->map(function ($group, $moduleKey) use (
                    $currentBySnapshotKey,
                    $snapshotKeyColumns,
                    $moduleCodeColumn,
                    $moduleNameColumn,
                    $menuCodeColumn,
                    $menuNameColumn,
                    $idColumn,
                    $subMenuColumn,
                    $pathColumn,
                    $componentKeyColumn,
                    $visibilityColumn,
                    $modalColumn,
                    $sortModuleColumn,
                    $sortSubmenuColumn,
                    $sortItemColumn
                ) {
                    $sortedRows = $group
                        ->sortBy(function ($row) use (
                            $sortModuleColumn,
                            $sortSubmenuColumn,
                            $sortItemColumn,
                            $menuCodeColumn
                        ) {
                            return sprintf(
                                '%010d|%010d|%010d|%s',
                                $sortModuleColumn
                                    ? (int) (
                                        $row[$sortModuleColumn]
                                        ?? 0
                                    )
                                    : 0,
                                $sortSubmenuColumn
                                    ? (int) (
                                        $row[$sortSubmenuColumn]
                                        ?? 0
                                    )
                                    : 0,
                                $sortItemColumn
                                    ? (int) (
                                        $row[$sortItemColumn]
                                        ?? 0
                                    )
                                    : 0,
                                (string) (
                                    $row[$menuCodeColumn] ?? ''
                                )
                            );
                        })
                        ->values();

                    $first = $sortedRows->first();

                    $moduleCode = trim(
                        (string) (
                            $first[$moduleCodeColumn] ?? ''
                        )
                    );

                    $moduleName = trim(
                        (string) (
                            $first[$moduleNameColumn]
                            ?? $moduleCode
                        )
                    );

                    $items = $sortedRows
                        ->map(function ($snapshotRow) use (
                            $currentBySnapshotKey,
                            $snapshotKeyColumns,
                            $menuCodeColumn,
                            $menuNameColumn,
                            $idColumn,
                            $subMenuColumn,
                            $pathColumn,
                            $componentKeyColumn,
                            $visibilityColumn,
                            $modalColumn,
                            $sortItemColumn
                        ) {
                            $snapshotKey =
                                $this->moduleRowKey(
                                    (array) $snapshotRow,
                                    $snapshotKeyColumns
                                );

                            $currentRow =
                                $currentBySnapshotKey->get(
                                    $snapshotKey
                                );

                            $exists = $currentRow !== null;

                            $displayRow = $exists
                                ? array_replace(
                                    (array) $snapshotRow,
                                    (array) $currentRow
                                )
                                : (array) $snapshotRow;

                            return [
                                'snapshotKey' => $snapshotKey,
                                'id' => $idColumn
                                    ? (
                                        $displayRow[$idColumn]
                                        ?? null
                                    )
                                    : null,
                                'menuCode' => (string) (
                                    $displayRow[
                                        $menuCodeColumn
                                    ] ?? ''
                                ),
                                'menuName' => (string) (
                                    $displayRow[
                                        $menuNameColumn
                                    ]
                                    ?? $displayRow[
                                        $menuCodeColumn
                                    ]
                                    ?? 'Unnamed Menu'
                                ),
                                'subMenu' => $subMenuColumn
                                    ? (string) (
                                        $displayRow[
                                            $subMenuColumn
                                        ] ?? ''
                                    )
                                    : '',
                                'path' => $pathColumn
                                    ? (string) (
                                        $displayRow[
                                            $pathColumn
                                        ] ?? ''
                                    )
                                    : '',
                                'componentKey' =>
                                    $componentKeyColumn
                                        ? (string) (
                                            $displayRow[
                                                $componentKeyColumn
                                            ] ?? ''
                                        )
                                        : '',
                                'isVisible' =>
                                    $visibilityColumn
                                        ? (int) (
                                            $displayRow[
                                                $visibilityColumn
                                            ] ?? 0
                                        ) === 1
                                        : false,
                                'isModal' => $modalColumn
                                    ? (int) (
                                        $displayRow[
                                            $modalColumn
                                        ] ?? 0
                                    ) === 1
                                    : false,
                                'exists' => $exists,
                                'licensed' => $exists,
                                'sortOrder' =>
                                    $sortItemColumn
                                        ? (int) (
                                            $displayRow[
                                                $sortItemColumn
                                            ] ?? 0
                                        )
                                        : 0,
                            ];
                        })
                        ->values();

                    $existingCount = $items
                        ->filter(
                            fn ($item) => $item['exists']
                        )
                        ->count();

                    $menuCount = $items->count();

                    return [
                        'key' => (string) $moduleKey,
                        'code' => (string) $moduleCode,
                        'name' => $moduleName !== ''
                            ? $moduleName
                            : (
                                $moduleCode !== ''
                                    ? $moduleCode
                                    : 'Unnamed Module'
                            ),
                        'sortOrder' => $sortModuleColumn
                            ? (int) (
                                $sortedRows->min(
                                    $sortModuleColumn
                                ) ?? 0
                            )
                            : 0,
                        'menuCount' => $menuCount,
                        'existingCount' =>
                            $existingCount,
                        'installedCount' =>
                            $existingCount,
                        'missingCount' =>
                            $menuCount - $existingCount,
                        'licensedCount' =>
                            $existingCount,
                        'unlicensedCount' =>
                            $menuCount - $existingCount,
                        'fullyInstalled' =>
                            $menuCount > 0
                            && $existingCount === $menuCount,
                        'fullyRemoved' =>
                            $existingCount === 0,
                        'items' => $items,
                    ];
                })
                ->sortBy(function ($module) {
                    return sprintf(
                        '%010d|%s',
                        (int) $module['sortOrder'],
                        (string) $module['code']
                    );
                })
                ->values();

            $context = $this->moduleSnapshotContext();

            return response()->json([
                'success' => true,
                'data' => [
                    'modules' => $modules,
                    'mode' => 'delete_restore',
                    'sourceTable' => self::MENU_TABLE,
                    'snapshotFile' =>
                        $context['relativePath'],
                    'snapshotCreatedAt' =>
                        $snapshot['createdAt'] ?? null,
                    'snapshotUpdatedAt' =>
                        $snapshot['updatedAt'] ?? null,
                    'snapshotKeyColumns' =>
                        $snapshotKeyColumns,
                    'totalMasterRows' =>
                        $snapshotRows->count(),
                    'totalExistingRows' =>
                        $snapshotRows
                            ->filter(function ($row) use (
                                $currentBySnapshotKey,
                                $snapshotKeyColumns
                            ) {
                                return $currentBySnapshotKey
                                    ->has(
                                        $this->moduleRowKey(
                                            (array) $row,
                                            $snapshotKeyColumns
                                        )
                                    );
                            })
                            ->count(),
                    'totalMissingRows' =>
                        $snapshotRows
                            ->reject(function ($row) use (
                                $currentBySnapshotKey,
                                $snapshotKeyColumns
                            ) {
                                return $currentBySnapshotKey
                                    ->has(
                                        $this->moduleRowKey(
                                            (array) $row,
                                            $snapshotKeyColumns
                                        )
                                    );
                            })
                            ->count(),
                    'subMenuColumn' =>
                        $subMenuColumn,
                    'groupedByModuleOnly' => false,
                    'groupedByModuleCodeAndName' => true,
                    'tenant' => [
                        'database' =>
                            $context['database'],
                        'host' => $context['host'],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error(
                'HeartStrong module JSON load failed',
                [
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to load JSON-backed module licensing.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete or restore one menu row or a complete module.
     *
     * Menu payload:
     * {
     *   "scope": "menu",
     *   "snapshotKey": "<sha256>",
     *   "enabled": false
     * }
     *
     * Module payload:
     * {
     *   "scope": "module",
     *   "moduleCode": "VE",
     *   "moduleName": "Vehicle Services",
     *   "enabled": false
     * }
     */
    public function updateModule(Request $request)
    {
        $validated = $request->validate([
            'scope' => [
                'sometimes',
                'string',
                'in:menu,module',
            ],
            'snapshotKey' => [
                'nullable',
                'string',
                'size:64',
            ],
            'moduleCode' => [
                'nullable',
                'string',
                'max:100',
            ],
            'moduleName' => [
                'nullable',
                'string',
                'max:255',
            ],
            'enabled' => [
                'sometimes',
                'boolean',
            ],
            'licensed' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if (
            !$request->exists('enabled')
            && !$request->exists('licensed')
        ) {
            throw ValidationException::withMessages([
                'enabled' =>
                    'The enabled value is required.',
            ]);
        }

        $scope = strtolower(
            trim(
                (string) (
                    $validated['scope']
                    ?? (
                        !empty($validated['moduleCode'])
                            ? 'module'
                            : 'menu'
                    )
                )
            )
        );

        $enabled = $request->exists('enabled')
            ? $request->boolean('enabled')
            : $request->boolean('licensed');

        $schemaColumns = collect(
            $this->tableColumns(self::MENU_TABLE)
        );

        if ($schemaColumns->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'HS_MENU was not found in the selected tenant database.',
            ], 404);
        }

        $columnNames = $schemaColumns
            ->pluck('name')
            ->values()
            ->all();

        $moduleCodeColumn = $this->firstExistingColumn(
            $columnNames,
            ['MODULE_CODE']
        );

        $moduleNameColumn = $this->firstExistingColumn(
            $columnNames,
            ['MODULE']
        );

        if (!$moduleCodeColumn || !$moduleNameColumn) {
            throw ValidationException::withMessages([
                'moduleCode' =>
                    'HS_MENU requires MODULE_CODE and MODULE.',
            ]);
        }

        $snapshotKeyColumns =
            $this->moduleSnapshotKeyColumns(
                $columnNames
            );

        $currentRows = $this->currentModuleRows();

        $snapshot = $this->ensureModuleSnapshot(
            $schemaColumns,
            $currentRows,
            $snapshotKeyColumns
        );

        $snapshotRows = collect(
            $snapshot['rows'] ?? []
        )->map(
            fn ($row) =>
                $this->sanitizeModuleSnapshotRow(
                    (array) $row
                )
        )->values();

        $currentByKey = $currentRows->keyBy(
            fn ($row) => $this->moduleRowKey(
                (array) $row,
                $snapshotKeyColumns
            )
        );

        if ($scope === 'module') {
            $moduleCode = trim(
                (string) (
                    $validated['moduleCode'] ?? ''
                )
            );

            $moduleName = trim(
                (string) (
                    $validated['moduleName'] ?? ''
                )
            );

            if ($moduleCode === '') {
                throw ValidationException::withMessages([
                    'moduleCode' =>
                        'The module code is required.',
                ]);
            }

            if ($moduleName === '') {
                throw ValidationException::withMessages([
                    'moduleName' =>
                        'The module name is required.',
                ]);
            }

            $targetRows = $snapshotRows
                ->filter(function ($row) use (
                    $moduleCodeColumn,
                    $moduleNameColumn,
                    $moduleCode,
                    $moduleName
                ) {
                    $rowModuleCode = trim(
                        (string) (
                            $row[$moduleCodeColumn] ?? ''
                        )
                    );

                    $rowModuleName = trim(
                        (string) (
                            $row[$moduleNameColumn] ?? ''
                        )
                    );

                    return strcasecmp(
                        $rowModuleCode,
                        $moduleCode
                    ) === 0
                        && strcasecmp(
                            $rowModuleName,
                            $moduleName
                        ) === 0;
                })
                ->values();

            if ($targetRows->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        "The selected module {$moduleCode} / {$moduleName} is not present in the JSON master.",
                ], 404);
            }

            $affectedRows =
                DB::connection('tenant')
                    ->transaction(function () use (
                        $enabled,
                        $targetRows,
                        $currentByKey,
                        $schemaColumns,
                        $snapshotKeyColumns
                    ) {
                        $affected = 0;

                        foreach ($targetRows as $snapshotRow) {
                            $key = $this->moduleRowKey(
                                (array) $snapshotRow,
                                $snapshotKeyColumns
                            );

                            $currentRow =
                                $currentByKey->get($key);

                            if ($enabled) {
                                if ($currentRow) {
                                    continue;
                                }

                                $this->insertModuleSnapshotRow(
                                    (array) $snapshotRow,
                                    $schemaColumns
                                );

                                $affected++;
                                continue;
                            }

                            if (!$currentRow) {
                                continue;
                            }

                            $affected +=
                                $this->deleteCurrentModuleRow(
                                    (array) $currentRow,
                                    $schemaColumns,
                                    $snapshotKeyColumns
                                );
                        }

                        return $affected;
                    });

            return response()->json([
                'success' => true,
                'message' => $enabled
                    ? 'Module restored from the tenant JSON master.'
                    : 'Module and all of its installed menus were removed from HS_MENU.',
                'data' => [
                    'scope' => 'module',
                    'moduleCode' => $moduleCode,
                    'moduleName' => $moduleName,
                    'enabled' => $enabled,
                    'affectedRows' => $affectedRows,
                ],
            ]);
        }

        $snapshotKey = trim(
            (string) (
                $validated['snapshotKey'] ?? ''
            )
        );

        if ($snapshotKey === '') {
            throw ValidationException::withMessages([
                'snapshotKey' =>
                    'The menu snapshot key is required.',
            ]);
        }

        $snapshotRow = $snapshotRows
            ->first(function ($row) use (
                $snapshotKey,
                $snapshotKeyColumns
            ) {
                return hash_equals(
                    $snapshotKey,
                    $this->moduleRowKey(
                        (array) $row,
                        $snapshotKeyColumns
                    )
                );
            });

        if (!$snapshotRow) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The selected menu is not present in the JSON master.',
            ], 404);
        }

        $currentRow = $currentByKey->get(
            $snapshotKey
        );

        $affectedRows =
            DB::connection('tenant')
                ->transaction(function () use (
                    $enabled,
                    $currentRow,
                    $snapshotRow,
                    $schemaColumns,
                    $snapshotKeyColumns
                ) {
                    if ($enabled) {
                        if ($currentRow) {
                            return 0;
                        }

                        $this->insertModuleSnapshotRow(
                            (array) $snapshotRow,
                            $schemaColumns
                        );

                        return 1;
                    }

                    if (!$currentRow) {
                        return 0;
                    }

                    return $this->deleteCurrentModuleRow(
                        (array) $currentRow,
                        $schemaColumns,
                        $snapshotKeyColumns
                    );
                });

        return response()->json([
            'success' => true,
            'message' => $enabled
                ? 'Menu restored from the tenant JSON master.'
                : 'Menu removed from HS_MENU. It remains available in the tenant JSON master.',
            'data' => [
                'scope' => 'menu',
                'snapshotKey' => $snapshotKey,
                'enabled' => $enabled,
                'exists' => $enabled,
                'affectedRows' => $affectedRows,
            ],
        ]);
    }

    /**
     * Restore every missing HS_MENU row from the tenant JSON master.
     */
    public function resetModules()
    {
        try {
            $schemaColumns = collect(
                $this->tableColumns(self::MENU_TABLE)
            );

            $columnNames = $schemaColumns
                ->pluck('name')
                ->values()
                ->all();

            $snapshotKeyColumns =
                $this->moduleSnapshotKeyColumns(
                    $columnNames
                );

            $currentRows = $this->currentModuleRows();

            $snapshot = $this->ensureModuleSnapshot(
                $schemaColumns,
                $currentRows,
                $snapshotKeyColumns
            );

            $currentKeys = $currentRows
                ->mapWithKeys(function ($row) use (
                    $snapshotKeyColumns
                ) {
                    return [
                        $this->moduleRowKey(
                            (array) $row,
                            $snapshotKeyColumns
                        ) => true,
                    ];
                });

            $missingRows = collect(
                $snapshot['rows'] ?? []
            )->map(
                fn ($row) =>
                    $this->sanitizeModuleSnapshotRow(
                        (array) $row
                    )
            )->filter(function ($row) use (
                $currentKeys,
                $snapshotKeyColumns
            ) {
                $key = $this->moduleRowKey(
                    (array) $row,
                    $snapshotKeyColumns
                );

                return !$currentKeys->has($key);
            })->values();

            $restoredRows =
                DB::connection('tenant')
                    ->transaction(function () use (
                        $missingRows,
                        $schemaColumns
                    ) {
                        $restored = 0;

                        foreach ($missingRows as $row) {
                            $this->insertModuleSnapshotRow(
                                (array) $row,
                                $schemaColumns
                            );
                            $restored++;
                        }

                        return $restored;
                    });

            return response()->json([
                'success' => true,
                'message' => $restoredRows > 0
                    ? "{$restoredRows} HS_MENU row(s) were restored from the tenant JSON master."
                    : 'All JSON master rows already exist in HS_MENU.',
                'data' => [
                    'restoredRows' => $restoredRows,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error(
                'HeartStrong module reset failed',
                [
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to restore HS_MENU from the JSON master.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function environment()
    {
        $definitions = collect(config('heartstrong.environment.settings', []));
        $files = config('heartstrong.environment.files', []);

        $settings = $definitions->map(function ($definition) use ($files) {
            $target = $definition['target'];
            $path = $files[$target] ?? null;
            $values = $path ? $this->readEnvFile($path) : [];
            $storedValue = $values[$definition['key']] ?? '';
            $isSecret = (bool) ($definition['secret'] ?? false);

            return [
                ...$definition,
                'value' => $isSecret ? '' : $storedValue,
                'hasValue' => $isSecret && $storedValue !== '',
                'fileExists' => $path ? is_file($path) : false,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => ['settings' => $settings],
        ]);
    }

    public function updateEnvironment(Request $request)
    {
        $validated = $request->validate([
            'values' => ['required', 'array', 'min:1'],
        ]);

        $definitions = collect(config('heartstrong.environment.settings', []))
            ->keyBy('key');

        $files = config('heartstrong.environment.files', []);
        $grouped = [];

        foreach ($validated['values'] as $key => $value) {
            if (!$definitions->has($key)) {
                throw ValidationException::withMessages([
                    "values.{$key}" => 'This environment key is not approved for HeartStrong.',
                ]);
            }

            $definition = $definitions[$key];
            $isSecret = (bool) ($definition['secret'] ?? false);
            $nextValue = (string) ($value ?? '');

            if ($isSecret && $nextValue === '') {
                continue;
            }

            $grouped[$definition['target']][$key] = $nextValue;
        }

        if (empty($grouped)) {
            return response()->json([
                'success' => true,
                'message' => 'No environment changes were submitted.',
                'data' => [
                    'restartRequired' => false,
                    'targets' => [],
                ],
            ]);
        }

        try {
            foreach ($grouped as $target => $values) {
                $path = $files[$target] ?? null;

                if (!$path) {
                    throw new \RuntimeException(
                        "No {$target} environment file is configured."
                    );
                }

                $this->writeEnvFile($path, $values);
            }

            Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Laravel environment updated. Restart the API process to apply the changes.',
                'data' => [
                    'restartRequired' => true,
                    'targets' => array_keys($grouped),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('HeartStrong environment update failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the environment file.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Return the selected tenant's current HS_MENU rows without LAC.
     */
    private function currentModuleRows()
    {
        return DB::connection('tenant')
            ->table(self::MENU_TABLE)
            ->get()
            ->map(
                fn ($row) =>
                    $this->sanitizeModuleSnapshotRow(
                        (array) $row
                    )
            )
            ->values();
    }

    /**
     * Build an isolated JSON path for the currently selected tenant.
     */
    private function moduleSnapshotContext(): array
    {
        $connection = DB::connection('tenant');

        $database = trim(
            (string) $connection->getDatabaseName()
        );

        $host = trim(
            (string) config(
                'database.connections.tenant.host',
                ''
            )
        );

        $identity = strtolower(
            $host . '|' . $database
        );

        $databaseSlug = Str::slug($database);

        if ($databaseSlug === '') {
            $databaseSlug = 'tenant';
        }

        $hash = substr(
            hash('sha256', $identity),
            0,
            12
        );

        $fileName =
            'hs-menu-master-'
            . $databaseSlug
            . '-'
            . $hash
            . '.json';

        $relativePath =
            self::MODULE_SNAPSHOT_DIRECTORY
            . '/'
            . $fileName;

        return [
            'host' => $host,
            'database' => $database,
            'identity' => $identity,
            'relativePath' => $relativePath,
            'path' => storage_path($relativePath),
        ];
    }

    /**
     * MODULE_CODE alone is not enough because some areas reuse the same
     * code for different modules (for example VE Inventory and
     * VE Vehicle Services).
     */
    private function moduleGroupKey(
        string $moduleCode,
        string $moduleName
    ): string {
        return strtoupper(trim($moduleCode))
            . '::'
            . strtoupper(trim($moduleName));
    }

    /**
     * MODULE_CODE + MENU_CODE is the stable restore identity.
     */
    private function moduleSnapshotKeyColumns(
        array $columnNames
    ): array {
        $moduleCodeColumn =
            $this->firstExistingColumn(
                $columnNames,
                ['MODULE_CODE']
            );

        $menuCodeColumn =
            $this->firstExistingColumn(
                $columnNames,
                ['MENU_CODE']
            );

        if (
            !$moduleCodeColumn
            || !$menuCodeColumn
        ) {
            throw new \RuntimeException(
                'HS_MENU requires MODULE_CODE and MENU_CODE for JSON restore matching.'
            );
        }

        return [
            $moduleCodeColumn,
            $menuCodeColumn,
        ];
    }

    /**
     * Never persist the old LAC column in the JSON master.
     */
    private function sanitizeModuleSnapshotRow(
        array $row
    ): array {
        foreach (array_keys($row) as $key) {
            if (
                strtoupper((string) $key)
                === 'LAC'
            ) {
                unset($row[$key]);
            }
        }

        return $row;
    }

    private function moduleRowKey(
        array $row,
        array $keyColumns
    ): string {
        $identity = [];

        foreach ($keyColumns as $column) {
            $value = $row[$column] ?? null;

            $identity[$column] = is_string($value)
                ? strtoupper(trim($value))
                : $value;
        }

        return hash(
            'sha256',
            json_encode(
                $identity,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            )
        );
    }

    /**
     * Prevent two rows from sharing the same JSON restore key.
     */
    private function assertUniqueModuleKeys(
        $rows,
        array $keyColumns,
        string $source
    ): void {
        $duplicates = collect($rows)
            ->groupBy(
                fn ($row) =>
                    $this->moduleRowKey(
                        (array) $row,
                        $keyColumns
                    )
            )
            ->filter(
                fn ($group) =>
                    $group->count() > 1
            );

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                "{$source} contains duplicate "
                . implode(' + ', $keyColumns)
                . ' combinations. Each menu must have a unique code inside its module.'
            );
        }
    }

    /**
     * Create or refresh the permanent tenant JSON master.
     *
     * Existing installed rows refresh their saved values. Rows previously
     * removed from HS_MENU remain in the JSON file and are never discarded.
     */
    private function ensureModuleSnapshot(
        $schemaColumns,
        $currentRows,
        array $snapshotKeyColumns
    ): array {
        $context = $this->moduleSnapshotContext();

        $this->assertUniqueModuleKeys(
            $currentRows,
            $snapshotKeyColumns,
            'HS_MENU'
        );

        $snapshot = [
            'version' => 1,
            'sourceTable' => self::MENU_TABLE,
            'tenant' => [
                'host' => $context['host'],
                'database' => $context['database'],
            ],
            'keyColumns' => $snapshotKeyColumns,
            'createdAt' => now()->toIso8601String(),
            'updatedAt' => now()->toIso8601String(),
            'rows' => [],
        ];

        if (File::exists($context['path'])) {
            $decoded = json_decode(
                (string) File::get(
                    $context['path']
                ),
                true
            );

            if (
                !is_array($decoded)
                || !isset($decoded['rows'])
                || !is_array($decoded['rows'])
            ) {
                throw new \RuntimeException(
                    'The tenant HS_MENU JSON master is invalid: '
                    . $context['relativePath']
                );
            }

            $snapshot = array_replace(
                $snapshot,
                $decoded
            );

            $snapshot['createdAt'] =
                $decoded['createdAt']
                ?? $snapshot['createdAt'];
        }

        $savedRows = collect(
            $snapshot['rows'] ?? []
        )->map(
            fn ($row) =>
                $this->sanitizeModuleSnapshotRow(
                    (array) $row
                )
        )->values();

        $this->assertUniqueModuleKeys(
            $savedRows,
            $snapshotKeyColumns,
            'JSON master'
        );

        $savedByKey = $savedRows->mapWithKeys(
            function ($row) use (
                $snapshotKeyColumns
            ) {
                return [
                    $this->moduleRowKey(
                        (array) $row,
                        $snapshotKeyColumns
                    ) => (array) $row,
                ];
            }
        );

        /*
         * Refresh rows that still exist in HS_MENU and append newly created
         * menus. Missing rows remain in $savedByKey for later restoration.
         */
        foreach ($currentRows as $row) {
            $sanitized =
                $this->sanitizeModuleSnapshotRow(
                    (array) $row
                );

            $key = $this->moduleRowKey(
                $sanitized,
                $snapshotKeyColumns
            );

            $savedByKey->put(
                $key,
                $sanitized
            );
        }

        $snapshot['version'] = 1;
        $snapshot['sourceTable'] =
            self::MENU_TABLE;
        $snapshot['tenant'] = [
            'host' => $context['host'],
            'database' => $context['database'],
        ];
        $snapshot['keyColumns'] =
            $snapshotKeyColumns;
        $snapshot['updatedAt'] =
            now()->toIso8601String();
        $snapshot['rows'] =
            $savedByKey->values()->all();

        File::ensureDirectoryExists(
            dirname($context['path'])
        );

        $encoded = json_encode(
            $snapshot,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        if (
            File::put(
                $context['path'],
                $encoded . PHP_EOL,
                true
            ) === false
        ) {
            throw new \RuntimeException(
                'Unable to write the tenant HS_MENU JSON master: '
                . $context['relativePath']
            );
        }

        return $snapshot;
    }

    /**
     * Insert a deleted row back into HS_MENU.
     * Identity and SQL Server rowversion columns are regenerated.
     */
    private function insertModuleSnapshotRow(
        array $snapshotRow,
        $schemaColumns
    ): void {
        $values = [];

        foreach ($schemaColumns as $column) {
            $name = $column['name'];
            $dataType = strtolower(
                (string) $column['dataType']
            );

            if (
                $column['isIdentity']
                || in_array(
                    $dataType,
                    ['timestamp', 'rowversion'],
                    true
                )
                || strtoupper($name) === 'LAC'
            ) {
                continue;
            }

            if (
                array_key_exists(
                    $name,
                    $snapshotRow
                )
            ) {
                $values[$name] =
                    $snapshotRow[$name];
            }
        }

        if (empty($values)) {
            throw new \RuntimeException(
                'No restorable HS_MENU values were found in the JSON master.'
            );
        }

        DB::connection('tenant')
            ->table(self::MENU_TABLE)
            ->insert($values);
    }

    /**
     * Delete one installed row using its primary key, falling back to the
     * stable JSON key when the table has no declared primary key.
     */
    private function deleteCurrentModuleRow(
        array $currentRow,
        $schemaColumns,
        array $snapshotKeyColumns
    ): int {
        $keyColumns = collect($schemaColumns)
            ->filter(
                fn ($column) =>
                    $column['isPrimaryKey']
            )
            ->pluck('name')
            ->values()
            ->all();

        if (empty($keyColumns)) {
            $idColumn = $this->firstExistingColumn(
                collect($schemaColumns)
                    ->pluck('name')
                    ->all(),
                ['ID']
            );

            if ($idColumn) {
                $keyColumns = [$idColumn];
            }
        }

        if (empty($keyColumns)) {
            $keyColumns = $snapshotKeyColumns;
        }

        $query = DB::connection('tenant')
            ->table(self::MENU_TABLE);

        foreach ($keyColumns as $column) {
            $value = $currentRow[$column]
                ?? null;

            $value === null
                ? $query->whereNull($column)
                : $query->where(
                    $column,
                    $value
                );
        }

        return $query->delete();
    }

    private function tableColumns(string $table): array
    {
        $sql = <<<'SQL'
SELECT
    c.COLUMN_NAME AS column_name,
    c.DATA_TYPE AS data_type,
    c.IS_NULLABLE AS is_nullable,
    c.CHARACTER_MAXIMUM_LENGTH AS max_length,
    CAST(
        COLUMNPROPERTY(
            OBJECT_ID(c.TABLE_SCHEMA + '.' + c.TABLE_NAME),
            c.COLUMN_NAME,
            'IsIdentity'
        ) AS INT
    ) AS is_identity,
    CASE WHEN pk.COLUMN_NAME IS NULL THEN 0 ELSE 1 END AS is_primary_key
FROM INFORMATION_SCHEMA.COLUMNS c
LEFT JOIN (
    SELECT
        ku.TABLE_SCHEMA,
        ku.TABLE_NAME,
        ku.COLUMN_NAME
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
    INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE ku
        ON tc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
       AND tc.TABLE_SCHEMA = ku.TABLE_SCHEMA
       AND tc.TABLE_NAME = ku.TABLE_NAME
    WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
) pk
    ON pk.TABLE_SCHEMA = c.TABLE_SCHEMA
   AND pk.TABLE_NAME = c.TABLE_NAME
   AND pk.COLUMN_NAME = c.COLUMN_NAME
WHERE c.TABLE_SCHEMA = 'dbo'
  AND c.TABLE_NAME = ?
ORDER BY c.ORDINAL_POSITION
SQL;

        return collect(
            DB::connection('tenant')->select($sql, [$table])
        )->map(function ($column) {
            return [
                'name' => $column->column_name,
                'dataType' => strtolower((string) $column->data_type),
                'nullable' => strtoupper((string) $column->is_nullable) === 'YES',
                'maxLength' => $column->max_length,
                'isIdentity' => (bool) $column->is_identity,
                'isPrimaryKey' => (bool) $column->is_primary_key,
            ];
        })->all();
    }

    private function firstExistingColumn(array $columnNames, array $candidates): ?string
    {
        $lookup = collect($columnNames)
            ->mapWithKeys(fn ($name) => [strtoupper((string) $name) => $name]);

        foreach ($candidates as $candidate) {
            $match = $lookup->get(strtoupper($candidate));

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function topLevelMenuRows($rows, ?string $parentColumn, ?string $levelColumn)
    {
        $collection = collect($rows);

        if ($parentColumn) {
            $topLevel = $collection->filter(function ($row) use ($parentColumn) {
                $value = strtoupper(trim((string) (((array) $row)[$parentColumn] ?? '')));

                return in_array($value, ['', '0', 'ROOT', 'NULL'], true);
            });

            if ($topLevel->isNotEmpty()) {
                return $topLevel->values();
            }
        }

        if ($levelColumn) {
            $numericLevels = $collection
                ->map(fn ($row) => (float) (((array) $row)[$levelColumn] ?? 0));
            $minimum = $numericLevels->min();

            return $collection->filter(
                fn ($row) => (float) (((array) $row)[$levelColumn] ?? 0) === $minimum
            )->values();
        }

        return $collection->values();
    }

    private function descendantMenuCodes(
        $rows,
        string $codeColumn,
        string $parentColumn,
        mixed $rootCode
    ): array {
        $byParent = collect($rows)->groupBy(function ($row) use ($parentColumn) {
            return strtoupper(trim((string) (((array) $row)[$parentColumn] ?? '')));
        });

        $queue = [strtoupper(trim((string) $rootCode))];
        $descendants = [];

        while (!empty($queue)) {
            $parent = array_shift($queue);

            foreach ($byParent->get($parent, collect()) as $row) {
                $data = (array) $row;
                $code = $data[$codeColumn] ?? null;

                if ($code === null || trim((string) $code) === '') {
                    continue;
                }

                $normalized = strtoupper(trim((string) $code));

                if (in_array($normalized, $descendants, true)) {
                    continue;
                }

                $descendants[] = $normalized;
                $queue[] = $normalized;
            }
        }

        return $descendants;
    }

    private function isSwitchOption(
        string $fieldName,
        array $column,
        mixed $value
    ): bool {
        if ($column['dataType'] === 'bit' || is_bool($value)) {
            return true;
        }

        $normalized = strtoupper(trim((string) $value));

        if (
            in_array(
                $normalized,
                ['Y', 'N', 'T', 'F', 'YES', 'NO', 'TRUE', 'FALSE', 'E', 'D'],
                true
            )
        ) {
            return true;
        }

        /*
         * A numeric 0 or 1 is not automatically a switch.
         * HS_OPTION contains level fields such as SI_DISCLEVEL and
         * PR_APPLEVEL whose valid values can currently be 0 or 1.
         */
        if (
            is_numeric($value) &&
            in_array((int) $value, [0, 1], true)
        ) {
            $name = strtoupper($fieldName);

            if (
                str_contains($name, 'LEVEL') ||
                str_contains($name, 'DECQTY') ||
                str_contains($name, 'DECUPRICE') ||
                str_contains($name, 'DECUCOST') ||
                str_contains($name, 'DECSELLPRICE')
            ) {
                return false;
            }

            return (
                str_ends_with($name, '_MODE') ||
                str_contains($name, 'AUTOEMAIL') ||
                str_contains($name, 'OTHPAGE') ||
                str_contains($name, 'DUPITEM') ||
                str_contains($name, 'BUYERWT') ||
                str_starts_with($name, 'IS_') ||
                str_contains($name, 'ACTIVE') ||
                str_contains($name, 'ENABLED')
            );
        }

        return false;
    }

    private function decodeSwitchValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(
            strtoupper(trim((string) $value)),
            ['Y', 'T', 'YES', 'TRUE', '1', 'E'],
            true
        );
    }

    private function switchPair(array $column, mixed $value): array
    {
        if ($column['dataType'] === 'bit' || is_bool($value)) {
            return [1, 0];
        }

        if (is_numeric($value)) {
            return [1, 0];
        }

        $current = strtoupper(trim((string) $value));

        return match (true) {
            in_array($current, ['Y', 'N'], true) => ['Y', 'N'],
            in_array($current, ['T', 'F'], true) => ['T', 'F'],
            in_array($current, ['YES', 'NO'], true) => ['YES', 'NO'],
            in_array($current, ['TRUE', 'FALSE'], true) => ['TRUE', 'FALSE'],
            in_array($current, ['E', 'D'], true) => ['E', 'D'],
            default => ['Y', 'N'],
        };
    }

    private function encodeSwitchValue(
        mixed $currentValue,
        string $dataType,
        bool $enabled
    ): mixed {
        [$enabledValue, $disabledValue] = $this->switchPair(
            ['dataType' => $dataType],
            $currentValue
        );

        return $enabled ? $enabledValue : $disabledValue;
    }

    private function optionControlType(array $column): string
    {
        return in_array(
            $column['dataType'],
            [
                'tinyint',
                'smallint',
                'int',
                'bigint',
                'decimal',
                'numeric',
                'float',
                'real',
                'money',
                'smallmoney',
            ],
            true
        ) ? 'number' : 'text';
    }

    private function optionNumberStep(array $column): string
    {
        return in_array(
            $column['dataType'],
            ['decimal', 'numeric', 'float', 'real', 'money', 'smallmoney'],
            true
        ) ? 'any' : '1';
    }

    private function normalizeOptionValue(
        string $fieldName,
        array $column,
        mixed $value
    ): mixed {
        if ($value === '' || $value === null) {
            if ($column['nullable']) {
                return null;
            }

            throw ValidationException::withMessages([
                'value' => "{$fieldName} cannot be blank.",
            ]);
        }

        $dataType = $column['dataType'];

        if (
            in_array(
                $dataType,
                ['tinyint', 'smallint', 'int', 'bigint'],
                true
            )
        ) {
            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw ValidationException::withMessages([
                    'value' => "{$fieldName} must be a whole number.",
                ]);
            }

            return (int) $value;
        }

        if (
            in_array(
                $dataType,
                [
                    'decimal',
                    'numeric',
                    'float',
                    'real',
                    'money',
                    'smallmoney',
                ],
                true
            )
        ) {
            if (!is_numeric($value)) {
                throw ValidationException::withMessages([
                    'value' => "{$fieldName} must be numeric.",
                ]);
            }

            return $value;
        }

        $stringValue = (string) $value;
        $maxLength = (int) ($column['maxLength'] ?? 0);

        if (
            $maxLength > 0 &&
            mb_strlen($stringValue) > $maxLength
        ) {
            throw ValidationException::withMessages([
                'value' => "{$fieldName} cannot exceed {$maxLength} characters.",
            ]);
        }

        return $stringValue;
    }

    private function applyKeys($query, array $keys, $columns): void
    {
        $validKeyCount = 0;

        foreach ($keys as $key => $value) {
            if (!$columns->has($key)) {
                continue;
            }

            $validKeyCount++;

            if ($value === null) {
                $query->whereNull($key);
            } else {
                $query->where($key, $value);
            }
        }

        if ($validKeyCount === 0) {
            throw ValidationException::withMessages([
                'keys' => 'No valid table key fields were supplied.',
            ]);
        }
    }

    private function readEnvFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $trimmed = trim($line);

            if (
                $trimmed === '' ||
                str_starts_with($trimmed, '#') ||
                !str_contains($line, '=')
            ) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (
                strlen($value) >= 2 &&
                (($value[0] === '"' && str_ends_with($value, '"')) ||
                 ($value[0] === "'" && str_ends_with($value, "'")))
            ) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = stripcslashes($value);
        }

        return $values;
    }

    private function writeEnvFile(string $path, array $values): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new \RuntimeException(
                "Environment directory does not exist: {$directory}"
            );
        }

        $contents = is_file($path) ? file_get_contents($path) : '';

        if ($contents === false) {
            throw new \RuntimeException(
                "Unable to read environment file: {$path}"
            );
        }

        if (is_file($path)) {
            $backup = $path . '.bak.' . now()->format('Ymd_His');

            if (!copy($path, $backup)) {
                throw new \RuntimeException(
                    "Unable to create environment backup: {$backup}"
                );
            }
        }

        $lines = preg_split('/\R/', $contents) ?: [];

        foreach ($values as $key => $value) {
            $replacement = $key . '=' . $this->encodeEnvValue($value);
            $found = false;

            foreach ($lines as $index => $line) {
                if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
                    $lines[$index] = $replacement;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $lines[] = $replacement;
            }
        }

        $temporaryPath = $path . '.tmp';

        if (file_put_contents(
            $temporaryPath,
            implode(PHP_EOL, $lines) . PHP_EOL,
            LOCK_EX
        ) === false) {
            throw new \RuntimeException(
                "Unable to write temporary environment file: {$temporaryPath}"
            );
        }

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new \RuntimeException(
                "Unable to replace environment file: {$path}"
            );
        }
    }

    private function encodeEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#="\'\\\\]/', $value)) {
            return '"' . addcslashes($value, "\\\"") . '"';
        }

        return $value;
    }
}
