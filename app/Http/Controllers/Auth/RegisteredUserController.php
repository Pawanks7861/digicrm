<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RegisteredUserController extends Controller
{
    /**
     * Public self-registration is disabled. Agency users are created from Settings.
     */
    public function create(): never
    {
        abort(404);
    }

    /**
     * Public self-registration is disabled. Agency users are created from Settings.
     */
    public function store(Request $request): never
    {
        abort(404);
    }
}
