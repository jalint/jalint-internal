<?php

use App\Http\Controllers\AdminInvoiceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\CompanyBankAccountController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerOfferController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\JalintPdfController;
use App\Http\Controllers\LhpDocumentController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OfferDocumentController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\RegulationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SampleMatrixController;
use App\Http\Controllers\SampleTypeController;
use App\Http\Controllers\SubkonController;
use App\Http\Controllers\TaskLetterController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TestMethodController;
// use App\Http\Controllers\UserController;
use App\Http\Controllers\TestPackageController;
use App\Http\Controllers\TestParameterController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WilayahController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/users/login', [AuthController::class, 'login']);
Route::post('/customer/login', [AuthController::class, 'loginCustomer']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
        Route::patch('customers/offers/{offer}/draft', [CustomerOfferController::class, 'updateDraft']);
        Route::post('customers/offers/{offer}/review', [CustomerOfferController::class, 'reviewCustomer']);
        Route::get('customers/offers/summary', [CustomerOfferController::class, 'summary']);
        Route::post('customers/invoces/payment', [AdminInvoiceController::class, 'storeCustomerPayment']);

        Route::apiResource(
            'customers/offers',
            CustomerOfferController::class
        );

        Route::post(
            '/offers/{offer}/documents/subkon/customer',
            [OfferDocumentController::class, 'uploadByCustomer']
        );
    });

    Route::apiResource('roles', RoleController::class);
    Route::patch('/customers/{customer}/reset-password', [CustomerController::class, 'resetPassword']);
    Route::apiResource('customers/types', CustomerTypeController::class);
    Route::apiResource('customers/contacts', CustomerContactController::class);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('sample-types', SampleTypeController::class);
    Route::apiResource('sample-matrices', SampleMatrixController::class);
    Route::apiResource('regulations', RegulationController::class);
    Route::apiResource('test-methods', TestMethodController::class);

    Route::get('test-parameters/grouped', [TestParameterController::class, 'listGroupedTestParameters']);
    Route::apiResource('test-parameters', TestParameterController::class);
    Route::apiResource('test-packages', TestPackageController::class);
    Route::apiResource('positions', PositionController::class);
    Route::apiResource('certifications', CertificationController::class);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('subkons', SubkonController::class);
    Route::apiResource('templates', TemplateController::class);
    Route::apiResource('bank-accounts', CompanyBankAccountController::class);
    Route::apiResource('companies', CompanyController::class);

    Route::post('/employees/{employee}/photo', [EmployeeController::class, 'uploadPhoto']);

    Route::apiResource('users', UserController::class);

    Route::post('task-letters/{id}/review', [TaskLetterController::class, 'review']);
    Route::get('task-letters/summary', [TaskLetterController::class, 'summary']);
    Route::apiResource('task-letters', TaskLetterController::class);

    Route::get('offers/summary', [OfferController::class, 'summary']);
    Route::post('offers/{offer}/review', [OfferController::class, 'review']);

    Route::apiResource('offers', OfferController::class);
    Route::post('admin/payments/{payment}/review', [AdminInvoiceController::class, 'reviewPayment']);

    Route::post(
        '/offers/{offer}/documents/subkon/admin',
        [OfferDocumentController::class, 'uploadByAdmin']
    )->middleware('role:admin_kuptdk');

    Route::get('/provinces', [WilayahController::class, 'index']);
    Route::get('/cities/{id}', [WilayahController::class, 'getCity']);

    Route::POST('lhp-documents/{lhp_document}/review', [LhpDocumentController::class, 'review']);
    Route::get('lhp-documents/eligible-offers', [OfferController::class, 'getEligibleOffersForLhp']);
    Route::get('lhp-documents/summary', [LhpDocumentController::class, 'summary']);
    Route::post('lhp-documents/analysis', [LhpDocumentController::class, 'fillAnalysis']);
    Route::apiResource('lhp-documents', LhpDocumentController::class);

    Route::get('invoice-payments/summary', [InvoicePaymentController::class, 'summary']);
    Route::apiResource('invoice-payments', InvoicePaymentController::class);

    Route::get('dashboards', [DashboardController::class, 'dashboardSummary']);
});

Route::get('task-letters/{id}/print', [JalintPdfController::class, 'suratTugasTFPDF']);
Route::get('invoices/print', [JalintPdfController::class, 'printInvoice']);
