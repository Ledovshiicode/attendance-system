<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->string('source')->default('employee')->after('counted_seconds');
            $table->foreignId('created_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->text('note')->nullable()->after('created_by');

            $table->index(['employee_id', 'check_in_at', 'check_out_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['employee_id', 'check_in_at', 'check_out_at']);
            $table->dropColumn(['source', 'created_by', 'note']);
        });
    }
};
