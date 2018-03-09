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
      'db_password' => getenv( 'DEVE_DATABASE_PASSWORD' ),
      'domain' => getenv( 'DEVE_DOMAIN' ),
      'url' => 'http://' . getenv( 'DEVE_DOMAIN' ),
      'title' => getenv( 'DEVE_TITLE' ),
      'password' => getenv( 'DEVE_PASSWORD' ),
      'email' => getenv( 'DEVE_EMAIL' )
    ) );
    $final_args['path'] = "/var/www/{$final_args['domain']}";
    $cmd_args = array( 'launch' => true, 'exit_error' => false );

    // set up the database for our wordpress install
    // self::run_query( "CREATE DATABASE IF NOT EXISTS {$final_args['database']};" );
    self::run_query( "CREATE USER IF NOT EXISTS '{$final_args['user']}'@'%' IDENTIFIED BY '{$final_args['db_password']}';" );
    self::run_query( "GRANT ALL PRIVILEGES ON {$final_args['database']}.* TO '{$final_args['user']}'@'%';" );
    self::run_query( "FLUSH PRIVILEGES;" );

    // create the directory and download wordpress core
    WP_CLI::runcommand( "core download --path={$final_args['path']} --quiet", $cmd_args );
    WP_CLI::runcommand( "core config --force --path={$final_args['path']} --dbname={$final_args['database']} --dbuser={$final_args['user']} --dbpass={$final_args['db_password']} --dbhost=mysql", $cmd_args );
    WP_CLI::runcommand( "db create --path={$final_args['path']}", $cmd_args );
    WP_CLI::runcommand( "core install --skip-email --path={$final_args['path']} --url={$final_args['url']} --title='{$final_args['title']}' --admin_user={$final_args['user']} --admin_password={$final_args['password']} --admin_email={$final_args['email']}", $cmd_args );

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

	private static function run_query( $query ) {
		self::run( '/usr/bin/env mysql --no-defaults --no-auto-rehash', array( 'execute' => $query ) );
	}

	private static function run( $cmd, $assoc_args = array(), $descriptors = null ) {
		$required = array_merge( $assoc_args, array(
			'host' => 'mysql',
			'user' => 'root',
			'pass' => getenv( 'MYSQL_ROOT_PASSWORD' ),
      'default-character-set' => 'utf8'
		));
		Utils\run_mysql_command( $cmd, $required, $descriptors );
	}
}
