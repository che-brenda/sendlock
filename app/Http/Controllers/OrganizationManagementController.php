<?php

namespace App\Http\Controllers;

use App\Models\Organization;

class OrganizationManagementController extends Controller
{
    public function index()
    {
        $organizations = Organization::latest()->get();

        return view(
            'organizations.index',
            compact('organizations')
        );
    }
}
