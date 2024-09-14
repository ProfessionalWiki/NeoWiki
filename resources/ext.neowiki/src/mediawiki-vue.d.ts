import { Vue } from 'vue';
import { Component } from '@vue/runtime-core';

declare module 'vue' {
	export function createMwApp( rootComponent: Component, rootProps?: rootProps ): Vue.App<Element>;
}

declare module '@vue/runtime-core' {
	interface ComponentCustomProperties {
		$i18n: ( ...args: Parameters<typeof window.mw.message> ) => mw.Message;
	}
}
