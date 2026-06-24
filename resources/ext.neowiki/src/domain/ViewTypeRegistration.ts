import type { Component } from 'vue';

/**
 * Plain-object shape a frontend extension passes to the neowiki.registration hook
 * via FrontendRegistrar.registerViewType(). The component must conform to the
 * ViewTypeProps contract.
 */
export interface ViewTypeRegistration {
	typeName: string;
	component: Component;
}
