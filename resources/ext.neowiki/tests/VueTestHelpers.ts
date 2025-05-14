import { mount, VueWrapper } from '@vue/test-utils';
import { Component, DefineComponent } from 'vue';
import { vi } from 'vitest';
import { NeoWikiTestServices } from './NeoWikiTestServices.ts';

export function createTestWrapper<TComponent extends DefineComponent<any, any, any>>(
	component: Component,
	props: InstanceType<TComponent>['$props']
): VueWrapper<InstanceType<TComponent>> {
	return mount(
		component,
		{
			props: props,
			global: {
				provide: NeoWikiTestServices.getServices(),
				mocks: {
					$i18n: vi.fn().mockImplementation( ( key ) => ( {
						text: () => key
					} ) )
				}
			}
		}
	) as VueWrapper<InstanceType<TComponent>>;
}
