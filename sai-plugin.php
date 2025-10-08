<?php
/**
 * Plugin Name: Anchors sin IA
 * Description: Herramienta para buscar entradas y extraer anchors sin IA.
 * Version: 1.0.0
 * Author: OpenAI
 * Text Domain: anchors-sin-ia
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SAI_PLUGIN_FILE' ) ) {
    define( 'SAI_PLUGIN_FILE', __FILE__ );
}

define( 'SAI_PLUGIN_DIR', plugin_dir_path( SAI_PLUGIN_FILE ) );
define( 'SAI_PLUGIN_URL', plugin_dir_url( SAI_PLUGIN_FILE ) );

require_once SAI_PLUGIN_DIR . 'includes/class-sai-anchors.php';
require_once SAI_PLUGIN_DIR . 'includes/class-sai-rest.php';

class Anchors_Sin_IA_Plugin {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        new SAI_REST_Controller();
    }

    /**
     * Registers the admin page under Tools.
     */
    public function register_admin_page() {
        add_management_page(
            __( 'Anchors sin IA', 'anchors-sin-ia' ),
            __( 'Anchors sin IA', 'anchors-sin-ia' ),
            'edit_posts',
            'anchors-sin-ia',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Outputs the container for the admin app.
     */
    public function render_admin_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Anchors sin IA', 'anchors-sin-ia' ) . '</h1><div id="sai-app"></div></div>';
    }

    /**
     * Enqueue assets only on plugin page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'tools_page_anchors-sin-ia' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'anchors-sin-ia-admin',
            SAI_PLUGIN_URL . 'assets/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'anchors-sin-ia-admin',
            SAI_PLUGIN_URL . 'assets/admin.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'anchors-sin-ia-admin',
            'AnchorsSinIA',
            [
                'restUrl' => esc_url_raw( rest_url( 'anchors/v1/' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'perPage' => 50,
                'i18n'    => [
                    'search'         => __( 'Buscar', 'anchors-sin-ia' ),
                    'view'           => __( 'Ver', 'anchors-sin-ia' ),
                    'select'         => __( 'Seleccionar', 'anchors-sin-ia' ),
                    'noResults'      => __( 'Sin resultados.', 'anchors-sin-ia' ),
                    'copySuccess'    => __( 'Anchors copiados al portapapeles.', 'anchors-sin-ia' ),
                    'copyError'      => __( 'No se pudo copiar. Copie manualmente.', 'anchors-sin-ia' ),
                    'loading'        => __( 'Cargando...', 'anchors-sin-ia' ),
                    'keywordLabel'   => __( 'Palabra clave (canónico)', 'anchors-sin-ia' ),
                    'includeBody'    => __( 'Buscar también en el contenido', 'anchors-sin-ia' ),
                    'wordCount'      => __( 'Palabras', 'anchors-sin-ia' ),
                    'preset'         => __( 'Preset', 'anchors-sin-ia' ),
                    'extractAnchors' => __( 'Extraer anchors', 'anchors-sin-ia' ),
                    'copyAnchors'    => __( 'Copiar anchors', 'anchors-sin-ia' ),
                    'tableHeader'    => [ __( 'Anchor', 'anchors-sin-ia' ), __( 'Clasificación', 'anchors-sin-ia' ), __( 'Frecuencia', 'anchors-sin-ia' ) ],
                    'keywordRequired'=> __( 'Introduce una palabra clave.', 'anchors-sin-ia' ),
                    'loadError'      => __( 'Ocurrió un error. Inténtalo nuevamente.', 'anchors-sin-ia' ),
                    'noAnchors'      => __( 'No hay anchors disponibles.', 'anchors-sin-ia' ),
                    'back'           => __( 'Volver a la búsqueda', 'anchors-sin-ia' ),
                    'extracting'     => __( 'Extrayendo...', 'anchors-sin-ia' ),
                    'pageLabel'      => __( 'Página', 'anchors-sin-ia' ),
                    'usedQuotas'     => __( 'Cuotas usadas', 'anchors-sin-ia' ),
                ],
            ]
        );
    }
}

new Anchors_Sin_IA_Plugin();
