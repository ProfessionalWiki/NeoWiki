import type { SubjectId } from '@/domain/SubjectId';

/**
 * Prop shape a View Type component must accept. A View Type renders the Subject
 * identified by subjectId; canEditSubject and layoutName are supplied by NeoWiki
 * when it mounts the component for a {{#view}} or Main Subject placeholder.
 *
 * Layout-specific configuration (Display Rules and settings) is not passed here:
 * resolve it from the layout store using layoutName (e.g. layout.getSettings()).
 */
export interface ViewTypeProps {
	subjectId: SubjectId;
	canEditSubject: boolean;
	layoutName?: string;
}
