<?php




// GLOBAL - CONTROLLERS
use App\Http\Controllers\HSDocController;
use App\Http\Controllers\HSDropdownController;
use App\Http\Controllers\HSOptionController;
use App\Http\Controllers\HSColConfigController;
use App\Http\Controllers\HSRptController;
use App\Http\Controllers\HSToolsController;
use App\Http\Controllers\PrintingController;
use App\Http\Controllers\FileAttachmentController;
use App\Http\Controllers\AllBIRController;
use App\Http\Controllers\TemplateLayoutController;

use App\Http\Controllers\HolidayController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\EmployeeController;


// GLOBAL REFERENCE - CONTROLLERS
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CutoffController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\GovTAXController;
use App\Http\Controllers\GovHDMFController;
use App\Http\Controllers\GovPHController;
use App\Http\Controllers\GovSSSController;



// GENERAL LEDGER - REFERENCE FILE CONTROLLERS
use App\Http\Controllers\COAMasterController;
use App\Http\Controllers\COAClassController;
use App\Http\Controllers\FSConsolidationController;
use App\Http\Controllers\RCTypeController;
use App\Http\Controllers\RCMastController;
use App\Http\Controllers\BankTypeController;
use App\Http\Controllers\BankMasterController;




// SECURITY - CONTROLLERS
use App\Http\Controllers\MenuController;
use App\Http\Controllers\AccessRightsController;
use App\Http\Controllers\MasterAccessRightsController;
use App\Http\Controllers\ReportAccessRightsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\AllTranApprovalController;



use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::options('{any}', function () {
    return response()->noContent();
})->where('any', '.*');


Route::get('/companies', [AuthController::class, 'companies']);
Route::post('/send-mail', [MailController::class, 'send']);




Route::middleware('tenant')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);

    /** ✅ ADDED (from attached api.php) — you had these commented out */
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/auth/heartbeat', [AuthController::class, 'heartbeat']);

    Route::post('/upsertCompany', [CompanyController::class, 'upsert']);
    Route::get('/getCompany', [CompanyController::class, 'get']);
    Route::get('/getGlobalTables', [CompanyController::class, 'getGlobalTables']);



    Route::get('/getUser', [UserController::class, 'get']);
    Route::get('/load', [UserController::class, 'load']);
    Route::get('/lookupUserAll', [UserController::class, 'lookupAll']);
    Route::post('/users/upsert', [UserController::class, 'upsert']);
    Route::post('/users/approve', [UserController::class, 'approveAccount']);
    Route::post('/users/delete', [UserController::class, 'delete']);
    Route::post('/users/request-password-reset', [UserController::class, 'requestPasswordReset']);
    Route::post('/users/change-password', [UserController::class, 'changePassword']);
    Route::post('/users/checkduplicate', [UserController::class, 'checkDuplicate']);
    Route::post('/users/checkinused', [UserController::class, 'checkInUsed']);
    // Profile Image
    Route::post('/user/profile-image', [UserController::class, 'uploadProfileImage']);
    Route::get('/user/profile-image/{userCode}', [UserController::class, 'getProfileImage']);
    Route::delete('/user/profile-image/{userCode}', [UserController::class, 'deleteProfileImage']);
    //HS Security
    Route::get('/security/policy',          [UserController::class, 'getPolicy']);
    Route::post('/security/policy/upsert',  [UserController::class, 'upsertPolicy']);
    Route::post('/getSecTrail', [UserController::class, 'getSecTrail']);
    Route::post('/users/release-locked', [UserController::class, 'releaseLockedAccount']);



    // Heart Strong
    Route::get('/getHSDoc', [HSDocController::class, 'get']);
    Route::get('/lookupHSDoc', [HSDocController::class, 'lookup']);

    Route::post('/getHSDropdown', [HSDropdownController::class, 'get']);
    Route::get('/getHSDropdownAll', [HSDropdownController::class, 'getAll']);
    Route::get('/getHSOption', [HSOptionController::class, 'get']);
    Route::get('/getHSColConfig', [HSColConfigController::class, 'get']);

    Route::get('/menu-items', [MenuController::class, 'items']);
    Route::get('/menu-routes', [MenuController::class, 'routes']);

    Route::get('/role', [AccessRightsController::class, 'loadRole']);
    Route::get('/getRole', [AccessRightsController::class, 'getRole']);
    Route::post('/deleteRole', [AccessRightsController::class, 'DeleteRole']);
    Route::get('/loadRole', [AccessRightsController::class, 'getUsers']);
    Route::get('/getRoleMenu', [AccessRightsController::class, 'getRoleMenu']);
    Route::get('/getUserRoles', [AccessRightsController::class, 'getUserRoles']);

    Route::post('/upsertRole', [AccessRightsController::class, 'UpsertRole']);
    Route::post('/upsertRoleMenu', [AccessRightsController::class, 'upsertRoleMenu']);
    Route::post('/UpsertUserRole', [AccessRightsController::class, 'UpsertUserRole']);
    Route::post('/upsert', [AccessRightsController::class, 'upsert']);
    Route::post('/deleteUserRole', [AccessRightsController::class, 'deleteUserRole']);

    Route::get('/checkDuplicateRole', [AccessRightsController::class, 'checkDuplicate']);
    Route::get('/checkInUsedRole', [AccessRightsController::class, 'checkInUsed']);

    Route::prefix('master-access-rights')->group(function () {
        Route::get('/load-master-data', [MasterAccessRightsController::class, 'loadMasterData']);
        Route::post('/get-user-master-data', [MasterAccessRightsController::class, 'getUserMasterData']);
        Route::post('/upsert-user-master-data', [MasterAccessRightsController::class, 'upsertUserMasterData']);
        Route::post('/delete-user-master-data', [MasterAccessRightsController::class, 'deleteUserMasterData']);
    });

    Route::prefix('report-access-rights')->group(function () {
        Route::get('load-report-data',         [ReportAccessRightsController::class, 'loadReportData']);
        Route::post('get-user-report-data',     [ReportAccessRightsController::class, 'getUserReportData']);
        Route::post('upsert-user-report-data',  [ReportAccessRightsController::class, 'upsertUserReportData']);
        Route::post('delete-user-report-data',  [ReportAccessRightsController::class, 'deleteUserReportData']);
    });

    //Printing
    Route::prefix('printing')->group(function () {
        Route::post('/handle', [PrintingController::class, 'handlePrinting']);
        Route::get('/pending', [PrintingController::class, 'loadPendingDocuments']);
        Route::post('/update-generated', [PrintingController::class, 'updateGeneratedDocument']);
        Route::get('/generate-id', [PrintingController::class, 'generateId']);
    });

    Route::get('/hsrpt', [HSRptController::class, 'index']);
    Route::get('/getHsrpt', [HSRptController::class, 'get']);
    Route::post('/initialize', [HSToolsController::class, 'initialize']);
    Route::get('/getHSTblColLen', [HSToolsController::class, 'getTblGetFieldLenght']);
    Route::post('/getDocTrail', [HSToolsController::class, 'getDocTrail']);
    Route::post('/getRefTrail', [HSToolsController::class, 'getRefTrail']);
    Route::post('/excelFileUpload', [HSToolsController::class, 'excelFileUpload']);



    Route::post('/printForm', [PrintingController::class, 'printForm']);
    Route::post('/printQuery', [PrintingController::class, 'printQuery']);
    Route::post('/printARReport', [PrintingController::class, 'printARReport']);
    Route::post('/printAPReport', [PrintingController::class, 'printAPReport']);
    Route::post('/printGLReport', [PrintingController::class, 'printGLReport']);
    Route::post('/exportHistoryReport', [PrintingController::class, 'exportHistoryReport']);
    Route::post('/upsertDocSign', [PrintingController::class, 'upsertDocSign']);
    Route::get('/getDocSign', [PrintingController::class, 'getDocSign']);



    // --Revised export using React
    Route::post('/getARReport', [PrintingController::class, 'getAR_Report']);
    Route::post('/getAPReport', [PrintingController::class, 'getAP_Report']);
    Route::post('/getGLReport', [PrintingController::class, 'getGL_Report']);
    Route::post('/getFGINVReport', [PrintingController::class, 'getFGINV_Report']);
    Route::post('/getMSINVReport', [PrintingController::class, 'getMSINV_Report']);
    Route::post('/getRMINVReport', [PrintingController::class, 'getRMINV_Report']);

	


    //Dev Express Printing
    Route::post('/print-dxr-form', [PrintingController::class, 'printDxrForm']);
    Route::post('/open-dxr-viewer', [PrintingController::class, 'openDxrViewer']);
    Route::post('/print-dxr-report', [PrintingController::class, 'printDxrReport']);            






    Route::post('/attachFile', [FileAttachmentController::class, 'attachFile']);
    Route::delete('/deleteFile/{id}', [FileAttachmentController::class, 'deleteFile']);
    Route::get('/downloadAll/{documentID}', [FileAttachmentController::class, 'downloadAll']);
    Route::get('/downloadFile/{id}', [FileAttachmentController::class, 'downloadFile']);
    Route::get('/getAttachFile', [FileAttachmentController::class, 'get']);


    Route::get('/bankType', [BankTypeController::class, 'index']);
    Route::post('/upsertBankType', [BankTypeController::class, 'upsert']);
    Route::get('/lookupBankType', [BankTypeController::class, 'lookup']);
    Route::post('/deleteBankType', [BankTypeController::class, 'delete']);
    Route::post('/checkDuplicateBankType', [BankTypeController::class, 'checkDuplicate']);
    Route::post('/checkInUsedBankType', [BankTypeController::class, 'checkInUsed']);
    Route::get('/getBankType', [BankTypeController::class, 'get']);


    Route::get('/rcMast', [RCMastController::class, 'index']);
    Route::post('/upsertRCMast', [RCMastController::class, 'upsert']);
    Route::get('/lookupRCMast', [RCMastController::class, 'lookup']);
    Route::get('/getRCMast', [RCMastController::class, 'get']);
    Route::post('/deleteRCMast', [RCMastController::class, 'delete']);
    Route::post('/checkInUsedRCMast', [RCMastController::class, 'checkInUsed']);
    Route::post('/checkDuplicateRCMast', [RCMastController::class, 'checkDuplicate']);
    Route::get('/loadRCMast', [RCMastController::class, 'loadRCMast']);


    Route::get('/rcType', [RCTypeController::class, 'index']);
    Route::post('/upsertRcType', [RCTypeController::class, 'upsert']);
    Route::get('/lookupRCType', [RCTypeController::class, 'lookup']);
    Route::get('/getRcType', [RCTypeController::class, 'get']);
    Route::post('/deleteRcType', [RCTypeController::class, 'delete']);
    Route::post('/checkInUsedRcType', [RCTypeController::class, 'checkInUsed']);
    Route::post('/checkDuplicateRcType', [RCTypeController::class, 'checkDuplicate']);
    Route::get('/loadRCType', [RCTypeController::class, 'loadRcType']);




    Route::get('/cutOff', [CutoffController::class, 'index']);
    Route::post('/upsertCutOff', [CutoffController::class, 'upsert']);
    Route::get('/lookupCutOff', [CutoffController::class, 'lookup']);
    Route::get('/getCutOff', [CutoffController::class, 'get']);
    Route::post('/deleteCutOff', [CutoffController::class, 'delete']);
    Route::post('/checkInUsedCutOff', [CutoffController::class, 'checkInUsed']);
    Route::post('/checkDuplicateCutOff', [CutoffController::class, 'checkDuplicate']);
    Route::get('/loadCutOff', [CutoffController::class, 'index']);

    Route::get('/rCType', [RCTypeController::class, 'index']);
    Route::post('/upsertRCType', [RCTypeController::class, 'upsert']);


    
    Route::get('/bank', [BankMasterController::class, 'index']);
    Route::post('/upsertBank', [BankMasterController::class, 'upsert']);
    Route::get('/lookupBank', [BankMasterController::class, 'lookup']);
    Route::get('/getBank', [BankMasterController::class, 'get']);
    Route::post('/checkDuplicateBank', [BankMasterController::class, 'checkDuplicate']);
    Route::post('/deleteBank', [BankMasterController::class, 'delete']);
    Route::post('/checkInUsedBank', [BankMasterController::class, 'checkInUsed']);
    Route::get('/validateDuplicateCheck', [BankMasterController::class, 'validateDuplicateCheck']);
    

    Route::get('/cOA', [COAMasterController::class, 'index']);
    Route::post('/upsertCOA', [COAMasterController::class, 'upsert']);
    Route::post('/lookupCOA', [COAMasterController::class, 'lookup']);
    Route::get('/getCOA', [COAMasterController::class, 'get']);
    Route::post('/lookupGL', [COAMasterController::class, 'lookupGL']);
    Route::post('/editEntries', [COAMasterController::class, 'editEntries']);
    Route::post('/deleteCOA', [COAMasterController::class, 'delete']);
    Route::post('/checkDuplicateCOA', [COAMasterController::class, 'checkDuplicate']);
    Route::post('/checkInUsedCOA', [COAMasterController::class, 'checkInUsed']);
    Route::get('/glfsmatching', [COAMasterController::class, 'index']);


    Route::get('/fsconso', [FSConsolidationController::class, 'index']);
    Route::post('/upsertFSConso', [FSConsolidationController::class, 'upsert']);
    Route::get('/lookupFSConso', [FSConsolidationController::class, 'lookup']);
    Route::get('/getFSConso', [FSConsolidationController::class, 'get']);
    Route::post('/deleteFSConso', [FSConsolidationController::class, 'delete']);
    Route::post('/checkDuplicateFSConso', [FSConsolidationController::class, 'checkDuplicate']);
    Route::post('/checkInUsedFSConso', [FSConsolidationController::class, 'checkInUsed']);
    Route::post('/upsertGLFSMatching', [FSConsolidationController::class, 'upsertGLFSMatching']);





    Route::get('/cOAClass', [COAClassController::class, 'index']);
    Route::post('/upsertCOAClass', [COAClassController::class, 'upsert']);
    Route::post('/lookupCOAClass', [COAClassController::class, 'lookup']);


    Route::get('/branch', [BranchController::class, 'index']);
    Route::post('/upsertBranch', [BranchController::class, 'upsert']);
    Route::get('/lookupBranch', [BranchController::class, 'lookup']);
    Route::get('/getBranch', [BranchController::class, 'get']);
    Route::post('/deleteBranch', [BranchController::class, 'delete']);
    Route::post('/checkDuplicateBranch', [BranchController::class, 'checkDuplicate']);
    Route::post('/checkInUsedBranch', [BranchController::class, 'checkInUsed']);

    Route::get('/holiday', [HolidayController::class, 'index']);
    Route::post('/holidayLookup', [HolidayController::class, 'lookup']);
    Route::post('/getHoliday', [HolidayController::class, 'get']);
    Route::post('/upsertHoliday', [HolidayController::class, 'upsert']);
    Route::post('/deleteHoliday', [HolidayController::class, 'delete']);
    Route::post('/checkInUsedHoliday', [HolidayController::class, 'checkInUsed']);
    Route::post('/checkDuplicateHoliday', [HolidayController::class, 'checkDuplicate']);


    Route::get('/govTax', [GovTAXController::class, 'index']);
    Route::post('/upsertGovTax', [GovTAXController::class, 'upsert']);
    Route::get('/lookupGovTax', [GovTAXController::class, 'lookup']);
    Route::get('/getGovTax', [GovTAXController::class, 'get']);


    Route::get('/govHdmf', [GovHDMFController::class, 'index']);
    Route::post('/upsertGovHdmf', [GovHDMFController::class, 'upsert']);
    Route::get('/lookupGovHdmf', [GovHDMFController::class, 'lookup']);
    Route::get('/getGovHdmf', [GovHDMFController::class, 'get']);


    Route::get('/govPh', [GovPHController::class, 'index']);
    Route::post('/upsertGovPh', [GovPHController::class, 'upsert']);
    Route::get('/lookupGovPh', [GovPHController::class, 'lookup']);
    Route::get('/getGovPh', [GovPHController::class, 'get']);


    Route::get('/govSss', [GovSSSController::class, 'index']);
    Route::post('/upsertGovSss', [GovSSSController::class, 'upsert']);
    Route::get('/lookupGovSss', [GovSSSController::class, 'lookup']);
    Route::get('/getGovSss', [GovSSSController::class, 'get']);

    Route::get('/area', [AreaController::class, 'index']);
    Route::post('/upsertArea', [AreaController::class, 'upsert']);
    Route::get('/lookupArea', [AreaController::class, 'lookup']);
    Route::get('/getArea', [AreaController::class, 'get']);
    Route::post('/checkDuplicateArea', [AreaController::class, 'checkDuplicate']);
    Route::post('/checkInUsedArea', [AreaController::class, 'checkInUsed']);
    Route::post('/deleteArea', [AreaController::class, 'delete']);


    Route::get('/employee', [EmployeeController::class, 'index']);
    Route::get('/lookupEmployee', [EmployeeController::class, 'lookup']);
    Route::get('/getEmployee', [EmployeeController::class, 'get']);
    Route::post('/upsertEmployee', [EmployeeController::class, 'upsert']);
    Route::post('/deleteEmployee', [EmployeeController::class, 'delete']);
    Route::post('/checkDuplicateEmployee', [EmployeeController::class, 'checkDuplicate']);
    Route::post('/checkInUsedEmployee', [EmployeeController::class, 'checkInUsed']);


});

