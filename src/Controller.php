<?php namespace TorMorten\View;

/**
 * Logic Controller
 */
abstract class Controller {

	/**
	 * Defines which views the controller will send data to
	 *
	 * @var array
	 */
	protected $views = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		do_action( 'wp_blade_controller_booted', $this );
	}

	/**
	 * Handles the logic
	 *
	 * @return array Should always return an array of data
	 */
	abstract public function process();

	/**
	 * Get the views
	 *
	 * @return array
	 */
	public function getViews() {
		return $this->views;
	}

}
