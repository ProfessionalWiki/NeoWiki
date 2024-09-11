import Vue from 'vue';
import { config } from '@vue/test-utils';

// Instead of using global, add Vue to the window object
(global as any).Vue = Vue;

config.global.mocks = {
	$i18n: ( str: string ) => str
};

config.global.stubs = {
	'i18n-html': true
};
