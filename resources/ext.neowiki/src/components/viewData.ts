import { SubjectId } from '@/domain/SubjectId';
import { SubjectIdParser } from '@/domain/SubjectIdParser';

export interface ViewData {
	id: string;
	element: HTMLElement;
	subjectId: SubjectId;
	canEditSubject: boolean;
	viewType?: string;
	layoutName?: string;
}

export async function getViewsData(
	elements: NodeListOf<HTMLElement>,
	subjectIdParser: SubjectIdParser,
	canEditSubject: ( subjectId: SubjectId ) => Promise<boolean>,
): Promise<ViewData[]> {
	const viewsData: ViewData[] = [];

	for ( const element of elements ) {
		const viewData = await getViewData( element, subjectIdParser, canEditSubject );
		if ( viewData ) {
			viewsData.push( viewData );
		}
	}
	return viewsData;
}

async function getViewData(
	element: HTMLElement,
	subjectIdParser: SubjectIdParser,
	canEditSubject: ( subjectId: SubjectId ) => Promise<boolean>,
): Promise<ViewData|null> {
	if ( !element.dataset.mwNeowikiSubjectId ) {
		return null;
	}

	try {
		const subjectId = subjectIdParser.parse( element.dataset.mwNeowikiSubjectId );
		return {
			id: subjectId.text,
			element: element,
			subjectId: subjectId,
			canEditSubject: await canEditSubject( subjectId ),
			viewType: element.dataset.mwNeowikiViewType,
			layoutName: element.dataset.mwNeowikiLayoutName,
		};
	} catch ( error ) {
		console.error( error );
		return null;
	}
}
