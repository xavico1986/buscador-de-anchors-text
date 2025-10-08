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

        $in_body = (bool) $request->get_param( 'in_body' );
        $page    = max( 1, (int) $request->get_param( 'page' ) );

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

        $query = new WP_Query( $args );

        $items = [];
        foreach ( $query->posts as $post ) {
            $items[] = [
                'id'    => $post->ID,
                'title' => get_the_title( $post ),
                'type'  => $post->post_type,
                'link'  => get_permalink( $post ),
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
}
