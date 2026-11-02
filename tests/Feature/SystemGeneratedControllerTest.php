<?php

namespace Tests\Feature;

use App\Http\Controllers\SystemGeneratedController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SystemGeneratedControllerTest extends TestCase
{
    private function payload(): array
    {
        return [
            'sgDate' => '2026-11-02', 'cutoffType' => 'M', 'cutoffFrequency' => 'M',
            'cutoffCode' => '202611M', 'cutoffStartDate' => '2026-11-01',
            'cutoffEndDate' => '2026-11-30', 'userCode' => 'ADMIN',
            'rows' => [[
                'empNo' => 'EMP001', 'edCode' => 'EARN001', 'amount' => 1000,
                'frequency' => 'M', 'startDate' => '2026-11-01', 'endDate' => '2026-11-30',
            ]],
        ];
    }

    private function expectProcedure(string $mode, array $input, array $result): void
    {
        DB::shouldReceive('select')->once()->withArgs(function ($sql, $bindings) use ($mode, $input) {
            return $sql === 'EXEC sproc_PHP_Tran_SystemGenerated @mode = ?, @params = ?'
                && $bindings[0] === $mode
                && json_decode($bindings[1], true) == ['jsonData' => $input];
        })->andReturn($result);
    }

    public function test_save_uses_sg_fields_without_overtime_fields(): void
    {
        $input = $this->payload();
        $this->expectProcedure('Save', $input, [(object) ['sgId' => 'id', 'sgNo' => 'SG00000001']]);
        $response = (new SystemGeneratedController())->save(Request::create('/', 'POST', ['jsonData' => $input]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
    }

    public function test_invalid_row_is_rejected_before_database_access(): void
    {
        DB::shouldReceive('select')->never();
        $input = $this->payload();
        $input['rows'][0]['amount'] = -1;
        $input['rows'][0]['endDate'] = '2026-10-31';
        $input['rows'][0]['frequency'] = 'invalid';
        try {
            (new SystemGeneratedController())->save(Request::create('/', 'POST', ['jsonData' => $input]));
            $this->fail('Invalid rows must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('jsonData.rows.0.amount', $exception->errors());
            $this->assertArrayHasKey('jsonData.rows.0.endDate', $exception->errors());
            $this->assertArrayHasKey('jsonData.rows.0.frequency', $exception->errors());
        }
    }

    public function test_cutoff_type_and_frequency_must_match(): void
    {
        DB::shouldReceive('select')->never();
        $input = $this->payload();
        $input['cutoffFrequency'] = 'S1';
        $this->expectException(ValidationException::class);
        (new SystemGeneratedController())->save(Request::create('/', 'POST', ['jsonData' => $input]));
    }

    public function test_row_requires_frequency_but_accepts_edfreq_alias(): void
    {
        $input = $this->payload();
        unset($input['rows'][0]['frequency']);
        try {
            (new SystemGeneratedController())->save(Request::create('/', 'POST', ['jsonData' => $input]));
            $this->fail('Missing frequency must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('jsonData.rows.0.frequency', $exception->errors());
        }
        $input['rows'][0]['edFreq'] = 'M';
        $this->expectProcedure('Save', $input, []);
        $this->assertSame(200, (new SystemGeneratedController())->save(
            Request::create('/', 'POST', ['jsonData' => $input]),
        )->getStatusCode());
    }

    public function test_get_accepts_sg_number(): void
    {
        $input = ['sgNo' => 'SG00000001'];
        $this->expectProcedure('Get', $input, [(object) ['result' => '{}']]);
        $this->assertSame(200, (new SystemGeneratedController())->get(
            Request::create('/', 'POST', ['jsonData' => $input]),
        )->getStatusCode());
    }

    public function test_history_supports_legacy_json_data_and_camel_case(): void
    {
        $filters = ['startDate' => '2026-11-01', 'endDate' => '2026-11-30'];
        foreach ([['json_data' => json_encode(['json_data' => $filters])], ['jsonData' => $filters]] as $payload) {
            $this->expectProcedure('History', $filters, [(object) ['result' => '{}']]);
            $this->assertSame(200, (new SystemGeneratedController())->history(
                Request::create('/', 'POST', $payload),
            )->getStatusCode());
        }
    }

    public function test_malformed_history_json_is_a_validation_error(): void
    {
        DB::shouldReceive('select')->never();
        $this->expectException(ValidationException::class);
        (new SystemGeneratedController())->history(Request::create('/', 'POST', ['json_data' => '{broken']));
    }

    public function test_business_error_from_procedure_returns_422(): void
    {
        $input = ['sgId' => 'id', 'userCode' => 'ADMIN'];
        $this->expectProcedure('Post', $input, [(object) ['errorCount' => 1, 'errorMsg' => 'Only Open transactions may be posted.']]);
        $response = (new SystemGeneratedController())->post(Request::create('/', 'POST', ['jsonData' => $input]));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }

    public function test_cancel_requires_reason(): void
    {
        DB::shouldReceive('select')->never();
        $this->expectException(ValidationException::class);
        (new SystemGeneratedController())->cancel(Request::create('/', 'POST', [
            'jsonData' => ['sgId' => 'id', 'userCode' => 'ADMIN'],
        ]));
    }

    public function test_database_exception_returns_500(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('Database unavailable'));
        Log::shouldReceive('error')->once();
        $response = (new SystemGeneratedController())->selectionReferences();
        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }

    public function test_routes_keep_tenant_and_cancellation_middleware(): void
    {
        $routes = app('router')->getRoutes();
        $endpoints = [
            ['GET', 'selectionReferences', 'selectionReferences'],
            ['POST', 'list', 'index'], ['POST', 'selectionEmployees', 'selectionEmployees'],
            ['POST', 'get', 'get'], ['POST', 'save', 'save'], ['POST', 'post', 'post'],
            ['POST', 'cancel', 'cancel'], ['POST', 'history', 'history'],
            ['POST', 'loadED', 'loadED'], ['POST', 'loadEDGL', 'loadEDGL'],
            ['POST', 'preparePayrun', 'preparePayrun'],
        ];
        foreach ($endpoints as [$verb, $path, $action]) {
            $route = $routes->match(Request::create('/api/systemGeneratedTransactions/' . $path, $verb));
            $this->assertSame(SystemGeneratedController::class . '@' . $action, $route->getActionName());
            $this->assertContains('tenant', $route->gatherMiddleware());
            if ($action === 'cancel') {
                $this->assertContains('posting.credential', $route->gatherMiddleware());
            }
        }
    }
}
