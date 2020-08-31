<?php
/**
 * Database connection test script for WordPress
 *
 * Parses the wp-config.php file for DB connection information and tests
 * a mysql connection to the DB server and selection of the database.
 * Errors will be reported.  Attempts will be made to repair table errors.
 *
 * Place this file in the same directory as wp-config.php
 *
 * This script can be run from the command line:
 * php /path/to/wp-mysql-test.php
 *
 * This script can also be called in a browser as long as the wp-config.php
 * file is in a web accessible directory.
 *
 * @author Matt Martz <matt@sivel.net>
 * @link https://gist.github.com/162913
 * @license http://www.gnu.org/licenses/gpl-2.0.txt
 */

// This will be overridden if WP_ALLOW_REPAIR is defined as true in wp-config.php
$allow_repair = false;
error_reporting(E_ALL);
ini_set('display_errors', 1);

header( 'Content-Type: text/plain' );
echo 'Test Started\n';

$wpconfig = dirname( __FILE__ ) . '/wp-config.php';

if ( ! file_exists( $wpconfig ) )
	die( "wp-config.php file cannot be found, please place this script in the same directory as wp-config.php.\n" );

if ( file_exists( dirname( dirname( __FILE__ ) ) . '/wp-config.php' ) && ! file_exists( dirname( dirname( __FILE__ ) ) . '/wp-settings.php' ) )
	printf( "There appears to be a wp-config.php file at %s which is\nnot part of a WordPress installation, but can be read by WordPress. Perhaps\nthe wp-config.php file at %s isn't needed?\n\n", dirname( dirname( __FILE__ ) ) . '/wp-config.php', $wpconfig );

$contents = file_get_contents( $wpconfig );

foreach ( array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST' ) as $config )
	preg_match( "/define[ ]*?\([^'\"]*?(['\"])$config\\1[^,]*?,[^'\"]*?(['\"])(.+)\\2[ ]*?\);/iU", $contents, $$config );

preg_match( '/\$table_prefix[ ]*?=[ ]*?([\'"]+)(.+)\\1[ ]*?;/iU', $contents, $table_prefix );

if ( ! $allow_repair && preg_match( "/define[ ]*?\([^'\"]*?(['\"])WP_ALLOW_REPAIR\\1[^,]*?,[^'\"]*?true[ ]*?\);/iU", $contents ) )
	$allow_repair = true;

$link = mysqli_connect( $DB_HOST[3], $DB_USER[3], $DB_PASSWORD[3] );
if ( mysqli_connect_errno() ) {
	die( sprintf( "Could not connect to the MySQL server: %s\n", mysqli_connect_error() ) );
}
echo "Connected successfully to the MySQL server\n";

$db_selected = mysqli_select_db($link , $DB_NAME[3]);
if ( ! $db_selected ) {
	die ( sprintf( "Could not select the database '%s': %s\n", $DB_NAME[3],  mysqli_error($link) ) );
}
echo "Database selected successfully\n";

echo "\nChecking tables for errors:\n";
$tables = mysqli_query($link, "SHOW TABLES LIKE '{$table_prefix[2]}%'" );
while ( $table = mysqli_fetch_array( $tables, MYSQLI_NUM ) ) {
	$status = mysqli_fetch_row( mysqli_query($link, "CHECK TABLE `{$table[0]}`" ) );
	$message = sprintf( 'The table %s is', $status[0] );
	if ( $status[3] != 'OK' ) {
		printf( "\n%s NOT OK. Error: %s\n", $message, $status[3] );
		if ( $allow_repair ) {
			printf( "Attempting to repair %s\n", $status[0] );
			$status = mysqli_fetch_row( mysqli_query( "REPAIR TABLE `{$table[0]}`") );
			$message = sprintf( 'The table %s', $status[0] );
			if ( $status[3] != 'OK' )
				printf( "%s could NOT be repaired. Error: %s\n\n", $message, $status[3] );
			else
				printf( "%s was successfully repaired\n\n", $message );;
		} else {
			printf( "Not repairing table %s due to configuration.\nChange \$allow_repair to true or define WP_ALLOW_REPAIR in wp-config.php\n\n", $status[0] );
		}
	} else {
		printf( "%s OK\n", $message );
	}
}

mysqli_close( $link );
