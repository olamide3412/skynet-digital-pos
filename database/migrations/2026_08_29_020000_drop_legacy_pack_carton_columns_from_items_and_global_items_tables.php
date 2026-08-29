<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $columnsToDrop = [];
            $legacyColumns = [
                'pack_price',
                'carton_price',
                'pack_wholesale_price',
                'carton_wholesale_price',
                'units_per_pack',
                'packs_per_carton',
                'pack_label',
                'carton_label',
            ];

            foreach ($legacyColumns as $col) {
                if (Schema::hasColumn('items', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        if (Schema::hasTable('global_items')) {
            Schema::table('global_items', function (Blueprint $table) {
                $columnsToDrop = [];
                $legacyColumns = [
                    'pack_price',
                    'carton_price',
                    'pack_wholesale_price',
                    'carton_wholesale_price',
                    'units_per_pack',
                    'packs_per_carton',
                    'pack_label',
                    'carton_label',
                ];

                foreach ($legacyColumns as $col) {
                    if (Schema::hasColumn('global_items', $col)) {
                        $columnsToDrop[] = $col;
                    }
                }

                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'pack_price')) {
                $table->decimal('pack_price', 10, 2)->nullable()->after('wholesale_price');
            }
            if (!Schema::hasColumn('items', 'carton_price')) {
                $table->decimal('carton_price', 10, 2)->nullable()->after('pack_price');
            }
            if (!Schema::hasColumn('items', 'pack_wholesale_price')) {
                $table->decimal('pack_wholesale_price', 10, 2)->nullable()->after('carton_price');
            }
            if (!Schema::hasColumn('items', 'carton_wholesale_price')) {
                $table->decimal('carton_wholesale_price', 10, 2)->nullable()->after('pack_wholesale_price');
            }
            if (!Schema::hasColumn('items', 'pack_label')) {
                $table->string('pack_label', 50)->nullable()->default('Pack')->after('unit_label');
            }
            if (!Schema::hasColumn('items', 'carton_label')) {
                $table->string('carton_label', 50)->nullable()->default('Carton')->after('pack_label');
            }
            if (!Schema::hasColumn('items', 'units_per_pack')) {
                $table->unsignedInteger('units_per_pack')->default(1)->after('carton_label');
            }
            if (!Schema::hasColumn('items', 'packs_per_carton')) {
                $table->unsignedInteger('packs_per_carton')->default(1)->after('units_per_pack');
            }
        });

        if (Schema::hasTable('global_items')) {
            Schema::table('global_items', function (Blueprint $table) {
                if (!Schema::hasColumn('global_items', 'pack_price')) {
                    $table->decimal('pack_price', 10, 2)->nullable()->after('wholesale_price');
                }
                if (!Schema::hasColumn('global_items', 'carton_price')) {
                    $table->decimal('carton_price', 10, 2)->nullable()->after('pack_price');
                }
                if (!Schema::hasColumn('global_items', 'pack_wholesale_price')) {
                    $table->decimal('pack_wholesale_price', 10, 2)->nullable()->after('carton_price');
                }
                if (!Schema::hasColumn('global_items', 'carton_wholesale_price')) {
                    $table->decimal('carton_wholesale_price', 10, 2)->nullable()->after('pack_wholesale_price');
                }
                if (!Schema::hasColumn('global_items', 'pack_label')) {
                    $table->string('pack_label', 50)->nullable()->default('Pack')->after('unit_label');
                }
                if (!Schema::hasColumn('global_items', 'carton_label')) {
                    $table->string('carton_label', 50)->nullable()->default('Carton')->after('pack_label');
                }
                if (!Schema::hasColumn('global_items', 'units_per_pack')) {
                    $table->unsignedInteger('units_per_pack')->default(1)->after('carton_label');
                }
                if (!Schema::hasColumn('global_items', 'packs_per_carton')) {
                    $table->unsignedInteger('packs_per_carton')->default(1)->after('units_per_pack');
                }
            });
        }
    }
};
