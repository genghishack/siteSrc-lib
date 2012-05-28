<?php
/*
 * $Id: Page_Class.php 70 2012-01-03 13:59:05Z genghishack $
 *
 * @author Chris Wade <chrisw@intermundomedia.com>
 * @author Simon Laug <simon@intermundomedia.com>
 *
 * This is the backbone of the site/src framework.  It's where the page being sent to the browser actually gets rendered.
 * There are functions here to keep it all organized, so that when Page->render is finally called, all of the information is in place,
 * and can be gathered up and sent to the browser in the proper order.
 *
 * Title, Meta Tags and CSS are rendered in the head, with each module's css file in descending heirarchical order.
 * CSS Files are followed by individual lines of css meant to override anything in those files.
 * JS files are rendered likewise in the footer, with individual lines of JS Code either preceding or following the files.
 *
 * 1.2: Adding features from TycoonU - browser detection, revised constructor, gzip, inline css and js replacing individual file http requests
 * 2.0: Integration of framework with Smarty Templates, phase I
 */
abstract class Page_Class
{
	// Some default values - can be overwritten using set methods
	protected $strDocType = '<!DOCTYPE HTML>'; // HTML 5

	// TODO: make sure this doctype and xmlns match
	protected $strHtmlTag = '<html xmlns="http://www.w3.org/1999/xhtml"
	                               xmlns:og="http://opengraphprotocol.org/schema/"
	                               xmlns:fb="http://www.facebook.com/2008/fbml">';
	protected $strTitle = '';
	protected $strContentType = 'text/html; charset=utf-8';

	// Establish some arrays to hold different types of content
	protected $arrMetaTags = array();
	protected $arrJsLeading = array();
	protected $arrJsFiles = array();
	protected $arrJsTrailing = array();
	protected $arrNoScript = array();
	protected $arrCssFiles = array();
	protected $arrCssTrailing = array();
	protected $arrBodyContent = array();
	protected $arrDebugVars = array();

	protected $blnNoCache = false;

	public $strProtocol;

	/**
	 * Constructor
	 * @return unknown_type
	 */
	public function __construct()
	{
		$arrObjectsToRegister = array(
			 'Site'
			,'Smarty'
		);

		foreach ($arrObjectsToRegister as $strObjectName)
		{
			$this->$strObjectName = (isset($GLOBALS[$strObjectName])) ? $GLOBALS[$strObjectName] : null;
		}

		$this->Site->registerPerformance('Page __construct()');

		$this->blnNoCache = $this->Site->getNoCache();

		$this->strProtocol = ( isset($_SERVER['HTTPS']) && ! empty($_SERVER['HTTPS']) ) ? "https" : "http";

		$this->Smarty->assign('httpProtocol', $this->strProtocol);

		if (!$this->Site->isModule())
		{
			$this->setDocType('<!DOCTYPE HTML>'); // HTML 5
			$this->setHtmlTag('<html>');

			// This gets rid of the image hover toolbar in IE.
			$this->registerMetaTag(
				array(
					'http-equiv' => 'imagetoolbar',
					'content'    => 'no'
				)
			);

			// Instantiates the Site Class on the client side.
			$this->registerJsTrailing("
				var Site = new Site_Class();
				    Site.setBaseUrl('{$this->Site->getBaseUrl()}');
				    Site.setBrowserName('{$this->Site->getBrowserName()}');
				    Site.setBrowserVersion('{$this->Site->getBrowserVersion()}');
				    Site.setLandingStamp('{$this->Site->getLandingStamp()}');
			");
			
			if ($this->Site->getDebug())
			{
				$this->registerJsFile('http://lib/vendor/techpatterns/your_computer_info.js');
			}
			
			$this->registerJsFile('http://lib/vendor/techpatterns/javascript_cookies.js');
		}

		$this->Site->registerPerformance('Page __construct()', 'stop');
	}

	public function setPixel($url)
	{
		$this->registerJsFile($url);
		$this->registerNoScript(
			'<img src="' . $url . '" height="1" width="1" border="0" alt=""/>'
		);

	}

	/*
	 * EXPERIMENTAL.
	 * Creates google analytics pixel code using parameters.
	 */
	public function setGoogleAnalyticsPixel($args=array())
	{
		if (!isset($args['account'])) { return false; }

		$strAccount = $args['account'];
		$strDomainName = (isset($args['domainName'])) ? $args['domainName'] : 'none';
		$blnAllowLinker = (isset($args['allowLinker'])) ? $args['allowLinker'] : false;
		$blnTrackPageview = (isset($args['trackPageview'])) ? $args['trackPageview'] : false;

		$strTrackPageView = '';
		if ($blnTrackPageview) {
			$strTrackPageview = "_gaq.push(['_trackPageview']);";
		}

		$this->registerJsLeading( "
			var _gaq = _gaq || [];
			_gaq.push(['_setAccount', '$strAccount']);
			_gaq.push(['_setDomainName', '$strDomainName']);
			_gaq.push(['_setAllowLinker', $blnAllowLinker]);
			$strTrackPageview

			(function() {
				var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
				ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
				var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
			})();
		" );
	}

	/**
	 * registerMetaTag
	 * stores an array of key/value pairs to be used as attributes when creating a meta tag
	 * @param $args Array
	 * @return void
	 */
	public function registerMetaTag($args)
	{
		if (!is_array($args)) {
			$args = array($args);
		}
		$this->arrMetaTags[] = $args;
	}

	public function registerCssFile($arrHref, $strModuleName='', $strMedia='all', $strCharSet='utf-8')
	{
		if (!is_array($arrHref)) {
			$arrHref = array($arrHref);
		}
		forEach($arrHref as $strHref) {
			$this->arrCssFiles[] = array(
				'href'    => $strHref,
				'media'   => $strMedia,
				'charset' => $strCharSet,
				'class'   => $strModuleName
			);
		}
	}

	public function registerJsFile($arrJsFiles, $strModuleName='')
	{
		if (!is_Array($arrJsFiles)) {
			$arrJsFiles = array($arrJsFiles);
		}
		forEach ($arrJsFiles as $strJsUrl) {
			if (!in_array($strJsUrl, $this->arrJsFiles)) {
				$this->arrJsFiles[] = array(
					'src' => $strJsUrl,
					'class' => $strModuleName
				);
			}
		}
	}

	public function registerCssTrailing($arrCss)
	{
		if (!is_array($arrCss)) {
			$arrCss = array($arrCss);
		}
		$strCssTrailing = implode("\n", $arrCss);
		$this->arrCssTrailing[] = $strCssTrailing;
	}

	public function registerJsLeading($arrJs)
	{
		if (!is_array($arrJs)) {
			$arrJs = array($arrJs);
		}
		$strJs = implode("\n", $arrJs);
		$this->arrJsLeading[] = $strJs;
	}

	public function registerJsTrailing($arrJs)
	{
		if (!is_array($arrJs)) {
			$arrJs = array($arrJs);
		}
		$strJs = implode("\n", $arrJs);
		$this->arrJsTrailing[] = $strJs;
	}

	public function registerNoScript($arrNoScriptLines)
	{
		if (!is_array($arrNoScriptLines)) {
			$arrNoScriptLines = array($arrNoScriptLines);
		}
		foreach ($arrNoScriptLines as $strNoScriptLine) {
			if (!in_array($strNoScriptLine, $this->arrNoScript)) {
				$this->arrNoScript[] = $strNoScriptLine;
			}
		}
	}

	public function registerBodyContent($arrContent)
	{
		if (!is_array($arrContent)) {
			$arrContent = array($arrContent);
		}
		forEach($arrContent as $content) {
			$this->arrBodyContent[] = $content;
		}
	}
	
	/*
	 * TODO: I HATE this name.  Every time I think of it I want to call it 'registerDebugVar'.
	 * And so it should be.  Meh.
	 */
	public function registerDebugVar($var, $title='var', $expand=false)
	{
		if (isset($this->arrDebugVars[$title])) {
			// something to keep var titles from overwriting others with the same name - add sequentual numbers in parens
		}
		$this->arrDebugVars[$title] = array(
			'data' => print_r($var, true), // print_r is the only one of the three that will handle recursion
			'expand' => $expand
		);
	}

	public function render($objContent)
	{
		$this->Site->registerPerformance('Page render()');

		header("Content-type: {$this->getContentType()}");

		if ($this->blnNoCache)
		{
			// Force browsers to not cache content using both a header and a meta tag
			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
			$this->registerMetaTag(
				array(
					'http-equiv' => 'Pragma',
					'content'    => 'no-cache'
				)
			);
		}

		// Attempt to gzip content
		if (!ob_start('ob_gzhandler')) // checks to see if browser will accept gzipped content, compresses if yes
		{
			ob_start(); // failover to uncompressed content
		}

		if (isSet($objContent->sTitle)) {
			$this->setTitle($objContent->sTitle);
		}
		else {
			$this->setTitle($this->Site->getTitle());
		}
		$objContent->init();
		$objContent->process();
		$objContent->render();

		$this->Smarty->assign('Page', array(
			 'docType'            => $this->getDocType()
			,'htmlTag'            => $this->getHtmlTag()
			,'browserClass'       => $this->Site->getBrowserName() . ' ' . $this->Site->getBrowserVersion()
			,'title'              => $this->getTitle()
			,'metaTags'           => $this->arrMetaTags
			,'cssFiles'           => $this->arrCssFiles
			,'cssTrailing'        => $this->arrCssTrailing
			,'bodyContent'        => implode($this->arrBodyContent, "\n")
			,'jsLeading'          => $this->arrJsLeading
			,'jsFiles'            => $this->arrJsFiles
			,'jsTrailing'         => $this->arrJsTrailing
			,'noScript'           => $this->arrNoScript
		));

		echo $this->Smarty->fetch('src/php/Page.tpl');

		$this->Site->registerPerformance('Page render()', 'stop');
		$this->Site->registerPerformance('<b>Total</b>', 'stop');

		if ($this->Site->getDebug())
		{
			$this->Smarty->assign('DebugVars', $this->arrDebugVars);
			$this->Site->calculatePerformance();
			$this->Smarty->assign('Performance', $this->Site->arrPerformance);
			
			$strCompInfo = get_include_contents('/var/www/lib/vendor/techpatterns/your_computer_info.php');
			$this->Smarty->assign('CompInfo', $strCompInfo);

			echo $this->Smarty->fetch('src/php/Debug.tpl');
		}

		ob_end_flush();
	}

    public function renderAjax($objContent)
    {
        header('Content-type: text/javascript');

        $strDestination = (isset($_REQUEST['destination'])) ? $_REQUEST['destination'] : null;

        $objContent->init();
        $objContent->process();

        $this->setContentType('text/javascript');

        echo json_encode(array(
             'cssFiles'         => $this->arrCssFiles
            ,'cssTrailing'      => $this->arrCssTrailing
            ,'jsLeading'        => $this->arrJsLeading
            ,'jsFiles'          => $this->arrJsFiles
            ,'jsTrailing'       => $this->arrJsTrailing
            ,'noScript'         => $this->arrNoScript
            ,'html'             => $objContent->render()
            ,'destination'      => $strDestination
        ));

        exit;
	}

	/*
	 * renderCssFiles
	 * rather than having the page make a separate http request for each css file, this function gets all of their contents and
	 * inserts them inline into the page.
	 */
	public function renderCssFiles()
	{
		$sCssFiles = "<style type=\"text/css\">\n";

		foreach ($this->arrCssFiles as $arrCssFile)
		{
			$arrCssFile['href'] = str_replace('/libcss/', '/css/', $arrCssFile['href']); // should it be preg_replace to make sure it's at the beginning of the string?
//			echo $rCssFile['href'] . '<br/>';
			if ($filePath = Util::file_exists_in_path("{$arrCssFile['href']}"))
			{
//				echo $filePath . '<br/>';
				$strCssFiles .= file_get_contents("{$filePath}") . "\n";
			}
		}

		$strCssFiles .= "</style>\n";

		return $strCssFiles;
	}

    /*
     * renderJsFiles
     * rather than having the page make a separate http request for each js file, this function gets all of their contents and
     * inserts them inline into the page.
     */
	public function renderJsFiles()
	{
		$strJsFiles = "<script type=\"text/javascript\">\n";

		forEach ($this->arrJsFiles as $arrJsFile)
		{
			$arrJsFile['url'] = str_replace('/libjs/', '/js/', $arrJsFile['url']); // see note in previous function

			if (strpos($arrJsFile['url'], 'http://') === false && $arrJsFile['inline'])
			{
//				echo $rJsFile['url'] . '<br/>';
				if ( $filePath = Util::file_exists_in_path("{$arrJsFile['url']}") )
				{
//					echo $filePath . '<br/>';
					$strJsFiles .= file_get_contents("{$filePath}") . "\n";
				}
			}
		}

		$strJsFiles .= "</script>\n";

		return $strJsFiles;
	}

	/******************************
	 * Simple getters and setters
	 ******************************/

	public function setDocType($string)
	{
		$this->strDocType = $string;
	}

	protected function getDocType()
	{
		return $this->strDocType;
	}

	public function setHtmlTag($string)
	{
		$this->strHtmlTag = $string;
	}

	protected function getHtmlTag()
	{
		return $this->strHtmlTag;
	}

	public function setTitle($string)
	{
		$this->strTitle = $string;
	}

	protected function getTitle()
	{
		return $this->strTitle;
	}

	public function setContentType($string)
	{
		$this->strContentType = $string;
	}

	protected function getContentType()
	{
		return $this->strContentType;
	}

}
?>
