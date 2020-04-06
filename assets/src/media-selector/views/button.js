import isUnsplashImage from '../helpers/isUnsplashImage';
import importImages from '../helpers/importImages';

const Button = wp.media.view.Button.extend( {
	/**
	 * @param {Object} event
	 */
	click( event ) {
		if ( '#' === this.attributes.href ) {
			event.preventDefault();
		}

		const state = this.controller.state();
		const selections = state.get( 'selection' );

		const hasUnsplashSelections = selections.some( attachment =>
			isUnsplashImage( attachment )
		);

		if ( ! hasUnsplashSelections ) {
			this.options.click.apply( this, arguments );
			return;
		}

		const toolbar = this.views.parent.views.parent;
		const spinner = toolbar.get( 'button-spinner' );

		this.$el.attr( 'disabled', true ); // Disable the button.
		spinner.show();

		importImages( selections )
			.then( () => {
				this.$el.attr( 'disabled', false ); // Enable button.
				spinner.hide();

				if ( this.options.click && ! this.model.get( 'disabled' ) ) {
					this.options.click.apply( this, arguments );
				}
			} )
			.catch( () => {
				// TODO: let user know import failed
				this.$el.attr( 'disabled', false ); // Enable button.
				spinner.hide();
			} );
	},
} );

export default Button;