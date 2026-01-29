import { mount, VueWrapper } from '@vue/test-utils';
import { Component, DefineComponent } from 'vue';
import { vi } from 'vitest';
import { NeoWikiTestServices } from './NeoWikiTestServices.ts';

export function createTestWrapper<TComponent extends DefineComponent<any, any, any>>(
	component: Component,
	props: InstanceType<TComponent>['$props'],
): VueWrapper<InstanceType<TComponent>> {
	return mount(
		component,
		{
			props: props,
			global: {
				provide: NeoWikiTestServices.getServices(),
				mocks: {
					$i18n: vi.fn().mockImplementation( ( key ) => ( {
						text: () => key,
					} ) ),
				},
			},
		},
	) as VueWrapper<InstanceType<TComponent>>;
}

export interface MwMockOptions {
	messages?: Record<string, string | ( ( ...params: string[] ) => string )>;
	config?: Record<string, any>;
	functions?: (
		'config' | 'message' | 'msg' | 'notify'
	)[];
}

export function setupMwMock(
	options: MwMockOptions = {},
): void {
	const {
		messages: customMessages = {},
		config: customConfig = {},
		functions = [
			'config',
			'message',
			'msg',
			'notify',
		],
	} = options;

	const mwMock: any = {};

	const resolveMessage = ( key: string, params: string[] ): string => {
		const message = customMessages[ key ];
		if ( typeof message === 'function' ) {
			return message( ...params );
		}
		if ( message !== undefined ) {
			return message;
		}
		return key + params.join( '' );
	};

	const implementations: Record<string, any> = {
		config: () => ( {
			get: vi.fn( ( key: string ) => customConfig[ key ] ),
		} ),
		message: () => vi.fn( ( key: string, ...params: string[] ) => ( {
			text: () => resolveMessage( key, params ),
			parse: () => resolveMessage( key, params ),
		} ) ),
		msg: () => vi.fn( ( key: string, ...params: string[] ) => resolveMessage( key, params ) ),
		notify: () => vi.fn(),
	};

	functions.forEach( ( funcName ) => {
		if ( implementations[ funcName ] ) {
			mwMock[ funcName ] = implementations[ funcName ]();
		}
	} );

	vi.stubGlobal( 'mw', mwMock );
}
