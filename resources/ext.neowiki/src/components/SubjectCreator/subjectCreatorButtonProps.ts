import type { InitialPage } from '@/components/SubjectCreator/InitialPage.ts';

// A type rather than an interface: createMwApp takes a plain props record, which only a type alias
// is assignable to.
export type SubjectCreatorButtonProps = {
	schemaName?: string;
	text?: string;
	hostPage: { hasMainSubject: boolean } | null;
	initialPage: InitialPage;
};

/**
 * What the `{{#create_subject}}` placeholder says, as the button's props.
 */
export function subjectCreatorButtonProps( dataset: DOMStringMap ): SubjectCreatorButtonProps {
	const hasMainSubject = dataset.mwNeowikiPageHasMainSubject;

	return {
		schemaName: dataset.mwNeowikiSchema,
		text: dataset.mwNeowikiText,
		// The parser function omits the attribute for a page that cannot hold Subjects.
		hostPage: hasMainSubject === undefined ? null : { hasMainSubject: hasMainSubject === 'true' },
		initialPage: initialPageChoice( dataset ),
	};
}

function initialPageChoice( dataset: DOMStringMap ): InitialPage {
	const title = dataset.mwNeowikiPageTitle;
	const page = dataset.mwNeowikiPage;

	if ( title !== undefined ) {
		return namedPageChoice( title, Number( dataset.mwNeowikiPageId ?? 0 ) );
	}

	if ( page === 'this' ) {
		return { choice: 'thisPage', fixed: true };
	}

	// Without page=, a new page is preselected but the choice stays open.
	return { choice: 'newPage', fixed: page === 'new' };
}

function namedPageChoice( title: string, pageId: number ): InitialPage {
	if ( pageId > 0 ) {
		return { choice: 'anotherPage', page: { pageId, title }, fixed: true };
	}

	return { choice: 'newPage', page: { pageId: null, title }, fixed: true };
}
