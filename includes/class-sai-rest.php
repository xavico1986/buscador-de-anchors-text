<?php
/**
 * REST controller for Anchors sin IA.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAI_REST_Controller {

    /**
     * Namespace.
     */
    const REST_NAMESPACE = 'anchors/v1';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Registers routes.
     */
    public function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/search',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_search' ],
                'permission_callback' => [ $this, 'permission_check' ],
                'args'                => [
                    'kw'      => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                    'in_body' => [
                        'type'              => 'integer',
                        'required'          => false,
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                    ],
                    'page'    => [
                        'type'              => 'integer',
                        'required'          => false,
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'exclude' => [
                        'type'     => 'array',
                        'required' => false,
                    ],
                    'context_id' => [
                        'type'              => 'integer',
                        'required'          => false,
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                    ],
                    'canonical' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/post/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_get_post' ],
                'permission_callback' => [ $this, 'permission_check' ],
                'args'                => [
                    'id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/extract',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_extract' ],
                'permission_callback' => [ $this, 'permission_check' ],
                'args'                => [
                    'id'        => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'canonical' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                    'body_text' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                ],
            ]
        );

        // -------- Link Builder endpoints --------
        register_rest_route(
            self::REST_NAMESPACE,
            '/linkbuilder/state',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_lb_get_state' ],
                'permission_callback' => [ $this, 'permission_check' ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/linkbuilder/reset',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_lb_reset' ],
                'permission_callback' => [ $this, 'permission_check' ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/linkbuilder/madre',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_lb_set_madre' ],
                'permission_callback' => [ $this, 'permission_check' ],
                'args'                => [
                    'id'        => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'canonical' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                    'keyword'   => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'in_content' => [
                        'type'              => 'integer',
                        'required'          => false,
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/linkbuilder/hijas',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_lb_set_hijas' ],
                'permission_callback' => [ $this, 'permission_check' ],
                'args'                => [
                    'ids'        => [
                        'type'     => 'array',
                        'required' => true,
                    ],
                    'keyword'    => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'in_content' => [
                        'type'              => 'integer',
                        'required'          => false,
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/linkbuilder/nietas',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_lb_set_nietas' ],
                'permission_callback' => [ $this, 'permission_check' ],
                'args'                => [
                    'ids'        => [
                        'type'     => 'array',
                        'required' => true,
                    ],
                    'keyword'    => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'in_content' => [
                        'type'              => 'integer',
                        'required'          => false,
                        'default'           => 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/linkbuilder/export',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle_lb_export' ],
                'permission_callback' => [ $this, 'permission_check' ],
            ]
        );
        // ----------------------------------------
    }

    /**
     * Permission callback.
     *
     * @return bool
     */
    public function permission_check() {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Handles search endpoint.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function handle_search( WP_REST_Request $request ) {
        $keyword = trim( (string) $request->get_param( 'kw' ) );
        if ( '' === $keyword ) {
            return rest_ensure_response(
                [
                    'items'      => [],
                    'total'      => 0,
                    'totalPages' => 0,
                ]
            );
        }

        $in_body    = (bool) $request->get_param( 'in_body' );
        $page       = max( 1, (int) $request->get_param( 'page' ) );
        $exclude    = $request->get_param( 'exclude' );
        $context_id = (int) $request->get_param( 'context_id' );
        $canonical  = sanitize_text_field( (string) $request->get_param( 'canonical' ) );

        $args = [
            'post_type'           => [ 'post', 'page' ],
            'post_status'         => 'publish',
            'posts_per_page'      => 50,
            'paged'               => $page,
            's'                   => $keyword,
            'ignore_sticky_posts' => true,
        ];

        if ( ! $in_body ) {
            $args['search_columns'] = [ 'post_title' ];
        } else {
            $args['search_columns'] = [ 'post_title', 'post_content' ];
        }

        if ( ! empty( $exclude ) && is_array( $exclude ) ) {
            $args['post__not_in'] = array_map( 'absint', $exclude );
        }

        $query = new WP_Query( $args );

        $items = [];
        foreach ( $query->posts as $post ) {
            $cannibal = null;
            if ( $context_id && $canonical ) {
                // Función auxiliar del Link Builder: puntaje/semaforo de canibalización.
                $cannibal = function_exists( 'sai_lb_cannibal_score' )
                    ? sai_lb_cannibal_score( $post->ID, $context_id, $canonical )
                    : null;
            }

            $items[] = [
                'id'               => $post->ID,
                'title'            => get_the_title( $post ),
                'type'             => $post->post_type,
                'link'             => get_permalink( $post ),
                'cannibalization'  => $cannibal,
            ];
        }

        wp_reset_postdata();

        return rest_ensure_response(
            [
                'items'      => $items,
                'total'      => (int) $query->found_posts,
                'totalPages' => (int) $query->max_num_pages,
            ]
        );
    }

    /**
     * Returns post detail without headings.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function handle_get_post( WP_REST_Request $request ) {
        $post_id = (int) $request['id'];
        $post    = get_post( $post_id );

        if ( ! $post || ! in_array( $post->post_type, [ 'post', 'page' ], true ) || 'publish' !== $post->post_status ) {
            return new WP_Error( 'sai_not_found', __( 'Entrada no encontrada.', 'anchors-sin-ia' ), [ 'status' => 404 ] );
        }

        $anchors = new SAI_Anchors();
        $content = get_post_field( 'post_content', $post );
        $content = strip_shortcodes( $content );
        $clean   = $anchors->clean_content( $content );
        $words   = $anchors->get_word_count( $clean );

        return rest_ensure_response(
            [
                'id'         => $post->ID,
                'title'      => get_the_title( $post ),
                'body_text'  => $clean,
                'word_count' => $words,
            ]
        );
    }

    /**
     * Handles extraction endpoint.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function handle_extract( WP_REST_Request $request ) {
        $id        = (int) $request->get_param( 'id' );
        $canonical = sanitize_text_field( $request->get_param( 'canonical' ) );
        $body_text = (string) $request->get_param( 'body_text' );

        $post = get_post( $id );
        if ( ! $post || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
            return new WP_Error( 'sai_not_found', __( 'Entrada no encontrada.', 'anchors-sin-ia' ), [ 'status' => 404 ] );
        }

        $anchors = new SAI_Anchors();
        $clean   = $anchors->clean_content( $body_text );
        if ( '' === $clean ) {
            $clean = $anchors->clean_content( get_post_field( 'post_content', $post ) );
        }

        $result = $anchors->extract( $canonical, $clean );

        return rest_ensure_response( $result );
    }

    // ===================== Link Builder handlers =====================

    /**
     * Returns stored linkbuilder state.
     *
     * @return WP_REST_Response
     */
    public function handle_lb_get_state() {
        return rest_ensure_response( function_exists( 'sai_lb_get_user_state' ) ? sai_lb_get_user_state() : [] );
    }

    /**
     * Resets linkbuilder state.
     *
     * @return WP_REST_Response
     */
    public function handle_lb_reset() {
        if ( function_exists( 'sai_lb_reset_state' ) ) {
            sai_lb_reset_state();
        }

        return rest_ensure_response( function_exists( 'sai_lb_get_user_state' ) ? sai_lb_get_user_state() : [] );
    }

    /**
     * Stores madre selection and extracts anchors.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_lb_set_madre( WP_REST_Request $request ) {
        $madre_id  = (int) $request->get_param( 'id' );
        $canonical = sanitize_text_field( $request->get_param( 'canonical' ) );
        $keyword   = sanitize_text_field( (string) $request->get_param( 'keyword' ) );
        $in_body   = (int) $request->get_param( 'in_content' );

        if ( ! $madre_id || '' === $canonical ) {
            return new WP_Error( 'sai_invalid_madre', __( 'Datos incompletos para la madre.', 'anchors-sin-ia' ), [ 'status' => 400 ] );
        }

        $post = get_post( $madre_id );
        if ( ! $post || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
            return new WP_Error( 'sai_not_found', __( 'Entrada no encontrada.', 'anchors-sin-ia' ), [ 'status' => 404 ] );
        }

        $plain              = function_exists( 'sai_lb_post_plain_text' ) ? sai_lb_post_plain_text( $madre_id ) : '';
        $anchors            = new SAI_Anchors();
        $anchors->request_title = get_the_title( $post );
        $result             = $anchors->extract( $canonical, $plain );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $state = function_exists( 'sai_lb_update_state' ) ? sai_lb_update_state(
            [
                'q_madre'           => $keyword,
                'in_content_madre'  => $in_body,
                'madre_id'          => $madre_id,
                'canonical'         => $canonical,
                'madre_anchors'     => isset( $result['anchors'] ) ? $result['anchors'] : [],
                'limit_hijas'       => max( 1, isset( $result['anchors'] ) ? count( $result['anchors'] ) : 0 ),
                'hijas_ids'         => [],
                'hijas_anchors'     => [],
                'limit_nietas'      => 1,
                'nietas_ids'        => [],
                'q_hijas'           => '',
                'in_content_hijas'  => 0,
                'q_nietas'          => '',
                'in_content_nietas' => 0,
            ]
        ) : [];

        return rest_ensure_response(
            [
                'state'   => $state,
                'anchors' => $result,
            ]
        );
    }

    /**
     * Stores hijas selection and extracts anchors.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_lb_set_hijas( WP_REST_Request $request ) {
        $ids       = $request->get_param( 'ids' );
        $keyword   = sanitize_text_field( (string) $request->get_param( 'keyword' ) );
        $in_body   = (int) $request->get_param( 'in_content' );
        $state     = function_exists( 'sai_lb_get_user_state' ) ? sai_lb_get_user_state() : [];
        $madre_id  = isset( $state['madre_id'] ) ? (int) $state['madre_id'] : 0;
        $canonical = isset( $state['canonical'] ) ? $state['canonical'] : '';

        if ( ! $madre_id || '' === $canonical ) {
            return new WP_Error( 'sai_missing_madre', __( 'Selecciona una madre antes de elegir hijas.', 'anchors-sin-ia' ), [ 'status' => 400 ] );
        }

        if ( ! is_array( $ids ) ) {
            $ids = [];
        }

        $ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
        $ids = array_filter( $ids, function ( $id ) use ( $madre_id ) {
            return $id && $id !== $madre_id;
        } );

        $limit = isset( $state['limit_hijas'] ) ? (int) $state['limit_hijas'] : 1;
        if ( count( $ids ) > $limit ) {
            return new WP_Error( 'sai_limit_hijas', __( 'Has superado el límite de hijas permitido.', 'anchors-sin-ia' ), [ 'status' => 400 ] );
        }

        $anchors_map      = [];
        $total_anchor_sum = 0;

        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( ! $post || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
                return new WP_Error( 'sai_not_found', __( 'Entrada no encontrada.', 'anchors-sin-ia' ), [ 'status' => 404 ] );
            }

            $plain                 = function_exists( 'sai_lb_post_plain_text' ) ? sai_lb_post_plain_text( $id ) : '';
            $anchors               = new SAI_Anchors();
            $anchors->request_title = get_the_title( $post );
            $result                = $anchors->extract( $canonical, $plain );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $anchor_list           = isset( $result['anchors'] ) ? $result['anchors'] : [];
            $anchors_map[ $id ]    = $anchor_list;
            $total_anchor_sum     += count( $anchor_list );
        }

        $limit_nietas = max( 1, $total_anchor_sum );

        $state = function_exists( 'sai_lb_update_state' ) ? sai_lb_update_state(
            [
                'q_hijas'           => $keyword,
                'in_content_hijas'  => $in_body,
                'hijas_ids'         => $ids,
                'hijas_anchors'     => $anchors_map,
                'limit_nietas'      => $limit_nietas,
                'nietas_ids'        => [],
                'q_nietas'          => '',
                'in_content_nietas' => 0,
            ]
        ) : [];

        return rest_ensure_response(
            [
                'state'   => $state,
                'anchors' => $anchors_map,
            ]
        );
    }

    /**
     * Stores nietas selection.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_lb_set_nietas( WP_REST_Request $request ) {
        $ids      = $request->get_param( 'ids' );
        $keyword  = sanitize_text_field( (string) $request->get_param( 'keyword' ) );
        $in_body  = (int) $request->get_param( 'in_content' );
        $state    = function_exists( 'sai_lb_get_user_state' ) ? sai_lb_get_user_state() : [];
        $madre_id = isset( $state['madre_id'] ) ? (int) $state['madre_id'] : 0;
        $hijas    = isset( $state['hijas_ids'] ) ? $state['hijas_ids'] : [];

        if ( ! $madre_id || empty( $hijas ) ) {
            return new WP_Error( 'sai_missing_hijas', __( 'Selecciona hijas antes de definir las nietas.', 'anchors-sin-ia' ), [ 'status' => 400 ] );
        }

        if ( ! is_array( $ids ) ) {
            $ids = [];
        }

        $ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
        $ids = array_filter( $ids, function ( $id ) use ( $madre_id, $hijas ) {
            return $id && $id !== $madre_id && ! in_array( $id, $hijas, true );
        } );

        $limit = isset( $state['limit_nietas'] ) ? (int) $state['limit_nietas'] : 1;
        if ( count( $ids ) > $limit ) {
            return new WP_Error( 'sai_limit_nietas', __( 'Has superado el límite de nietas permitido.', 'anchors-sin-ia' ), [ 'status' => 400 ] );
        }

        $state = function_exists( 'sai_lb_update_state' ) ? sai_lb_update_state(
            [
                'q_nietas'          => $keyword,
                'in_content_nietas' => $in_body,
                'nietas_ids'        => $ids,
            ]
        ) : [];

        return rest_ensure_response( $state );
    }

    /**
     * Generates CSV export for the current selection.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_lb_export() {
        $state = function_exists( 'sai_lb_get_user_state' ) ? sai_lb_get_user_state() : [];

        if ( empty( $state['madre_id'] ) ) {
            return new WP_Error( 'sai_missing_madre', __( 'No hay madre seleccionada para exportar.', 'anchors-sin-ia' ), [ 'status' => 400 ] );
        }

        if ( ! function_exists( 'sai_lb_generate_csv_data' ) ) {
            return new WP_Error( 'sai_missing_exporter', __( 'Exportador no disponible.', 'anchors-sin-ia' ), [ 'status' => 500 ] );
        }

        $export = sai_lb_generate_csv_data( $state );

        if ( is_wp_error( $export ) ) {
            return $export;
        }

        return rest_ensure_response( $export );
    }
}
