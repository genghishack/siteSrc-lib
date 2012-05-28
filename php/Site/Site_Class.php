<?php
/*
 * $Id: Site_Class.php 70 2012-01-03 13:59:05Z genghishack $
 *
 * @author Chris Wade <chrisw@intermundonet.com>
 *
 * This is a core file of the site/src framework.  It's essentially a bootstrap file.  Configuration of a site
 * implementation is done by extending this class within the project's site/src/php directory, in the file Site.php.
 *
 * Versions
 * 1.0: First implementation
 * 1.1: Organized and documented, integration with Smarty, performance debugging added, improved registerPage
 */

// If a user navigates directly to this file, redirect them to the index page.
if (strpos($_SERVER['REQUEST_URI'], 'Site_Class.php')) {
	header ("Location: /index.php");
}

abstract class Site_Class
{
	/******************************************************************
	 * These variables may (and in some cases, should) be overwritten
	 * in the child Site.php class specific to your project.
	 ******************************************************************/

	protected $strTitle      = 'MySite'; // Title of your site - goes between the <title> tags
	protected $strTimeZone   = 'America/Denver'; // TODO: Set timezone according to GeoIP

	protected $blnDebug         = false;
	protected $blnShowComments  = false;
	protected $blnIsFacebookApp = false;
	protected $blnNoCache       = false;

	// Only set these two vars if you don't want their values detected automatically.
	protected $strBasePath = ''; // Path to your site's main directory
	protected $strBaseUrl  = ''; // URL of your site

	/******************************
	 * End user-defined variables
	 ******************************/

	public $arrPerformance = array();

	protected $strProtocol     = 'http';
	protected $strLandingStamp = '';

	protected $blnIsModule = false;

	protected $Smarty;
	protected $Page;
	protected $FbApp;

	/**
	 * Constructor
	 *
	 * Initializes some variables to their default or user settings and
	 * gets all of the required classes.
	 *
	 * @return unknown_type
	 */
	public function __construct()
	{
		$this->registerPerformance('<b>Total</b>');

		if (!empty($_GET['debug'])) {
			$this->blnDebug = true;
		}

		date_default_timezone_set($this->strTimeZone);

		// Create a landing timestamp for MySQL
		$this->strLandingStamp = date('Y-m-d H:i:s');

		// Is the "Site" being called a module or a full site?
		$this->blnIsModule = (strpos($_SERVER['REQUEST_URI'], 'src/module')) ? true : false;

		if (empty($this->strBasePath)) {
			$this->strBasePath = $_SERVER['DOCUMENT_ROOT'];
		}

		if (strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https')) {
			$this->strProtocol = 'https';
		}

		if (empty($this->strBaseUrl)) {
			$this->strBaseUrl = "{$this->strProtocol}://{$_SERVER['SERVER_NAME']}";
		}

		$this->getRequiredFiles();
	}

	/**
	 * getRequiredFiles
	 *
	 * This is the method that will most typically be extended in Site.php.
	 * If additional files are needed that are dependent on the files included here,
	 * override this method, call parent::getRequiredFiles() and then add any
	 * additional includes.
	 *
	 * If you want to override any of the inclusions of versioned library files done here,
	 * you can do so by requiring the version of the class that you want prior to beginning
	 * the class declaration of 'Site' in Site.php.
	 *
	 * @return unknown_type
	 */
	protected function getRequiredFiles()
	{
		// This order in the path makes php check for its includes in the site/src directory, then lib if that fails
		set_include_path('.' 
		                  . PATH_SEPARATOR . "{$this->strBasePath}/src" 
		                  . PATH_SEPARATOR . '/home/genghishack/lib'
		                  . PATH_SEPARATOR . get_include_path()
		);

		if (!class_exists('Page_Class', false))
		{
			require_once ('php/Page/Page_Class.php');
		}
		if (!class_exists('Module_Class', false))
		{
			require_once ('php/Module/Module_Class.php');
		}
		if (!class_exists('Util_Class', false))
		{
			require_once ('php/Util/Util_Class.php');
		}
		if (!class_exists('Smarty', false))
		{
			require_once ('vendor/smarty/Smarty-3.0.8/libs/Smarty.class.php');
		}
		
		require_once ('vendor/php-activerecord/php-activerecord-1.0/ActiveRecord.php');
		
		require_once ('vendor/techpatterns/php_browser_detection.php');

		require_once ('php/Page.php');
		require_once ('php/Module.php');
		require_once ('php/Util.php');

		if ($this->isFacebookApp())
		{
			require_once ('vendor/facebook/facebook-php-sdk-3.0.1/src/facebook.php');

			require_once ('php/FbApp/FbApp_Class.php');
			require_once ('php/FbApp.php');
		}
	}

	/**
	 * init
	 *
	 * This method is necessary to the framework and must be called from outside the
	 * Site object, after it's instantiated.  The reason for this is that the Site
	 * object needs to be available to the other objects that are registered in this step.
	 *
	 * @return unknown_type
	 */
	public function init()
	{
		$this->registerActiveRecord();
		$this->registerSmarty();
		$this->registerPage();
		if ($this->isFacebookApp()) {
			$this->registerFbApp();
		}
	}

	// TODO: This function should also perhaps follow a default inclusion
	// pattern like that used in getRequiredFiles
	protected function registerPage()
	{
		$this->registerPerformance('Site registerPage()');

		$this->Page = $GLOBALS['Page'] = new Page();

		if (!$this->isModule())
		{
			$this->Page->registerCssFile('http://lib.genghishack.com/css/Base/Base_1.0.css');                     // global css common to all sites
			$this->Page->registerCssFile('/src/css/site.css');                                    // site-specific css

			$this->Page->registerJsFile ('http://lib.genghishack.com/vendor/jquery/jquery-1.7.1.min.js', '', false);                                      // jQuery
//			$this->Page->registerJsFile ('http://lib.genghishack.com/vendor/jquery/plugins/validate/jquery.validate.js');                                                  // jQuery form validation plugin
			$this->Page->registerJsFile ('http://lib.genghishack.com/vendor/jquery/jquery-tmpl.min.js');
			$this->Page->registerJsFile ('http://lib.genghishack.com/vendor/jquery/jquery-ui-1.8.14.custom/js/jquery-ui-1.8.14.custom.min.js');           // jQuery UI
			$this->Page->registerCssFile('http://lib.genghishack.com/vendor/jquery/jquery-ui-1.8.14.custom/css/smoothness/jquery-ui-1.8.14.custom.css'); // jQuery UI CSS
			$this->Page->registerJsFile ('http://lib.genghishack.com/vendor/jquery/blueimp-jQuery-File-Upload-598194f/jquery.iframe-transport.js');
			$this->Page->registerJsFile ('http://lib.genghishack.com/vendor/jquery/blueimp-jQuery-File-Upload-598194f/jquery.fileupload.js');
			$this->Page->registerJsFile ('http://lib.genghishack.com/vendor/jquery/blueimp-jQuery-File-Upload-598194f/jquery.fileupload-ui.js');
			$this->Page->registerCssFile('http://lib.genghishack.com/vendor/jquery/blueimp-jQuery-File-Upload-598194f/jquery.fileupload-ui.css');
			
			$this->Page->registerJsFile('http://lib.genghishack.com/js/Extensions/Extensions_1.0.js');            // useful JS extensions
			$this->Page->registerJsFile('/src/js/extensions.js');                                 // This project's general extensions to JS

			$this->Page->registerJsFile('http://lib.genghishack.com/js/Site/Site_1.1.js');                        // client-side Site_Class
			$this->Page->registerJsFile('/src/js/site.js');                                       // This project's extensions to the Site_Class

			$this->Page->registerJsFile('http://lib.genghishack.com/js/AjaxModule/ajaxModule_Class.js', '', false); // ajax module loader
			$this->Page->registerJsFile('/src/js/ajaxModule.js');                                   // This project's extensions to ajaxModule

			// TODO: Set this google analytics pixel in Page, using Google account number set in Site
//			$this->Page->setGoogleAnalyticsPixel(array(
//				 'account'       => 'UA-21949718-1'
//				,'domainName'    => 'none'
//				,'allowLinker'   => true
//				,'trackPageview' => true
//			));
		}

		$this->registerPerformance('Site registerPage()', 'stop');
	}

	private function registerSmarty()
	{
		$this->registerPerformance('Site registerSmarty()');

		$this->Smarty = $GLOBALS['Smarty'] = new Smarty();

		$this->Smarty->setTemplateDir("$this->strBasePath");
		$this->Smarty->setCompileDir("$this->strBasePath/src/smarty/templates_c");
		$this->Smarty->setCacheDir("$this->strBasePath/src/smarty/cache");
		$this->Smarty->setConfigDir("$this->strBasePath/src/smarty/configs");

		$this->Smarty->assign('Site', array(
			'baseUrl' => $this->strBaseUrl
		));

		$this->registerPerformance('Site registerSmarty()', 'stop');

	}

	protected function registerActiveRecord()
	{
		$this->registerPerformance('Site registerActiveRecord()');
		
		$cfg = ActiveRecord\Config::instance();
		$cfg->set_model_directory("{$this->getBasePath()}/src/models");
		$cfg->set_connections(array(
			'development' => "mysql://{$this->dbUser}:{$this->dbPass}@{$this->dbHost}:{$this->dbPort}/{$this->dbName}"
		));

		$this->registerPerformance('Site registerActiveRecord()', 'stop');
	}
	
	protected function registerFbApp()
	{
		$this->registerPerformance('Site registerFbApp()');

		global $fbApp;

		if (class_exists('FbApp'))
		{
			$fbApp = new FbApp();
		}

		$this->registerPerformance('Site registerFbApp()', 'stop');
	}

	public function registerPerformance($label, $startStop='start')
	{
		if (!isset($this->arrPerformance[$label])) {
			$this->arrPerformance[$label] = array();
		}
		$this->arrPerformance[$label][$startStop] = microtime();
	}

	public function calculatePerformance()
	{
		foreach ($this->arrPerformance as &$arrItem)
		{
			$arrItem['total'] = '--';

			if (!empty($arrItem['start']) && !empty($arrItem['stop']))
			{
				list($uStart, $sStart) = explode(' ', $arrItem['start']);
				$arrItem['start'] = bcadd($uStart, $sStart, 7);

				list($uStop, $sStop) = explode(' ', $arrItem['stop']);
				$arrItem['stop'] = bcadd($uStop, $sStop, 7);

				$arrItem['total'] = bcsub($arrItem['stop'], $arrItem['start'], 7);
			}

			if (empty($arrItem['start'])) {
				$arrItem['start'] = '--';
			} else {
				$arrItem['start'] = date('H:i:s', $arrItem['start']) . " (+$uStart)";
			}

			if (empty($arrItem['stop'])) {
				$arrItem['stop'] = '--';
			} else {
				$arrItem['stop'] = date('H:i:s', $arrItem['stop']) . " (+$uStop)";
			}
		}
	}


	/******************
	 * Simple getters
	 ******************/

	public function getBrowserName()
	{
		return browser_detection('browser_name');
	}

	public function getBrowserVersion()
	{
		return browser_detection('browser_name') . floor(browser_detection('browser_math_number'));
	}

	public function getBaseUrl()
	{
		return $this->strBaseUrl;
	}

	public function getBasePath()
	{
		return $this->strBasePath;
	}

	public function getTimeZone()
	{
		return $this->strTimeZone;
	}

	public function getLandingStamp()
	{
		return $this->strLandingStamp;
	}

	public function getTitle()
	{
		return $this->strTitle;
	}

	public function getProtocol()
	{
		return $this->strProtocol;
	}

	public function getDebug()
	{
		return $this->blnDebug;
	}

	public function getShowComments()
	{
		return $this->blnShowComments;
	}

	public function getNoCache()
	{
		return $this->blnNoCache;
	}

	public function isFacebookApp()
	{
		return $this->blnIsFacebookApp;
	}

	public function isModule()
	{
		return $this->blnIsModule;
	}
}
?>
