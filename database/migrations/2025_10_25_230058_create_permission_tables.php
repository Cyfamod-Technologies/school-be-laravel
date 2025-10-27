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
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $teamKey = $columnNames['team_foreign_key'] ?? null;
        $modelKey = $columnNames['model_morph_key'] ?? 'model_id';

        throw_if(empty($tableNames), new Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.'));
        throw_if($teams && empty($teamKey), new Exception('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.'));

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
        Schema::enableForeignKeyConstraints();

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('school_id')->nullable();
            $table->string('name');
            $table->string('guard_name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'name', 'guard_name'], 'permissions_school_name_guard_name_unique');
            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $teamKey) {
            $table->bigIncrements('id');
            if ($teams || config('permission.testing')) {
                $table->uuid($teamKey)->nullable();
                $table->index($teamKey, 'roles_school_id_index');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->text('description')->nullable();
            $table->timestamps();

            if ($teams || config('permission.testing')) {
                $table->unique([$teamKey, 'name', 'guard_name'], 'roles_school_name_guard_name_unique');
                $table->foreign($teamKey)
                    ->references('id')
                    ->on('schools')
                    ->cascadeOnDelete();
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $teamKey, $pivotPermission, $teams, $modelKey) {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type');
            $table->uuid($modelKey);
            $table->index([$modelKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            if ($teams) {
                $table->uuid($teamKey)->nullable();
                $table->index($teamKey, 'model_has_permissions_school_id_index');

                $table->primary([$teamKey, $pivotPermission, $modelKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([$pivotPermission, $modelKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $teamKey, $pivotRole, $teams, $modelKey) {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type');
            $table->uuid($modelKey);
            $table->index([$modelKey, 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            if ($teams) {
                $table->uuid($teamKey)->nullable();
                $table->index($teamKey, 'model_has_roles_school_id_index');

                $table->primary([$teamKey, $pivotRole, $modelKey, 'model_type'], 'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([$pivotRole, $modelKey, 'model_type'], 'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
