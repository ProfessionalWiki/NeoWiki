import { vi } from 'vitest';

/**
 * Makes `Intl.DateTimeFormat` use the given locale wherever code asks for the browser's default
 * (`undefined`), so output that depends on it can be asserted as literal text. Undo with
 * `vi.restoreAllMocks()`.
 */
export function useDefaultLocale( locale: string ): void {
	const RealDateTimeFormat = Intl.DateTimeFormat;

	vi.spyOn( Intl, 'DateTimeFormat' ).mockImplementation(
		function ( locales?: string | string[], options?: Intl.DateTimeFormatOptions ) {
			return new RealDateTimeFormat( locales ?? locale, options );
		} as typeof Intl.DateTimeFormat,
	);
}
