import Vue from 'vue';

declare module 'vue' {
	interface VueConstructor {
		createMwApp: typeof Vue.createApp;
	}
}
