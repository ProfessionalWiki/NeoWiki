( function () {
	'use strict';

	// eslint-disable-next-line security/detect-unsafe-regex -- bounded quantifiers, no backtracking
	module.exports = /^#[0-9a-fA-F]{6}$/;
}() );
