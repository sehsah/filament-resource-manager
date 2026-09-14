<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('filament_navigation_profile_assignments');
    }

    public function down(): void
    {
        // Assignments are no longer part of the package.
    }
};
