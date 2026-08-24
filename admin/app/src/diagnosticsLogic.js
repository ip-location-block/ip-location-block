export const activeChecks = ( report ) =>
	( report?.checks || [] ).filter(
		( check ) => check.status !== 'pass' && ! check.acknowledged
	);

export const criticalChecks = ( report ) =>
	activeChecks( report ).filter( ( check ) => check.status === 'critical' );

export const acknowledgedChecks = ( report ) =>
	( report?.checks || [] ).filter(
		( check ) => check.status !== 'pass' && check.acknowledged
	);

export const passedChecks = ( report ) =>
	( report?.checks || [] ).filter( ( check ) => check.status === 'pass' );

export const orderedIssues = ( report ) => {
	const rank = { critical: 0, warning: 1, info: 2 };
	return activeChecks( report ).sort(
		( left, right ) =>
			( rank[ left.status ] ?? 9 ) - ( rank[ right.status ] ?? 9 )
	);
};

export const issueCount = ( report ) =>
	Number( report?.counts?.critical || 0 ) +
	Number( report?.counts?.warning || 0 );

export const environmentText = ( environment ) =>
	( environment?.sections || [] )
		.flatMap( ( section ) => [
			`## ${ section.title }`,
			...( section.rows || [] ).map(
				( row ) => `- ${ row.label } ${ row.value }`
			),
			'',
		] )
		.join( '\n' )
		.trim();
