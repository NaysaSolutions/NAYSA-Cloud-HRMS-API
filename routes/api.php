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
use App\Http\Controllers\BankInfoController;
use App\Http\Controllers\LeaveCreditController;
use App\Http\Controllers\LeaveManagementController;
use App\Http\Controllers\PayrollYearEndController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\EmployeeStatusController;
use App\Http\Controllers\GovTAXController;
use App\Http\Controllers\GovHDMFController;
use App\Http\Controllers\GovPHController;
use App\Http\Controllers\GovSSSController;
use App\Http\Controllers\PayGroupController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\EmployeeGovInfo;
use App\Http\Controllers\EmployeeGovParams;


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
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\AllTranApprovalController;


// TRANSACTIONS - CONTROLLERS
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\LeaveTransactionController;
use App\Http\Controllers\LoanManagementController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\OvertimeTransactionController;




use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;







//HR-PAY Controller 


//Global Reference 
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\RefRCController;
use App\Http\Controllers\RefDeptTypeController;
use App\Http\Controllers\RefDeptGroupController;
use App\Http\Controllers\RefDepartmentController;
use App\Http\Controllers\RefLoanController;
use App\Http\Controllers\RefEDController;
use App\Http\Controllers\RefPayrollPeriodController;




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

    Route::get('/shift', [ShiftController::class, 'index']);
    Route::post('/upsertShift', [ShiftController::class, 'upsert']);
    Route::post('/deleteShift', [ShiftController::class, 'delete']);
    Route::post('/checkDuplicateShift', [ShiftController::class, 'checkDuplicate']);
    Route::post('/checkInUsedShift', [ShiftController::class, 'checkInUsed']);

    Route::get('/refRC', [RefRCController::class, 'index']);
    Route::post('/upsertRefRC', [RefRCController::class, 'upsert']);
    Route::post('/deleteRefRC', [RefRCController::class, 'delete']);
    Route::post('/checkDuplicateRefRC', [RefRCController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefRC', [RefRCController::class, 'checkInUsed']);
    Route::get('/lookupRefRC', [RefRCController::class, 'lookup']);

    Route::get('/refDeptType', [RefDeptTypeController::class, 'index']);
    Route::post('/upsertRefDeptType', [RefDeptTypeController::class, 'upsert']);
    Route::post('/deleteRefDeptType', [RefDeptTypeController::class, 'delete']);
    Route::post('/checkDuplicateRefDeptType', [RefDeptTypeController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefDeptType', [RefDeptTypeController::class, 'checkInUsed']);
    Route::get('/lookupRefDeptType', [RefDeptTypeController::class, 'lookup']);

    Route::prefix('user-management')->group(function () {
        Route::get('/load', [UserManagementController::class, 'load']);
        Route::get('/get', [UserManagementController::class, 'get']);
        Route::get('/policy', [UserManagementController::class, 'getPolicy']);
        Route::post('/policy/upsert', [UserManagementController::class, 'upsertPolicy']);
        Route::post('/upsert', [UserManagementController::class, 'upsert']);
        Route::post('/approve', [UserManagementController::class, 'approveAccount']);
        Route::post('/delete', [UserManagementController::class, 'delete']);
        Route::post('/checkduplicate', [UserManagementController::class, 'checkDuplicate']);
        Route::post('/request-password-reset', [UserManagementController::class, 'requestPasswordReset']);
        Route::post('/release-account', [UserManagementController::class, 'releaseLockedAccount']);
    });

    // Route::get('/getUser', [UserController::class, 'getUser']);

    Route::get('/refDeptGroup', [RefDeptGroupController::class, 'index']);
    Route::post('/upsertRefDeptGroup', [RefDeptGroupController::class, 'upsert']);
    Route::post('/deleteRefDeptGroup', [RefDeptGroupController::class, 'delete']);
    Route::post('/checkDuplicateRefDeptGroup', [RefDeptGroupController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefDeptGroup', [RefDeptGroupController::class, 'checkInUsed']);
    Route::get('/lookupRefDeptGroup', [RefDeptGroupController::class, 'lookup']);




    Route::get('/refDepartment', [RefDepartmentController::class, 'index']);
    Route::post('/upsertRefDepartment', [RefDepartmentController::class, 'upsert']);
    Route::post('/deleteRefDepartment', [RefDepartmentController::class, 'delete']);
    Route::post('/checkDuplicateRefDepartment', [RefDepartmentController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefDepartment', [RefDepartmentController::class, 'checkInUsed']);



    Route::get('/refLoan', [RefLoanController::class, 'index']);
    Route::post('/upsertRefLoan', [RefLoanController::class, 'upsert']);
    Route::post('/deleteRefLoan', [RefLoanController::class, 'delete']);
    Route::post('/checkDuplicateRefLoan', [RefLoanController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefLoan', [RefLoanController::class, 'checkInUsed']);
    Route::get('/lookupRefLoan', [RefLoanController::class, 'lookup']);





    



    Route::get('/refED', [RefEDController::class, 'index']);
    Route::post('/upsertRefED', [RefEDController::class, 'upsert']);
    Route::post('/deleteRefED', [RefEDController::class, 'delete']);
    Route::post('/checkDuplicateRefED', [RefEDController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefED', [RefEDController::class, 'checkInUsed']);
    Route::get('/lookupRefED', [RefEDController::class, 'lookup']);
    Route::get('/getRefED', [RefEDController::class, 'get']);


    Route::get('/cutOff', [RefPayrollPeriodController::class, 'index']);
    Route::post('/upsertCutOff', [RefPayrollPeriodController::class, 'upsert']);
    Route::get('/lookupCutOff', [RefPayrollPeriodController::class, 'lookup']);
    Route::get('/getCutOff', [RefPayrollPeriodController::class, 'get']);
    Route::post('/deleteCutOff', [RefPayrollPeriodController::class, 'delete']);
    Route::post('/checkInUsedCutOff', [RefPayrollPeriodController::class, 'checkInUsed']);
    Route::post('/checkDuplicateCutOff', [RefPayrollPeriodController::class, 'checkDuplicate']);
    Route::get('/loadCutOff', [RefPayrollPeriodController::class, 'index']);















    
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

    Route::get('/load', [AccessRightsController::class, 'load']);
    Route::post('/getUserBranchAccess', [AccessRightsController::class, 'getUserBranchAccess']);
    Route::post('/upsertUserBranchAccess', [AccessRightsController::class, 'upsertUserBranchAccess']);
    Route::get('/loadPaygroups', [AccessRightsController::class, 'loadPaygroups']);
    Route::post('/getUserPaygroupAccess', [AccessRightsController::class, 'getUserPaygroupAccess']);
    Route::post('/upsertUserPaygroupAccess', [AccessRightsController::class, 'upsertUserPaygroupAccess']);
    Route::post('/getUserMenuAccess', [AccessRightsController::class, 'getUserMenuAccess']);
    Route::post('/upsertUserMenuAccess', [AccessRightsController::class, 'upsertUserMenuAccess']);

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


    Route::get('/refLeave', [LeaveController::class, 'index']);
    Route::get('/lookupRefLeave', [LeaveController::class, 'lookup']);
    Route::get('/getRefLeave', [LeaveController::class, 'get']);

    Route::post('/upsertRefLeave', [LeaveController::class, 'upsert']);
    Route::post('/deleteRefLeave', [LeaveController::class, 'delete']);
    Route::post('/checkDuplicateRefLeave', [LeaveController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefLeave', [LeaveController::class, 'checkInUsed']);

    Route::get('/refOvertime', [OvertimeController::class, 'index']);
    Route::get('/lookupRefOvertime', [OvertimeController::class, 'lookup']);
    Route::get('/getRefOvertime', [OvertimeController::class, 'get']);

    Route::post('/upsertRefOvertime', [OvertimeController::class, 'upsert']);
    Route::post('/deleteRefOvertime', [OvertimeController::class, 'delete']);
    Route::post('/checkDuplicateRefOvertime', [OvertimeController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefOvertime', [OvertimeController::class, 'checkInUsed']);

    Route::get('/refEmployeeStatus', [EmployeeStatusController::class, 'index']);
    Route::get('/lookupRefEmployeeStatus', [EmployeeStatusController::class, 'lookup']);
    Route::get('/getRefEmployeeStatus', [EmployeeStatusController::class, 'get']);
    Route::post('/upsertRefEmployeeStatus', [EmployeeStatusController::class, 'upsert']);
    Route::post('/deleteRefEmployeeStatus', [EmployeeStatusController::class, 'delete']);
    Route::post('/checkDuplicateRefEmployeeStatus', [EmployeeStatusController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefEmployeeStatus', [EmployeeStatusController::class, 'checkInUsed']);

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






    Route::POST('/atc', [ATCController::class, 'index']);
    Route::post('/upsertATC', [ATCController::class, 'upsert']);
    Route::get('/lookupATC', [ATCController::class, 'lookup']);
    Route::post('/deleteATC', [ATCController::class, 'delete']);
    Route::post('/checkDuplicateATC', [ATCController::class, 'checkDuplicate']);
    Route::post('/checkInUsedATC', [ATCController::class, 'checkInUsed']);
    Route::get('/getATC', [ATCController::class, 'get']);




    // Route::get('/cutOff', [CutoffController::class, 'index']);
    // Route::post('/upsertCutOff', [CutoffController::class, 'upsert']);
    // Route::get('/lookupCutOff', [CutoffController::class, 'lookup']);
    // Route::get('/getCutOff', [CutoffController::class, 'get']);
    // Route::post('/deleteCutOff', [CutoffController::class, 'delete']);
    // Route::post('/checkInUsedCutOff', [CutoffController::class, 'checkInUsed']);
    // Route::post('/checkDuplicateCutOff', [CutoffController::class, 'checkDuplicate']);
    // Route::get('/loadCutOff', [CutoffController::class, 'index']);

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


    Route::get('/bankInfo', [BankInfoController::class, 'index']);
    Route::post('/upsertBankInfo', [BankInfoController::class, 'upsert']);
    Route::get('/lookupBankInfo', [BankInfoController::class, 'lookup']);
    Route::get('/getBankInfo', [BankInfoController::class, 'get']);
    Route::post('/deleteBankInfo', [BankInfoController::class, 'delete']);
    Route::post('/checkDuplicateBankInfo', [BankInfoController::class, 'checkDuplicate']);
    Route::post('/checkInUsedBankInfo', [BankInfoController::class, 'checkInUsed']);

    Route::get('/leaveCredit', [LeaveCreditController::class, 'index']);
    Route::post('/upsertLeaveCredit', [LeaveCreditController::class, 'upsert']);
    Route::get('/lookupLeaveCredit', [LeaveCreditController::class, 'lookup']);
    Route::get('/getLeaveCredit', [LeaveCreditController::class, 'get']);
    Route::post('/deleteLeaveCredit', [LeaveCreditController::class, 'delete']);
    Route::post('/checkDuplicateLeaveCredit', [LeaveCreditController::class, 'checkDuplicate']);
    Route::post('/checkInUsedLeaveCredit', [LeaveCreditController::class, 'checkInUsed']);

    Route::get('/generateLeaveCredits/references', [LeaveManagementController::class, 'generateCreditReferences']);
    Route::post('/generateLeaveCredits/preview', [LeaveManagementController::class, 'generateCredits']);
    Route::post('/generateLeaveCredits/save', [LeaveManagementController::class, 'saveGeneratedCredits']);

    Route::get('/leaveLedger/references', [LeaveManagementController::class, 'ledgerReferences']);
    Route::post('/leaveLedger/load', [LeaveManagementController::class, 'loadLedger']);

    Route::get('/leaveCreditBalance/references', [LeaveManagementController::class, 'balanceReferences']);
    Route::post('/leaveCreditBalance/load', [LeaveManagementController::class, 'loadBalances']);
    Route::post('/leaveCreditBalance/recalculate', [LeaveManagementController::class, 'recalculateBalances']);
    Route::post('/leaveCreditBalance/save', [LeaveManagementController::class, 'saveBalances']);

    Route::get('/bonusProcessing/references', [PayrollYearEndController::class, 'bonusReferences']);
    Route::post('/bonusProcessing/load', [PayrollYearEndController::class, 'loadBonus']);

    Route::get('/taxReconciliation/references', [PayrollYearEndController::class, 'taxReconReferences']);
    Route::post('/taxReconciliation/load', [PayrollYearEndController::class, 'loadTaxRecon']);

    Route::get('/birAlphalist/references', [PayrollYearEndController::class, 'alphalistReferences']);
    Route::post('/birAlphalist/load', [PayrollYearEndController::class, 'loadAlphalist']);


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

    Route::get('/paygroup', [PaygroupController::class, 'index']);
    Route::post('/upsertPayGroup', [PaygroupController::class, 'upsert']);
    Route::post('/checkDuplicatePayGroup', [PaygroupController::class, 'checkDuplicate']);
    Route::post('/deletePayGroup', [PaygroupController::class, 'delete']);
    Route::post('/checkInUsedPayGroup', [PaygroupController::class, 'checkInUsed']);

    Route::get('/pos', [POSController::class, 'index']);
    Route::post('/upsertPOS', [POSController::class, 'upsert']);
    Route::post('/checkDuplicatePOS', [POSController::class, 'checkDuplicate']);
    Route::post('/deletePOS', [POSController::class, 'delete']);
    Route::post('/checkInUsedPOS', [POSController::class, 'checkInUsed']);

    Route::get('/client', [ClientController::class, 'index']);
    Route::post('/upsertClient', [ClientController::class, 'upsert']);
    Route::post('/checkDuplicateClient', [ClientController::class, 'checkDuplicate']);
    Route::post('/deleteClient', [ClientController::class, 'delete']);
    Route::post('/checkInUsedClient', [ClientController::class, 'checkInUsed']);

    Route::get('/employeeGovInfo', [EmployeeGovInfo::class, 'index']);
    Route::post('/upsertEmployeeGovInfo', [EmployeeGovInfo::class, 'upsert']);

    Route::get('/employeeGovParams', [EmployeeGovParams::class, 'index']);
    Route::post('/upsertEmployeeGovParams', [EmployeeGovParams::class, 'upsert']);

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

    Route::get('/refLoan', [LoanController::class, 'index']);
    Route::get('/lookupRefLoan', [LoanController::class, 'lookup']);
    Route::get('/getRefLoan', [LoanController::class, 'get']);
    Route::post('/upsertRefLoan', [LoanController::class, 'upsert']);
    Route::post('/deleteRefLoan', [LoanController::class, 'delete']);
    Route::post('/checkDuplicateRefLoan', [LoanController::class, 'checkDuplicate']);
    Route::post('/checkInUsedRefLoan', [LoanController::class, 'checkInUsed']);




    // Put this inside your existing tenant/authenticated middleware group if applicable.
    Route::prefix('timesheet')->controller(TimesheetController::class)->group(function () {
        Route::post('/load', 'load');
        Route::post('/generate', 'generate');
        Route::post('/recalculate', 'recalculate');
        Route::post('/get', 'get');
        Route::post('/validate', 'validateTimesheet');
        Route::post('/finalize', 'finalize');
        Route::post('/unfinalize', 'unfinalize');
        Route::post('/loadPayItem', 'loadPayItem');
    });

    
    Route::prefix('leave-transaction')->middleware('auth:sanctum')->group(function () {
        Route::post('/references', [LeaveTransactionController::class, 'references']);
        Route::post('/employees', [LeaveTransactionController::class, 'employees']);
        Route::post('/load', [LeaveTransactionController::class, 'load']);
        Route::post('/get', [LeaveTransactionController::class, 'get']);
        Route::post('/upsert', [LeaveTransactionController::class, 'upsert']);
        Route::post('/delete', [LeaveTransactionController::class, 'delete']);
        Route::post('/validate', [LeaveTransactionController::class, 'validateLeave']);
        Route::post('/finalize', [LeaveTransactionController::class, 'finalizeLeave']);
        Route::post('/unfinalize', [LeaveTransactionController::class, 'unfinalizeLeave']);
    });

    
    Route::prefix('overtimeTransactions')->controller(OvertimeTransactionController::class)->group(function () {
        Route::post('/list', 'index');
        Route::get('/selectionReferences', 'selectionReferences');
        Route::post('/selectionEmployees', 'selectionEmployees');
        Route::post('/get', 'get');
        Route::post('/save', 'save');
        Route::post('/post', 'post');
        Route::post('/cancel', 'cancel')->middleware('posting.credential');
        Route::post('/history', 'history');
    });



    Route::prefix('loanTransactions')->controller(LoanManagementController::class)->group(function () {
        Route::post('/list', 'index');
        Route::get('/selectionReferences', 'selectionReferences');
        Route::post('/selectionEmployees', 'selectionEmployees');
        Route::post('/get', 'get');
        Route::post('/save', 'save');
        Route::post('/post', 'post');
        Route::post('/cancel', 'cancel')->middleware('posting.credential');
        Route::post('/paymentHistory', 'paymentHistory');
        Route::post('/history', 'history');
        Route::get('/ledgerReferences', 'ledgerReferences');
        Route::post('/ledger', 'loadLedger');
    });




});
