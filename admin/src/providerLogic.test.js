import {
	activeProviderStatuses,
	formatAllowance,
	makeExclusiveProviderMap,
	quotaBlocksProvider,
	quotaSummary,
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
} );
