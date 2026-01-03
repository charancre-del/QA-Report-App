<?php
/**
 * User Roles
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Auth;

/**
 * Handles custom user roles and capabilities.
 */
class User_Roles {

    /**
     * Available roles.
     *
     * @var array
     */
    const ROLES = [
        'cqa_super_admin'       => 'QA Super Admin',
        'cqa_regional_director' => 'QA Regional Director',
        'cqa_qa_officer'        => 'QA Officer',
        'cqa_program_manager'   => 'QA Program Manager',
    ];

    /**
     * Role capabilities.
     *
     * @var array
     */
    const CAPABILITIES = [
        'cqa_super_admin' => [
            'read'                 => true,
            'cqa_manage_settings'  => true,
            'cqa_manage_users'     => true,
            'cqa_manage_schools'   => true,
            'cqa_view_all_reports' => true,
            'cqa_create_reports'   => true,
            'cqa_edit_all_reports' => true,
            'cqa_delete_reports'   => true,
            'cqa_export_reports'   => true,
            'cqa_use_ai_features'  => true,
        ],
        'cqa_regional_director' => [
            'read'                 => true,
            'cqa_manage_schools'   => true,
            'cqa_view_all_reports' => true,
            'cqa_create_reports'   => true,
            'cqa_edit_own_reports' => true,
            'cqa_export_reports'   => true,
            'cqa_use_ai_features'  => true,
        ],
        'cqa_qa_officer' => [
            'read'                 => true,
            'cqa_view_all_reports' => true,
            'cqa_create_reports'   => true,
            'cqa_edit_own_reports' => true,
            'cqa_export_reports'   => true,
            'cqa_use_ai_features'  => true,
        ],
        'cqa_program_manager' => [
            'read'                => true,
            'cqa_view_own_reports' => true,
            'cqa_export_reports'   => true,
        ],
    ];

    /**
     * Get all QA roles.
     *
     * @return array
     */
    public static function get_roles() {
        return self::ROLES;
    }

    /**
     * Get capabilities for a role.
     *
     * @param string $role Role name.
     * @return array
     */
    public static function get_role_capabilities( $role ) {
        return self::CAPABILITIES[ $role ] ?? [];
    }

    /**
     * Check if a user has a QA role.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public static function has_qa_role( $user_id ) {
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return false;
        }

        foreach ( array_keys( self::ROLES ) as $role ) {
            if ( in_array( $role, $user->roles, true ) ) {
                return true;
            }
        }

        // Also check if administrator
        return in_array( 'administrator', $user->roles, true );
    }

    /**
     * Get the user's QA role.
     *
     * @param int $user_id User ID.
     * @return string|null
     */
    public static function get_user_qa_role( $user_id ) {
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return null;
        }

        foreach ( array_keys( self::ROLES ) as $role ) {
            if ( in_array( $role, $user->roles, true ) ) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Assign a QA role to a user.
     *
     * @param int    $user_id User ID.
     * @param string $role    Role name.
     * @return bool
     */
    public static function assign_role( $user_id, $role ) {
        if ( ! isset( self::ROLES[ $role ] ) ) {
            return false;
        }

        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return false;
        }

        // Remove existing QA roles
        foreach ( array_keys( self::ROLES ) as $existing_role ) {
            $user->remove_role( $existing_role );
        }

        // Add new role
        $user->add_role( $role );

        return true;
    }

    /**
     * Remove all QA roles from a user.
     *
     * @param int $user_id User ID.
     */
    public static function remove_all_qa_roles( $user_id ) {
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return;
        }

        foreach ( array_keys( self::ROLES ) as $role ) {
            $user->remove_role( $role );
        }
    }

    /**
     * Get users by QA role.
     *
     * @param string $role Role name.
     * @return array
     */
    public static function get_users_by_role( $role ) {
        return get_users( [
            'role' => $role,
        ] );
    }
}
