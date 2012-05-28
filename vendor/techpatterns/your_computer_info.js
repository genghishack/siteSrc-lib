/*
Script Name: Your Computer Information
Author: Harald Hope, Website: http://TechPatterns.com/
Script Source URI: http://TechPatterns.com/downloads/browser_detection.php
Version: 1.2.4
Copyright (C) 3 April 2010

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

Get the full text of the GPL here: http://www.gnu.org/licenses/gpl.txt

This script requires the Full Featured Browser Detection and the Javascript Cookies scripts
to function.
You can download them here.
http://TechPatterns.com/downloads/browser_detection_php_ar.txt
http://TechPatterns.com/downloads/javascript_cookies.txt

Please note: this version requires the php browser_detection script version 5.3.3 or
newer, because of the new full return of arrays $moz_array and $webkit_array as keys
10 and 11, and $webkit_array key 7, and use of the new array key 14, true_msie_version.
*/

/*
If your page is XHMTL 1 strict, you have to
put this code into a js library file or your
page will not validate
*/

function client_data(info)
{
	if (info == 'width')
	{
		width_height_html = '<h4 class="right-bar">Current Screen Resolution</h4>';
		width = (screen.width) ? screen.width:'';
		height = (screen.height) ? screen.height:'';
		// check for windows off standard dpi screen res
		if (typeof(screen.deviceXDPI) == 'number') {
			width *= screen.deviceXDPI/screen.logicalXDPI;
			height *= screen.deviceYDPI/screen.logicalYDPI;
		} 
		width_height_html += '<p class="right-bar">' + width + " x " +
			height + " pixels</p>";
		(width && height) ? document.write(width_height_html):'';
	}
	else if (info == 'js' )
	{
		document.write('<p class="right-bar">JavaScript is enabled.</p>');
	}
	else if ( info == 'cookies' )
	{
		expires ='';
		Set_Cookie( 'cookie_test', 'it_worked' , expires, '', '', '' );
		string = '<h4  class="right-bar">Cookies</h4><p class="right-bar">';
		if ( Get_Cookie( 'cookie_test' ) )
		{
			string += 'Cookies are enabled</p>';
		}
		else {
			string += 'Cookies are disabled</p>';
		}
		document.write( string );
	}
}

