<?php namespace TorMorten\View;

/**
 * Logic Controllers
 */
class Controllers {

	/**
	 * An array containing all controllers
	 *
	 * @var array
	 */
	protected $controllers = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		$filterControllers = apply_filters( 'wp_blade_controllers', array() );
		if ( $filterControllers ) {
			$this->register( $filterControllers );
		}
	}

	/**
	 * Get all controllers
	 *
	 * @return array
	 */
	public function getControllers() {
		return $this->controllers;
	}

	public function getControllersForView( $view ) {
		$data = array();
		foreach ( $this->getControllers() as $controller ) {
			if ( in_array( $view, $controller->getViews() ) ) {
				$data = array_merge($data, $controller->process());
			}
		}
		return $data;
	}

	/**
	 * Register one or multiple controllers
	 *
	 * @param mixed   $controllers An array of strings or a single string
	 * @return void
	 */
	public function register( $controllers ) {
		if ( !is_array( $controllers ) ) {
			$controllers = array( $controllers );
		}

		foreach ( $controllers as $controller ) {
			$this->controllers[] = new $controller;
		}

	}

}
