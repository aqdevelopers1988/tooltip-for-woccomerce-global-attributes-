<?php
/**
 * Plugin Name: Tooltip for WooCommerce Global Attributes
 * Description: Adds configurable tooltips to WooCommerce global product attributes, with color controls and a custom tooltip icon upload.
 * Version: 1.0.2
 * Author: Codex
 * Text Domain: tooltip-for-woocommerce-global-attributes
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.9
 *
 * @package Tooltip_For_WooCommerce_Global_Attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TFWGA_VERSION', '1.0.2' );
define( 'TFWGA_PLUGIN_FILE', __FILE__ );
define( 'TFWGA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TFWGA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Main plugin class.
 */
final class TFWGA_Plugin {
	const ATTRIBUTE_TOOLTIPS_OPTION = 'tfwga_attribute_tooltips';
	const SETTINGS_OPTION           = 'tfwga_settings';
	const SETTINGS_PAGE_SLUG        = 'tfwga-settings';

	/**
	 * Default plugin settings.
	 *
	 * @return array<string, string>
	 */
	private static function default_settings() {
		return array(
			'background_color' => '#111827',
			'text_color'       => '#ffffff',
			'icon_color'       => '#2563eb',
			'border_color'     => '#1d4ed8',
			'icon_url'         => '',
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_woocommerce_features' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		add_action( 'woocommerce_after_add_attribute_fields', array( __CLASS__, 'render_add_attribute_tooltip_field' ) );
		add_action( 'woocommerce_after_edit_attribute_fields', array( __CLASS__, 'render_edit_attribute_tooltip_field' ) );
		add_action( 'woocommerce_attribute_added', array( __CLASS__, 'save_attribute_tooltip' ), 10, 2 );
		add_action( 'woocommerce_attribute_updated', array( __CLASS__, 'save_attribute_tooltip' ), 10, 2 );
		add_action( 'woocommerce_attribute_deleted', array( __CLASS__, 'delete_attribute_tooltip' ), 10, 3 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
		add_filter( 'woocommerce_display_product_attributes', array( __CLASS__, 'append_tooltips_to_display_attributes' ), 20, 2 );
	}

	/**
	 * Declare compatibility with WooCommerce custom order tables.
	 *
	 * @return void
	 */
	public static function declare_woocommerce_features() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', TFWGA_PLUGIN_FILE, true );
		}
	}

	/**
	 * Register option for the settings page.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'tfwga_settings_group',
			self::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	/**
	 * Add settings submenu under WooCommerce.
	 *
	 * @return void
	 */
	public static function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Attribute Tooltips', 'tooltip-for-woocommerce-global-attributes' ),
			esc_html__( 'Attribute Tooltips', 'tooltip-for-woocommerce-global-attributes' ),
			'manage_woocommerce',
			self::SETTINGS_PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		$is_settings_page  = false !== strpos( $hook_suffix, self::SETTINGS_PAGE_SLUG );
		$is_attributes_page = isset( $_GET['page'], $_GET['tab'] )
			&& 'product_attributes' === sanitize_text_field( wp_unslash( $_GET['page'] ) )
			&& 'attributes' === sanitize_text_field( wp_unslash( $_GET['tab'] ) );

		if ( ! $is_settings_page && ! $is_attributes_page ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_style( 'tfwga-admin', TFWGA_PLUGIN_URL . 'assets/css/admin.css', array(), TFWGA_VERSION );
		wp_enqueue_script( 'tfwga-admin', TFWGA_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), TFWGA_VERSION, true );
		wp_localize_script(
			'tfwga-admin',
			'tfwgaAdmin',
			array(
				'chooseIcon' => esc_html__( 'Choose tooltip icon', 'tooltip-for-woocommerce-global-attributes' ),
				'useIcon'    => esc_html__( 'Use this icon', 'tooltip-for-woocommerce-global-attributes' ),
			)
		);
	}

	/**
	 * Enqueue frontend styles and expose configured colors as CSS variables.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets() {
		wp_enqueue_style( 'tfwga-frontend', TFWGA_PLUGIN_URL . 'assets/css/frontend.css', array(), TFWGA_VERSION );
		wp_enqueue_script( 'tfwga-frontend', TFWGA_PLUGIN_URL . 'assets/js/frontend.js', array(), TFWGA_VERSION, true );
		wp_localize_script(
			'tfwga-frontend',
			'tfwgaFrontend',
			array(
				'tooltips'   => self::get_frontend_tooltips_data(),
				'closeLabel' => esc_html__( 'Close tooltip', 'tooltip-for-woocommerce-global-attributes' ),
			)
		);

		$settings = self::get_settings();
		$css      = sprintf(
			'.tfwga-tooltip,.tfwga-tooltip-modal{--tfwga-bg:%1$s;--tfwga-text:%2$s;--tfwga-icon:%3$s;--tfwga-border:%4$s;}',
			esc_html( $settings['background_color'] ),
			esc_html( $settings['text_color'] ),
			esc_html( $settings['icon_color'] ),
			esc_html( $settings['border_color'] )
		);

		wp_add_inline_style( 'tfwga-frontend', $css );
	}

	/**
	 * Render tooltip field when adding a global attribute.
	 *
	 * @return void
	 */
	public static function render_add_attribute_tooltip_field() {
		wp_nonce_field( 'tfwga_save_attribute_tooltip', 'tfwga_attribute_tooltip_nonce' );
		?>
		<div class="form-field">
			<label for="tfwga_attribute_tooltip"><?php esc_html_e( 'Tooltip text', 'tooltip-for-woocommerce-global-attributes' ); ?></label>
			<textarea name="tfwga_attribute_tooltip" id="tfwga_attribute_tooltip" rows="4" cols="40" placeholder="<?php esc_attr_e( 'Explain this attribute for shoppers.', 'tooltip-for-woocommerce-global-attributes' ); ?>"></textarea>
			<p class="description"><?php esc_html_e( 'Shown next to this global attribute label on the storefront.', 'tooltip-for-woocommerce-global-attributes' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render tooltip field when editing a global attribute.
	 *
	 * @param object $attribute Attribute taxonomy object.
	 * @return void
	 */
	public static function render_edit_attribute_tooltip_field( $attribute ) {
		$attribute_id = isset( $attribute->attribute_id ) ? absint( $attribute->attribute_id ) : 0;

		if ( ! $attribute_id && isset( $_GET['edit'] ) ) {
			$attribute_id = absint( wp_unslash( $_GET['edit'] ) );
		}

		$tooltip = self::get_attribute_tooltip( $attribute_id );
		wp_nonce_field( 'tfwga_save_attribute_tooltip', 'tfwga_attribute_tooltip_nonce' );
		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="tfwga_attribute_tooltip"><?php esc_html_e( 'Tooltip text', 'tooltip-for-woocommerce-global-attributes' ); ?></label>
			</th>
			<td>
				<textarea name="tfwga_attribute_tooltip" id="tfwga_attribute_tooltip" rows="4" cols="50"><?php echo esc_textarea( $tooltip ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Shown next to this global attribute label on the storefront.', 'tooltip-for-woocommerce-global-attributes' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save tooltip text for a global attribute.
	 *
	 * @param int   $attribute_id Attribute ID.
	 * @param array $attribute    Attribute data.
	 * @return void
	 */
	public static function save_attribute_tooltip( $attribute_id, $attribute ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( empty( $_POST['tfwga_attribute_tooltip_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tfwga_attribute_tooltip_nonce'] ) ), 'tfwga_save_attribute_tooltip' ) ) {
			return;
		}

		$tooltips                  = self::get_all_attribute_tooltips();
		$tooltips[ absint( $attribute_id ) ] = isset( $_POST['tfwga_attribute_tooltip'] )
			? wp_kses_post( wp_unslash( $_POST['tfwga_attribute_tooltip'] ) )
			: '';

		update_option( self::ATTRIBUTE_TOOLTIPS_OPTION, $tooltips );
	}

	/**
	 * Delete tooltip text when a global attribute is deleted.
	 *
	 * @param int    $attribute_id Attribute ID.
	 * @param string $attribute_name Attribute name.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public static function delete_attribute_tooltip( $attribute_id, $attribute_name, $taxonomy ) {
		$tooltips = self::get_all_attribute_tooltips();
		unset( $tooltips[ absint( $attribute_id ) ] );
		update_option( self::ATTRIBUTE_TOOLTIPS_OPTION, $tooltips );
	}

	/**
	 * Append tooltip markup to product attributes displayed in WooCommerce tables.
	 *
	 * This intentionally runs on the final display-attributes array instead of the
	 * generic woocommerce_attribute_label filter. Some themes escape plain labels,
	 * which caused raw tooltip HTML to appear in the attribute table and broke the
	 * layout. The display array is the WooCommerce product-page context where HTML
	 * labels are expected.
	 *
	 * @param array<string, array<string, string>> $product_attributes Product attributes prepared for display.
	 * @param WC_Product                          $product Product object.
	 * @return array<string, array<string, string>>
	 */
	public static function append_tooltips_to_display_attributes( $product_attributes, $product ) {
		if ( is_admin() || ! function_exists( 'wc_attribute_taxonomy_id_by_name' ) || ! is_array( $product_attributes ) ) {
			return $product_attributes;
		}

		foreach ( $product_attributes as $attribute_key => $product_attribute ) {
			$attribute_id = self::get_attribute_id_from_display_attribute( $attribute_key, $product );

			if ( ! $attribute_id ) {
				continue;
			}

			$tooltip = self::get_attribute_tooltip( $attribute_id );

			if ( '' === trim( $tooltip ) ) {
				continue;
			}

			if ( ! isset( $product_attribute['label'] ) ) {
				continue;
			}

			$clean_label = self::clean_existing_tooltip_markup( $product_attribute['label'] );

			$product_attributes[ $attribute_key ]['label'] = self::get_tooltip_markup( $tooltip, $clean_label ) . ' ' . $clean_label;
		}

		return $product_attributes;
	}

	/**
	 * Resolve a WooCommerce global attribute ID from the display attribute key.
	 *
	 * @param string     $attribute_key Display attribute key.
	 * @param WC_Product $product Product object.
	 * @return int
	 */
	private static function get_attribute_id_from_display_attribute( $attribute_key, $product ) {
		$possible_names = array( $attribute_key );

		if ( is_object( $product ) && is_callable( array( $product, 'get_attributes' ) ) ) {
			$product_attributes = $product->get_attributes();

			if ( isset( $product_attributes[ $attribute_key ] ) && is_object( $product_attributes[ $attribute_key ] ) && is_callable( array( $product_attributes[ $attribute_key ], 'get_name' ) ) ) {
				$possible_names[] = $product_attributes[ $attribute_key ]->get_name();
			}
		}

		foreach ( array_unique( array_filter( $possible_names ) ) as $possible_name ) {
			$attribute_id = wc_attribute_taxonomy_id_by_name( $possible_name );

			if ( ! $attribute_id && 0 === strpos( $possible_name, 'pa_' ) ) {
				$attribute_id = wc_attribute_taxonomy_id_by_name( substr( $possible_name, 3 ) );
			}

			if ( $attribute_id ) {
				return absint( $attribute_id );
			}
		}

		return 0;
	}

	/**
	 * Remove previously escaped tooltip markup from labels.
	 *
	 * This helps stores recover after the earlier implementation saved/cached escaped
	 * markup in product attributes or theme output.
	 *
	 * @param string $label Attribute label.
	 * @return string
	 */
	private static function clean_existing_tooltip_markup( $label ) {
		$label = (string) $label;
		$label = preg_replace( '/\s*&lt;(span|button)[^&]*class=&quot;tfwga-tooltip&quot;.*$/s', '', $label );
		$label = preg_replace( '/\s*<(span|button)[^>]*class="tfwga-tooltip".*$/s', '', $label );

		return trim( $label );
	}

	/**
	 * Build tooltip data for custom specification tables rendered outside WooCommerce filters.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_frontend_tooltips_data() {
		$tooltips = self::get_all_attribute_tooltips();
		$data     = array();

		foreach ( $tooltips as $attribute_id => $tooltip ) {
			if ( '' === trim( (string) $tooltip ) ) {
				continue;
			}

			$label = self::get_attribute_label_by_id( absint( $attribute_id ) );

			if ( '' === $label ) {
				continue;
			}

			$data[] = array(
				'label'   => $label,
				'tooltip' => wp_kses_post( wpautop( $tooltip ) ),
				'iconUrl' => self::get_settings()['icon_url'],
			);
		}

		return $data;
	}

	/**
	 * Get a global attribute label by WooCommerce attribute ID.
	 *
	 * @param int $attribute_id Attribute ID.
	 * @return string
	 */
	private static function get_attribute_label_by_id( $attribute_id ) {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'wc_attribute_taxonomy_name' ) || ! function_exists( 'wc_attribute_label' ) ) {
			return '';
		}

		foreach ( wc_get_attribute_taxonomies() as $attribute_taxonomy ) {
			if ( absint( $attribute_taxonomy->attribute_id ) !== absint( $attribute_id ) ) {
				continue;
			}

			$taxonomy_name = wc_attribute_taxonomy_name( $attribute_taxonomy->attribute_name );
			$label         = wc_attribute_label( $taxonomy_name );

			return '' !== $label ? $label : $attribute_taxonomy->attribute_label;
		}

		return '';
	}

	/**
	 * Build tooltip markup.
	 *
	 * @param string $tooltip Tooltip text.
	 * @return string
	 */
	private static function get_tooltip_markup( $tooltip, $label = '' ) {
		$settings = self::get_settings();
		$icon_url = $settings['icon_url'];
		$icon     = '';

		if ( $icon_url ) {
			$icon = sprintf( '<img src="%1$s" alt="" class="tfwga-tooltip__image" />', esc_url( $icon_url ) );
		} else {
			$icon = '<span class="tfwga-tooltip__fallback-icon" aria-hidden="true">!</span>';
		}

		$button_label = '' !== trim( (string) $label )
			? sprintf( /* translators: %s is an attribute label. */ __( 'View %s tooltip', 'tooltip-for-woocommerce-global-attributes' ), wp_strip_all_tags( $label ) )
			: wp_strip_all_tags( $tooltip );

		return sprintf(
			'<button type="button" class="tfwga-tooltip" aria-label="%1$s" data-tfwga-title="%2$s" data-tfwga-content="%3$s">%4$s</button>',
			esc_attr( $button_label ),
			esc_attr( wp_strip_all_tags( $label ) ),
			esc_attr( wp_kses_post( wpautop( $tooltip ) ) ),
			$icon
		);
	}

	/**
	 * Render the WooCommerce submenu settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = self::get_settings();
		?>
		<div class="wrap tfwga-settings">
			<h1><?php esc_html_e( 'Attribute Tooltip Settings', 'tooltip-for-woocommerce-global-attributes' ); ?></h1>
			<p><?php esc_html_e( 'Customize the storefront tooltip colors and upload a tooltip icon used by all configured global attributes.', 'tooltip-for-woocommerce-global-attributes' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'tfwga_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tfwga_background_color"><?php esc_html_e( 'Tooltip background color', 'tooltip-for-woocommerce-global-attributes' ); ?></label></th>
						<td><input type="text" id="tfwga_background_color" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[background_color]" value="<?php echo esc_attr( $settings['background_color'] ); ?>" class="tfwga-color-field" data-default-color="#111827" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="tfwga_text_color"><?php esc_html_e( 'Tooltip text color', 'tooltip-for-woocommerce-global-attributes' ); ?></label></th>
						<td><input type="text" id="tfwga_text_color" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>" class="tfwga-color-field" data-default-color="#ffffff" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="tfwga_icon_color"><?php esc_html_e( 'Default icon color', 'tooltip-for-woocommerce-global-attributes' ); ?></label></th>
						<td><input type="text" id="tfwga_icon_color" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[icon_color]" value="<?php echo esc_attr( $settings['icon_color'] ); ?>" class="tfwga-color-field" data-default-color="#2563eb" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="tfwga_border_color"><?php esc_html_e( 'Default icon border color', 'tooltip-for-woocommerce-global-attributes' ); ?></label></th>
						<td><input type="text" id="tfwga_border_color" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[border_color]" value="<?php echo esc_attr( $settings['border_color'] ); ?>" class="tfwga-color-field" data-default-color="#1d4ed8" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="tfwga_icon_url"><?php esc_html_e( 'Tooltip icon', 'tooltip-for-woocommerce-global-attributes' ); ?></label></th>
						<td>
							<div class="tfwga-icon-control">
								<input type="url" id="tfwga_icon_url" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[icon_url]" value="<?php echo esc_url( $settings['icon_url'] ); ?>" class="regular-text tfwga-icon-url" />
								<button type="button" class="button tfwga-upload-icon"><?php esc_html_e( 'Upload icon', 'tooltip-for-woocommerce-global-attributes' ); ?></button>
								<button type="button" class="button tfwga-remove-icon"><?php esc_html_e( 'Remove', 'tooltip-for-woocommerce-global-attributes' ); ?></button>
							</div>
							<div class="tfwga-icon-preview" aria-live="polite">
								<?php if ( $settings['icon_url'] ) : ?>
									<img src="<?php echo esc_url( $settings['icon_url'] ); ?>" alt="<?php esc_attr_e( 'Current tooltip icon preview', 'tooltip-for-woocommerce-global-attributes' ); ?>" />
								<?php endif; ?>
							</div>
							<p class="description"><?php esc_html_e( 'Upload a small SVG, PNG, JPG, or GIF icon. If empty, a styled exclamation icon is used.', 'tooltip-for-woocommerce-global-attributes' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array<string, string> $settings Raw settings.
	 * @return array<string, string>
	 */
	public static function sanitize_settings( $settings ) {
		$defaults = self::default_settings();
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array();

		foreach ( array( 'background_color', 'text_color', 'icon_color', 'border_color' ) as $color_key ) {
			$color = isset( $settings[ $color_key ] ) ? sanitize_hex_color( $settings[ $color_key ] ) : '';
			$clean[ $color_key ] = $color ? $color : $defaults[ $color_key ];
		}

		$clean['icon_url'] = isset( $settings['icon_url'] ) ? esc_url_raw( $settings['icon_url'] ) : '';

		return $clean;
	}

	/**
	 * Get merged settings.
	 *
	 * @return array<string, string>
	 */
	private static function get_settings() {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
	}

	/**
	 * Get all stored global attribute tooltips.
	 *
	 * @return array<int, string>
	 */
	private static function get_all_attribute_tooltips() {
		$tooltips = get_option( self::ATTRIBUTE_TOOLTIPS_OPTION, array() );
		return is_array( $tooltips ) ? $tooltips : array();
	}

	/**
	 * Get tooltip text by attribute ID.
	 *
	 * @param int $attribute_id Attribute ID.
	 * @return string
	 */
	private static function get_attribute_tooltip( $attribute_id ) {
		$tooltips = self::get_all_attribute_tooltips();
		return isset( $tooltips[ absint( $attribute_id ) ] ) ? (string) $tooltips[ absint( $attribute_id ) ] : '';
	}
}

TFWGA_Plugin::init();
