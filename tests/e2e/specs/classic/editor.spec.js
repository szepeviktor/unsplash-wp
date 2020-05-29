/**
 * WordPress dependencies
 */
import { visitAdminPage } from '@wordpress/e2e-test-utils';

/**
 * Internal dependencies
 */
import { activatePlugin, clickButton, deactivatePlugin, clickSelector } from '../../utils';
import {
	UNSPLASH_LIBRARY_BUTTON,
	UNSPLASH_LIBRARY_SEARCH_INPUT,
	UNSPLASH_CONTRAINER,
	UNSPLASH_MODAL,
	UNSPLASH_NO_RESULTS,
} from '../../constants';

describe( 'Classic editor', () => {
	beforeAll( async () => {
		await activatePlugin( 'classic-editor' );
	} );

	afterAll( async () => {
		await deactivatePlugin( 'classic-editor' );
	} );

	beforeEach( async () => {
		await visitAdminPage( 'post-new.php', {} );
		await clickButton( 'Add Media' );
		await page.waitForSelector( UNSPLASH_MODAL );
		await clickSelector( UNSPLASH_LIBRARY_BUTTON );
	} );

	it( 'should the tab exist', async () => {
		// Wait unsplash tab.
		await expect( page ).toMatchElement( UNSPLASH_LIBRARY_BUTTON );
	} );

	it( 'Search: results found', async () => {
		await page.waitForSelector( UNSPLASH_LIBRARY_SEARCH_INPUT );
		await page.focus( UNSPLASH_LIBRARY_SEARCH_INPUT );
		await page.keyboard.type( 'WordPress' );
		await page.waitForSelector( UNSPLASH_CONTRAINER );
		await expect( page ).toMatchElement( UNSPLASH_CONTRAINER );
	} );

	it( 'Search: no results found', async () => {
		await page.waitForSelector( UNSPLASH_LIBRARY_SEARCH_INPUT );
		await page.focus( UNSPLASH_LIBRARY_SEARCH_INPUT );
		await page.keyboard.type( 'dsfdsfs' );
		await page.waitForSelector( UNSPLASH_NO_RESULTS );
		await expect( page ).toMatchElement( UNSPLASH_NO_RESULTS );
	} );

	it( 'insert image', async () => {
		await page.waitForSelector( UNSPLASH_CONTRAINER );
		const btnSelector =
			'.unsplash-browser .attachments .unsplash-attachment:first-of-type';
		await clickSelector( btnSelector );
		const btnSelect = '.media-button-insert';
		await clickSelector( btnSelect );
		await page.waitFor( 3000 );
		const imgClass = 'size-large';
		// Switch to HTML mode
		await expect( page ).toClick( '#content-html' );

		const textEditorContent = await page.$eval(
			'.wp-editor-area',
			element => element.value
		);
		expect( textEditorContent ).toContain( imgClass );
	} );
} );
