<?php

namespace App\Http\Controllers\V1\RoleActions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
class PermissionManagement extends Controller
{
    public function getRoles()
    {
        try {
            $roles = $this->bk_db->table('roles')
                ->select('Id', 'RoleName', 'RoleDescription')
                ->get();

            if ($roles->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No roles found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Roles fetched successfully',
                'data' => $roles,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching roles',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    public function getUsersByRole(Request $request)
    {
        $request->validate([
            'role_id' => 'required|integer',
        ]);

        $role_id = $request->role_id;
        try {
            $users = $this->bk_db->table('portaluserlogoninfo')
                ->join('portaluserrolepermissions', 'portaluserlogoninfo.Id', '=', 'portaluserrolepermissions.User')
                ->where('portaluserrolepermissions.Role', $role_id)
                ->select('portaluserlogoninfo.Id', 'portaluserlogoninfo.Email','portaluserlogoninfo.FirstName','portaluserlogoninfo.OtherNames', 'portaluserlogoninfo.UserName', 'portaluserlogoninfo.CompanyName as CompanyName','portaluserlogoninfo.PhoneNumber as PhoneNumber','portaluserlogoninfo.AccountId as AccountId')
                ->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No users found for the role',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Users fetched successfully',
                'data' => $users,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching users',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    public function getAllRolesForUser(Request $request)
    {
        $request->validate([
            'user_email' => 'required|email',
        ]);

        $user_email = $request->user_email;

        try {
            // Get user id using email
            $user = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $user_email)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Get all roles assigned to this user
            $userRoles = $this->bk_db->table('userroles')
                ->join('roles', 'userroles.Role', '=', 'roles.Id')
                ->where('userroles.User', $user->Id)
                ->select('roles.Id', 'roles.RoleName', 'roles.RoleDescription')
                ->get();

            if ($userRoles->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No roles found for this user',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'User roles fetched successfully',
                'data' => $userRoles,
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error fetching user roles:', ['error' => $th->getMessage()]);
            Log::error('Error stack trace:', ['trace' => $th->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching user roles',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getUserPermissions(Request $request)
    {
        $request->validate([
            'role_id' => 'required|integer',
            'user_email' => 'required|email',
        ]);

        $role_id = $request->role_id;
        $user_email = $request->user_email;
        try {
            // Get user id using email
            $user = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $user_email)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Get role name for default permissions
            $role = $this->bk_db->table('roles')
                ->where('Id', $role_id)
                ->first();

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            // Get default permissions for this role type
            $defaultPermissions = $this->getDefaultPermissionsForRole($role->RoleName);
            $defaultPermissions = array_filter($defaultPermissions, function($value) {
                return $value === true;
            });

            // Get user's specific permissions from portaluserrolepermissions
            $userPermissions = $this->bk_db->table('portaluserrolepermissions')
                ->where('Role', $role_id)
                ->where('User', $user->Id)
                ->first();

            // If user has specific permissions, override defaults
            $finalPermissions = $defaultPermissions;
            if ($userPermissions) {
                foreach ($defaultPermissions as $permName => $value) {
                    // Check if this permission exists in the user's permissions
                    if (property_exists($userPermissions, $permName)) {
                        $finalPermissions[$permName] = (bool)$userPermissions->$permName;
                    }
                }
            }

            // Convert to array of granted permissions for backward compatibility
            $grantedPermissions = [];
            foreach ($finalPermissions as $permName => $isGranted) {
                if ($isGranted) {
                    $grantedPermissions[] = $permName;
                }
            }

            if (empty($grantedPermissions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No permissions found for the user',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'User permissions fetched successfully',
                'data' => $grantedPermissions,
                'all_permissions' => $finalPermissions
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error fetching user permissions:', ['error' => $th->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching user permissions',
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function getRolePermissions(Request $request)
    {
        $request->validate([
            'role_id' => 'required|integer',
        ]);

        $role_id = $request->role_id;

        try {
            // Get role name for default permissions
            $role = $this->bk_db->table('roles')
                ->where('Id', $role_id)
                ->first();

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            // Get default permissions for this role type
            $defaultPermissions = $this->getDefaultPermissionsForRole($role->RoleName);

            // Filter out permissions that are set to false
            $grantedPermissions = array_filter($defaultPermissions, function($value) {
                return $value === true;
            });

            if (empty($grantedPermissions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No permissions found for this role',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role permissions fetched successfully',
                'data' => $grantedPermissions
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error fetching Role permissions:', ['error' => $th->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching Role permissions',
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function modifyUserPermissions(Request $request)
    {
        Log::info("began");
        $request->validate([
            'admin_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'role_id' => 'required|integer',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'permissions' => 'required|array',
        ]);

        Log::info("Validation passed", [
            'admin_email' => $request->admin_email,
            'role_id' => $request->role_id,
            'user_email' => $request->user_email,
            'permissions' => $request->permissions
        ]);

        $role_id = $request->role_id;
        $user_email = $request->user_email;
        $permissions = $request->permissions;
        $admin_email = $request->admin_email;

        try {
            // Get user ID using email
            Log::info("Fetching user by email: " . $user_email);
            $user = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $user_email)
                ->first();

            Log::info("Fetching admin by email: " . $admin_email);
            $user_making_request = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $admin_email)
                ->first();

            if (!$user) {
                Log::error("User not found: " . $user_email);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            if (!$user_making_request) {
                Log::error("Admin user not found: " . $admin_email);
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found',
                ], 404);
            }

            Log::info("Users good", [
                'user_id' => $user->Id,
                'admin_id' => $user_making_request->Id
            ]);

            // Check if user role permission record exists
            Log::info("Checking if user role permission exists for role: " . $role_id . " and user: " . $user->Id);
            $userRolePermission = $this->bk_db->table('portaluserrolepermissions')
                ->where('Role', $role_id)
                ->where('User', $user->Id)
                ->first();

            // Prepare the permission record
            $permissionRecord = [
                'Role' => $role_id,
                'User' => $user->Id,
                'altered_by' => $user_making_request->Id,
                'dola' => Carbon::now(),
            ];

            // Add each permission field
            foreach ($permissions as $permissionName => $value) {
                $permissionRecord[$permissionName] = $value;
            }

            if (!$userRolePermission) {
                Log::info("Creating new user role permission record");
                $permissionRecord['created_on'] = Carbon::now();
                $userRolePermissionId = $this->bk_db->table('portaluserrolepermissions')
                    ->insertGetId($permissionRecord);
                Log::info("New permission record created with ID: " . $userRolePermissionId);
            } else {
                Log::info("Updating existing user role permission record: " . $userRolePermission->Id);
                // Update existing permissions
                $this->bk_db->table('portaluserrolepermissions')
                    ->where('Id', $userRolePermission->Id)
                    ->update($permissionRecord);
                Log::info("Permission record updated successfully");
            }

            Log::info("User permissions modified successfully");
            return response()->json([
                'success' => true,
                'message' => 'User permissions modified successfully',
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error modifying user permissions:', ['error' => $th->getMessage()]);
            Log::error('Error stack trace:', ['trace' => $th->getTraceAsString()]);
            Log::error('User email that caused error: ' . $user_email);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while modifying user permissions',
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
                'user_email_provided' => $user_email
            ], 500);
        }
    }

    public function addUserToNewRole(Request $request)
    {
        $request->validate([
            'admin_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'user_email' => 'required|email|exists:bk_db.portaluserlogoninfo,Email',
            'role_id' => 'required|integer|exists:bk_db.roles,Id'
        ]);

        try {
            $this->bk_db->beginTransaction();

            // Get admin user
            $admin = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $request->admin_email)
                ->first();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found',
                    'data' => null
                ], 404);
            }

            // Get user to modify
            $user = $this->bk_db->table('portaluserlogoninfo')
                ->where('Email', $request->user_email)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null
                ], 404);
            }

            // Get role
            $role = $this->bk_db->table('roles')
                ->where('Id', $request->role_id)
                ->first();

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                    'data' => null
                ], 404);
            }

            // Check if user already has this role
            $existingUserRole = $this->bk_db->table('userroles')
                ->where('User', $user->Id)
                ->where('Role', $request->role_id)
                ->first();

            if (!$existingUserRole) {
                // Add role to user
                $this->bk_db->table('userroles')->insert([
                    'User' => $user->Id,
                    'Role' => $request->role_id,
                    'created_by' => $admin->Id,
                    'created_on' => Carbon::now(),
                    'altered_by' => $admin->Id,
                    'dola' => Carbon::now()
                ]);
            }

            // Assign default permissions based on role
            $roleName = strtolower(str_replace(' ', '', $role->RoleName));
            $permissions = $this->getDefaultPermissionsForRole($roleName);

            // Delete existing permissions for the user with this role
            $this->bk_db->table('portaluserrolepermissions')
                ->where('User', $user->Id)
                ->where('Role', $request->role_id)
                ->delete();

            // Prepare the permission record
            $permissionRecord = [
                'Role' => $request->role_id,
                'User' => $user->Id,
                'created_by' => $admin->Id,
                'created_on' => Carbon::now(),
                'altered_by' => $admin->Id,
                'dola' => Carbon::now(),
            ];

            // Add each permission field
            foreach ($permissions as $permissionName => $value) {
                $permissionRecord[$permissionName] = $value;
            }

            // Insert the new permission record
            $this->bk_db->table('portaluserrolepermissions')
                ->insert($permissionRecord);

            $this->bk_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'User role and permissions updated successfully',
                'data' => [
                    'user_id' => $user->Id,
                    'role_id' => $request->role_id,
                    'role_name' => $role->RoleName
                ]
            ], 200);

        } catch (\Throwable $th) {
            $this->bk_db->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while modifying user role',
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function updateDefaultPermissions($user_id, $role_id)
    {
        // $user = User::find($user_id);
    try {
        $user = $this->bk_db->table('portaluserlogoninfo')
        ->where('Id', $user_id)
        ->first();

        // $role = Role::find($role_id);

        $role = $this->bk_db->table('roles')
        ->where('Id', $role_id)
        ->first();

        if (!$user || !$role) {
            return response()->json(['success' => false, 'message' => 'User or Role not found','data' => null], 404);
        }

        // Assign default permissions based on role
        $roleName = strtolower(str_replace(' ', '', $role->RoleName));
        $permissions = $this->getDefaultPermissionsForRole($roleName);
        // Log::info('Default permissions for role: ' . $role->RoleName, ['permissions' => $permissions]);

        // // Store permissions in the database
        // $userRolePermission = UserRolePermission::updateOrCreate(
        //     ['user_id' => $user_id, 'role_id' => $role_id],
        //     $permissions
        // );

          // Delete existing permissions for the user
          $this->bk_db->table('portaluserrolepermissions')
          ->where('User', $user_id)
          ->delete();

        // Prepare the permission record
        $permissionRecord = [
            'Role' => $role_id,
            'User' => $user_id,
            // 'created_by' => $user_id, // or another appropriate ID
            'created_on' => Carbon::now(),
            // 'altered_by' => null,
            // 'dola' => now(),
        ];

        // Add each permission field
        foreach ($permissions as $permissionName => $value) {
            $permissionRecord[$permissionName] = $value;
        }

        // Insert the new permission record
        $insertPermission = $this->bk_db->table('portaluserrolepermissions')
            ->insert($permissionRecord);

        $this->bk_db->commit();
        Log::info('Permissions updated successfully:', ['permissions' => $permissionRecord]);
        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully',
            'data' =>  $permissionRecord,
        ]);

    } catch (\Throwable $th) {
        $this->bk_db->rollBack();
        Log::error('Error updating permissions:', ['error' => $th->getMessage()]);

        return response()->json([
            'success' => false,
            'message' => 'An error occurred while updating permissions',
            'error' => $th->getMessage(),
            'line' => $th->getLine(),
        ], 500);
    }

    }

    private function getDefaultPermissionsForRole($roleName)
    {
        $permissions = [
            // Dashboard
            'CanAccessBondscreens' => false,
            'CanAccessBondCalc' => false,
            'CanViewYieldGraphs' => false,
            'CanAccessDurationScreen' => false,
            'CanAccessReturnScreen' => false,
            'CanAccessBarbellScreen' => false,

            //Research Assistant
            'CanAccessResearchAssistant' => false,
            'CanAccessResearchAssistantTools' => false,
            // Bond Stats
            'CanAccessBondStats' => false,
            'CanViewBondStats' => false,
            'CanAccessRiskMetrics' => false,

            // Portfolio
            'CanManagePortfolio' => false,
            'CanGenerateQuote' => false,
            'CanAccessPortfolioNotepad' => false,
            'CanAccessProfitAndLoss' => false,
            'CanAccessPortfolioScorecard' => false,
            'CanAccessRiskProfile' => false,
            'CanAccessStressTesting' => false,
            'CanViewFaceValue' => false,

            // Quotes & Transactions
            'CanManageQuotes' => false,
            'CanSubmitBid' => false,
            'CanAccessMyTransactions' => false,
            'CanAccessAllTransactions' => false,
            'CanManageTransactions' => false,
            'CanApproveQuote' => false,
            'CanRejectQuote' => false,
            'CanDelegateQuote' => false,

            // Other Features
            'CanAccessMessages' => false,
            'CanSubmitMessage' => false,
            'CanApproveIntermediary' => false,
            'CanUpdateAccountSettings' => false,
            'CanReceiveNotifications' => false,
            'CanManageUploads' => false,
            'CanAccessSubscriptions' => false,
            'CanPurchaseSubscription' => false,
            'CanAccessHelp' => false,
            'CanAccessFAQ' => false,


            // Admin Features
            'CanManageAccounts' => false,
            'CanViewUserAccounts' => false,
            'CanCreateUserAccount' => false,
            'CanResetPassword' => false,
            'CanDeleteUserAccount' => false,
            'CanAccessAdmin' => false,
            'CanCreateSubscriptionPackage' => false,
            'CanAccessActivityLogs' => false,
            'CanAccessInvoices' => false,
            'CanAccessAnalysis' => false,
            'CanViewInvoices' => false,
            'CanViewAnalysis' => false,
            'CanAccessFinancials' => false,
            'CanViewFinancials' => false,
        ];

        switch (strtolower(trim($roleName))) {
            case 'individual':
            case 'agent':
            case 'broker':
            case 'authorizeddealer':
            case 'corporate':
                $permissions = array_merge($permissions, [
                    // Dashboard
                    'CanAccessBondscreens' => true,
                    'CanAccessBondCalc' => true,
                    'CanViewYieldGraphs' => true,
                    'CanAccessDurationScreen' => true,
                    'CanAccessReturnScreen' => true,
                    'CanAccessBarbellScreen' => true,

                    //Research Assistant
                    'CanAccessResearchAssistant' => true,
                    'CanAccessResearchAssistantTools' => true,

                    // Bond Stats
                    'CanAccessBondStats' => true,
                    'CanViewBondStats' => true,
                    'CanAccessRiskMetrics' => true,


                    // Portfolio
                    'CanManagePortfolio' => true,
                    'CanGenerateQuote' => true,
                    'CanAccessPortfolioNotepad' => true,
                    'CanAccessProfitAndLoss' => true,
                    'CanAccessPortfolioScorecard' => true,
                    'CanAccessRiskProfile' => true,
                    'CanAccessStressTesting' => true,
                    'CanViewFaceValue' => true,

                    // Quotes & Transactions
                    'CanManageQuotes' => true,
                    'CanSubmitBid' => true,
                    'CanAccessMyTransactions' => true,
                    'CanManageTransactions' => true,
                    'CanApproveQuote' => true,
                    'CanRejectQuote' => true,
                    'CanDelegateQuote' => true,

                    // Other Features
                    'CanAccessMessages' => true,
                    'CanSubmitMessage' => true,
                    'CanApproveIntermediary' => true,
                    'CanUpdateAccountSettings' => true,
                    'CanAccessSubscriptions' => true,
                    'CanPurchaseSubscription' => true,
                    'CanReceiveNotifications' => true,
                    'CanAccessHelp' => true,
                    'CanAccessFAQ' => true
                ]);
                break;

            case 'admin':
                foreach ($permissions as $key => $value) {
                    $permissions[$key] = true;
                }
                break;
        }

        return $permissions;
    }


}


