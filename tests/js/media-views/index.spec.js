/**
 * Internal dependencies
 */
import { checkType } from '../../../assets/src/media-views';

/**
 * Mock domReady
 */
jest.mock( '@wordpress/dom-ready', () => () => ( {
	domReady: () => {},
} ) );

describe( 'media-views', () => {
	describe( 'SelectUnsplash', () => {
		it( 'should do something', () => {
			expect( true ).toBe( true );
		} );
	} );

	describe( 'QueryUnsplash', () => {
		it( 'should do something', () => {
			expect( true ).toBe( true );
		} );
	} );

	describe( 'checkType', () => {
		it( 'should not return true', () => {
			expect( checkType( [] ) ).toBe( false );
		} );

		it( 'should return true', () => {
			expect( checkType( [ 'image' ] ) ).toBe( true );
		} );
	} );
} );
