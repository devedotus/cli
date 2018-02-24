<?php

namespace Deve_CLI;

use WP_CLI;
use WP_CLI\Process;
use WP_CLI\Utils;

class SiteCommand {
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
  public function site( $args, $assoc_args ) {
    $defaults = array(
      'wpsubdom' => false,
      'wpsubdir' => false,
      'nginx-dir' => '/etc/nginx/sites-available',
      'php-dir' => '/usr/local/etc/php-available',
			'www-dir' => '/var/www'
    );
    
    $assoc_args = array_merge( $defaults, $assoc_args );
    $assoc_args['domain'] = $args[0];
    
    // if neither wpsubdir nor wpsubdom are set it's a single site
    $assoc_args['wpss'] = ( $assoc_args['wpsubdir'] || $assoc_args['wpsubdom'] ) ? false : true;
    
    $force = Utils\get_flag_value( $assoc_args, 'force' );
    $package_root = dirname( dirname( __FILE__ ) );
    $template_path = $package_root . '/templates';
    
    $files_written = $this->create_files( array(
      "{$assoc_args['nginx-dir']}/{$assoc_args['domain']}.conf" => Utils\mustache_render( "{$template_path}/nginx.mustache", $assoc_args ),
      "{$assoc_args['php-dir']}/{$assoc_args['domain']}.conf"   => Utils\mustache_render( "{$template_path}/php-pool.mustache", $assoc_args )
    ), $force );
    
		// create the www root dir
		$wwwdir = "{$assoc_args['www-dir']}/{$assoc_args['domain']}";
		if ( ! is_dir( $wwwdir ) ) {
			Process::create( Utils\esc_cmd( 'mkdir -p %s', $wwwdir ) )->run();
		}
		
    if ( empty( $files_written ) ) {
      WP_CLI::log( 'All configuration files were skipped.' );
    } else {
      WP_CLI::success( 'Created configuration files for nginx and php.' );
    }
    
    if ( Utils\get_flag_value( $assoc_args, 'activate' ) ) {
      WP_CLI::runcommand( "deve site-activate {$assoc_args['domain']} --nginx-dir={$assoc_args['nginx-dir']}", array( 'launch' => false ) );
    }
  }
  
	/**
	 * Activates a site, creates users and groups, reloads the services.
	 *
	 * Default behavior is to create the following files:
	 * - symlinks site into sites-enabled
	 * - symlinks pool into php-fpm.d
	 * - creates user/group in php container
	 * - starts the pool and verifies it running
	 * - reloads the nginx configuration (hotswap)
	 *
	 * Unless specified with `--activate`, the site is not sym-linked into the
	 * `nginx/sites-enabled` directory.
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
	 * @when before_wp_load
   */
  public function site_activate( $args, $assoc_args ) {
    $defaults = array(
      'nginx-dir' => '/etc/nginx/sites-enabled',
			'php-di' => '/usr/local/etc/php-fpm.d'
    );
    
    $assoc_args = array_merge( $defaults, $assoc_args );
    $assoc_args['domain'] = $args[0];
    
    
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
				$answer = \cli\prompt(
					'Skip this file, or replace it with scaffolding?',
					$default = false,
					$marker = '[s/r]: '
				);
			} while ( ! in_array( $answer, array( 's', 'r' ) ) );
			$should_write_file = 'r' === $answer;
		}
		$outcome = $should_write_file ? 'Replacing' : 'Skipping';
		WP_CLI::log( $outcome . PHP_EOL );
		return $should_write_file;
	}
  
	private function create_files( $files_and_contents, $force ) {
		$wrote_files = array();
		foreach ( $files_and_contents as $filename => $contents ) {
			$should_write_file = $this->prompt_if_files_will_be_overwritten( $filename, $force );
			if ( ! $should_write_file ) {
				continue;
			}
			if ( ! is_dir( dirname( $filename ) ) ) {
				Process::create( Utils\esc_cmd( 'mkdir -p %s', dirname( $filename ) ) )->run();
			}
			if ( ! file_put_contents( $filename, $contents ) ) {
				WP_CLI::error( "Error creating file: $filename" );
			} elseif ( $should_write_file ) {
				$wrote_files[] = $filename;
			}
		}
		return $wrote_files;
	}
}