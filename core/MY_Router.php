<?php
/**
 * MY_Router
 * Insert description here
 *
 * @package CodeIgniter / Orange
 * @author Don Myers
 * @copyright 2018
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 * @version 2.0
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */
class MY_Router extends CI_Router {

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $clean_controller = null;

	/**
	 * track if the combined cached configuration has been loaded
	 *
	 * @var boolean
	 */
	protected $clean_method = null;

	/**
	 * _set_default_controller
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected function _set_default_controller() {
		if (empty($this->default_controller)) {
			throw new Exception('Unable to determine what should be displayed. A default route has not been specified in the routing file.');
		}

		$segments = $this->controller_method($this->default_controller);

		if (PHP_SAPI === 'cli' or defined('STDIN')) {
			$segments[1] = substr($segments[1], 0, -6).'CliAction';
		}

		$this->set_class($segments[0]);
		$this->set_method($segments[1]);
		$this->uri->rsegments = [1=>$segments[0],2=>$segments[1]];
	}

	/**
	 * _validate_request
	 * Insert description here
	 *
	 * @param $segments
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function _validate_request($segments) {
		$uri = implode('/',str_replace('-','_',$segments));

		foreach (orange_autoload_files::paths('controllers') as $key=>$rec) {
			if (preg_match('#^'.$key.'$#', strtolower($uri), $matches)) {
				$segs = explode('/',trim($matches[1],'/'));
				$this->directory = $rec['directory'];
				$this->clean_controller = $rec['clean_controller'];
				$this->clean_method = (empty($segs[0])) ? 'index' : strtolower($segs[0]);
				$segments = [];
				$segments[0] = $this->clean_controller.'Controller';
				$segments[1] = $this->clean_method.$this->fetch_request_method(true).'Action';
				array_shift($segs);

				foreach ($segs as $uu) {
					$segments[] = $uu;
				}

				return $segments;
			}
		}

		$this->directory = '';

		log_message('debug', 'MY_Router::_validate_request::404');

		return $this->controller_method($this->routes['404_override']);
	}

	/**
	 * fetch_request_method
	 * Insert description here
	 *
	 * @param $filter_get
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function fetch_request_method($filter_get=false) {
		$method = isset($_SERVER['REQUEST_METHOD']) ? ucfirst(strtolower($_SERVER['REQUEST_METHOD'])) : 'Cli';

		return ($filter_get && $method == 'Get') ? '' : $method;
	}

	/**
	 * fetch_directory
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function fetch_directory() {
		return substr($this->directory, strpos($this->directory,'/controllers/') + 13);
	}

	/**
	 * fetch_class
	 * Insert description here
	 *
	 * @param $clean
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function fetch_class($clean=false) {
		return ($clean) ? $this->clean_controller : $this->class;
	}

	/**
	 * fetch_method
	 * Insert description here
	 *
	 * @param $clean
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	public function fetch_method($clean=false) {
		return ($clean) ? $this->clean_method : $this->method;
	}

	/**
	 * controller_method
	 * Insert description here
	 *
	 * @param $input
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @throws
	 * @example
	 */
	protected function controller_method($input) {
		$segments[0] = $input;
		$segments[1] = 'index';

		if (strpos($input, '/') !== false) {
			$segments = explode('/', $input, 2);
		}

		$this->clean_controller = ucfirst(strtolower($segments[0]));
		$this->clean_method = strtolower($segments[1]);
		$segments[0] .= 'Controller';
		$segments[1] .= 'Action';

		return $segments;
	}


	/**
	 * Set route mapping
	 *
	 * Determines what should be served based on the URI request,
	 * as well as any "routes" that have been set in the routing config file.
	 *
	 * @return	void
	 */
	protected function _set_routing() {
		$route = load_config('routes','route');

		// Validate & get reserved routes
		if (isset($route) && is_array($route)) {
			isset($route['default_controller']) && $this->default_controller = $route['default_controller'];
			isset($route['translate_uri_dashes']) && $this->translate_uri_dashes = $route['translate_uri_dashes'];
			unset($route['default_controller'], $route['translate_uri_dashes']);

			$this->routes = $route;
		}

		// Is there anything to parse?
		if ($this->uri->uri_string !== '') {
			$this->_parse_routes();
		} else {
			$this->_set_default_controller();

			$this->_parse_routes(true);
		}
	}

	/**
	 * Parse Routes
	 *
	 * Matches any routes that may exist in the config/routes.php file
	 * against the URI to determine if the class/method need to be remapped.
	 *
	 * @return	void
	 */
	protected function _parse_routes($skip_set=false) {
		// Turn the segment array into a URI string
		$uri = implode('/', $this->uri->segments);

		// Get HTTP verb
		$http_verb = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : 'cli';

		// Loop through the route array looking for wildcards
		foreach ($this->routes as $key => $val) 	{
			// Check if route format is using HTTP verbs
			if (is_array($val)) {
				$val = array_change_key_case($val, CASE_LOWER);
				
				if (isset($val[$http_verb])) {
					$val = $val[$http_verb];
				} else {
					continue;
				}
			}

			// Convert wildcards to RegEx
			$key = str_replace(array(':any', ':num'), array('[^/]+', '[0-9]+'), $key);

			// Does the RegEx match?
			if (preg_match('#^'.$key.'$#', $uri, $matches)) 	{
				// Are we using callbacks to process back-references?
				if (!is_string($val) && is_callable($val)) {
					// Remove the original string from the matches array.
					array_shift($matches);

					// Execute the callback using the values in matches as its parameters.
					$val = call_user_func_array($val, $matches);
				} elseif (strpos($val, '$') !== FALSE && strpos($key, '(') !== FALSE) 	{
					// Are we using the default routing method for back-references?
					$val = preg_replace('#^'.$key.'$#', $val, $uri);
				}

				if (!$skip_set) {
					$this->_set_request(explode('/', $val));
				}
				
				return;
			}
		}

		// If we got this far it means we didn't encounter a
		// matching route so we'll set the site default route
		$this->_set_request(array_values($this->uri->segments));
	}

} /* end class */