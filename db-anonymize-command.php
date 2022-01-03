<?php

namespace WP_CLI\DBAnonymize;

use WP_CLI;

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

$wpcli_db_anonymize_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_db_anonymize_autoloader ) ) {
	require_once $wpcli_db_anonymize_autoloader;
}

WP_CLI::add_command( 'db-anonymize', DBAnonymizeCommand::class );
