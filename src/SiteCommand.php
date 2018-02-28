<?php

namespace Deve_CLI;

use WP_CLI;
use WP_CLI\Process;
use WP_CLI\Utils;
use WP_CLI\Formatter;
use Deve_CLI\Docker\DockerClient;

class SiteCommand {
  const NGINX_DIR = 'nginx-dir';
  const PHP_DIR   = 'php-dir';
  const WWW_DIR   = 'www-dir';
  const DOMAIN    = 'domain';

  const DEFAULTS = array(
    'w3tc' => false,
    'wpsc' => false,
    'wpce' => false,
    'wpfc' => false,
    'wpsubdir' => false,
    'wpsubdom' => false,
    'hhvm' => false,
    'pagespeed' => false,
    'default-server' => false,
    'activate' => false,
    'force' => false,
    'ssl' => true,
    self::NGINX_DIR => '/etc/nginx',
    self::PHP_DIR => '/usr/local/etc',
    self::WWW_DIR => '/var/www'
  );

  /**
   * Outputs a list of sites in the system.
   *
   * ## OPTIONS
   *
   * [--field=<field>]
   * : Prints the value of a single field for each site.
   *
   * [--fields=<fields>]
   * : Limit the output to specific object fields. Defaults to domain,active,default-server,ssl.
   *
   * [--format=<format>]
   * : Render output in a particular format.
   * ---
   * default: table
   * options:
   *   - table
   *   - csv
   *   - count
   *   - json
   *   - yaml
   * ---
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
  public function site_list( $_, $assoc_args ) {
    $assoc_args = array_merge( static::DEFAULTS, $assoc_args );

    $files = glob( "{$assoc_args[self::NGINX_DIR]}/sites-available/*.conf" );
    if ( ! is_dir( "{$assoc_args[self::NGINX_DIR]}/sites-enabled" ) ) {
      $enabled = array();
    } else {
      $enabled = glob( "{$assoc_args[self::NGINX_DIR]}/sites-enabled/*.conf" );
    }

    foreach ( $files as $key => $file ) {
      $active = in_array( $file, $enabled ) !== null;

      $lines = file( $file );
      array_splice( $lines, 1 );

      $file = json_decode( substr( $lines[0], 1 ), 1 );
      $file['active'] = $active;

      $files[$key] = $file;
    }

    $formatter = new WP_CLI\Formatter( $assoc_args, array( self::DOMAIN, 'active', 'default-server', 'ssl' ) );
    $formatter->display_items( $files );
  }

  /**
   * Generate the configuration for nginx, php and create the folder.
   *
   * Default behavior is to create the following files:
   * - domain.conf (/etc/nginx/sites-available)
   * - domain.conf (/usr/local/etc/php-available)
   * - /var/www/domain (root folder)
   *
   * Unless specified with `--activate`, the site is not sym-linked into the
   * `nginx/sites-enabled` directory.
   *
   * ## OPTIONS
   *
   * <domain>
   * : The domain of the new site. E.g. example.com
   *
   * [--w3tc]
   * : Add rules for w3-total-cache plugin
   *
   * [--wpsc]
   * : Add rules for wordpress super-cache plugin
   *
   * [--wpce]
   * : Add rules for wordpress cache-enabler plugin
   *
   * [--wpfc]
   * : Add rules for nginx fastcgi_cache
   *
   * [--wpsubdir]
   * : Add rules for wordpress multisite in sub directory
   *
   * [--wpsubdom]
   * : Add rules for wordpress multisite in subdomain
   *
   * [--hhvm]
   * : Create the site with hhvm instead of php
   *
   * [--pagespeed]
   * : Enable google pagespeed for the site
   *
   * [--default-server]
   * : Set the site as default site for Nginx.
   *
   * [--activate]
   * : Don't generate files for integration testing.
   *
   * [--skip-ssl]
   * : Don't add ssl certificates to nginx config.
   *
   * [--force]
   * : Overwrite files that already exist.
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
  public function site_create( $args, $assoc_args ) {
    $skip_ssl = Utils\get_flag_value( $assoc_args, 'skip-ssl' );
    unset( $assoc_args['skip-ssl'] );

    $assoc_args = array_merge( static::DEFAULTS, $assoc_args );
    $assoc_args[self::DOMAIN] = $args[0];

    // if neither wpsubdir nor wpsubdom are set it's a single site
    $assoc_args['wpss'] = ( $assoc_args['wpsubdir'] || $assoc_args['wpsubdom'] ) ? false : true;
    $assoc_args['ssl'] = ! $skip_ssl;
    $assoc_args['config'] = json_encode( $assoc_args );

    if ( $assoc_args['ssl'] && ! $this->has_ssl( $assoc_args[self::DOMAIN] ) ) {
      WP_CLI::error( "SSL certificates for {$assoc_args[self::DOMAIN]} can not be found." );
    }

    $force = Utils\get_flag_value( $assoc_args, 'force' );
    $package_root = dirname( dirname( __FILE__ ) );
    $template_path = $package_root . '/templates';

    $files_written = $this->process_files( array(
      "{$assoc_args[self::NGINX_DIR]}/sites-available/{$assoc_args[self::DOMAIN]}.conf" => Utils\mustache_render( "{$template_path}/nginx.mustache", $assoc_args ),
      "{$assoc_args[self::PHP_DIR]}/php-available/{$assoc_args[self::DOMAIN]}.conf"   => Utils\mustache_render( "{$template_path}/php-pool.mustache", $assoc_args )
    ), $force, 'file_put_contents' );

    WP_CLI::log( 'Configuration files created.' );

    // create the www root dir
    $wwwdir = "{$assoc_args[self::WWW_DIR]}/{$assoc_args[self::DOMAIN]}";
    if ( ! is_dir( $wwwdir ) ) {
      Process::create( Utils\esc_cmd( 'mkdir -p %s', $wwwdir ) )->run();
    }

    WP_CLI::log( 'WWW directory created.' );

    if ( empty( $files_written ) ) {
      WP_CLI::log( 'All configuration files were skipped.' );
    } else {
      WP_CLI::success( "Site `{$assoc_args[self::DOMAIN]}` created." );
    }

    if ( Utils\get_flag_value( $assoc_args, 'activate' ) ) {
      $command = "deve site activate {$assoc_args[self::DOMAIN]} " .
                        "--nginx-dir={$assoc_args[self::NGINX_DIR]} " .
                        "--php-dir={$assoc_args[self::PHP_DIR]} " .
                        "--www-dir={$assoc_args[self::WWW_DIR]}";
      WP_CLI::runcommand( $command, array( 'launch' => false ) );
    }
  }

  /**
   * Deletes the sites configuration and data.
   *
   * Default behavior is to delete the following files:
   * - domain.conf (/etc/nginx/sites-available)
   * - domain.conf (/usr/local/etc/php-available)
   * - /var/www/domain (root folder)
   *
   * ## OPTIONS
   *
   * <domain>
   * : The domain of the new site. E.g. example.com
   *
   * [--activate]
   * : Don't generate files for integration testing.
   *
   * [--w3tc]
   * : Add rules for w3-total-cache plugin
   *
   * [--wpsc]
   * : Add rules for wordpress super-cache plugin
   *
   * [--wpce]
   * : Add rules for wordpress cache-enabler plugin
   *
   * [--wpfc]
   * : Add rules for nginx fastcgi_cache
   *
   * [--wpsubdir]
   * : Add rules for wordpress multisite in sub directory
   *
   * [--wpsubdom]
   * : Add rules for wordpress multisite in subdomain
   *
   * [--hhvm]
   * : Create the site with hhvm instead of php
   *
   * [--pagespeed]
   * : Enable google pagespeed for the site
   *
   * [--force]
   * : Overwrite files that already exist.
   *
   * [--default-server]
   * : Set the site as default site for Nginx.
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
  public function site_delete($args, $assoc_args) {
    $assoc_args = array_merge( static::DEFAULTS, $assoc_args );
    $assoc_args[self::DOMAIN] = $args[0];

    $command = "deve site deactivate {$assoc_args[self::DOMAIN]} " .
                      "--nginx-dir={$assoc_args[self::NGINX_DIR]} " .
                      "--php-dir={$assoc_args[self::PHP_DIR]} " .
                      "--www-dir={$assoc_args[self::WWW_DIR]}";
    WP_CLI::runcommand( $command, array( 'launch' => true, 'exit_error' => false, 'return' => 'all' ) );

    $nginx = "{$assoc_args[self::NGINX_DIR]}/sites-available/{$assoc_args[self::DOMAIN]}.conf";
    $php = "{$assoc_args[self::PHP_DIR]}/php-available/{$assoc_args[self::DOMAIN]}.conf";

    if ( ! file_exists( $nginx ) ) {
      WP_CLI::error( "Nginx configuration for '{$assoc_args[self::DOMAIN]}' does not exist." );
    }

    if ( ! file_exists( $php ) ) {
      WP_CLI::error( "PHP pool configuration for '{$assoc_args[self::DOMAIN]}' does not exist." );
    }

    $nginx = unlink( $nginx );
    $php = unlink( $php );
    $this->delete_dir( "{$assoc_args[self::WWW_DIR]}/{$assoc_args[self::DOMAIN]}" );

    if ( !$nginx || !$php ) {
      if ( !$nginx ) { WP_CLI::log( 'Nginx configuration could not be removed.' ); }
      if ( !$php ) { WP_CLI::log( 'PHP configuration could not be removed.' ); }
    } else {
      WP_CLI::success( 'Unlinked configuration files for nginx and php.' );
    }
  }

  /**
   * Activates a site, reloads the services.
   *
   * Default behavior is to create the following files:
   * - symlinks site into sites-enabled
   * - symlinks pool into php-fpm.d
   * - creates the certificates
   * - starts the pool and verifies it running
   * - reloads the nginx configuration (hotswap)
   *
   * ## OPTIONS
   *
   * <domain>
   * : The domain of the new site. E.g. example.com
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
  public function site_activate( $args, $assoc_args ) {
    $assoc_args = array_merge( static::DEFAULTS, $assoc_args );
    $assoc_args[self::DOMAIN] = $args[0];
    $nginx_available = "{$assoc_args[self::NGINX_DIR]}/sites-available/{$assoc_args[self::DOMAIN]}.conf";
    $php_available = "{$assoc_args[self::PHP_DIR]}/php-available/{$assoc_args[self::DOMAIN]}.conf";

    if ( ! file_exists( $nginx_available ) ) {
      WP_CLI::error( "Nginx configuration for '{$assoc_args[self::DOMAIN]}' does not exist." );
    }

    if ( ! file_exists( $php_available ) ) {
      WP_CLI::error( "PHP pool configuration for '{$assoc_args[self::DOMAIN]}' does not exist." );
    }

    $nginx_enabled = str_replace( '/sites-available/', '/sites-enabled/', $nginx_available );
    $php_enabled = str_replace( '/php-available/', '/php-fpm.d/', $php_available );

    $files_linked = $this->process_files( array(
      $nginx_enabled => $nginx_available,
      $php_enabled => $php_available
    ), false, 'symlink');

    if ( empty( $files_linked ) ) {
      WP_CLI::log( 'All configuration files were skipped.' );
    } else {
      WP_CLI::success( 'Linked configuration files for nginx and php.' );
    }
  }

  /**
   * Deactivates a site, reloads the services.
   *
   * Default behavior is to create the following files:
   * - unlinks site into sites-enabled
   * - unlinks pool into php-fpm.d
   * - starts the pool and verifies it running
   * - reloads the nginx configuration (hotswap)
   *
   * ## OPTIONS
   *
   * <domain>
   * : The domain of the new site. E.g. example.com
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
  public function site_deactivate( $args, $assoc_args ) {
    $assoc_args = array_merge( static::DEFAULTS, $assoc_args );
    $assoc_args[self::DOMAIN] = $args[0];

    $nginx_enabled = "{$assoc_args[self::NGINX_DIR]}/sites-enabled/{$assoc_args[self::DOMAIN]}.conf";
    $php_enabled = "{$assoc_args[self::PHP_DIR]}/php-fpm.d/{$assoc_args[self::DOMAIN]}.conf";

    if ( ! file_exists( $nginx_enabled ) ) {
      WP_CLI::error( "Nginx configuration for '{$assoc_args[self::DOMAIN]}' does not exist." );
    }

    if ( ! file_exists( $php_enabled ) ) {
      WP_CLI::error( "PHP pool configuration for '{$assoc_args[self::DOMAIN]}' does not exist." );
    }

    $nginx = unlink( $nginx_enabled );
    $php = unlink( $php_enabled );

    if ( !$nginx || !$php ) {
      if ( !$nginx ) { WP_CLI::log( 'Nginx configuration could not be removed.' ); }
      if ( !$php ) { WP_CLI::log( 'PHP configuration could not be removed.' ); }
    } else {
      WP_CLI::success( 'Unlinked configuration files for nginx and php.' );
    }
  }

  /**
   * Create an SSL certificate for the provided domain.
   *
   * Default behavior is:
   * - stop deve_web
   * - create a certbot container
   * - run certbot container
   * - wait until certbot is complete
   * - restart deve_web
   *
   * ## OPTIONS
   *
   * <domain>
   * : The domain of the new site. E.g. example.com
   *
   * <email>
   * : The email to use for certbot. E.g. jdoe@example.com
   *
   * [--dry-run]
   * : Only test if certificates would work.
   *
   * [--verbose]
   * : Run this command verbose.
   *
   * @when before_wp_load
   */
  public function site_ssl( $args, $assoc_args ) {
    $verbose = Utils\get_flag_value( $assoc_args, 'verbose' );
    $assoc_args = array_merge( static::DEFAULTS, $assoc_args );
    $domain = $args[0];
    $email = $args[1];

    if ( $verbose ) { WP_CLI::debug( "domain [{$domain}] email [{$email}]" ); }

    $client = DockerClient::getInstance( $verbose );
    $containers =  $client->find_containers( array(
      'status' => array( 'running' ),
      'name' => array( 'deve_web' )
    ) );

    foreach ( $containers->getDecodedBody() as $container ) {
      $name = $container->Names[0];
      $code = $client->stop_container( $container->Id )->getStatusCode();
      if ( $code === 204 || $code === 304 ) {
        WP_CLI::line( "Container {$name} stopped." );
      } else {
        WP_CLI::error( "Error stopping {$name}!" );
      }
    }

    $cmd = array(
      'certonly', '--standalone', '-n', '--agree-tos', '-m', $email, '-d', $domain //, '--http-01-port', '6789',
      // '--config-dir', "{$dir}/etc", '--logs-dir', "{$dir}/logs", '--work-dir', "{$dir}/lib"
    );
    if (Utils\get_flag_value( $assoc_args, 'dry-run' ) ) {
      array_push( $cmd, '--dry-run' );
    }
    $ssl = $client->run( array(
      'Image' => 'certbot/certbot',
      'Binds' => array( "deve_ssl:/etc/letsencrypt" ),
      'PortBindings' => array( '80/tcp' => array( array( 'HostPort' => '80' ) ) ),
      'Cmd' => $cmd
    ) );
    if ( $ssl ) {
      WP_CLI::success( 'The SSL certificate has been created.' );
    } else {
      WP_CLI::warning( 'Could not create the certificate.' );
    }

    foreach ( $containers->getDecodedBody() as $container ) {
      $name = $container->Names[0];
      $code = $client->start_container( $container->Id )->getStatusCode();
      if ( $code === 204 || $code === 304 ) {
        WP_CLI::line( "Container {$name} started." );
      } else {
        WP_CLI::error( "Error starting {$name}!" );
      }
    }
  }

  private function prompt_if_files_will_be_overwritten( $filename, $force ) {
    $should_write_file = true;
    if ( ! file_exists( $filename ) ) {
      return true;
    }
    WP_CLI::warning( 'File already exists' );
    WP_CLI::log( $filename );
    if ( ! $force ) {
      do {
        $answer = \cli\prompt( 'Skip this file, or replace it with scaffolding?', false, '[s/r]: ' );
      } while ( ! in_array( $answer, array( 's', 'r' ) ) );
      $should_write_file = 'r' === $answer;
    }
    $outcome = $should_write_file ? 'Replacing' : 'Skipping';
    WP_CLI::log( $outcome . PHP_EOL );
    return $should_write_file;
  }

  private function process_files( $files, $force, $processor ) {
    $wrote_files = array();
    foreach ( $files as $filename => $contents ) {
      $should_write_file = $this->prompt_if_files_will_be_overwritten( $filename, $force );
      if ( ! $should_write_file ) {
        continue;
      }
      if ( ! is_dir( dirname( $filename ) ) ) {
        Process::create( Utils\esc_cmd( 'mkdir -p %s', dirname( $filename ) ) )->run();
      }
      if ( ! $this->$processor( $filename, $contents ) ) {
        WP_CLI::error( "Error creating file: $filename" );
      } elseif ( $should_write_file ) {
        $wrote_files[] = $filename;
      }
    }
    return $wrote_files;
  }

  private function symlink( $target, $source ) {
    return symlink( realpath( $source ), realpath( dirname( $target ) ) . "/" . basename( $target ) );
  }

  private function file_put_contents( $file, $content ) {
    return file_put_contents( $file, $content );
  }

  private function delete_dir( $dir ) {
    if ( ! is_dir( $dir ) ) { return; }
    if ( substr( $dir, strlen( $dir ) - 1, 1 ) != '/' ) {
      $dir .= '/';
    }

    $files = glob( $dir . '*', GLOB_MARK );
    foreach ( $files as $file ) {
      if ( is_dir( $file ) ) { $this->delete_dir( $file ); }
      unlink( $file );
    }

    rmdir( $dir );
  }

  private function has_ssl( $domain ) {
    $dir = "/etc/letsencrypt/live/{$domain}";
    return file_exists( "{$dir}/fullchain.pem" ) && file_exists( "{$dir}/privkey.pem" );
  }
}
