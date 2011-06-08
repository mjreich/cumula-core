<?php
/**
 * Cumula
 *
 * Cumula — framework for the cloud.
 *
 * @package    Cumula
 * @version    0.1.0
 * @author     Seabourne Consulting
 * @license    MIT License
 * @copyright  2011 Seabourne Consulting
 * @link       http://cumula.org
 */

/**
 * BaseMVCController Class
 *
 * The MVC Controller contains all application code for an MVC style component.
 *
 * @package		Cumula
 * @subpackage	Core
 * @author     Seabourne Consulting
 */
abstract class BaseMVCController extends EventDispatcher {
	public $component;
	
	protected $_before_filters = array();
	protected $_after_filters = array();
	protected $_template = false;
	protected $_data;
	
	/**
	 * The constructor.  Initializes the MVCContentBlock
	 * 
	 * @param $component
	 * @return unknown_type
	 */
	public function __construct($component) {
		parent::__construct();
		$this->component = &$component;
		$this->startup();
		$this->_beforeFilter('_setTemplate');
		$this->_data = array();
	}
	
	protected function _setTemplate() {
		if ($this->_template) {
			Application::getTemplater()->setTemplateDir($App->rootDirectory.'/../templates/'.$this->_template.'/');
		}	
	}
	
	public function useTemplate($template) {
		$this->_template = $template;
	}
	
	/**
	 * Add a function to be run before the route handler is called.
	 * 
	 * @param $function The name of the function to be called
	 * @return unknown_type
	 */
	protected function _beforeFilter($function) {
		$this->_before_filters[] = $function;
	}
	
	/**
	 * Add a function to be run after the route handler is called.
	 * 
	 * @param $function  The name of the function to be called
	 * @return unknown_type
	 */
	protected function _afterFilter($function) {
		$this->_after_filters[] = $function;
	}
	
	/**
	 * Helper function for easily registering a route with the router.
	 * 
	 * @param $route
	 * @param $method
	 * @return unknown_type
	 */
	public function registerRoute($route, $method = null) {
		if(!$method) {
			$parts = explode('/', $route);
			$last = $parts[count($parts)-1];
			$method = $last;
		}
		$this->component->registerRoute($route, &$this, "____".$method);
	}

	/**
	 * Magic method to handle incoming route requests.  
	 * 
	 * @param $name
	 * @param $arguments
	 * @return unknown_type
	 */
	public function __call($name, $arguments) {
		$func = $this->_parseFunc($name);
		
		foreach($this->_before_filters as $filter) {
			call_user_func_array(array(&$this, $filter), $arguments);
		}
		
		if(method_exists(static::_getThis(), $func))
			$output = call_user_func_array(array(static::_getThis(), $func), $arguments);
		else {
			trigger_error("Call to undefined method ".$func, E_USER_ERROR);
		}
		
		
		foreach($this->_after_filters as $filter) {
			call_user_func_array(array(&$this, $filter), $arguments);
		}
		
		return $output;
	}
	
	/**
	 * Parses the passed magic method function into the final call.
	 * 
	 * @param $function
	 * @return unknown_type
	 */
	protected function _parseFunc($function) {
		return str_replace('____', '', $function);
	}

	/**
	 * Renders the view template file for the function.
	 * 
	 * @return unknown_type
	 */
	public function render() {
		$bt = debug_backtrace(false); //TODO: See if there's a better way to do this than debug backtrace.
		$caller = $bt[1];
		$view_dir = $this->component->config->getConfigValue('views_directory', static::_getThis()->component->rootDirectory().'/views/'.lcfirst(str_replace('Controller', '', get_called_class())));
		$file_name = $view_dir.'/'.$caller['function'].'.tpl.php';
		$args = func_get_args();
		if(count($args))	
			extract($args[0], EXTR_OVERWRITE);
		ob_start();
		include $file_name;
		$contents = ob_get_contents();
		ob_end_clean();
		$this->_send_render($contents);
	}
	
	/**
	 * Creates a new content block to contain $content, and adds it to the output queue.
	 * 
	 * @param $content
	 * @return unknown_type
	 */
	protected function _send_render($content) {
		$block = new ContentBlock();
		$block->content = $content;
		$block->data['variable_name'] = 'content';
		
		$this->component->addOutputBlock($block);
	}
	
	/**
	 * Helper function for redirecting client to a new location.
	 * 
	 * @param $url The url to redirect to.
	 * @return unknown_type
	 */
	protected function redirectTo($url) {
		if(substr($url, 0, 1) == '/') {
			$config = Application::getSystemConfig();
			$base_path = $config->getValue(SETTING_DEFAULT_BASE_PATH, '');
			$url = $base_path.$url;
		}
		$this->component->redirectTo($url);
	}
	
	/**
	 * returns a url that includes the system base path
	 * 
	 * @param $url
	 * @return unknown_type
	 */
	public function linkTo($url) {
		$session = Application::getSystemConfig();
		$base = $session->getValue(SETTING_DEFAULT_BASE_PATH);
		return ($base == '/') ? $url : $base.$url;
	}
	
	/**
	 * Helper function for returning the final static implementation of the class, using Late Static Bindings.
	 * 
	 * @return unknown_type
	 */
	protected function _getThis() {
		return $this;
	}
	
	public function __set($name, $value) {
		$this->_data[$name] = $value;
	}
	
	public function __get($name) {
		if(isset($this->_data[$name]))
			return $this->_data[$name];
	}
	
	public function __isset($name) {
		return isset($this->_data[$name]);
	}
	
	public function __unset($name) {
		if(isset($this->_data[$name]))
			unset($this->_data[$name]);
	}

	public function getInstanceVars() {
		return $this->_data;
	}
}