<?php namespace TorMorten\View;

/**
 * Uses the Blade templating engine
 */
class Blade {

	/**
	 * The single instance of the class.
	 *
	 * @var FjellCommerce
	 * @since 1.0
	 */
	protected static $_instance = null;

	/**
	 * View factory
	 *
	 * @var TorMorten\Views\Factory
	 */
	protected $factory;

	/**
	 * View folder
	 *
	 * @var string
	 */
	protected $views;

	/**
	 * View cache
	 *
	 * @var string
	 */
	protected $view_cache;

	/**
	 * Cache folder
	 *
	 * @var string
	 */
	protected $cache;

	/**
	 * Controllers
	 *
	 * @var Controllers
	 */
	protected $controller;

	/**
	 * Set up hooks and initialize Blade
	 */
	public function __construct($actions = true) {

		do_action( 'wp_blade_booting' );

		// Directory where views are loaded from
		$viewDirectory    = trailingslashit( apply_filters( 'wp_blade_views_directory', defined( 'BLADE_VIEWS' ) ? BLADE_VIEWS : trailingslashit( get_template_directory() ) . 'views' ) );

		// Directory where compiled templates are cached
		$cacheDirectory   = trailingslashit( apply_filters( 'wp_blade_cache_directory', defined( 'BLADE_CACHE' ) ? BLADE_CACHE : trailingslashit( get_template_directory() ) . '.views_cache' ) );

		// Set class properties
		$this->views      = array( $viewDirectory );
		$this->cache      = $cacheDirectory;
		$this->view_cache = $this->views[0] . 'cache';

		// Collect the controllers
		$this->controller = new Controllers;

		// Create cache directories if needed
		$this->maybeCreateCacheDirectory();
		$this->maybeCreateViewCacheDirectory();

		// Create the blade instance
		$this->factory = new Factory( $this->views, $this->cache );

		 // extend the compiler
		$this->extend();

		if($actions) {
			// Bind to template include action
			add_action( 'template_include', array( $this, 'blade_include' ) );

			// Listen for Buddypress include action
			add_filter( 'bp_template_include', array( $this, 'blade_include' ) );
		}

		do_action( 'wp_blade_booted', $this );

	}

	/**
	 * Main Blade Instance.
	 *
	 * Ensures only one instance of Blade is loaded or can be loaded.
	 *
	 * @since 1.0
	 * @static
	 * @return TorMorten\View\Blade
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Set up hooks and initialize Blade
	 */
	public static function create() {
		return self::instance();
	}

	/**
	 * Return the compiler instance
	 * @return mixed
	 */
	public function compiler() {
		return $this->factory->compiler();
	}

	/**
	 * Return the factory instance
	 * @return mixed
	 */
	public function factory() {
		return $this->factory;
	}

	/**
	 * Renders a given template
	 *
	 * @param string  $template Path to the template
	 * @param array   $with     Additional args to pass to the tempalte
	 * @return string           Compiled template
	 */
	public function view( $template, $with = array() ) {
		$template = apply_filters( 'wp_blade_include_template', $template, $with );
		$with     = apply_filters( 'wp_blade_include_arguments', $with, $template );
		$html     = apply_filters( 'wp_blade_include_html', $this->factory->render( $template, $with ), $template, $with );
		return $html;
	}

	/**
	 * Compiles a string
	 * @param  string $string A string with Blade flavored PHP
	 * @param  array  $with An array with extra data passed to the string
	 * @return string
	 */
	public function viewString( $string, $with = array() ) {
		$key = md5($string);
		$blade_file = $this->view_cache . '/' . $key . '.blade.php';
		$template = 'cache.'. $key;

		if(!file_exists( $blade_file )) {
			// add the code to the cached blade file
			file_put_contents( $blade_file, $string );
		}

		return $this->view( $template, $with );
	}

	/**
	 * Renders a given template statically
	 *
	 * @param string  $template Path to the template
	 * @param array   $with     Additional args to pass to the tempalte
	 * @return string           Compiled template
	 */
	public static function render( $template, $with = array() ) {
		$instance = self::instance();
		return $instance->view( $template, $with );
	}



	/**
	 * Renders a given template statically
	 *
	 * @param string  $template Path to the template
	 * @param array   $with     Additional args to pass to the tempalte
	 * @return string           Compiled template
	 */
	public static function renderString( $string, $with = array() ) {
		$instance = self::instance();
		return $instance->viewString( $string, $with );
	}

	/**
	 * Checks whether the cache directory exists, and if not creates it.
	 *
	 * @return boolean
	 */
	public function maybeCreateCacheDirectory() {
		if ( !is_dir( $this->cache ) ) {
			if ( wp_mkdir_p( $this->cache ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks whether the view cache directory exists, and if not creates it.
	 *
	 * @return boolean
	 */
	public function maybeCreateViewCacheDirectory() {
		if ( !is_dir( $this->view_cache ) ) {
			if ( wp_mkdir_p( $this->view_cache ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Include the template
	 *
	 * @return string
	 */
	public function blade_include( $template ) {

		if ( !current_theme_supports( 'blade-templates' ) )
			return $template;

		if ( ! $template )
			return $template; // Noting to do here. Come back later.

		// all templates for our engine must live in the template directory
		if ( stripos( $template, get_template_directory() ) === FALSE ) {
			return $template;
		}

		$file = basename( $template );
		$view = str_replace( '.php', '', $file );

		if ( apply_filters( 'wp_blade_compile_root_template', $this->viewExpired( $template ) ) ) {

			// get the base name
			$file = basename( $template );

			// with a blade extension, we have to do this because blade wont recognize the root files without the .blade.php extension
			$blade = str_replace( '.php', '.blade.php', $file );
			$blade_file = $this->view_cache . '/' . $blade;

			// get the code
			$code = file_get_contents( $template );

			// add the code to the cached blade file
			file_put_contents( $blade_file, $code );

			// blade friendly name
			$view = str_replace( '.php', '', $file );

			// get data for the view
			$data = $this->getController( $view );

			// run the blade code
			echo $this->view( 'cache.'. $view, $data );

			// halt including
			return '';
		}
		else {

			// get the base name
			$file = basename( $template );

			// blade friendly name
			$view = str_replace( '.php', '', $file );

			// get data for the view
			$data = $this->getController( $view );

			// run the blade code
			echo $this->view( 'cache.'. $view, $data );

			// halt including
			return '';
		}

		// return an empty string to stop wordpress from including the template when we are doing it
		return $template;
	}

	/**
	 * Check if the view has a controller which can be attached
	 *
	 * @param string  $view The view name
	 * @return mixed A controller instance or false
	 */
	protected function getController( $view ) {
		return $this->controller->getControllersForView( $view );
	}

	/**
	 * Adds controller
	 *
	 * @param mixed   $controller Array or string of class names
	 */
	public function addController( $controller ) {
		$this->controller->register( $controller );
	}

	/**
	 * Checks if the view was changed after we stored it for caching
	 *
	 * @param string  $path Path to the file
	 * @return boolean
	 */
	protected function viewExpired( $path ) {

		$file = basename( $path );

		$blade = str_replace( '.php', '.blade.php', $file );
		$blade_file = $this->view_cache . '/' . $blade;

		if ( !file_exists( $blade_file ) ) {
			return true;
		}

		$lastModified = filemtime( $path );

		return $lastModified >= filemtime( $blade_file );

	}

	/**
	 * Checks if a root view exists
	 *
	 * @return boolean
	 */
	protected function viewExists( $view ) {
		try {
			$this->factory->make( 'cache.'. $view, array() )->render();
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Extend blade with some custom directives
	 *
	 * @return void
	 */
	protected function extend() {

		/**
		 * WP Query Directives
		 */
		$this->compiler()->directive( 'wpposts', function() {
			return '<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>';
		} );

		$this->compiler()->directive( 'wpquery', function( $expression ) {
			$php  = '<?php $bladequery = new WP_Query'.$expression.'; ';
			$php .= 'if ( $bladequery->have_posts() ) : ';
			$php .= 'while ( $bladequery->have_posts() ) : ';
			$php .= '$bladequery->the_post(); ?> ';
			return $php;
		} );

		$this->compiler()->directive( 'wpempty', function() {
			return '<?php endwhile; ?><?php else: ?>';
		} );

		$this->compiler()->directive( 'wpend', function() {
			return '<?php endif; wp_reset_postdata(); ?>';
		} );

		/**
		 * Advanced Custom Field Directives
		 */

		$this->compiler()->directive( 'acfempty', function() {
			return '<?php endwhile; ?><?php else: ?>';
		} );

		$this->compiler()->directive( 'acfend', function() {
			return '<?php endif; ?>';
		} );

		$this->compiler()->directive( 'acf', function( $expression ) {
			$php = '<?php if ( have_rows('.$expression.') ) : ';
			$php .= 'while ( have_rows('.$expression.') ) : the_row(); ?>';
			return $php;
		} );

		$this->compiler()->directive( 'acffield', function( $expression ) {
			$php = '<?php if ( get_field('.$expression.') ) : ';
			$php .= 'the_field('.$expression.'); endif; ?>';
			return $php;
		} );

		$this->compiler()->directive( 'acfhas', function( $expression ) {
			$php = '<?php if ( $field = get_field('.$expression.') ) : ?>';
			return $php;
		} );

		$this->compiler()->directive( 'acfsub', function( $expression ) {
			$php = '<?php if ( get_sub_field('.$expression.') ) : ';
			$php .= 'the_sub_field('.$expression.'); endif; ?>';
			return $php;
		} );

		/**
		 * Just some handy directives
		 */

		$this->compiler()->directive( 'var', function( $expression ) {
			$expression = substr( $expression, 1, -1 );
			$segments = explode( ',', $expression, 2 );
			$segments = array_map( 'trim', $segments );

			$key = substr( $segments[0], 1, -1 );
			$value = $segments[1];

			return '<?php $'.$key.' = apply_filters(\'wp_blade_variable_sanitize\', '. $value .'); ?>';
		} );

		do_action( 'wp_blade_add_directive', $this->factory->compiler() );

	}

}
