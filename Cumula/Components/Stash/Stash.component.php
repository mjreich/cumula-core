<?php
namespace Cumula\Components\Stash;
use \Cumula\Base\Component as BaseComponent;
use \Cumula\Autoloader as Autoloader;
use \Cumula\Components\Cache\Cache as Cache;

/**
 * Stash Component
 * @package Cumula
 * @author Craig Gardner <craig@seabourneconsulting.com>
 **/
class Stash extends BaseComponent 
{
	/**
	 * Properties
	 */
	/**
	 * Table of stashes and the callbacks to call to get the stash
	 * @var array
	 **/
	private $stashTable = array();

	/**
	 * Public Methods
	 */
	/**
	 * Component startup Method
	 * @param void
	 * @return void
	 **/
	public function startup() 
	{
		A('Router')->bind('GatherRoutes', array($this, 'provideRoutes'));
		$this->bind('stash_get_map', 
			function($event, $dispatcher) { 
				$dispatcher->addStash('mystash', 'http://www.fcc.gov/api/content.jsonp?callback=jsonCallback_c4bd2a7021615615285e77df2d0edbb7&type=edoc&terms%5B%5D=12&limit=5&fields=all');
			}
		);
	} // end function startup

	/**
	 * Provide the routes to the router
	 * @param string $event Name of the event dispatched
	 * @param Router $dispatcher Router Class that dispatched the event
	 * @return void
	 **/
	public function provideRoutes($event, $dispatcher) 
	{
		return array(
			'/stash/$stash' => array(&$this, 'handleStash'),
			'/stash' => array(&$this, 'mainStash'),
		);
	} // end function provideRoutes

	/**
	 * Handle the main stash url
	 * @param string $route Route of the request
	 * @param \Cumula\Router $router Router handling the request
	 * @param array $args Array of arguments
	 * @param \Cumula\Request $request Request Object
	 * @param \Cumula\Response $response Response Object
	 * @return void
	 **/
	public function mainStash($route, $router, $args, $request, $response) 
	{
		if (isset($args['url'])) 
		{
			$output = $this->getUrlStash($args['url']);
			if ($output !== FALSE) 
			{
				$headers = isset($output['headers']) ? $output['headers'] : array('Content-Type: text/plain');
				$this->sendResponse($output['content'], $headers, 200);
				return TRUE;
			}
		}
		$response->send404();
		return FALSE;
	} // end function mainStash

	/**
	 * Get a stash for a URL
	 * @param string $url URL to fetch a stash for
	 * @return void
	 **/
	public function getUrlStash($url) 
	{ 
		if ($this->is_url($url) == FALSE)
		{
			return FALSE;
		}
		
		$stashName = md5($url);
		if (($cache = Cache::get($stashName, 'stash')) === FALSE) {
			// This will need to be replaced with the Cumula HTTP Client
			$ch = curl_init($url);

			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => TRUE,
			));
			
			$content = curl_exec($ch);
			$header = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

			if (strlen($content) > 0 && strlen($header) > 0)
			{
				$cache = array(
					'content' => $content,
					'headers' => array(
						'Content-Type' => $header,
						'Stash-From-Cache' => 'no',
					)
				);

				Cache::set($stashName, $cache, array('bin' => 'stash'));
			}
		}
		else {
			$cache['headers']['Stash-From-Cache'] = 'yes';
		}

		return $cache;
	} // end function getUrlStash

	/**
	 * Handle the stash 
	 * @param void
	 * @return void
	 **/
	public function handleStash($route, $router, $args, $request, $response) 
	{
		$this->dispatch('stash_get_map');
		// Check the stash and execute callback if the stash is available
		if (($stash = $this->stashExists($args['stash'])) !== FALSE) {
			$output = $this->getUrlStash($stash['url']);
			$headers = isset($output['headers']) ? $output['headers'] : array('Content-Type: text/plain');
			$this->sendResponse($output['content'], $headers, 200);
			return TRUE;
		}
		$response->send404();
		return FALSE;
	} // end function handleStash

	/**
	 * Meta information method for the module
	 * @param void
	 * @return array
	 **/
	public static function getInfo() 
	{
		return array(
			'name' => 'Stash Component',
			'description' => 'Provide a proxy for caching outbound requests',
			'version' => '0.1',
			'dependencies' => array('Cumula\\Components\\Cache'),
		);
	} // end function getInfo

	/**
	 * Figure out whether the stash exists or not
	 * @param string $stashName Name of the stash to check
	 * @return mixed Returns FALSE if the stash does not exist and the callback for the stash if it does exist
	 **/
	public function stashExists($stashName) 
	{
		$stashTable = $this->getStashTable();
		return isset($stashTable[$stashName]) ? $stashTable[$stashName] : FALSE;
	} // end function stashExists

	/**
	 * Add a stash to the stash table
	 * @param string $stashName Name of the stash
	 * @param string $url URL to fetch
	 * @param integer $expire When to expire the stash 
	 * @return void
	 **/
	public function addStash($stashName, $url, $expire = NULL) 
	{

		if (is_null($expire))
		{
			$expire = strtotime('+1 minute');
		}

		$stashTable = $this->getStashTable();
		$stashTable[$stashName] = array(
			'url' => $url,
			'expire' => $expire
		);
		$this->setStashTable($stashTable);
	} // end function addStash

	/**
	 * Helper Functions
	 */
	/**
	 * Determine whether or not the value passed is a URL
	 * Original function found at http://crunchbang.org/wiki/php-function-isurl/
	 * @param string $url string being checked
	 * @return boolean True if the string is a valid URL
	 */
	function is_url($url)
	{
		$url = substr($url, -1) == "/" ? substr($url, 0, -1) : $url;
		if (!$url || $url == "")
		{
			return FALSE;
		}

		if (!($parts = @parse_url($url))) 
		{
			return FALSE;
		}
		else 
		{
			if (!isset($parts['scheme']) || ($parts['scheme'] != "http" && $parts['scheme'] != "https" && $parts['scheme'] != "ftp" && $parts['scheme'] != "gopher" )) {
			 	return FALSE;
			}
			else if (!isset($parts['host']) || !preg_match( "@^[0-9a-zA-Z]([-.]?[0-9a-zA-Z])*.[a-zA-Z]{2,4}$@", $parts['host'], $regs)) 
			{
				return FALSE;
			}
			else if (isset($parts['user']) && !preg_match( "@^([0-9a-zA-Z-]|[_])*$@", $parts['user'], $regs)) 
			{
				return FALSE;
			}
			else if (isset($parts['pass']) && !preg_match( "@^([0-9a-zA-Z-]|[_])*$@", $parts['pass'], $regs)) 
			{
				return FALSE;
			}
			else if (isset($parts['path']) && !preg_match( "@^[0-9a-zA-Z/_.\@~-]*$@", $parts['path'], $regs)) 
			{
				return FALSE;
			}
			else if (isset($parts['query']) && !preg_match( "@^[0-9a-zA-Z?&=%#,_]*$@", $parts['query'], $regs)) 
			{
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * Send the response
	 * @param void
	 * @return void
	 **/
	public function sendResponse($output, $headers, $code) 
	{
		A('Response')->sendRawResponse($headers, $output, $code);
		A('Application')->unbind('BootPostprocess', array(A('Templater'), 'postProcessRender'));
	} // end function sendResponse

	/**
	 * Getters and Setters
	 */
	/**
	 * Getter for $this->stashTable = array()
	 * @param void
	 * @return array
	 * @author Craig Gardner <craig@seabourneconsulting.com>
	 **/
	protected function getStashTable() 
	{
		return $this->stashTable;
	} // end function getStashTable()
	
	/**
	 * Setter for $this->stashTable = array()
	 * @param array
	 * @return void
	 * @author Craig Gardner <craig@seabourneconsulting.com>
	 **/
	protected function setStashTable($arg0) 
	{
		$this->stashTable = $arg0;
		return $this;
	} // end function setStashTable()
} 
