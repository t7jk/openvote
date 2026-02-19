<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API — Grupy i zadania masowe (Faza 2).
 *
 * Endpointy:
 *   GET  /groups                      — lista grup (Admin/Redaktor)
 *   GET  /groups/{id}/members         — członkowie grupy (paginacja, offset)
 *   POST /groups/{id}/sync            — start synchronizacji → zwraca job_id
 *   POST /groups/sync-all             — sync wszystkich grup-miast → zwraca job_id
 *   GET  /jobs/{job_id}/progress      — status zadania masowego
 *   POST /jobs/{job_id}/next          — przetwórz następną partię
 */
class Evoting_Groups_Rest_Controller {

    private const NAMESPACE = 'evoting/v1';

    /** Uprawnienie do zarządzania grupami. */
    private function can_manage(): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $role = Evoting_Role_Manager::get_user_role( get_current_user_id() );
        return in_array( $role, [ Evoting_Role_Manager::ROLE_POLL_ADMIN, Evoting_Role_Manager::ROLE_POLL_EDITOR ], true );
    }

    public function register_routes(): void {

        // GET /groups
        register_rest_route( self::NAMESPACE, '/groups', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_groups' ],
            'permission_callback' => [ $this, 'can_manage' ],
        ] );

        // GET /groups/{id}/members
        register_rest_route( self::NAMESPACE, '/groups/(?P<id>\d+)/members', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_members' ],
            'permission_callback' => [ $this, 'can_manage' ],
            'args'                => [
                'id'     => [ 'sanitize_callback' => 'absint' ],
                'offset' => [
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        // POST /groups/{id}/sync — synchronizuj jedną grupę
        register_rest_route( self::NAMESPACE, '/groups/(?P<id>\d+)/sync', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'sync_group' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
            'args'                => [
                'id' => [ 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // POST /groups/sync-all — synchronizuj wszystkie grupy-miasta
        register_rest_route( self::NAMESPACE, '/groups/sync-all', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'sync_all_city_groups' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
        ] );

        // GET /jobs/{job_id}/progress
        register_rest_route( self::NAMESPACE, '/jobs/(?P<job_id>[a-zA-Z0-9_.]+)/progress', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_job_progress' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
            'args'                => [
                'job_id' => [ 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // POST /jobs/{job_id}/next
        register_rest_route( self::NAMESPACE, '/jobs/(?P<job_id>[a-zA-Z0-9_.]+)/next', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'process_next_batch' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
            'args'                => [
                'job_id' => [ 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    // ─── Handlery ────────────────────────────────────────────────────────────

    public function get_groups( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $groups_table = $wpdb->prefix . 'evoting_groups';
        $groups       = $wpdb->get_results( "SELECT * FROM {$groups_table} ORDER BY name ASC" );

        $data = array_map( fn( $g ) => [
            'id'           => (int) $g->id,
            'name'         => $g->name,
            'type'         => $g->type,
            'description'  => $g->description,
            'member_count' => (int) $g->member_count,
            'created_at'   => $g->created_at,
        ], $groups );

        return new WP_REST_Response( $data, 200 );
    }

    public function get_members( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $group_id     = $request->get_param( 'id' );
        $offset       = $request->get_param( 'offset' );
        $groups_table = $wpdb->prefix . 'evoting_groups';
        $gm_table     = $wpdb->prefix . 'evoting_group_members';

        $group = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$groups_table} WHERE id = %d", $group_id )
        );

        if ( ! $group ) {
            return new WP_Error( 'not_found', __( 'Grupa nie istnieje.', 'evoting' ), [ 'status' => 404 ] );
        }

        $total = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$gm_table} WHERE group_id = %d", $group_id )
        );

        $members = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT gm.user_id, gm.source, gm.added_at, u.display_name, u.user_email
                 FROM {$gm_table} gm
                 INNER JOIN {$wpdb->users} u ON gm.user_id = u.ID
                 WHERE gm.group_id = %d
                 ORDER BY u.display_name ASC
                 LIMIT %d OFFSET %d",
                $group_id,
                Evoting_Batch_Processor::BATCH_SIZE,
                $offset
            )
        );

        $data = array_map( fn( $m ) => [
            'user_id'      => (int) $m->user_id,
            'display_name' => $m->display_name,
            'email'        => $m->user_email,
            'source'       => $m->source,
            'added_at'     => $m->added_at,
        ], $members );

        return new WP_REST_Response( [
            'group_id' => $group_id,
            'total'    => $total,
            'offset'   => $offset,
            'members'  => $data,
            'has_more' => ( $offset + Evoting_Batch_Processor::BATCH_SIZE ) < $total,
        ], 200 );
    }

    public function sync_group( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $group_id     = $request->get_param( 'id' );
        $groups_table = $wpdb->prefix . 'evoting_groups';

        $group = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$groups_table} WHERE id = %d", $group_id )
        );

        if ( ! $group ) {
            return new WP_Error( 'not_found', __( 'Grupa nie istnieje.', 'evoting' ), [ 'status' => 404 ] );
        }

        if ( 'city' !== $group->type ) {
            return new WP_Error(
                'not_city_group',
                __( 'Synchronizacja automatyczna działa tylko dla grup typu "city".', 'evoting' ),
                [ 'status' => 400 ]
            );
        }

        $job_id = Evoting_Batch_Processor::start_job( 'sync_group', [
            'group_id'   => (int) $group_id,
            'city_value' => $group->name,
        ] );

        return new WP_REST_Response( [
            'job_id'  => $job_id,
            'message' => __( 'Synchronizacja uruchomiona.', 'evoting' ),
        ], 200 );
    }

    public function sync_all_city_groups( WP_REST_Request $request ): WP_REST_Response {
        $job_id = Evoting_Batch_Processor::start_job( 'sync_all_city_groups', [] );

        return new WP_REST_Response( [
            'job_id'  => $job_id,
            'message' => __( 'Synchronizacja wszystkich grup-miast uruchomiona.', 'evoting' ),
        ], 200 );
    }

    public function get_job_progress( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $job_id = $request->get_param( 'job_id' );
        $job    = Evoting_Batch_Processor::get_job( $job_id );

        if ( false === $job ) {
            return new WP_Error( 'job_expired', __( 'Zadanie wygasło lub nie istnieje.', 'evoting' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $this->format_job( $job_id, $job ), 200 );
    }

    public function process_next_batch( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $job_id = $request->get_param( 'job_id' );
        $job    = Evoting_Batch_Processor::process_batch( $job_id );

        if ( false === $job ) {
            return new WP_Error( 'job_expired', __( 'Zadanie wygasło lub nie istnieje.', 'evoting' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $this->format_job( $job_id, $job ), 200 );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function format_job( string $job_id, array $job ): array {
        return [
            'job_id'    => $job_id,
            'type'      => $job['type'],
            'status'    => $job['status'],
            'total'     => $job['total'],
            'processed' => $job['processed'],
            'offset'    => $job['offset'],
            'pct'       => $job['total'] > 0
                ? round( $job['processed'] / $job['total'] * 100 )
                : ( 'done' === $job['status'] ? 100 : 0 ),
            'results_count' => count( $job['results'] ),
        ];
    }
}
