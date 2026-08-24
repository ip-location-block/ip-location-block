import {
	activeProviderStatuses,
	clearPublicProtection,
	formatAllowance,
	makeExclusiveProviderMap,
	providerDisconnectImpact,
	protectionEnabled,
	quotaBlocksProvider,
	quotaSummary,
	readyProviderNames,
	restoreProviderDisconnect,
	stageProviderDisconnect,
} from './providerLogic';

describe( 'provider logic', () => {
	test( 'an exclusive switch disables every known provider', () => {
		expect(
			makeExclusiveProviderMap(
				{ IPInfoDB: 'old-key', ExtensionProvider: 'on', Cache: '@' },
				[ 'IPInfoDB', 'IP2Location', 'IP Location Block' ],
				'IP Location Block',
				'native-key'
			)
		).toEqual( {
			IPInfoDB: '',
			ExtensionProvider: '',
			IP2Location: '',
			'IP Location Block': 'native-key',
			Cache: '@',
		} );
	} );

	test( 'keyless providers use the enabled sentinel', () => {
		expect( makeExclusiveProviderMap( {}, [], 'Provider', '' ) ).toEqual( {
			Provider: '@',
		} );
	} );

	test.each( [ 'exhausted', 'rate_limited', 'key_upgrade_required' ] )(
		'%s blocks provider readiness',
		( status ) => {
			expect( quotaBlocksProvider( { status } ) ).toBe( true );
		}
	);

	test( 'formats live quota and published allowances distinctly', () => {
		expect( quotaSummary( { total: 1234, status: 'ok' } ) ).toBe(
			'1,234 remaining'
		);
		expect( formatAllowance( { total: 15000, term: 'month' } ) ).toBe(
			'15,000 / month'
		);
	} );

	test( 'returns only active provider records', () => {
		expect(
			activeProviderStatuses( {
				providers: [
					{ name: 'A', active: true },
					{ name: 'B', active: false },
				],
			} )
		).toEqual( [ { name: 'A', active: true } ] );
	} );

	test( 'resolves ready providers from saved, implicit, and verified draft state', () => {
		const settings = {
			providers: { Native: 'key', Disabled: '' },
		};
		const providers = [
			{ name: 'Native', selected: true },
			{ name: 'Local', selected: true, local: true, databaseReady: true },
			{ name: 'Draft', selected: false },
		];
		const status = {
			providers: [
				{ name: 'Native', active: true, ready: true },
				{ name: 'Disabled', active: true, ready: true },
			],
		};

		expect(
			readyProviderNames( settings, providers, status, { Draft: true } )
		).toEqual( [ 'Native', 'Local' ] );
	} );

	test( 'disconnect impact keeps protection when a ready fallback remains', () => {
		const settings = {
			matching_rule: 1,
			validation: { public: 3 },
			providers: { Native: 'key', Fallback: '@' },
		};
		const status = {
			providers: [
				{ name: 'Native', active: true, ready: true },
				{ name: 'Fallback', active: true, ready: true },
			],
		};
		const impact = providerDisconnectImpact(
			settings,
			[],
			status,
			{},
			'Native'
		);

		expect( impact ).toMatchObject( {
			fallbackNames: [ 'Fallback' ],
			disablesProtection: false,
			protectionWasEnabled: true,
		} );
	} );

	test( 'last-provider disconnect stages protection off and preserves rules', () => {
		const settings = {
			matching_rule: 1,
			black_list: 'US',
			validation: { public: 3, login: 1 },
			public: { matching_rule: 1, black_list: 'US:State:Kentucky' },
			providers: { Native: 'secret', Cache: '@' },
		};
		const impact = {
			disablesProtection: true,
		};
		const { next, snapshot } = stageProviderDisconnect(
			settings,
			'Native',
			impact
		);

		expect( next.providers.Native ).toBe( '' );
		expect( next.providers.Cache ).toBe( '@' );
		expect( next.matching_rule ).toBe( -1 );
		expect( next.validation ).toEqual( { public: 2, login: 1 } );
		expect( next.public ).toEqual( settings.public );
		expect( next.black_list ).toBe( settings.black_list );
		expect( restoreProviderDisconnect( next, snapshot ) ).toEqual(
			settings
		);
	} );

	test( 'fallback disconnect leaves every protection field untouched', () => {
		const settings = {
			matching_rule: 0,
			validation: { public: 5 },
			providers: { Native: 'secret', Fallback: '@' },
		};
		const { next } = stageProviderDisconnect( settings, 'Native', {
			disablesProtection: false,
		} );

		expect( next.matching_rule ).toBe( 0 );
		expect( next.validation.public ).toBe( 5 );
		expect( protectionEnabled( next ) ).toBe( true );
	} );

	test.each( [
		[ 0, 0 ],
		[ 1, 0 ],
		[ 2, 2 ],
		[ 3, 2 ],
		[ 5, 4 ],
	] )( 'clears only the public protection bit in %s', ( value, expected ) => {
		expect( clearPublicProtection( value ) ).toBe( expected );
	} );
} );
