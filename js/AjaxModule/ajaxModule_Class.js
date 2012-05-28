/*
 * $Id: ajaxModule_Class.js 54 2011-07-13 16:35:48Z genghishack $
 * 
 * ajaxModule_1.0.js - calls a module through ajax and returns its html as JSON.
 * optionally will return the css and js filenames as well, in the event a module was not pre-loaded
 * or the css or js were changed with the new module call.  lazy-loads the js and css onto the page in this event.
 */
function ajaxModule_Class()
{
	this.init();
}

ajaxModule_Class.prototype.init = function()
{
	
}

/***
 * load: the main method of ajaxModule, calls the requested module according to parameters
 * 
 * oArgs: {
 *      module:      name of module to be loaded
 * 	   ,data:        query string arguments in JSON format to be passed to the server
 *     ,post:        boolean - whether to use 'POST' method or not
 *     ,destination: selector describing location where module should be placed
 *     ,loader:      boolean - should a loader mask be placed over the destination?
 *     ,empty:       boolean - should destination be emptied first?
 *     ,loadCss:     boolean - should the Css be loaded (i.e. if module does not already exist on page)
 *     ,loadJs:      boolean - should the Js be loaded (i.e. if module does not already exist on page)
 * 	   ,context:     context for the callback (defaults to 'this')
 *     ,callback:    function to be called after the module is loaded
 * }
 */
ajaxModule_Class.prototype.load = function(oArgs) {
	
	var data = oArgs.data || {};
	var module = oArgs.module;
	var type = (oArgs.post) ? 'POST' : 'GET';
	
	if (oArgs.loader && oArgs.destination) {
		this.showLoader(oArgs.destination);
	}
	
	$.ajax({
		 url:'/src/module/' + module + '/' + module + '.php'
		,data:data
		,type:type
		,dataType:'json'
		,context:this
		,success:function(oResponse) {
			this._handleLoadedModule(oResponse,oArgs);
		}.Context(this)
	});
}

ajaxModule_Class.prototype._handleLoadedModule = function(oResponse,oArgs) 
{
	var fCallback = oArgs.callback || function(){};
	var oContext = oArgs.context || this;
	var moduleName = oArgs.module;
	var loadCss = oArgs.loadCss || false;
	var loadJs = oArgs.loadJs || false;
	
	var html        = unescape(oResponse.html);
	var cssFiles    = oResponse.cssFiles;
	var cssTrailing = (oResponse.cssTrailing).join('\n');
	var jsLeading   = '<script>' + (oResponse.jsLeading).join('\n') + '</script>';
	var jsFiles     = oResponse.jsFiles;
	var jsTrailing  = '<script>' + (oResponse.jsTrailing).join('\n') + '</script>';

	// module css
	if (loadCss)
	{
		for (var s in cssFiles) 
		{
			$('link.' + moduleName).remove();
			
			var cssFile = cssFiles[s];
			var cssTag = [
			     '<link href="' + cssFile.href + '"'
			    ,'      class="' + moduleName + '"'
			    ,'      media="' + cssFile.media + '"'
			    ,'      type="text/css"'
			    ,'      rel="stylesheet"'
			    ,'/>'
			].join('');
	 	
			$('head').append(cssTag);
		}
	 
		$('head').append(cssInline);
	}
	
	// module html
	if (oArgs.empty && oArgs.destination) {
		$(oArgs.destination).empty();
	}
	$(oArgs.destination).html(html);

	// module js
	if (loadJs)
	{
		$('body').append(jsLeading);
		
	 	for (var s in jsFiles) 
	 	{
			$('script.' + moduleName).remove();
			
			var jsHref = jsFiles[s];
			var jsTag = [
		         '<script src="' + jsHref + '"'
	 		    ,'        class="' + moduleName + '"'
	 		    ,'></script>'
	 		].join('');
	 	
 			$('body').append(jsTag);
	 	}
	 	
	 	$('body').append(jsInline);
	}
	
	if (oArgs.loader && oArgs.destination) {
		this.hideLoader(oArgs.destination);
	}

	fCallback.call(oContext);
}

/*
 * TODO: These two functions should live at a higher level.
 */
ajaxModule_Class.prototype.showLoader = function(destination)
{
	// find the height, width and position of the destination and position the loader mask over it
	$('#loaderMask').offset(function(index, coords) {
		return $(destination).offset();
	}).height(function(index, height) {
		return $(destination).height();
	}).width(function(index, width) {
		return $(destination).width();
	});
}

ajaxModule_Class.prototype.hideLoader = function()
{
	$('#loaderMask').offset({top:'0px',left:'0px'}).height('0px').width('0px');
}

ajaxModule = new ajaxModule_Class();
