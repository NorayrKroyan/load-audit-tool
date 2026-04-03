<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('load', function (Blueprint $table) {
            if (! Schema::hasColumn('load', 'entered_at')) {
                $table->dateTime('entered_at')->nullable()->after('is_finished');
            }

            if (! Schema::hasColumn('load', 'audit_user_id')) {
                $table->unsignedBigInteger('audit_user_id')->nullable()->after('entered_at');
                $table->index('audit_user_id');
            }

            if (! Schema::hasColumn('load', 'audited_at')) {
                $table->dateTime('audited_at')->nullable()->after('audit_user_id');
                $table->index('audited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('load', function (Blueprint $table) {
            if (Schema::hasColumn('load', 'audited_at')) {
                $table->dropIndex(['audited_at']);
                $table->dropColumn('audited_at');
            }

            if (Schema::hasColumn('load', 'audit_user_id')) {
                $table->dropIndex(['audit_user_id']);
                $table->dropColumn('audit_user_id');
            }

            if (Schema::hasColumn('load', 'entered_at')) {
                $table->dropColumn('entered_at');
            }
        });
    }
};
