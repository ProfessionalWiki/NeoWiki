import { computed, ComputedRef, Ref } from 'vue';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { PropertyDefinition } from '@/domain/PropertyDefinition.ts';
import { ValueInputEmitFunction } from '@/components/Value/ValueInputContract.ts';

/**
 * Which on-screen surfaces an edit could have resolved, so the matching stale
 * server violations can be optimistically cleared:
 *
 * - `'all'` — the input renders one aggregate error for the whole property with
 *   no per-part slot (Select, Relation), so every held violation is stale on any
 *   edit. Each is cleared by its own index (numeric or null).
 * - a list of value-part indices — the input renders an independent slot per
 *   part (multi Text/Url), so only the edited slots are cleared, and the shared
 *   field-level summary slot is cleared alongside them. A single-value or
 *   field-level input passes `[]` to clear only that summary slot.
 */
export type ClearScope = 'all' | readonly number[];

export interface UseServerViolationsReturn {
	relevant: () => readonly SubjectViolation[];
	format: ( violation: SubjectViolation ) => string;
	firstMessage: ComputedRef<string | null>;
	fieldLevelMessage: ComputedRef<string | null>;
	emitClears: ( touched: ClearScope ) => void;
}

function isFieldLevel( violation: SubjectViolation ): boolean {
	return violation.valuePartIndex === null || violation.valuePartIndex === undefined;
}

/**
 * Shared server-violation handling for the value inputs. Centralises the
 * property-name filter and the `neowiki-field-` message formatter (each was
 * copied across four/five inputs) and, crucially, the optimistic
 * clear-on-edit logic — which differs only by how each input DISPLAYS its
 * violations. Display stays in the individual inputs, because the aggregate,
 * per-part-plus-summary, and field-level models genuinely differ.
 *
 * The parent dialog drops violations by exact (propertyName, valuePartIndex)
 * match, so every clear must carry the held violation's own index; a single
 * null clear never removes a part-indexed one.
 *
 * @param property The field's Property Definition; violations match on its name.
 * @param serverViolations The violations passed to this input (may be undefined).
 * @param emit The component's emit function; used for clear-server-violation.
 * @param formatArg Per-arg formatter; Date/DateTime format their bounds for display.
 */
export function useServerViolations<P extends PropertyDefinition>(
	property: Ref<P>,
	serverViolations: Ref<readonly SubjectViolation[] | undefined>,
	emit: ValueInputEmitFunction,
	formatArg: ( arg: string ) => string = ( arg ) => arg,
): UseServerViolationsReturn {
	function propertyName(): string {
		return property.value.name.toString();
	}

	function relevant(): readonly SubjectViolation[] {
		const name = propertyName();
		return ( serverViolations.value ?? [] ).filter( ( v ) => v.propertyName === name );
	}

	function format( violation: SubjectViolation ): string {
		return mw.message(
			`neowiki-field-${ violation.code }`,
			...( violation.args as string[] ).map( formatArg ),
		).text();
	}

	const firstMessage = computed<string | null>( () => {
		const hit = relevant()[ 0 ];
		return hit ? format( hit ) : null;
	} );

	const fieldLevelMessage = computed<string | null>( () => {
		const hit = relevant().find( isFieldLevel );
		return hit ? format( hit ) : null;
	} );

	function emitClears( touched: ClearScope ): void {
		const held = relevant();
		const name = propertyName();

		if ( touched === 'all' ) {
			for ( const violation of held ) {
				emit( 'clear-server-violation', { propertyName: name, valuePartIndex: violation.valuePartIndex } );
			}
			return;
		}

		for ( const index of touched ) {
			if ( held.some( ( v ) => v.valuePartIndex === index ) ) {
				emit( 'clear-server-violation', { propertyName: name, valuePartIndex: index } );
			}
		}
		if ( held.some( isFieldLevel ) ) {
			emit( 'clear-server-violation', { propertyName: name, valuePartIndex: null } );
		}
	}

	return { relevant, format, firstMessage, fieldLevelMessage, emitClears };
}
