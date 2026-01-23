import { useSubjectStore } from '@/stores/SubjectStore';
import { Subject } from '@/domain/Subject';

interface UseSubjectSaverReturn {
	saveSubject: ( subject: Subject, comment?: string ) => Promise<boolean>;
}

export function useSubjectSaver(): UseSubjectSaverReturn {
	const subjectStore = useSubjectStore();

	const saveSubject = async ( subject: Subject, comment?: string ): Promise<boolean> => {
		const subjectName = subject.getLabel();
		try {
			await subjectStore.updateSubject( subject, comment );
			// TODO: i18n
			mw.notify( `Updated ${ subjectName }.`, { type: 'success' } );
			return true;
		} catch ( error ) {
			mw.notify(
				error instanceof Error ? error.message : String( error ),
				{
					// TODO: i18n
					title: `Failed to update ${ subjectName }.`,
					type: 'error',
				},
			);
			return false;
		}
	};

	return {
		saveSubject,
	};
}
