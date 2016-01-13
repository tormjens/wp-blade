<?php namespace Tormorten\WPBlade;

/**
 * Uses the Blade templating engine
 */
class Blade {

	/**
	 * Blade engine
	 * @var Blade
	 */
	protected $blade;

	/**
	 * View folder
	 * @var string
	 */
	protected $views;

	/**
	 * View cache
	 * @var string
	 */
	protected $view_cache;

	/**
	 * Cache folder
	 * @var string
	 */
	protected $cache;

	/**
	 * Controllers
	 * @var Controllers
	 */
	public $controller;

	/**
	 * Set up hooks and initialize Blade
	 */
	public function __construct($views, $cache) {

		$this->views = $views;
		$this->cache = $cache;
		$this->view_cache = $this->views . '/cache';
		$this->controller = new Controllers;

		$this->maybeCreateCacheDirectory();

		$this->blade = new \Philo\Blade\Blade($this->views, $this->cache);
		$this->extend();

		// Bind to template include action
		add_action( 'template_include', array( $this, 'blade_include' ) );

		// Listen for Buddypress include action
		add_filter( 'bp_template_include', array( $this, 'blade_include' ));

	}

	/**
	 * Checks whether the cache directory exists, and if not creates it.
	 * @return boolean
	 */
	public function maybeCreateCacheDirectory() {
		if(!is_dir($this->cache)) {
			if(wp_mkdir_p($this->cache)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Include the template
	 * @return string
	 */
	public function blade_include( $template ) {

		if( ! $template )
			return $template; // Noting to do here. Come back later.

		// all templates for our engine must live in the template directory
		if( stripos( $template, get_template_directory() ) === FALSE ) {
			return $template;
		}

		if( $this->viewExpired($template) ) {

			// get the base name
			$file = basename($template);

			// with a blade extension, we have to do this because blade wont recognize the root files without the .blade.php extension
			$blade = str_replace('.php', '.blade.php', $file);
			$blade_file = $this->view_cache . '/' . $blade;

			// get the code
			$code = file_get_contents($template);

			// add the code to the cached blade file
			file_put_contents($blade_file, $code);

			// blade friendly name
			$view = str_replace('.php', '', $file);

			// find a controller
			$controller = $this->getController($view);

			// run the blade code
			echo $this->blade->view()->make('cache.'. $view)->with(['data' => $controller ? $controller->process() : []])->render();

			// halt including
			return '';
		}
		else {

			// get the base name
			$file = basename($template);

			// blade friendly name
			$view = str_replace('.php', '', $file);

			// find a controller
			$controller = $this->getController($view);

			// run the blade code
			echo $this->blade->view()->make('cache.'. $view)->with(['data' => $controller ? $controller->process() : []])->render();

			// halt including
			return '';
		}

		// return an empty string to stop wordpress from including the template when we are doing it
		return $template;
	}

	/**
	 * Check if the view has a controller which can be attached
	 * @param  string $view The view name
	 * @return mixed A controller instance or false
	 */
	protected function getController($view)
	{
		foreach($this->controller->getControllers() as $controller) {
			if(in_array($view, $controller->getViews())) {
				return $controller;
			}
		}
		return false;
	}

	/**
	 * Checks if the view was changed after we stored it for caching
	 * @param  string $path Path to the file
	 * @return boolean
	 */
	protected function viewExpired($path) {

		$file = basename($path);

		$blade = str_replace('.php', '.blade.php', $file);
		$blade_file = $this->view_cache . '/' . $blade;

		if(!file_exists($blade_file)) {
			return true;
		}

		$lastModified = filemtime($path);

        return $lastModified >= filemtime($blade_file);

	}

	/**
	 * Extend blade
	 * @return void
	 */
	protected function extend() {

		// add @acfrepeater
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
			if(!function_exists('get_field')) {
				return $view;
			}
		    $pattern = '/(\s*)@acf\(((\s*)(.+))\)/';
			$replacement = '$1<?php if ( have_rows( $2 ) ) : ';
			$replacement .= 'while ( have_rows( $2 ) ) : the_row(); ?>';

		    return preg_replace($pattern, $replacement, $view);
		});

		// add @acfempty
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
		    return str_replace('@acfempty', '<?php endwhile; ?><?php else: ?>', $view);
		});

		// add @acfend
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
			if(!function_exists('get_field')) {
				return $view;
			}
		    return str_replace('@acfend', '<?php endif; ?>', $view);
		});

		// add @subfield
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
			if(!function_exists('get_field')) {
				return $view;
			}
		    $pattern = '/(\s*)@subfield\(((\s*)(.+))\)/';
			$replacement = '$1<?php if ( get_sub_field( $2 ) ) : ';
			$replacement .= 'the_sub_field($2); endif; ?>';

		    return preg_replace($pattern, $replacement, $view);
		});

		// add @field
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
			if(!function_exists('get_field')) {
				return $view;
			}
		    $pattern = '/(\s*)@field\(((\s*)(.+))\)/';
			$replacement = '$1<?php if ( get_field( $2 ) ) : ';
			$replacement .= 'the_field($2); endif; ?>';

		    return preg_replace($pattern, $replacement, $view);
		});

		// add @hasfield
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
			if(!function_exists('get_field')) {
				return $view;
			}
		    $pattern = '/(\s*)@hasfield\(((\s*)(.+))\)/';
			$replacement = '$1<?php if ( get_field( $2 ) ) : ?>';

		    return preg_replace($pattern, $replacement, $view);
		});

		// add @wpposts
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
		    return str_replace('@wpposts', '<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>', $view);
		});

		// add @wpquery
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
		    $pattern = '/(\s*)@wpquery(\s*\(.*\))/';
			$replacement  = '$1<?php $bladequery = new WP_Query$2; ';
			$replacement .= 'if ( $bladequery->have_posts() ) : ';
			$replacement .= 'while ( $bladequery->have_posts() ) : ';
			$replacement .= '$bladequery->the_post(); ?> ';

			return preg_replace( $pattern, $replacement, $view );
		});

		// add @wpempty
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
		    return str_replace('@wpempty', '<?php endwhile; ?><?php else: ?>', $view);
		});

		// add @wpend
		$this->blade->getCompiler()->extend(function($view, $compiler)
		{
		    return str_replace('@wpend', '<?php endif; wp_reset_postdata(); ?>', $view);
		});

		// add @scripts
		$self = $this;
		$this->blade->getCompiler()->directive('scripts', function($expression) use ($self) {
            return '<?php '. get_class($self) .'::add_scripts('.$expression.'); ?>';
        });

	}

	/**
	 * Adds scripts added via the scripts directive
	 * @param array $scripts
	 */
	public static function add_scripts($scripts) {
		add_action('wp_enqueue_scripts', function() use($scripts) {
			foreach($scripts as $script) {
				$name = basename($script);
				$slug = sanitize_title($name);
				wp_enqueue_script( $slug, get_template_directory_uri() . '/' . $script, ['jquery'] );
			}
		});
	}

}
