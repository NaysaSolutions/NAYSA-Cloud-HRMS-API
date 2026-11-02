<?php

namespace Tests\Feature;

use App\Http\Controllers\RefEDController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefEDControllerTest extends TestCase
{
    private function form(): array
    {
        $data = array_fill_keys(['edType', 'code', 'reportHdr', 'reportField', 'order', 'compHdmf',
            'acctCode', 'acctName', 'tax2316', 'ntax2316', 'ctrp', 'deminimis', 'taxable',
            'compSss', 'compPh', 'bonusFlag', 'includeBonexamt', 'includeMp13', 'active'], '');
        return array_merge($data, ['edCode' => 'E99', 'edName' => 'Test only', 'edType' => 'EARN',
            'active' => 'Y', 'userCode' => 'TESTUSER', 'action' => 'ADD']);
    }

    public function test_upsert_accepts_object_and_existing_serialized_wrapper(): void
    {
        $form = $this->form();
        DB::shouldReceive('select')->twice()->withArgs(function ($sql, $bindings) use ($form) {
            return $sql === 'EXEC sproc_PHP_REF_ED @mode = ?, @params = ?'
                && $bindings[0] === 'Upsert'
                && json_decode($bindings[1], true) == ['json_data' => $form];
        })->andReturn([(object) ['errormsg' => '', 'errorcount' => 0]]);
        foreach ([$form, json_encode(['json_data' => $form])] as $input) {
            $response = (new RefEDController())->upsert(Request::create('/', 'POST', ['json_data' => $input]));
            $this->assertSame(200, $response->getStatusCode());
            $this->assertTrue($response->getData(true)['success']);
        }
    }

    public function test_oversize_code_is_rejected_before_sql(): void
    {
        DB::shouldReceive('select')->never();
        $form = $this->form();
        $form['edCode'] = 'E999';
        $this->expectException(ValidationException::class);
        (new RefEDController())->upsert(Request::create('/', 'POST', ['json_data' => $form]));
    }

    public function test_get_supports_existing_ed_code_query_parameter(): void
    {
        DB::shouldReceive('select')->once()->withArgs(function ($sql, $bindings) {
            return $bindings[0] === 'Get' && json_decode($bindings[1], true) === ['json_data' => ['edCode' => 'D01']];
        })->andReturn([(object) ['result' => '[]']]);
        $response = (new RefEDController())->get(Request::create('/', 'GET', ['ED_CODE' => 'D01']));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_malformed_input_is_validation_error(): void
    {
        DB::shouldReceive('select')->never();
        $this->expectException(ValidationException::class);
        (new RefEDController())->upsert(Request::create('/', 'POST', ['json_data' => '{broken']));
    }

    public function test_usage_check_reads_actual_references(): void
    {
        DB::shouldReceive('select')->once()->withArgs(function ($sql, $bindings) {
            return str_contains($sql, 'sys.columns') && str_contains($sql, 'QUOTENAME') && $bindings === ['D01'];
        })->andReturn([(object) ['isInUsed' => 1]]);
        $response = (new RefEDController())->checkInUsed(Request::create('/', 'POST', ['json_data' => ['edCode' => 'D01']]));
        $this->assertTrue($response->getData(true)['isInUsed']);
        $this->assertSame('1', json_decode($response->getData(true)['data'][0]['result'], true)['result']);
    }

    public function test_delete_rejects_used_code_without_executing_delete(): void
    {
        DB::shouldReceive('select')->once()->andReturn([(object) ['isInUsed' => 1]]);
        $response = (new RefEDController())->delete(Request::create('/', 'POST', [
            'json_data' => ['edCode' => 'D01', 'userCode' => 'TESTUSER'],
        ]));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }

    public function test_procedure_business_error_is_not_reported_as_success(): void
    {
        DB::shouldReceive('select')->once()->andReturn([(object) ['errormsg' => 'Invalid E/D Code', 'errorcount' => 1]]);
        $response = (new RefEDController())->upsert(Request::create('/', 'POST', ['json_data' => $this->form()]));
        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_existing_routes_remain_bound_to_the_controller_and_tenant(): void
    {
        foreach ([['GET', 'refED', 'index'], ['GET', 'lookupRefED', 'lookup'], ['GET', 'getRefED', 'get'],
            ['POST', 'upsertRefED', 'upsert'], ['POST', 'deleteRefED', 'delete'],
            ['POST', 'checkDuplicateRefED', 'checkDuplicate'], ['POST', 'checkInUsedRefED', 'checkInUsed']] as [$method, $uri, $action]) {
            $route = app('router')->getRoutes()->match(Request::create('/api/' . $uri, $method));
            $this->assertSame(RefEDController::class . '@' . $action, $route->getActionName());
            $this->assertContains('tenant', $route->gatherMiddleware());
        }
    }
}
