<?php
/**
 * Centralized authorization policy.
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Auth;

use ChromaQA\Models\Report;
use ChromaQA\Models\School;

/**
 * Enforces report ownership and school scope at every application boundary.
 */
class Access_Policy {

    /**
     * Get a WordPress user, defaulting to the current user.
     *
     * @param int $user_id Optional user ID.
     * @return \WP_User|false
     */
    private static function get_user( $user_id = 0 ) {
        $user_id = $user_id ?: \get_current_user_id();
        return $user_id ? \get_userdata( $user_id ) : false;
    }

    /**
     * Determine whether a user has a role.
     *
     * @param object $user User object.
     * @param string $role Role slug.
     * @return bool
     */
    private static function has_role( $user, $role ) {
        return $user && in_array( $role, (array) $user->roles, true );
    }

    /**
     * Determine whether a user has unrestricted school scope.
     *
     * A broad capability is not enough for regional users; their role remains
     * region-scoped even though the legacy role grants cqa_view_all_reports.
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function has_global_scope( $user_id = 0 ) {
        $user = self::get_user( $user_id );
        if ( ! $user ) {
            return false;
        }

        return \user_can( $user, 'manage_options' )
            || ( self::has_role( $user, 'cqa_super_admin' ) && \user_can( $user, 'cqa_view_all_reports' ) )
            || ( self::has_role( $user, 'cqa_qa_officer' ) && \user_can( $user, 'cqa_view_all_reports' ) );
    }

    /**
     * Get the assigned region for a regional director.
     *
     * @param int $user_id Optional user ID.
     * @return string
     */
    public static function get_region_scope( $user_id = 0 ) {
        $user = self::get_user( $user_id );
        if ( ! self::has_role( $user, 'cqa_regional_director' ) ) {
            return '';
        }

        return trim( (string) \get_user_meta( $user->ID, 'cqa_region', true ) );
    }

    /**
     * Get the assigned school for a school-scoped user.
     *
     * @param int $user_id Optional user ID.
     * @return int
     */
    public static function get_school_scope( $user_id = 0 ) {
        $user = self::get_user( $user_id );
        if ( ! $user ) {
            return 0;
        }

        return absint( \get_user_meta( $user->ID, 'cqa_school_id', true ) );
    }

    /**
     * Whether the current user can list reports.
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function can_list_reports( $user_id = 0 ) {
        $user = self::get_user( $user_id );
        return $user && (
            \user_can( $user, 'cqa_view_own_reports' )
            || \user_can( $user, 'cqa_view_all_reports' )
            || \user_can( $user, 'cqa_create_reports' )
        );
    }

    /**
     * Whether the current user can list schools in their scope.
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function can_list_schools( $user_id = 0 ) {
        if ( ! self::can_list_reports( $user_id ) && ! self::can_manage_any_school( $user_id ) ) {
            return false;
        }

        return self::has_global_scope( $user_id )
            || '' !== self::get_region_scope( $user_id )
            || 0 !== self::get_school_scope( $user_id );
    }

    /**
     * Whether the user has the school-management capability.
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function can_manage_any_school( $user_id = 0 ) {
        $user = self::get_user( $user_id );
        return $user && \user_can( $user, 'cqa_manage_schools' );
    }

    /**
     * Whether the user can manage application settings.
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function can_manage_settings( $user_id = 0 ) {
        $user = self::get_user( $user_id );
        return $user && (
            \user_can( $user, 'manage_options' )
            || \user_can( $user, 'cqa_manage_settings' )
        );
    }

    /**
     * Whether a school is within the user's scope.
     *
     * @param School|null $school School object.
     * @param int         $user_id Optional user ID.
     * @return bool
     */
    public static function can_access_school( $school, $user_id = 0 ) {
        if ( ! $school ) {
            return false;
        }

        if ( self::has_global_scope( $user_id ) ) {
            return true;
        }

        $region = self::get_region_scope( $user_id );
        if ( '' !== $region ) {
            return hash_equals( $region, (string) $school->region );
        }

        $school_id = self::get_school_scope( $user_id );
        return $school_id > 0 && $school_id === (int) $school->id;
    }

    /**
     * Whether the user can create a school with the supplied region.
     *
     * @param string $region Requested region.
     * @param int    $user_id Optional user ID.
     * @return bool
     */
    public static function can_create_school( $region, $user_id = 0 ) {
        if ( ! self::can_manage_any_school( $user_id ) ) {
            return false;
        }

        if ( self::has_global_scope( $user_id ) ) {
            return true;
        }

        $assigned_region = self::get_region_scope( $user_id );
        return '' !== $assigned_region && hash_equals( $assigned_region, trim( (string) $region ) );
    }

    /**
     * Whether the user can change a school.
     *
     * @param School|null $school School object.
     * @param int         $user_id Optional user ID.
     * @return bool
     */
    public static function can_manage_school( $school, $user_id = 0 ) {
        return self::can_manage_any_school( $user_id ) && self::can_access_school( $school, $user_id );
    }

    /**
     * Whether the user can create a report for a school.
     *
     * @param School|null $school School object.
     * @param int         $user_id Optional user ID.
     * @return bool
     */
    public static function can_create_report_for_school( $school, $user_id = 0 ) {
        $user = self::get_user( $user_id );
        return $user
            && \user_can( $user, 'cqa_create_reports' )
            && self::can_access_school( $school, $user_id );
    }

    /**
     * Whether the user can view a report.
     *
     * @param Report|null $report Report object.
     * @param int         $user_id Optional user ID.
     * @return bool
     */
    public static function can_view_report( $report, $user_id = 0 ) {
        $user = self::get_user( $user_id );
        if ( ! $user || ! $report ) {
            return false;
        }

        $user_id = $user->ID;

        if ( ( \user_can( $user, 'cqa_view_own_reports' ) || \user_can( $user, 'cqa_create_reports' ) )
            && (int) $report->user_id === (int) $user_id ) {
            return true;
        }

        $school = School::find( $report->school_id );

        if ( \user_can( $user, 'cqa_view_all_reports' ) && self::can_access_school( $school, $user_id ) ) {
            return true;
        }

        if ( self::has_role( $user, 'cqa_program_manager' )
            && \user_can( $user, 'cqa_view_own_reports' )
            && self::can_access_school( $school, $user_id ) ) {
            return true;
        }

        return false;
    }

    /**
     * Whether the user can edit a report.
     *
     * @param Report|null $report Report object.
     * @param int         $user_id Optional user ID.
     * @return bool
     */
    public static function can_edit_report( $report, $user_id = 0 ) {
        $user = self::get_user( $user_id );
        if ( ! $user || ! $report ) {
            return false;
        }

        if ( \user_can( $user, 'cqa_edit_all_reports' )
            && self::can_access_school( School::find( $report->school_id ), $user->ID ) ) {
            return true;
        }

        return \user_can( $user, 'cqa_edit_own_reports' )
            && (int) $report->user_id === (int) $user->ID;
    }

    /**
     * Whether the user can delete a report.
     *
     * @param Report|null $report Report object.
     * @param int         $user_id Optional user ID.
     * @return bool
     */
    public static function can_delete_report( $report, $user_id = 0 ) {
        $user = self::get_user( $user_id );
        if ( ! $user || ! $report ) {
            return false;
        }

        if ( \user_can( $user, 'cqa_delete_reports' )
            && self::can_access_school( School::find( $report->school_id ), $user->ID ) ) {
            return true;
        }

        return \user_can( $user, 'cqa_delete_own_reports' )
            && (int) $report->user_id === (int) $user->ID;
    }

    /**
     * Whether the user can export a report.
     *
     * @param Report|null $report Report object.
     * @param int         $user_id Optional user ID.
     * @return bool
     */
    public static function can_export_report( $report, $user_id = 0 ) {
        $user = self::get_user( $user_id );
        return $user
            && \user_can( $user, 'cqa_export_reports' )
            && self::can_view_report( $report, $user->ID );
    }

    /**
     * Whether the user can run AI against a report.
     *
     * @param Report|null $report Report object.
     * @param int         $user_id Optional user ID.
     * @return bool
     */
    public static function can_use_ai_for_report( $report, $user_id = 0 ) {
        $user = self::get_user( $user_id );
        return $user
            && \user_can( $user, 'cqa_use_ai_features' )
            && self::can_edit_report( $report, $user->ID );
    }

    /**
     * Whether the user can use the document parser.
     *
     * @param int $user_id Optional user ID.
     * @return bool
     */
    public static function can_parse_document( $user_id = 0 ) {
        $user = self::get_user( $user_id );
        return $user
            && \user_can( $user, 'cqa_create_reports' )
            && \user_can( $user, 'cqa_use_ai_features' );
    }

    /**
     * Apply the current user's scope to a School::all() query.
     *
     * @param array $args Query arguments.
     * @param int   $user_id Optional user ID.
     * @return array
     */
    public static function scope_school_args( $args = [], $user_id = 0 ) {
        if ( self::has_global_scope( $user_id ) ) {
            return $args;
        }

        $region = self::get_region_scope( $user_id );
        if ( '' !== $region ) {
            if ( ! empty( $args['region'] ) && ! hash_equals( $region, (string) $args['region'] ) ) {
                $args['ids'] = [];
                return $args;
            }
            $args['region'] = $region;
            return $args;
        }

        $school_id = self::get_school_scope( $user_id );
        $args['ids'] = $school_id > 0 ? [ $school_id ] : [];
        return $args;
    }

    /**
     * Apply the current user's scope to a Report::all()/count() query.
     *
     * @param array $args Query arguments.
     * @param int   $user_id Optional user ID.
     * @return array
     */
    public static function scope_report_args( $args = [], $user_id = 0 ) {
        $user = self::get_user( $user_id );
        if ( ! $user ) {
            $args['school_ids'] = [];
            return $args;
        }

        if ( \user_can( $user, 'cqa_view_all_reports' ) && self::has_global_scope( $user->ID ) ) {
            return $args;
        }

        $region = self::get_region_scope( $user->ID );
        if ( \user_can( $user, 'cqa_view_all_reports' ) && '' !== $region ) {
            $schools = School::all( [
                'region' => $region,
                'limit'  => 10000,
            ] );
            $school_ids = array_map( 'intval', \wp_list_pluck( $schools, 'id' ) );

            if ( ! empty( $args['school_id'] ) && ! in_array( (int) $args['school_id'], $school_ids, true ) ) {
                $args['school_ids'] = [];
            } else {
                $args['school_ids'] = $school_ids;
            }
            return $args;
        }

        $school_id = self::get_school_scope( $user->ID );
        if ( self::has_role( $user, 'cqa_program_manager' )
            && \user_can( $user, 'cqa_view_own_reports' )
            && $school_id > 0 ) {
            if ( ! empty( $args['school_id'] ) && (int) $args['school_id'] !== $school_id ) {
                $args['school_ids'] = [];
            } else {
                $args['school_id'] = $school_id;
            }
            return $args;
        }

        $args['user_id'] = (int) $user->ID;
        return $args;
    }
}
