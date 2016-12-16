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
		return new static;
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

			// find a controller
			$controller = $this->getController( $view );

			$data = $controller ? $controller->process() : array();

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

			// find a controller
			$controller = $this->getController( $view );

			$data = $controller ? $controller->process() : array();

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
		foreach ( $this->controller->getControllers() as $controller ) {
			if ( in_array( $view, $controller->getViews() ) ) {
				return $controller;
			}
		}
		return false;
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
		$this->factory->compiler()->directive( 'wpposts', function() {
			return '<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>';
		} );

		$this->factory->compiler()->directive( 'wpquery', function( $expression ) {
			$php  = '<?php $bladequery = new WP_Query'.$expression.'; ';
			$php .= 'if ( $bladequery->have_posts() ) : ';
			$php .= 'while ( $bladequery->have_posts() ) : ';
			$php .= '$bladequery->the_post(); ?> ';
			return $php;
		} );

		$this->factory->compiler()->directive( 'wpempty', function() {
			return '<?php endwhile; ?><?php else: ?>';
		} );

		$this->factory->compiler()->directive( 'wpend', function() {
			return '<?php endif; wp_reset_postdata(); ?>';
		} );

		/**
		 * Advanced Custom Field Directives
		 */

		$this->factory->compiler()->directive( 'acfempty', function() {
			if ( function_exists( 'get_field' ) ) {
				return '';
			}
			return '<?php endwhile; ?><?php else: ?>';
		} );

		$this->factory->compiler()->directive( 'acfend', function() {
			if ( function_exists( 'get_field' ) ) {
				return '';
			}
			return '<?php endif; ?>';
		} );

		$this->factory->compiler()->directive( 'acf', function( $expression ) {
			if ( function_exists( 'get_field' ) ) {
				return '';
			}
			$php = '<?php if ( have_rows'.$expression.' ) : ';
			$php .= 'while ( have_rows'.$expression.' ) : the_row(); ?>';
			return $php;
		} );

		$this->factory->compiler()->directive( 'acffield', function( $expression ) {
			if ( function_exists( 'get_field' ) ) {
				return '';
			}
			$php = '<?php if ( get_field'.$expression.' ) : ';
			$php .= 'the_field'.$expression.'; endif; ?>';
			return $php;
		} );

		$this->factory->compiler()->directive( 'acfhas', function( $expression ) {
			if ( function_exists( 'get_field' ) ) {
				return '';
			}
			$php = '<?php if ( $field = get_field'.$expression.' ) : ';
			return $php;
		} );

		$this->factory->compiler()->directive( 'acfsub', function( $expression ) {
			if ( function_exists( 'get_field' ) ) {
				return '';
			}
			$php = '<?php if ( get_sub_field'.$expression.' ) : ';
			$php .= 'the_sub_field'.$expression.'; endif; ?>';
			return $php;
		} );

		/**
		 * Just some handy directives
		 */

		$this->factory->compiler()->directive( 'var', function( $expression ) {
			$expression = substr( $expression, 1, -1 );
			$segments = explode( ',', $expression, 2 );
			$segments = array_map( 'trim', $segments );

			$key = substr( $segments[0], 1, -1 );
			$value = $segments[1];

			return '<?php $'.$key.' = apply_filters(\'wp_blade_variable_sanitize\', '. $value .'); ?>';
		} );

	}

}
