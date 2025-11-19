<?php
namespace GuideOS\AdventCalendar;

use WP_Block;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    private static $instance;

    /**
     * Stores block instance metadata for front-end bootstrapping.
     *
     * @var array
     */
    private $instances = [];

    const TEST_COOKIE = 'guideos_advent_test';
    const AJAX_ACTION = 'guideos_advent_open_door';

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_block' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'handle_open_door' ] );
        add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ $this, 'handle_open_door' ] );
    }

    public function register_block(): void {
        $asset_path = GUIDEOS_ADVENT_PATH . 'build';

        if ( ! file_exists( $asset_path . '/block.json' ) ) {
            return;
        }

        register_block_type( $asset_path, [
            'render_callback' => [ $this, 'render_block' ],
        ] );
    }

    public function enqueue_editor_assets(): void {
        $asset_path = GUIDEOS_ADVENT_PATH . 'build/index.asset.php';
        if ( ! file_exists( $asset_path ) ) {
            return;
        }
        $asset = include $asset_path;
        wp_enqueue_script( 'guideos-advent-editor', GUIDEOS_ADVENT_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
        wp_enqueue_style( 'guideos-advent-editor', GUIDEOS_ADVENT_URL . 'build/index.css', [], GUIDEOS_ADVENT_VERSION );
    }

    public function enqueue_frontend_assets(): void {
        $asset_path = GUIDEOS_ADVENT_PATH . 'build/view.asset.php';
        if ( ! file_exists( $asset_path ) ) {
            return;
        }

        $asset = include $asset_path;
        wp_register_script( 'guideos-advent-view', GUIDEOS_ADVENT_URL . 'build/view.js', $asset['dependencies'], $asset['version'], true );
        wp_register_style( 'guideos-advent-style', GUIDEOS_ADVENT_URL . 'build/style-index.css', [], GUIDEOS_ADVENT_VERSION );
    }

    public function render_block( array $attributes, string $content, WP_Block $block ): string {
        $instance_id = $attributes['instanceId'] ?? '';
        if ( empty( $instance_id ) ) {
            return '';
        }

        $this->maybe_enable_test_cookie();
        $this->register_instance_bootstrap( $block->context['postId'] ?? 0, $instance_id );

        wp_enqueue_script( 'guideos-advent-view' );
        wp_enqueue_style( 'guideos-advent-style' );

        ob_start();
        ?>
        <section class="guideos-advent" data-instance="<?php echo esc_attr( $instance_id ); ?>">
            <div class="guideos-advent__grid"></div>
            <div class="guideos-advent__modal" hidden>
                <div class="guideos-advent__modal-backdrop"></div>
                <div class="guideos-advent__modal-content" role="dialog" aria-modal="true">
                    <button class="guideos-advent__modal-close" type="button" aria-label="<?php esc_attr_e( 'Modal schließen', 'guideos-advent' ); ?>">&times;</button>
                    <div class="guideos-advent__modal-body"></div>
                </div>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function register_instance_bootstrap( int $post_id, string $instance_id ): void {
        $this->instances[ $instance_id ] = [
            'postId'   => $post_id,
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( self::AJAX_ACTION ),
            'testMode' => $this->has_test_cookie(),
        ];

        add_action( 'wp_footer', [ $this, 'print_instance_settings' ], 20 );
    }

    public function print_instance_settings(): void {
        if ( empty( $this->instances ) ) {
            return;
        }

        $data = wp_json_encode( $this->instances );
        if ( ! $data ) {
            return;
        }

        echo '<script type="application/json" id="guideos-advent-data">' . $data . '</script>';
    }

    private function maybe_enable_test_cookie(): void {
        if ( isset( $_GET['guideos_advent_test'] ) && '1' === $_GET['guideos_advent_test'] ) {
            $token = wp_hash( site_url() . '|' . time() );
            setcookie( self::TEST_COOKIE, $token, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            $_COOKIE[ self::TEST_COOKIE ] = $token;
        }
    }

    private function has_test_cookie(): bool {
        return ! empty( $_COOKIE[ self::TEST_COOKIE ] );
    }

    public function handle_open_door(): void {
        wp_send_json_error( new WP_Error( 'not_implemented', 'Door handling not yet implemented.' ) );
    }
}
