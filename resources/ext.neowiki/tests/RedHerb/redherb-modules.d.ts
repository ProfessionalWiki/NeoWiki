/**
 * RedHerb's package files are plain JavaScript, so they carry no types of their own.
 * Declaring them here keeps the specs importing them by their @redherb alias without
 * dragging their ResourceLoader-flavoured CommonJS through vue-tsc.
 *
 * Each package file is reachable as a default export, because that is what a CommonJS
 * module.exports becomes once resourceLoaderCommonJs has rewritten it.
 */
declare module '@redherb/*.vue' {
	import type { Component } from 'vue';
	const component: Component;
	export default component;
}

declare module '@redherb/*.js' {
	const moduleExports: any;
	export default moduleExports;
}
