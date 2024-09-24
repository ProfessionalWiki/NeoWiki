import { Vue } from 'vue';
import { Component } from '@vue/runtime-core';
import { mw } from 'types-mediawiki';

declare module '@vue/runtime-core' {
	export function createMwApp( rootComponent: Component, rootProps?: rootProps ): Vue.App<Element>;

	interface ComponentCustomProperties {
		$i18n: ( ...args: Parameters<typeof window.mw.message> ) => mw.Message;
	}
}
