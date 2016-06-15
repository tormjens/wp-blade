<?php namespace TorMorten\View;

/**
 * Logic Controllers
 */
class Controllers {

	/**
	 * An array containing all controllers
	 * @var array
	 */
	protected $controllers = [];

	/**
	 * Constructor
	 */
	public function __construct() {}

	/**
	 * Get all controllers
	 * @return array
	 */
	public function getControllers() {
		return $this->controllers;
	}

	/**
	 * Register one or multiple controllers
	 * @param  mixed $controllers An array of strings or a single string
	 * @return void
	 */
	public function register($controllers) {
		if(!is_array($controllers)) {
			$controllers = [$controllers];
		}

		foreach($controllers as $controller) {
			$this->controllers[] = new $controller;
		}

	}

}
