import { inject } from 'vue';
import { FormatSpecificComponentRegistry } from '@/FormatSpecificComponentRegistry.ts';

export enum Service {
	ComponentRegistry = 'ComponentRegistry'
}

export function injectComponentRegistry(): FormatSpecificComponentRegistry {
	return inject( Service.ComponentRegistry ) as FormatSpecificComponentRegistry;
}
