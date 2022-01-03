<?php

namespace WP_CLI\DBAnonymize;

use WP_CLI;
use WP_CLI_Command;

class DBAnonymizeCommand extends WP_CLI_Command {
	public $db_prefix;

	public $user_meta = '';
	public $anonymize_woocommerce = false;
	public $confirm = true;

	// TODO: Whitelist users
	public $user_whitelist = '';

	/**
	 * Anonymizes WP Database
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db-anonymize
	 *
	 * [--woocommerce]
	 * : Anonymize woocommerce.
	 * default: false
	 *
	 *
	 * [--confirm]
	 * : Ask for confirmation.
	 * default: true
	 *
	 *
	 * [--user-meta]
	 * : Anonymize custom user metas containing the string passed. The process will explode comma separated values and treat usermeta meta_keys that start with the corresponding value
	 * default: ''
	 *
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		$this->confirm        					= \WP_CLI\Utils\get_flag_value( $assoc_args, 'confirm', true );
		$this->anonymize_woocommerce      = \WP_CLI\Utils\get_flag_value( $assoc_args, 'woocommerce', false );
		$this->user_meta       						= \WP_CLI\Utils\get_flag_value( $assoc_args, 'user-meta', '' );

		// TODO: Whitelist users
		$this->user_whitelist       						= \WP_CLI\Utils\get_flag_value( $assoc_args, 'user-whitelist', '' );

		wp_debug_mode();    // re-set `display_errors` after WP-CLI overrides it, see https://github.com/wp-cli/wp-cli/issues/706#issuecomment-203610437

		$wpdb->show_errors( WP_DEBUG ); // This makes it easier to catch errors while developing this command, but we don't need to show them to users

		$this->db_prefix = $wpdb->base_prefix;

		if ( is_multisite() ) {
			\WP_CLI::error( "This command doesn't support MultiSite yet." );
		}

		$this->confirm();

		try {
			\WP_CLI::line();

			$this->anonymize_wordpress();
			$this->anonymize_woocommerce();
			$this->anonymize_custom_user_metas();

			\WP_CLI::success( 'Successfully anonymized database.' );
		} catch ( Exception $exception ) {
			\WP_CLI::error( $exception->getMessage(), false );
			\WP_CLI::error( "You should check your site to see if it's broken. If it is, you can fix it by restoring your database from backups." );
		}
	}


	protected function confirm() {
		\WP_CLI::line();

		if ( ! $this->confirm ) {
			return;
		}

		\WP_CLI::warning( "Use this at your own risk. If something goes wrong, it could break your site. Before running this, make sure to back up your database running `wp db export`." );

		\WP_CLI::confirm( sprintf(
			"\nAre you sure you want to anonymize %s's database?",
			parse_url( site_url(), PHP_URL_HOST ),
		) );
	}


	/**
	 * Anonymize user data
	 *
	 * @throws Exception
	 */
	protected function anonymize_wordpress() {
		global $wpdb;

		$rows = $wpdb->get_results( "SELECT user_email FROM `{$this->db_prefix}users`;" );

		if ( ! $rows ) {
			throw new \Exception( 'MySQL error: ' . $wpdb->last_error );
		}

		// Anonymize user_login, user_nicename, display_name
		// TODO: Whitelist users, don't hardcode user 1
		$update_query = "UPDATE {$this->db_prefix}users SET user_login = 'xxxxxxxx', user_nicename = 'xxxxxxxx', display_name = 'xxxxxxxx' WHERE ID NOT IN (1);";
		if ( $wpdb->query( $update_query ) === false ) {
			throw new \Exception( 'MySQL error: ' . $wpdb->last_error );
		}

		// First Name, Last Name
		$update_query = "UPDATE {$this->db_prefix}usermeta SET meta_value = 'xxxxxxxx' WHERE meta_key IN ('nickname', 'first_name', 'last_name');";
		if ( $wpdb->query( $update_query ) === false ) {
			throw new \Exception( 'MySQL error: ' . $wpdb->last_error );
		}

		// Anonymize Emails
		//$update_query = "UPDATE {$this->db_prefix}users SET user_email = concat(substring_index(user_email, '@', 1), FLOOR(rand() * 90000 + 10000), '@localhost.dev');";
		$update_query = "UPDATE {$this->db_prefix}users SET user_email = concat(FLOOR(rand() * 90000 + 10000), '@localhost.dev') WHERE ID NOT IN (1);";
		if ( $wpdb->query( $update_query ) === false ) {
			throw new \Exception( 'MySQL error: ' . $wpdb->last_error );
		}
	}


	/**
	 * Anonymize WooCommerce related data
	 *
	 * @throws Exception
	 */
	protected function anonymize_woocommerce(){
		if ( ! $this->anonymize_woocommerce ) {
			return;
		}

		global $wpdb;

		// Users addresses
		// Billing
		$update_query = "UPDATE {$this->db_prefix}usermeta SET meta_value = 'xxxxxxxx' WHERE meta_key LIKE 'billing_%';";
		if ( $wpdb->query( $update_query ) === false ) {
			throw new \Exception( 'MySQL error: ' . $wpdb->last_error );
		}

		// Shipping
		$update_query = "UPDATE {$this->db_prefix}usermeta SET meta_value = 'xxxxxxxx' WHERE meta_key LIKE 'shipping_%';";
		if ( $wpdb->query( $update_query ) === false ) {
			throw new \Exception( 'MySQL error: ' . $wpdb->last_error );
		}

		// Orders
		// Billing
		$update_query = "UPDATE {$this->db_prefix}postmeta SET meta_value = 'xxxxxxxx' WHERE meta_key LIKE '_billing_%';";
		if ( $wpdb->query( $update_query ) === false ) {
			throw new \Exception( 'MySQL error: ' . $wpdb->last_error );
		}

		// Shipping
		$update_query = "UPDATE {$this->db_prefix}postmeta SET meta_value = 'xxxxxxxx' WHERE meta_key LIKE '_shipping_%';";
		if ( $wpdb->query( $update_query ) === false ) {
			throw new \Exception( 'MySQL error: ' . $wpdb->last_error );
		}

	}


	/**
	 * Anonymize custom data
	 *
	 * @throws Exception
	 */
	protected function anonymize_custom_user_metas(){
		if(empty( $this->user_meta )){
			return;
		}

		global $wpdb;

		$metas = explode(',', $this->user_meta);
		foreach ($metas as $key => $value) {
			$update_query = "UPDATE {$this->db_prefix}usermeta SET meta_value = 'xxxxxxxx' WHERE meta_key LIKE '{$value}%';";
			if ( $wpdb->query( $update_query ) === false ) {
				throw new \Exception( 'MySQL error: ' . $wpdb->last_error );
			}
		}
	}

}
