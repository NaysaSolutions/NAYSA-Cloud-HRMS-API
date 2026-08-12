<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MSInvStockCardController extends Controller
{
    public function setup(Request $request)
    {
        return response()->json([
            'data' => [
                // change to WAC to show Location Balance tab instead of FIFO Balance
                'inventorySetup' => 'FIFO',
            ],
        ]);
    }

    public function fifoBalance(Request $request)
    {
        return response()->json([
            'data' => [
                'summary' => [
                    [
                        'itemCode' => 'FS0000000014',
                        'itemName' => 'Gloves - Surgical',
                        'uomCode' => 'PAIR',
                        'quantity' => 25,
                        'qtyAllocated' => 5,
                        'qtyAvailable' => 20,
                    ],
                    [
                        'itemCode' => 'OS0000000002',
                        'itemName' => 'Ballpen Blue',
                        'uomCode' => 'PC',
                        'quantity' => 10,
                        'qtyAllocated' => 0,
                        'qtyAvailable' => 10,
                    ],
                    [
                        'itemCode' => 'OS0000000011',
                        'itemName' => 'Club Carbon',
                        'uomCode' => 'BX',
                        'quantity' => 5,
                        'qtyAllocated' => 0,
                        'qtyAvailable' => 4,
                    ],
                ],
                'details' => [
                    'FS0000000014' => [
                        [
                            'rrDate' => '11/04/2025',
                            'rrNo' => 'RR00000001',
                            'unitCost' => 225,
                            'qtyIn' => 35,
                            'qtyOut' => 25,
                            'balance' => 10,
                            'whouseCode' => 'WA02-02',
                            'locCode' => 'WA02-02',
                            'lotNo' => '',
                            'bbDate' => null,
                            'qcStat' => 'PASSED',
                            'poNo' => '00000014',
                        ],
                        [
                            'rrDate' => '11/04/2025',
                            'rrNo' => 'RR00000001',
                            'unitCost' => 225,
                            'qtyIn' => 5,
                            'qtyOut' => 0,
                            'balance' => 5,
                            'whouseCode' => 'WA02-02',
                            'locCode' => 'WA02-01',
                            'lotNo' => '',
                            'bbDate' => null,
                            'qcStat' => 'PASSED',
                            'poNo' => '00000014',
                        ],
                    ],
                    'OS0000000002' => [
                        [
                            'rrDate' => '11/06/2025',
                            'rrNo' => 'RR00000002',
                            'unitCost' => 24,
                            'qtyIn' => 5,
                            'qtyOut' => 1,
                            'balance' => 4,
                            'whouseCode' => 'WA02-02',
                            'locCode' => 'WA02-02',
                            'lotNo' => '',
                            'bbDate' => null,
                            'qcStat' => 'PASSED',
                            'poNo' => '00000021',
                        ],
                    ],
                    'OS0000000011' => [
                        [
                            'rrDate' => '11/06/2025',
                            'rrNo' => 'RR00000003',
                            'unitCost' => 24,
                            'qtyIn' => 10,
                            'qtyOut' => 0,
                            'balance' => 10,
                            'whouseCode' => 'WA02-02',
                            'locCode' => 'WA02-02',
                            'lotNo' => '',
                            'bbDate' => null,
                            'qcStat' => 'PASSED',
                            'poNo' => '00000021',
                        ],
                    ],
                ],
                'allocated' => [
                    'FS0000000014' => [
                        ['docNo' => 'MSIS-00000002', 'docType' => 'MSIS', 'qtyPicked' => 5],
                        ['docNo' => 'MSRTV-00000001', 'docType' => 'MSRTV', 'qtyPicked' => 0],
                        ['docNo' => 'MSADJ-00000001', 'docType' => 'MSADJ', 'qtyPicked' => 0],
                    ],
                    'OS0000000002' => [],
                ],
            ],
        ]);
    }

    public function locationBalance(Request $request)
    {
        return response()->json([
            'data' => [
                'summary' => [
                    [
                        'itemCode' => 'FS0000000014',
                        'itemName' => 'Gloves - Surgical',
                        'uomCode' => 'PAIR',
                        'quantity' => 25,
                        'qtyAllocated' => 5,
                        'qtyAvailable' => 20,
                    ],
                    [
                        'itemCode' => 'OS0000000002',
                        'itemName' => 'Ballpen Blue',
                        'uomCode' => 'PC',
                        'quantity' => 10,
                        'qtyAllocated' => 0,
                        'qtyAvailable' => 10,
                    ],
                ],
                'details' => [
                    'FS0000000014' => [
                        [
                            'whouseCode' => 'WA02-02',
                            'locCode' => 'WA02-02',
                            'lotNo' => '',
                            'bbDate' => null,
                            'qcStat' => 'PASSED',
                            'qtyIn' => 35,
                            'qtyOut' => 25,
                            'balance' => 10,
                        ],
                        [
                            'whouseCode' => 'WA02-02',
                            'locCode' => 'WA02-01',
                            'lotNo' => '',
                            'bbDate' => null,
                            'qcStat' => 'PASSED',
                            'qtyIn' => 5,
                            'qtyOut' => 0,
                            'balance' => 5,
                        ],
                    ],
                ],
                'allocated' => [
                    'FS0000000014' => [
                        ['docNo' => 'MSIS-00000002', 'docType' => 'MSIS', 'qtyPicked' => 5],
                        ['docNo' => 'MSRTV-00000001', 'docType' => 'MSRTV', 'qtyPicked' => 0],
                        ['docNo' => 'MSADJ-00000001', 'docType' => 'MSADJ', 'qtyPicked' => 0],
                    ],
                ],
            ],
        ]);
    }

    public function stockCard(Request $request)
    {
        return response()->json([
            'data' => [
                'rows' => [
                    [
                        'cutoff' => '202511',
                        'docType' => 'MSRR',
                        'docNo' => '00000001',
                        'docDate' => '11/04/2025',
                        'rrNo' => 'RR00000001',
                        'particular' => 'SHITSU TAPES AND PLASTICS',        
                        'itemNo' => 'FS0000000014',
                        'itemDescription' => 'Gloves - Surgical',
                        'warehouse' => 'WA02-02',
                        'location' => 'WA02-02',
                        'qtyIn' => 35,
                        'qtyOut' => 0,
                        'runBal' => 35,
                        'unitCost' => 225,
                        'amount' => 7875,
                        'postedBy' => 'NAYSA ADMIN',
                        'dateStamp' => '04/16/2026',
                        'timeStamp' => '2349',
                    ],
                    [
                        'cutoff' => '202511',
                        'docType' => 'MSIS',
                        'docNo' => '00000001',
                        'docDate' => '11/06/2025',
                        'rrNo' => 'RR00000001',
                        'particular' => 'Issuance',                      
                        'itemNo' => 'FS0000000014',
                        'itemDescription' => 'Gloves - Surgical',
                        'warehouse' => 'WA02-02',
                        'location' => 'WA02-02',
                        'qtyIn' => 0,
                        'qtyOut' => 10,
                        'runBal' => 25,
                        'unitCost' => 225,
                        'amount' => 2250,
                        'postedBy' => 'NAYSA ADMIN',
                        'dateStamp' => '04/16/2026',
                        'timeStamp' => '2350',
                    ],
                    [
                        'cutoff' => '202511',
                        'docType' => 'MSRTV',
                        'docNo' => '00000001',
                        'docDate' => '11/08/2025',
                        'rrNo' => 'RR00000001',
                        'particular' => 'Damaged',
                        'itemNo' => 'FS0000000014',
                        'itemDescription' => 'Gloves - Surgical',
                        'warehouse' => 'WA02-02',
                        'location' => 'WA02-02',
                        'qtyIn' => 0,
                        'qtyOut' => 5,
                        'runBal' => 20,
                        'unitCost' => 225,
                        'amount' => 1125,
                        'postedBy' => 'NAYSA ADMIN',
                        'dateStamp' => '04/16/2026',
                        'timeStamp' => '2356',
                    ],
                    [
                        'cutoff' => '202511',
                        'docType' => 'MSADJ',
                        'docNo' => '00000001',
                        'docDate' => '11/10/2025',
                        'rrNo' => 'RR00000001',
                        'particular' => 'Item Loss',        
                        'itemNo' => 'FS0000000014',
                        'itemDescription' => 'Gloves - Surgical',
                        'warehouse' => 'WA02-02',
                        'location' => 'WA02-02',
                        'qtyIn' => 0,
                        'qtyOut' => 10,
                        'runBal' => 10,
                        'unitCost' => 225,
                        'amount' => 2250,
                        'postedBy' => 'NAYSA ADMIN',
                        'dateStamp' => '04/16/2026',
                        'timeStamp' => '2357',
                    ],
                    [
                        'cutoff' => '202511',
                        'docType' => 'MSST',
                        'docNo' => '00000001',
                        'docDate' => '11/12/2025',
                        'rrNo' => 'RR00000001',
                        'particular' => 'Transfer to LOC A',        
                        'itemNo' => 'FS0000000014',
                        'itemDescription' => 'Gloves - Surgical',
                        'warehouse' => 'WA02-02',
                        'location' => 'WA02-01',
                        'qtyIn' => 5,
                        'qtyOut' => 0,
                        'runBal' => 15,
                        'unitCost' => 225,
                        'amount' => 1125,
                        'postedBy' => 'NAYSA ADMIN',
                        'dateStamp' => '04/16/2026',
                        'timeStamp' => '2359',
                    ],
                ],
                'totals' => [
                    'beginningBalance' => 0,
                    'totalInbound' => 40,
                    'totalOutbound' => 25,
                    'endingBalance' => 15,
                ],
            ],
        ]);
    }

    public function stockStatus(Request $request)
    {
        return response()->json([
            'data' => [
                'summary' => [
                    [
                        'itemNo' => 'FS0000000014',
                        'itemDescription' => 'Gloves - Surgical',
                        'uom' => 'PAIR',
                        'category' => 'FACTORY SUPPLIES',
                        'itemClass' => 'GLVS',
                        'rrNo' => 'RR00000001',
                        'beginningBalance' => 15,
                        'quantityIn' => 0,
                        'quantityOut' => 0,
                        'endingBalance' => 15,
                        'unitCost' => 225,
                        'amount' => 3375,
                        'inventoryAcct' => '1-104500',
                    ],
                ],
                'perItem' => [
                    [
                        'itemNo' => 'FS0000000014',
                        'itemDescription' => 'Gloves - Surgical',
                        'warehouse' => 'WA02-02',
                        'location' => 'WA02-02',
                        'beginningBalance' => 15,
                        'quantityIn' => 0,
                        'quantityOut' => 0,
                        'endingBalance' => 15,
                        'unitCost' => 225,
                        'amount' => 3375,
                    ],
                ],
                'perLot' => [
                    [
                        'itemNo' => 'FS0000000014',
                        'itemDescription' => 'Gloves - Surgical',
                        'warehouse' => 'WA02-02',
                        'location' => 'WA02-02',
                        'lotNo' => '',
                        'bbDate' => null,
                        'qcStat' => 'PASSED',
                        'balance' => 15,
                        'unitCost' => 225,
                        'amount' => 3375,
                    ],
                ],
            ],
        ]);
    }
}
