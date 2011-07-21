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

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."includes".DIRECTORY_SEPARATOR."core.inc");

/**
 * Application Class
 *
 * The core application class.  This class does two main things:
 * 
 * 1. It initializes a few core classes, like the component_manager to handle plugins and extensiosn
 * 2. It works through a bootstrap proces which forms the core of the application lifecycle.
 *
 * ### Events
 * The Application Class defines the following events:
 *
 * #### BOOT_INIT
 * The first part of the boot stage, BOOT_INIT can be used by any component that registers for startup treatment with the 
 * component manager.  BOOT_INIT should be used to initialize components.
 *
 * **Args**:
 * 
 * 1. **Request**: the Request object 
 * 2. **Response**: the Response object
 * 
 * #### BOOT_STARTUP
 * BOOT_STARTUP should be used to do startup tasks that are dependent on all classes being initialized and loaded into the 
 * global namespace.
 *
 * **Args**:
 * 
 * 1. **Request**: the Request object 
 * 2. **Response**: the Response object
 *
 * #### BOOT_PREPARE
 * BOOT_PREPARE should be used to collect information or generally prepare components for processing.
 *
 * **Args**:
 * 
 * 1. **Request**: the Request object 
 * 2. **Response**: the Response object
 *
 * #### BOOT_PREPROCESS
 * BOOT_PREPROCESS can be used to filter and/or adjust functionality before the request is processed.
 *
 * **Args**:
 * 
 * 1. **Request**: the Request object 
 * 2. **Response**: the Response object
 *
 * #### BOOT_PROCESS
 * BOOT_PROCESS should be used to run application logic and render content for display on the client browser.
 *
 * **Args**:
 * 
 * 1. **Request**: the Request object 
 * 2. **Response**: the Response object
 * 
 * #### BOOT_POSTPROCESS
 * BOOT_POSTPROCESS can be used for any cleanup that needs to happen, or filtering of rendered content.
 *
 * **Args**:
 * 
 * 1. **Request**: the Request object 
 * 2. **Response**: the Response object
 * 
 * #### BOOT_CLEANUP
 * BOOT_CLEANUP should be used by components to perform any actions that need to be done before the output is sent to the client.
 *
 * **Args**:
 * 
 * 1. **Request**: the Request object 
 * 2. **Response**: the Response object
 *
 * #### BOOT_SHUTDOWN
 * BOOT_SHUTDOWN signals that the output has been dispatched to the client.  This should be used to save settings or do any 
 * cleanup before the entire system is shutdown.
 *
 * **Args**:
 * 
 * 1. **Request**: the Request object 
 * 2. **Response**: the Response object
 *
 * @package		Cumula
 * @subpackage	Core
 * @author     Seabourne Consulting
 */
final class Application extends EventDispatcher {
	/**
	 * The boot process
	 * 
	 * @var array
	 */
	public $bootProcess = array(BOOT_INIT, 
						  	 	   BOOT_STARTUP, 
										 BOOT_PREPARE,
						   		   BOOT_PREPROCESS, 
						   		   BOOT_PROCESS, 
						   		   BOOT_POSTPROCESS, 
						   		   BOOT_CLEANUP, 
						   		   BOOT_SHUTDOWN);
						   	
	
	protected static $_request;
	protected static $_response;
	
	/**
	 * Constructor
	 * 
	 */
	public function __construct($startupCallback = null, $paths = null) {
		$this->_setupConstants($paths);
		$this->_setupBootstrap();
				
		parent::__construct();
		
		if(is_callable($startupCallback))
			call_user_func($startupCallback);
		
		$this->boot();
	}
	
	private function _setupConstants($paths = array()) {
        // Only define ROOT if it isn't already defined
        defined('ROOT') ||
            define('ROOT', realpath(implode(DIRECTORY_SEPARATOR, array(__DIR__, '..', '..'))));

		if(count($paths) < 1) {
			$core_path	= ROOT . DIRECTORY_SEPARATOR . 'cumula';
			$core_component_path = $core_path . DIRECTORY_SEPARATOR . 'components';
			$contrib_component_path = APPROOT . DIRECTORY_SEPARATOR . 'components';
			$config_path = APPROOT . DIRECTORY_SEPARATOR . 'config';
			$data_path = APPROOT . DIRECTORY_SEPARATOR . 'data';
			$template_path = APPROOT . DIRECTORY_SEPARATOR . 'templates';
		} else {
			extract($paths);
		}

		//TODO: rewrite the part to support passing in arbitrary paths
        defined('APPROOT') ||
            define('APPROOT', ROOT . DIRECTORY_SEPARATOR . 'app');

        defined('COMPROOT') ||
            define('COMPROOT', $core_component_path . DIRECTORY_SEPARATOR);

        defined('CONFIGROOT') ||
            define('CONFIGROOT', $config_path . DIRECTORY_SEPARATOR);
        if (!file_exists(CONFIGROOT)) {
            mkdir(CONFIGROOT, 0775, true);
        }

        defined('DATAROOT') ||
            define('DATAROOT', $data_path . DIRECTORY_SEPARATOR);
        if (!file_exists(DATAROOT)) {
            mkdir(DATAROOT, 0775, true);
        }

        defined('CONTRIBCOMPROOT') ||
            define('CONTRIBCOMPROOT', $contrib_component_path . DIRECTORY_SEPARATOR);
        if (!file_exists(CONTRIBCOMPROOT)) {
            mkdir(CONTRIBCOMPROOT, 0775, true);
        }

        defined('TEMPLATEROOT') ||
            define('TEMPLATEROOT', $template_path . DIRECTORY_SEPARATOR);

		define('CUMULAVERSION', "0.2.0");
	}
	
	/**
	 * Initializes the boot process by adding the individual steps as events
	 */
	private function _setupBootstrap() {
		foreach($this->bootProcess as $step) {
			$this->addEvent($step);
		}
	}
	
	/**
	 * Iterates through the boot process, triggering events for each.
	 */
	public function boot() {
		foreach($this->bootProcess as $step) {
			$this->dispatch($step, array(Request::getInstance(), Response::getInstance()));
		}
	}
	
	public static function __callStatic($name, $args) {
		if(strstr($name, 'get')) {
			$className = str_replace('get', '', $name);
            if (class_exists($className)) {
                return call_user_func(array($className, 'getInstance'));
            }
            return FALSE;
		}
	}
}
