import type { PageChoice } from '@/components/common/PageChoice.ts';

/** Which page the Subject being created goes on. */
export type SubjectPageChoice = 'thisPage' | 'anotherPage' | 'newPage';

/** The page choice a caller hands the Subject creator. */
export interface InitialPage {
	choice: SubjectPageChoice;
	/** The page 'anotherPage' means, and the title 'newPage' gets. Absent where the caller names none. */
	page?: PageChoice;
	/** The creator states this page instead of asking which. */
	fixed: boolean;
}
