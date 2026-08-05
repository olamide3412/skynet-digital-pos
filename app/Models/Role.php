<?php

namespace App\Models;

/**
 * This model now delegates to Spatie's Role model.
 * It's kept as an alias/reference for backward compatibility in code
 * that still type-hints App\Models\Role.
 *
 * For new code, use Spatie\Permission\Models\Role directly or via `Role::` facade.
 */
class Role extends \Spatie\Permission\Models\Role
{
    // Spatie's role model handles everything.
    // Branch-scoped roles use team_id = branch_id (configured in permission.php).
}
