<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staffs', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number', 15)->unique();
            $table->string('firstname');
            $table->string('surname');
            $table->string('marital_status')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('state_of_origin')->nullable();
            $table->string('lga')->nullable();
            $table->string('present_qualification')->nullable();
            $table->string('next_of_kin')->nullable();
            $table->string('phone_of_next_kin')->nullable();
            $table->string('address_of_next_kin')->nullable();
            $table->string('residential_address')->nullable();
            $table->string('department')->nullable();
            $table->string('staff_position')->nullable();
            $table->decimal('monthly_salary', 10, 2)->default(0);
            $table->string('bank_account')->nullable();
            $table->string('bank_name')->nullable();
            $table->date('date_of_appointment')->nullable();
            $table->string('photo_path', 100)->nullable();
            $table->enum('status', ['ACTIVE', 'RESIGNED', 'TERMINATED', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staffs');
    }
};
