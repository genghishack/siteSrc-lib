/*
 * $Id: Extensions_1.0.js 53 2011-07-13 16:28:46Z genghishack $
 * 
 * extensions_1.0.js
 * 
 * useful extensions to javascript
 */

/**
 * The .bind method from Prototype.js - changed to .Context for compatibility with jQuery
 * 
 * Allows assignment of context to a function - useful for callbacks that may lose context otherwise.
 * Use when calling a handler that exists outside of the current object scope.
 * 
 * Also useful for maintaining object scope in jQuery.  Example use:
 * 
 * function myObj();
 * myObj.prototype.myEvent = function()
 * {
 *     $('div').click(
 *         function() {
 *             this.myHandler();  // Because we're using .Context(this) on the anonymous function,
 *         }.Context(this)        // 'this' refers to myObj, rather than the div that was clicked.
 *     );
 * }
 * myObj.prototype.myHandler = function()
 * {
 *     // Therefore this handler will be called, instead of jQuery throwing an error by attempting
 *     // to call a myHandler() method that doesn't exist on the object created by $('div').
 * }
 */ 
if (!Function.prototype.Context) { // check if native implementation available
	Function.prototype.Context = function() { 
		var fn = this, args = Array.prototype.slice.call(arguments),
		object = args.shift();
		return function() { 
			return fn.apply(object, args.concat(Array.prototype.slice.call(arguments))); 
		}; 
	};
}
