import {
	acknowledgedChecks,
	criticalChecks,
	environmentText,
	issueCount,
	orderedIssues,
	passedChecks,
} from './diagnosticsLogic';

const report = {
	counts: { critical: 1, warning: 1 },
	checks: [
		{ id: 'pass', status: 'pass', acknowledged: false },
		{ id: 'warning', status: 'warning', acknowledged: false },
		{ id: 'critical', status: 'critical', acknowledged: false },
		{ id: 'ack', status: 'warning', acknowledged: true },
		{ id: 'info', status: 'info', acknowledged: false },
	],
};

describe( 'diagnostics logic', () => {
	test( 'counts only unacknowledged actionable issues', () => {
		expect( issueCount( report ) ).toBe( 2 );
	} );

	test( 'orders critical checks before warnings and information', () => {
		expect( orderedIssues( report ).map( ( check ) => check.id ) ).toEqual(
			[ 'critical', 'warning', 'info' ]
		);
		expect( criticalChecks( report ).map( ( check ) => check.id ) ).toEqual(
			[ 'critical' ]
		);
	} );

	test( 'separates passed and acknowledged checks', () => {
		expect( passedChecks( report ).map( ( check ) => check.id ) ).toEqual( [
			'pass',
		] );
		expect(
			acknowledgedChecks( report ).map( ( check ) => check.id )
		).toEqual( [ 'ack' ] );
	} );

	test( 'formats support information as reviewable plain text', () => {
		expect(
			environmentText( {
				sections: [
					{
						title: 'System',
						rows: [ { label: 'PHP:', value: '8.2' } ],
					},
				],
			} )
		).toBe( '## System\n- PHP: 8.2' );
	} );
} );
