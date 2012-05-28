<?php
/*
 * $Id: Module_Class.php 53 2011-07-13 16:28:46Z genghishack $
 *
 * Module_Class.php - provides basic init, process and render functionality for modules,
 * and allows infinite nesting of modules.
 *
 * Automatically looks for and registers (to Page object) css and js files of the same name as the module,
 * if they reside within the module's folder.
 */
abstract class Module_Class
{
	protected $modules = array();

	/**
	 * @param $preventRecursion
	 */
	function __construct($preventRecursion=false)
	{
		$arrObjectsToRegister = array(
			 'Site'
			,'Page'
			,'DB'
			,'User'
			,'fbApp'
			,'fbSdk'
			,'fbSession'
			,'Smarty'
		);

		foreach ($arrObjectsToRegister as $strObjectName)
		{
			$this->$strObjectName = (isset($GLOBALS[$strObjectName])) ? $GLOBALS[$strObjectName] : null;
		}

		if (!$preventRecursion && strpos($_SERVER['REQUEST_URI'], 'src/module'))
		{
			$sModuleName = $this->name;
			$this->Page->renderAjax(new $sModuleName(1));
			exit;
		}

	}

	public function registerModule($sLabel, $oModule=false)
	{
		if (!class_exists($sLabel)) {
			include("module/$sLabel/$sLabel.php");
		}
		$oModule = ($oModule) ? $oModule : new $sLabel(1);
		$this->modules[$sLabel] =& $oModule;
		$oModule->id = $sLabel;
	}

	protected function getModule($sLabel)
	{
		return (isSet($this->modules[$sLabel])) ? $this->modules[$sLabel] : false;
	}

	protected function initModule($sLabel, $oModule, $oParams=array())
	{
		$oModule = $this->getModule($sLabel);
		if (!$oModule) {
			return "\n\n<!-- INIT ERROR: unrecognized module: $sLabel -->\n\n";
		}
		$oModule->init($oParams);
	}

	protected function processModule($sLabel, $oParams=array())
	{
		$oModule = $this->getModule($sLabel);
		if (!$oModule) {
			return "\n\n<!-- PROCESS ERROR: unrecognized module: $sLabel -->\n\n";
		}
		$oModule->process($oParams);
	}

	protected function renderModule($sLabel)
	{
		$oModule = $this->getModule($sLabel);
		if (!$oModule) {
			return "\n\n<!-- RENDERING ERROR: unrecognized module: $sLabel -->\n\n";
		}
		$sRenderedModule = $oModule->render();
		return $sRenderedModule;
	}

	public function init($oParams=array())
	{
		// TODO: this may need to look in the lib dir for the modules as well
		if (file_exists("{$this->Site->getBasePath()}/src/module/{$this->name}/{$this->name}.css"))
		{
			$this->Page->registerCssFile("/src/module/{$this->name}/{$this->name}.css", $this->name);
		}

		if (file_exists("{$this->Site->getBasePath()}/src/module/{$this->name}/{$this->name}.js"))
		{
			$this->Page->registerJsFile("/src/module/{$this->name}/{$this->name}.js", $this->name);
		}

		forEach($this->modules as $sLabel => $oModule)
		{
			$this->initModule($sLabel, $oModule, $oParams);
		}
	}

	public function process($oParams=array())
	{
		forEach($this->modules as $sLabel => $oModule) {
			$this->processModule($sLabel, $oModule, $oParams);
		}
	}

	public function render($oParams=array())
	{
		forEach($this->modules as $sLabel => $oModule) {
			$this->$sLabel = $this->renderModule($sLabel, $oModule, $oParams);
		}
	}

}
?>