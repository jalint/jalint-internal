<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerAccountRequest;
use App\Http\Requests\UpdateCustomerAccountRequest;
use App\Models\CustomerAccount;

class CustomerAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerAccountRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerAccount $customerAccount)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerAccountRequest $request, CustomerAccount $customerAccount)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerAccount $customerAccount)
    {
        //
    }
}
