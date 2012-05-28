/*
 * $Id: Site_1.1.js 70 2012-01-03 13:59:05Z genghishack $
 * 
 * site_1.1.js
 * 
 * Client-side component of Site_Class.  Used mainly to store variables needed when doing ajax calls
 * and browser-specific actions.  Any var needed on the client side can be set here from the PHP side
 * and referenced in the JS with Site.varname.
 */
function Site_Class()
{
	this.init();
}

Site_Class.prototype.init = function()
{
	this.baseUrl        = '';
	this.pageName       = '';
	this.browserName    = '';
	this.browserVersion = '';
	this.landingStamp   = '';
	this.fbUser         = '';
	this.fbSession      = {};
	
	this.setEvents();
};

Site_Class.prototype.setEvents = function()
{
	// Expand/collapse the debug var displays
	$('#VarDisplay .title').live('click', function(oEvent) {
		var oElementClicked = $(oEvent.target);
		var oTarget = oElementClicked.next('.var').toggle(100);
	});
	
	$('#lightBox').live('click', this.hideLightBox.Context(this));
	$('#lightBox .content').live('click', function(ev) {
		ev.stopPropagation();
	});
};

Site_Class.prototype.showLightBox = function()
{
	$('html').addClass('lightBox');
};

Site_Class.prototype.hideLightBox = function()
{
	$('#lightBox .content').empty();
	$('html').removeClass('lightBox');
};


Site_Class.prototype.setBaseUrl = function(string)
{
	this.baseUrl = string;
};

Site_Class.prototype.setPageName = function(string)
{
	this.pageName = string;
};

Site_Class.prototype.setBrowserName = function(string)
{
	this.browserName = string;
};

Site_Class.prototype.setBrowserVersion = function(string)
{
	this.browserVersion = string;
};

Site_Class.prototype.setLandingStamp = function(string)
{
	this.landingStamp = string;
};

Site_Class.prototype.setFbUser = function(string)
{
	this.fbUser = string;
};

Site_Class.prototype.setFbSession = function(object)
{
	this.fbSession = object;
};
