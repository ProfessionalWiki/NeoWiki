import type { SubjectId } from '@/domain/SubjectId';

/**
 * Prop shape a View Type component must accept. A View Type renders the Subject
 * identified by subjectId; canEditSubject and layoutName are supplied by NeoWiki
 * when it mounts the component for a {{#view}} or Main Subject placeholder.
 */
export interface ViewTypeProps {
	subjectId: SubjectId;
	canEditSubject: boolean;
	layoutName?: string;
}
