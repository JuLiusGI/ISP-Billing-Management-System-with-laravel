<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Gives every controller $this->authorize(). Laravel 12 ships this base
    // class empty, so authorisation has to be opted into explicitly.
    use AuthorizesRequests;
}
