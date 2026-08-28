<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('import_library_files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('item_type_id')->nullable()->constrained('import_item_types')->nullOnDelete();
            $table->string('item_type_tag')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_format', 10)->default('xlsx');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->text('description')->nullable();
            $table->unsignedInteger('import_count')->default(0);
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'item_type_tag']);
        });

        // Seed initial default store/item type tags
        $defaultTypes = [
            ['name' => 'Pharmacy',                'description' => 'Pharmaceuticals, drugs, medical supplies & dispensary products'],
            ['name' => 'Supermarket & Grocery',   'description' => 'Fast-moving consumer goods, food, beverages & household items'],
            ['name' => 'Phone & Gadgets',         'description' => 'Mobile phones, smart devices, tablets & IMEI-tracked electronics'],
            ['name' => 'Electronics & Computers', 'description' => 'Computing accessories, cables, home appliances & gadgets'],
            ['name' => 'Fashion & Boutique',      'description' => 'Clothing, shoes, bags & apparel products'],
            ['name' => 'Restaurant & Eatery',     'description' => 'Prepared meals, kitchen recipes, drinks & food inventory'],
            ['name' => 'Hardware & Tools',        'description' => 'Building materials, plumbing, electrical & workshop tools'],
            ['name' => 'General Retail',          'description' => 'Multi-purpose merchandise and general commerce catalogs'],
        ];

        foreach ($defaultTypes as $type) {
            DB::table('import_item_types')->insert([
                'name'        => $type['name'],
                'slug'        => Str::slug($type['name']),
                'description' => $type['description'],
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_library_files');
        Schema::dropIfExists('import_item_types');
    }
};
