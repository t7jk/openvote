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
class Openvote_Groups_Rest_Controller {

    private const NAMESPACE = 'openvote/v1';

    /** Uprawnienie do zarządzania grupami (lista, członkowie). Wywoływane przez REST API — musi być public. */
    public function can_manage(): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $role = Openvote_Role_Manager::get_user_role( get_current_user_id() );
        return in_array( $role, [ Openvote_Role_Manager::ROLE_POLL_ADMIN, Openvote_Role_Manager::ROLE_POLL_EDITOR ], true );
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

        // POST /jobs/{job_id}/stop — zatrzymaj zadanie (synchronizację)
        register_rest_route( self::NAMESPACE, '/jobs/(?P<job_id>[a-zA-Z0-9_.]+)/stop', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'stop_job' ],
            'permission_callback' => fn() => current_user_can( 'manage_options' ),
            'args'                => [
                'job_id' => [ 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // GET /users/search-by-email — wyszukiwanie użytkowników po e-mailu (dla formularza koordynatorów, bazy 10k+).
        register_rest_route( self::NAMESPACE, '/users/search-by-email', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'search_users_by_email' ],
            'permission_callback' => [ $this, 'can_manage' ],
            'args'                => [
                'email' => [
                    'required'          => true,
                    'sanitize_callback'  => 'sanitize_text_field',
                    'validate_callback'  => function ( $v ) {
                        return is_string( $v ) && strlen( $v ) >= 2;
                    },
                ],
            ],
        ] );
    }

    // ─── Handlery ────────────────────────────────────────────────────────────

    /**
     * Wyszukaj użytkowników po adresie e-mail (LIKE). Max 20 wyników, wydajne przy 10k+ użytkownikach.
     */
    public function search_users_by_email( WP_REST_Request $request ) {
        $email_term = $request->get_param( 'email' );
        if ( ! is_string( $email_term ) || strlen( $email_term ) < 2 ) {
            return new WP_REST_Response( [ 'code' => 'invalid_param', 'message' => __( 'Podaj co najmniej 2 znaki.', 'openvote' ) ], 400 );
        }
        global $wpdb;
        try {
            $like = '%' . $wpdb->esc_like( $email_term ) . '%';
            $ids  = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->users} WHERE user_email LIKE %s ORDER BY user_email ASC LIMIT 20",
                    $like
                )
            );
            $results = [];
            foreach ( (array) $ids as $id ) {
                $user = get_userdata( (int) $id );
                if ( ! $user || ! $user->exists() ) {
                    continue;
                }
                $results[] = [
                    'id'     => (int) $user->ID,
                    'label'  => $this->format_user_label_for_roles( $user ),
                    'email'  => $user->user_email,
                ];
            }
            return new WP_REST_Response( $results, 200 );
        } catch ( \Throwable $e ) {
            return new WP_REST_Response(
                [ 'code' => 'search_error', 'message' => __( 'Błąd wyszukiwania.', 'openvote' ) ],
                500
            );
        }
    }

    /**
     * Etykieta użytkownika w stylu "Imię Nazwisko - login (Miasto)".
     */
    private function format_user_label_for_roles( \WP_User $user ): string {
        $first = trim( (string) Openvote_Field_Map::get_user_value( $user, 'first_name' ) );
        $last  = trim( (string) Openvote_Field_Map::get_user_value( $user, 'last_name' ) );
        $nick  = trim( (string) Openvote_Field_Map::get_user_value( $user, 'nickname' ) );
        if ( $nick === '' ) {
            $nick = $user->user_login;
        }
        $full_name = trim( $first . ' ' . $last );
        $parts     = $full_name !== '' ? [ $full_name, $nick ] : [ $nick ];
        $display   = implode( ' - ', $parts );
        if ( ! Openvote_Field_Map::is_city_disabled() ) {
            $city = trim( (string) Openvote_Field_Map::get_user_value( $user, 'city' ) );
            if ( $city !== '' ) {
                $display .= ' (' . $city . ')';
            }
        }
        return $display;
    }

    public function get_groups( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $groups_table = $wpdb->prefix . 'openvote_groups';
        $groups       = $wpdb->get_results( "SELECT * FROM {$groups_table} ORDER BY name ASC" );

        if ( openvote_is_coordinator_restricted_to_own_groups() ) {
            $my_ids  = array_flip( Openvote_Role_Manager::get_user_groups( get_current_user_id() ) );
            $groups  = array_filter( $groups, function ( $g ) use ( $my_ids ) {
                return isset( $my_ids[ (int) $g->id ] );
            } );
        }

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
        $groups_table = $wpdb->prefix . 'openvote_groups';
        $gm_table     = $wpdb->prefix . 'openvote_group_members';

        $group = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$groups_table} WHERE id = %d", $group_id )
        );

        if ( ! $group ) {
            return new WP_Error( 'not_found', __( 'Grupa nie istnieje.', 'openvote' ), [ 'status' => 404 ] );
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
                Openvote_Batch_Processor::BATCH_SIZE,
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
            'has_more' => ( $offset + Openvote_Batch_Processor::BATCH_SIZE ) < $total,
        ], 200 );
    }

    public function sync_group( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $group_id     = $request->get_param( 'id' );
        $groups_table = $wpdb->prefix . 'openvote_groups';

        $group = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$groups_table} WHERE id = %d", $group_id )
        );

        if ( ! $group ) {
            return new WP_Error( 'not_found', __( 'Grupa nie istnieje.', 'openvote' ), [ 'status' => 404 ] );
        }

        if ( 'city' !== $group->type ) {
            return new WP_Error(
                'not_city_group',
                __( 'Synchronizacja automatyczna działa tylko dla grup typu "city".', 'openvote' ),
                [ 'status' => 400 ]
            );
        }

        $job_id = Openvote_Batch_Processor::start_job( 'sync_group', [
            'group_id'   => (int) $group_id,
            'city_value' => $group->name,
        ] );

        return new WP_REST_Response( [
            'job_id'  => $job_id,
            'message' => __( 'Synchronizacja uruchomiona.', 'openvote' ),
        ], 200 );
    }

    public function sync_all_city_groups( WP_REST_Request $request ): WP_REST_Response {
        $job_id = Openvote_Batch_Processor::start_job( 'sync_all_city_groups', [] );

        return new WP_REST_Response( [
            'job_id'  => $job_id,
            'message' => __( 'Synchronizacja wszystkich grup-miast uruchomiona.', 'openvote' ),
        ], 200 );
    }

    public function get_job_progress( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $job_id = $request->get_param( 'job_id' );
        $job    = Openvote_Batch_Processor::get_job( $job_id );

        if ( false === $job ) {
            return new WP_Error( 'job_expired', __( 'Zadanie wygasło lub nie istnieje.', 'openvote' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $this->format_job( $job_id, $job ), 200 );
    }

    public function process_next_batch( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $job_id = $request->get_param( 'job_id' );
        $job    = Openvote_Batch_Processor::process_batch( $job_id );

        if ( false === $job ) {
            return new WP_Error( 'job_expired', __( 'Zadanie wygasło lub nie istnieje.', 'openvote' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $this->format_job( $job_id, $job ), 200 );
    }

    /**
     * Zatrzymaj zadanie synchronizacji (POST /jobs/{job_id}/stop).
     */
    public function stop_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $job_id = $request->get_param( 'job_id' );
        if ( ! Openvote_Batch_Processor::cancel_job( $job_id ) ) {
            return new WP_Error( 'cannot_stop', __( 'Zadanie nie istnieje lub już jest zakończone.', 'openvote' ), [ 'status' => 400 ] );
        }
        $job = Openvote_Batch_Processor::get_job( $job_id );
        return new WP_REST_Response( $this->format_job( $job_id, $job ), 200 );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function format_job( string $job_id, array $job ): array {
        $data = [
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
            'logs'      => isset( $job['logs'] ) && is_array( $job['logs'] ) ? $job['logs'] : [],
        ];
        if ( isset( $job['users_synced'] ) && is_numeric( $job['users_synced'] ) ) {
            $data['users_synced'] = (int) $job['users_synced'];
        }
        if ( isset( $job['total_users'] ) && is_numeric( $job['total_users'] ) ) {
            $data['total_users'] = (int) $job['total_users'];
        }
        if ( isset( $job['started_at'] ) && $job['started_at'] > 0 ) {
            $data['started_at'] = (int) $job['started_at'];
        }
        $users_synced = (int) ( $job['users_synced'] ?? 0 );
        $total_users  = (int) ( $job['total_users'] ?? 0 );
        $started_at   = (int) ( $job['started_at'] ?? 0 );
        if ( $users_synced > 0 && $total_users > $users_synced && $started_at > 0 ) {
            $elapsed_sec = time() - $started_at;
            if ( $elapsed_sec > 0 ) {
                $remaining    = $total_users - $users_synced;
                $rate_per_sec = $users_synced / $elapsed_sec;
                $est_sec      = $remaining / $rate_per_sec;
                $data['estimated_minutes_remaining'] = max( 1, (int) ceil( $est_sec / 60 ) );
            }
        }
        return $data;
    }
}
