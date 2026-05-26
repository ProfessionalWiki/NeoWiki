export type Severity = 'error' | 'warning';

export type Constraint =
	| { kind: 'required'; severity?: Severity }
	| { kind: 'minLength'; value: number; severity?: Severity }
	| { kind: 'maxLength'; value: number; severity?: Severity }
	| { kind: 'uniqueItems'; severity?: Severity }
	| { kind: 'cardinality'; maxItems: number; severity?: Severity }
	| { kind: 'enum'; allowedValues: string[]; severity?: Severity };
