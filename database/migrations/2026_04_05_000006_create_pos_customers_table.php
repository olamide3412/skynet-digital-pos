<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('address')->default('NA');
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->mediumText('note')->default('No Note');
            $table->decimal('debt_bal', 10, 2)->default(0);
            $table->string('contact_name')->default('NA');
            $table->string('contact_phone')->default('NA');
            $table->string('contact_address')->default('NA');
            $table->boolean('watch_list')->default(false);
            $table->timestamps();
            $table->index(['branch_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_customers');
    }
};
