import type { SubjectAuthorizer } from '@/application/SubjectAuthorizer';
import type { SubjectId } from '@neo/domain/SubjectId';
import type { RightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher';

export class RightsBasedSubjectAuthorizer implements SubjectAuthorizer {

	public constructor( private readonly rightsFetcher: RightsFetcher ) {
	}

	public async canCreateChildSubject( _pageId: number ): Promise<boolean> {
		// TODO: should this be considered a page edit and use 'edit' right instead?
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'createpage' ) && rights.includes( 'edit' );
	}

	public async canEditSubject( _subjectId: SubjectId ): Promise<boolean> {
		// TODO: check edit right on specific page containing the subject.
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'edit' );
	}

	public async canDeleteSubject( _subjectId: SubjectId ): Promise<boolean> {
		// TODO: check delete right on specific page containing the subject.
		// TODO: or should this be considered a page edit and use 'edit' right instead?
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'delete' );
	}

	public async canCreateMainSubject(): Promise<boolean> {
		// TODO: should this be considered a page edit and use 'edit' right instead?
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'createpage' ) && rights.includes( 'edit' );
	}
}
