import { computed, Ref, ref, watch } from 'vue';
import { useResizeObserver } from '@wikimedia/codex';

/**
 * Limits an element's text to a number of lines and reports whether it has more
 * to show.
 *
 * The clamp is a measured max-height rather than `-webkit-line-clamp`, because
 * that needs `display: -webkit-box`, which lays element children out as their
 * own vertical boxes and so cannot hold a control on the last line.
 *
 * Overflow is measured on the text node rather than on the element around it:
 * the element's scroll height also counts any controls it holds, and the
 * invisible area padding their pointer target, which reports text that fits as
 * overflowing.
 */
export function useClampedText(
	element: Ref<HTMLElement | null>,
	lines: Ref<number | undefined>,
	clamped: Ref<boolean>,
): {
		overflowing: Ref<boolean>;
		measure: () => void;
	} {
	const overflowing = ref( false );

	function measure(): void {
		const target = element.value;

		if ( target === null ) {
			overflowing.value = false;
			return;
		}

		if ( lines.value === undefined ) {
			releaseClamp( target );
			overflowing.value = false;
			return;
		}

		// Released while the reader has it open. Whether the text is longer than
		// the clamp is a fact about the text, so the last verdict stands and the
		// control that reopens it survives.
		if ( !clamped.value ) {
			releaseClamp( target );
			return;
		}

		const lineHeight = lineHeightOf( target );
		const clampHeight = lineHeight * lines.value;

		applyClamp( target, lineHeight, clampHeight );
		overflowing.value = renderedTextHeight( target ) - clampHeight > 1;
	}

	// The observed element is replaced whenever the host swaps display for an
	// editor, so the observer follows the ref rather than one element.
	const observed = computed( () => element.value ?? undefined );

	watch( useResizeObserver( observed ), measure );
	watch( [ element, lines, clamped ], measure, { immediate: true } );

	return { overflowing, measure };
}

/**
 * Written only when it changes: the same write inside a resize callback resizes
 * the observed element again, which browsers report as an undelivered
 * notification loop.
 */
function applyClamp( element: HTMLElement, lineHeight: number, clampHeight: number ): void {
	const maxHeight = clampHeight + 'px';

	if ( element.style.maxHeight !== maxHeight ) {
		element.style.maxHeight = maxHeight;
	}

	// Lets a control covering the last line match its height exactly.
	if ( element.style.getPropertyValue( LINE_HEIGHT_PROPERTY ) !== lineHeight + 'px' ) {
		element.style.setProperty( LINE_HEIGHT_PROPERTY, lineHeight + 'px' );
	}
}

function releaseClamp( element: HTMLElement ): void {
	element.style.maxHeight = '';
	element.style.removeProperty( LINE_HEIGHT_PROPERTY );
}

const LINE_HEIGHT_PROPERTY = '--ext-neowiki-clamped-text-line-height';

function lineHeightOf( element: HTMLElement ): number {
	const styles = window.getComputedStyle( element );
	const lineHeight = parseFloat( styles.lineHeight );

	if ( Number.isFinite( lineHeight ) ) {
		return lineHeight;
	}

	// `normal` does not resolve to a length in every browser.
	const fontSize = parseFloat( styles.fontSize );
	return ( Number.isFinite( fontSize ) ? fontSize : 16 ) * 1.5;
}

function renderedTextHeight( element: HTMLElement ): number {
	const text = Array.from( element.childNodes )
		.find( ( node ): node is Text => node.nodeType === Node.TEXT_NODE );

	if ( text === undefined ) {
		return 0;
	}

	const range = document.createRange();

	// Absent wherever nothing is laid out, such as jsdom, where no text can
	// overflow anything in the first place.
	if ( typeof range.getBoundingClientRect !== 'function' ) {
		return 0;
	}

	// Clipping only hides the overflowing lines, so they are still laid out and
	// still counted here.
	range.selectNodeContents( text );
	return range.getBoundingClientRect().height;
}
