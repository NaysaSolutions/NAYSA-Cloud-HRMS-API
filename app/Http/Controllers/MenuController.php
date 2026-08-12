<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{

        
    // public function items(Request $req)
    // {
        
    //     $userCode      = $req->query('USER_CODE');            
    //     $includeHidden = (int) $req->query('include_hidden', 0);
    //     $mode          = $req->query('mode', 'ReturnJson');


    //     $rows = DB::select(
    //         'EXEC dbo.sproc_PHP_HSMenu @IncludeHidden = ?, @mode = ?, @userCode = ?',
    //         [$includeHidden, $mode, $userCode]
    //     );

    //     $row  = $rows[0] ?? null;
    //     $json = $row->JsonResult ?? '[]';      
    //     return response()->json([
    //         'menuItems' => json_decode($json, true) ?? []
    //     ]);
    // }

    public function items(Request $req)
{
    try {
        $userCode      = $req->query('USER_CODE');
        $includeHidden = (int) $req->query('include_hidden', 0);
        $mode          = $req->query('mode', 'ReturnJson');

        $rows = DB::select(
            'EXEC dbo.sproc_PHP_HSMenu @IncludeHidden = ?, @mode = ?, @userCode = ?',
            [$includeHidden, $mode, $userCode]
        );

        $row  = $rows[0] ?? null;
        $json = $row->JsonResult ?? '[]';

        return response()->json([
            'success'   => true,
            'menuItems' => json_decode($json, true) ?? [],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}





    public function routes(Request $req)
    {

        $userCode      = $req->query('USER_CODE');            
        $includeHidden = (int) $req->query('include_hidden', 0);
        $mode          = $req->query('mode', 'ReturnRoutes');


        $rows = DB::select(
            'EXEC dbo.sproc_PHP_HSMenu @IncludeHidden = ?, @mode = ?, @userCode = ?',
            [$includeHidden, $mode, $userCode]
        );

        $routes = array_map(function ($r) {
            return [
                'code'         => $r->menu_code ?? $r->code ?? null,
                'name'         => $r->menu_name ?? $r->name ?? null,
                'path'         => $r->path ?? null,
                'componentKey' => $r->component_key ?? $r->componentKey ?? null,
            ];
        }, $rows ?? []);

        $routes = array_values(array_filter($routes, fn($x) => $x['path'] && $x['componentKey']));
        return response()->json(['routes' => $routes]);
    }
}
