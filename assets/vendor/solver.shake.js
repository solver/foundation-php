/**
 * @author Stan Vass
 * @copyright © 2013-2014 Solver Ltd. (http://www.solver.bg)
 * @license MIT (http://opensource.org/licenses/MIT)
 */

solver = window.solver || {};
solver.shake = solver.shake || {};

/**
 * Allows JS submission of forms and display of validation errors without refreshing the page.
 * 
 * Depends on the jQuery Form Plugin (http://malsup.com/jquery/form/).
 */
solver.shake.ajaxForm = function (form, beforeSubmit, onSuccess, onError, responseType) {
	function beforeSubmitInternal() {
		var eventSlots = $('.-events', form);
		
		// Reset event contents.
		eventSlots.html('').hide();
		
		if (beforeSubmit) beforeSubmit();
	}
	
	function onSuccessInternal(data) {
		var eventSlots = $('.-events', form);
		var esc = solver.shake.escape;
			
		// Build an index of event fields to fill in.
		var eventSlotsByPath = {};
		
		for (var i = 0, l = eventSlots.length; i < l; i++) {
			eventSlotsByPath[eventSlots.eq(i).attr('data')] = eventSlots.eq(i);
		}
		
		// Fill in the events.
		if (data.log) for (var i = 0, l = data.log.length; i < l; i++) {
			var event = data.log[i];
			
			if (eventSlotsByPath[event.path]) {
				eventSlotsByPath[event.path].show().append('<span class="event -' + event.type + '">' + esc(event.message) + '</span>');
			}
		}
		
		if (onSuccess) onSuccess(data);
	}
	
	function onErrorInternal(data) {
		if (onError) onError();
	}
	
	
	$(form).ajaxForm({
		beforeSerialize: beforeSubmitInternal,
		dataType: responseType,
		error: onErrorInternal,
		success: onSuccessInternal
	});
};

solver.shake.escape = function (string) {
	// TODO: Optimize.
	return string
		.replace(/&/g, '&amp;')
    	.replace(/>/g, '&gt;')
    	.replace(/</g, '&lt;')
    	.replace(/"/g, '&quot;')
    	.replace(/'/g, '&#39;');
};

/**
 * Works similar to its PHP counterpart, used to quickly read deeply nested values in an object/array tree without
 * painstaking manual checks if the key exists at every level.
 */
solver.shake.DataBox = function (object) {
	this.value = object;
};

var proto = solver.shake.DataBox.prototype;

/**
 * Returns the value at the given object string key, or the default value, if there's no value at that key.
 * 
 * @param  string key
 * Dot delimiter key path to the value to read.
 * 
 * @param  mixed defaultValue
 * Value to return if the request key path is not defined (if not specified, returns the JS undefined value).
 * 
 * @return mixed
 */
proto.get = function (key, defaultValue) {
	if (typeof defaultValue === 'undefined') defaultValue = undefined; // Maybe not needed, but just in case.

	key = key.split('.');

	var value = this.value;

	if (typeof value !== 'object') return defaultValue;

	for (var i = 0, m = key.length; i < m; i++) {
		if (value === null || typeof value !== 'object') return defaultValue;
		if (typeof value[key[i]] === 'undefined') return defaultValue;
		value = value[key[i]];
	}

	return value;
}

proto.unbox = function () {
	return this.value;
}

solver.shake.ArrayUtils = {};

/**
 * Merges a tree of objects/arrays/scalars recursively, properties of the second replacing (or setting) properties of
 * the first.
 */
solver.shake.ArrayUtils.mergeRecursive = function (mergeTo, mergeFrom) {
	var merge = function (a, b) {
		for (k in b) if (b.hasOwnProperty(k)) {
			var bv = b[k];
			if (typeof a.k !== 'undefined') {
				var av = a[k];
				if (typeof av === 'object' && typeof bv === 'object') {
					merge(av, bv);
				} else {
					av = bv;
				}
			} else {
				a[k] = bv;
			}
		}
	};

	merge(mergeTo, mergeFrom);
};

/**
 * Takes input with keys delimited by dots and/or brackets such as:
 * 
 * {'foo.bar.baz' : 123} -or- {'foo[bar][baz]' : 123}}
 *
 * and returns an output such as:
 *
 * {'foo' : {'bar' : {'baz' : 123}}}
 *
 * IMPORTANT: The current implementation always produces Object instances, even if a set of keys might form a valid
 * Array.
 */
solver.shake.ArrayUtils.splitKeys = function (object) {
	var bracketToDot = solver.shake.ArrayUtils.bracketToDot;
	var output = {};

	for (key in object) if (object.hasOwnProperty(key)) {
		var value = object[key];
		if (key.indexOf('[') > -1) key = bracketToDot(key);
		var key = key.split('.');

		var node = output;

		for (var i = 0, m = key.length; i < m; i++) {
			if (i < m - 1) {
				if (typeof node[key[i]] !== 'object') node[key[i]] = {};
				node = node[key[i]];
			} else {
				node[key[i]] = value;
			}
		};
	}

	return output;
}

/**
 * Takes a bracket delimited path such as:
 *
 * "foo[bar][baz]"
 *
 * and returns a dot delimited path such as:
 *
 * "foo.bar.baz"
 */
solver.shake.ArrayUtils.bracketToDot = function (path) {
	return path.replace(/[\[\]]+/g, '.').replace(/^\.+|\.+$/g, '');
}
