<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

class MigrateController extends Controller
{
    public function migrate()
    {
        try {
            Artisan::call('migrate');

            return response()->json(['message' => 'Migrations run successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Migrations failed', 'error' => $e->getMessage()], 500);
        }
    }
}
