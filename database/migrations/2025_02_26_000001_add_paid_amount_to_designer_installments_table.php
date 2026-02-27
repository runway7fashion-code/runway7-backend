<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designer_installments', function (Blueprint $table) {
            $table->decimal('paid_amount', 10, 2)->default(0)->after('amount');
        });

        // Add 'partial' to the status enum (PostgreSQL CHECK constraint)
        DB::statement("ALTER TABLE designer_installments DROP CONSTRAINT designer_installments_status_check");
        DB::statement("ALTER TABLE designer_installments ADD CONSTRAINT designer_installments_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'paid'::text, 'overdue'::text, 'cancelled'::text, 'partial'::text]))");

        // Sync existing paid installments
        DB::statement("UPDATE designer_installments SET paid_amount = amount WHERE status = 'paid'");
    }

    public function down(): void
    {
        // Revert partial installments to pending before removing the constraint
        DB::statement("UPDATE designer_installments SET status = 'pending', paid_amount = 0 WHERE status = 'partial'");

        DB::statement("ALTER TABLE designer_installments DROP CONSTRAINT designer_installments_status_check");
        DB::statement("ALTER TABLE designer_installments ADD CONSTRAINT designer_installments_status_check CHECK (status::text = ANY (ARRAY['pending'::text, 'paid'::text, 'overdue'::text, 'cancelled'::text]))");

        Schema::table('designer_installments', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
