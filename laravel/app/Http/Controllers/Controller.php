<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="SmartSprouts API",
 *     version="1.0.0",
 *     description="API documentation for SmartSprouts backend",
 *
 *     @OA\Contact(
 *         email="support@smartsprouts.local"
 *     )
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
