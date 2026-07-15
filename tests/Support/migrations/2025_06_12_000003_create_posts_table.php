<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only host model table WITH SoftDeletes, used to exercise
 * Concerns\HasMedia's delete/soft-delete/restore/force-delete cleanup
 * rules. Also doubles as the "different model type" side of the
 * type-isolation tests against Product (both tables auto-increment from
 * 1, so Product #1 and Post #1 legitimately share the same numeric ID
 * while being different `mediable_type` values).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
