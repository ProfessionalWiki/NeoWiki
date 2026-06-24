import type { SubjectId } from '@/domain/SubjectId';

/**
 * Prop shape every View Type component must accept to render a View. Identifies
 * the Subject to render (subjectId) plus the per-View context (canEditSubject,
 * layoutName) that NeoWiki supplies when it mounts the component for a {{#view}}
 * or Main Subject placeholder.
 *
 * Layout-specific configuration (Display Rules and settings) is not passed here:
 * resolve it from the layout store using layoutName (e.g. layout.getSettings()).
 */
export interface ViewProps {
	subjectId: SubjectId;
	canEditSubject: boolean;
	layoutName?: string;
}
