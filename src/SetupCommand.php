<?php

namespace Deve_CLI;

use WP_CLI;
use WP_CLI\Process;
use WP_CLI\Utils;
use WP_CLI\Formatter;

class SetupCommand {
  /**
   * Creates the inital Wordpress installation.
   *
   * ## OPTIONS
   *
   * [--nginx-dir=<nginx-dir>]
   * : Specify a Nginx directory for the command. Defaults to Nginx's `/etc/nginx/sites-available` directory.
   *
   * [--php-dir=<php-dir>]
   * : Specify a PHP directory for the command. Defaults to PHP's `/usr/local/etc/php-available` directory.
   *
   * [--www-dir=<www-dir>]
   * : Specify a WWW directory for the command. Defaults to WWW's `/var/www` directory.
   *
   * @when before_wp_load
   */
  public function setup( $args, $assoc_args ) {
    $final_args = array_merge( $assoc_args, array(
      'database' => getenv( 'DEVE_DATABASE' ),
      'user' => getenv( 'DEVE_USER' ),
      'password' => getenv( 'DEVE_DATABASE_PASSWORD' )
    ) );

    WP_CLI::log( "CREATE DATABASE IF NOT EXISTS {$final_args['database']};" );
    self::run( "CREATE DATABASE IF NOT EXISTS {$final_args['database']};" );
    self::run( "CREATE USER '{$final_args['user']}'@'%' IDENTIFIED BY '{$final_args['password']}';" );
    self::run( "GRANT ALL PRIVILEGES ON {$final_args['database']}.* TO '{$final_args['user']}'@'%';" );
    self::run( "FLUSH PRIVILEGES;" );

    WP_CLI::success( 'This worked!' );


    // DEVE_DOMAIN=deve.us
    // DEVE_USER=admin
    // DEVE_PASSWORD=example
    // DEVE_EMAIL=thomas@stachl.me
    // DEVE_TITLE=Default Deve.us Install
    // DEVE_DATABASE=deve
    // DEVE_DATABASE_PASSWORD=mQXC2en5QnVkeCNQPd

    // mkdir -p "/var/www/$DEVE_DOMAIN" && cd $_
    // wp core download
    // wp core config --dbname=$DEVE_DOMAIN --dbuser=$DEVE_DOMAIN --dbpass=$PASSWDDB
    // wp db create
    // wp core install --url=$DEVE_DOMAIN --title="$DEVE_TITLE" --admin_user=$DEVE_USER --admin_password=$DEVE_PASSWORD --admin_email=$DEVE_EMAIL
    // # set -- wp "deve site create $DEVE_DOMAIN"
  }

	private static function run( $cmd ) {
		$required = array(
			'host' => 'mysql',
			'user' => 'root',
			'pass' => getenv( 'MYSQL_ROOT_PASSWORD' ),
      'default-character-set' => 'utf8'
		);
		Utils\run_mysql_command( $cmd, $required, null );
	}
}
