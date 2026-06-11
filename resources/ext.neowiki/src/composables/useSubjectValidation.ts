import { ref, onScopeDispose, type Ref } from 'vue';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';

export type SubjectValidator = () => Promise<SubjectViolation[]>;

export interface UseSubjectValidationOptions {
	debounceMs: number;
}

export interface UseSubjectValidation {
	violations: Ref<SubjectViolation[]>;
	revalidate: () => void;
	flush: () => Promise<void>;
	reset: () => void;
}

export function useSubjectValidation(
	validate: SubjectValidator,
	options: UseSubjectValidationOptions,
): UseSubjectValidation {
	const violations = ref<SubjectViolation[]>( [] );
	let debounceTimer: ReturnType<typeof setTimeout> | null = null;
	let requestSequence = 0;

	function clearTimer(): void {
		if ( debounceTimer !== null ) {
			clearTimeout( debounceTimer );
			debounceTimer = null;
		}
	}

	async function run( expectedSequence: number ): Promise<void> {
		let result: SubjectViolation[];
		try {
			result = await validate();
		} catch {
			// A failing validator must never break editing/saving or surface as an
			// unhandled rejection; keep the last-known violations in place.
			return;
		}
		if ( expectedSequence !== requestSequence ) {
			return;
		}
		violations.value = result;
	}

	function revalidate(): void {
		clearTimer();
		requestSequence++;
		const expectedSequence = requestSequence;

		if ( options.debounceMs <= 0 ) {
			run( expectedSequence );
			return;
		}
		debounceTimer = setTimeout( () => run( expectedSequence ), options.debounceMs );
	}

	async function flush(): Promise<void> {
		clearTimer();
		requestSequence++;
		await run( requestSequence );
	}

	function reset(): void {
		clearTimer();
		requestSequence++;
		violations.value = [];
	}

	// Discard any pending debounce when the owning component/scope is torn down,
	// so a closed dialog never fires a stray validation request.
	onScopeDispose( clearTimer );

	return { violations, revalidate, flush, reset };
}
