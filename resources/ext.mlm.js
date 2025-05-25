( function () {
	mw.mlm = mw.mlm || {};
	$( document ).on( 'click', '#ca-mlm', ( e ) => {
		if ( !mw.mlm.dialog ) {
			return;
		}
		const windowManager = new OO.ui.WindowManager( {
			factory: mw.mlm.factory
		} );
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );

		windowManager.openWindow( 'mlm' );
		e.stopPropagation();
		return false;
	} );

	mw.loader.using( 'oojs-ui', () => {

		mw.mlm.factory = new OO.Factory();

		mw.mlm.srcTitle = mw.config.get(
			'mlmSourceTitle',
			''
		);
		mw.mlm.translations = mw.config.get(
			'mlmTranslations',
			{}
		);
		mw.mlm.languages = mw.config.get(
			'mlmLanguages',
			[]
		);
		mw.mlm.languageFlags = mw.config.get(
			'mlmLanguageFlags',
			{}
		);
		let css = '';
		for ( const code in mw.mlm.languageFlags ) {
			css += ' .oo-ui-icon-mlm-lang-flag-' + code + ' {\n\
					background-image: url("' + mw.mlm.languageFlags[ code ] + '");\n\
				}\n\
			';
		}
		$( "<style type='text/css'>" + css + '</style>' ).appendTo( 'head' );
		const lang = mw.config.get( 'wgContentLanguage' ).split( '-' );
		mw.mlm.lang = lang[ 0 ];

		mw.mlm.dialog = function ( config ) {
			this.translations = {};
			mw.mlm.dialog.super.call( this, config );
		};
		OO.inheritClass( mw.mlm.dialog, OO.ui.ProcessDialog );
		OO.initClass( mw.mlm.dialog );

		// Specify a symbolic name (e.g., 'simple', in this example)
		// using the static 'name' property.
		mw.mlm.dialog.static.name = 'mlm';
		mw.mlm.dialog.static.title = mw.message(
			'mlm-contentaction-label'
		).plain();
		mw.mlm.dialog.static.actions = [ {
			action: 'save',
			label: mw.message( 'mlm-input-label-save' ).plain(),
			flags: [ 'primary', 'constructive' ],
			disabled: true
		}, {
			action: 'cancel',
			label: mw.message( 'mlm-input-label-cancel' ).plain(),
			flags: 'safe'
		}, {
			action: 'delete',
			label: mw.message( 'mlm-input-label-delete' ).plain(),
			flags: 'destructive'
		} ];

		mw.mlm.dialog.prototype.initialize = function () {
			mw.mlm.dialog.super.prototype.initialize.call( this );

			this.panel = new OO.ui.PanelLayout( { padded: true, expanded: false, id: 'mlm-manager' } );
			this.content = new OO.ui.FieldsetLayout();
			this.errorSection = new OO.ui.Layout();
			this.errorSection.$element.css( 'color', 'red' );
			this.errorSection.$element.css( 'font-weight', 'bold' );
			this.errorSection.$element.css( 'text-align', 'center' );

			const options = [];
			for ( var i = 0; i < mw.mlm.languages.length; i++ ) {
				if ( mw.mlm.languages[ i ] === mw.mlm.lang ) {
					continue;
				}
				options.push( new OO.ui.MenuOptionWidget( {
					icon: 'mlm-lang-flag-' + mw.mlm.languages[ i ],
					data: mw.mlm.languages[ i ],
					label: mw.mlm.languages[ i ]
				} ) );
			}

			this.srcLang = new OO.ui.ButtonWidget( { disabled: true } );
			this.srcLang.$element.find( 'a' ).css(
				'background',
				'url(' + mw.mlm.languageFlags[ mw.mlm.lang ] + ')'
			);
			this.srcLang.$element.find( 'a' ).css(
				'background-size',
				'40px 30px'
			);
			this.srcLang.$element.find( 'a' ).css(
				'background-repeat',
				'no-repeat'
			);
			this.srcLang.$element.find( 'a' ).css(
				'width',
				'40px'
			);
			this.srcText = new OO.ui.TextInputWidget( {
				value: mw.mlm.srcTitle,
				required: true,
				label: mw.message( 'mlm-input-label-sourcetitle' ).plain(),
				disabled: mw.mlm.srcTitle !== ''
			} );
			this.srcText.on( 'change', this.onSrcTextChange.bind( this ) );

			this.srcSection = new OO.ui.HorizontalLayout( {
				items: [
					this.srcLang,
					this.srcText
				]
			} );
			this.srcSection.$element.css( 'display', 'flex' );

			this.translationsSection = new OO.ui.FieldsetLayout();
			for ( var i = 0; i < mw.mlm.translations.length; i++ ) {
				this.updateTranslations( mw.mlm.translations[ i ] );
			}

			this.translationLang = new OO.ui.DropdownWidget( {
				value: '',
				menu: { items: options },
				label: mw.message( 'allmessages-language' ).plain()
			} );
			this.translationLang.menu.on( 'select', function ( item ) {
				this.setIcon( item.getIcon() );
			}.bind( this.translationLang ) );

			this.translationText = new OO.ui.TextInputWidget( {
				value: mw.mlm.srcTitle === '' ? mw.config.get( 'wgTitle' ) : '',
				label: mw.message(
					'mlm-input-label-translationtitles',
					1
				).text()
			} );
			this.translationAdd = new OO.ui.ButtonWidget( {
				label: mw.message( 'mlm-input-label-add' ).plain(),
				flags: [ 'primary', 'progressive' ]
			} );

			const me = this;
			this.translationAdd.on( 'click', me.onTranslationAdd.bind( this ) );

			this.addSection = new OO.ui.FieldsetLayout( {
				items: [
					this.translationLang,
					this.translationText,
					this.translationAdd
				]
			} );

			this.content.addItems( [
				this.errorSection,
				new OO.ui.LabelWidget( {
					label: mw.message( 'mlm-input-label-sourcetitle-section' ).plain()
				} ),
				this.srcSection,
				new OO.ui.LabelWidget( {
					label: mw.message( 'mlm-input-label-translationtitles-section' ).plain()
				} ),
				this.addSection,
				this.translationsSection
			] );

			this.panel.$element.append( this.content.$element );
			this.$body.append( this.panel.$element );
		};

		mw.mlm.dialog.prototype.save = function () {
			const api = new mw.Api();
			return api.postWithToken( 'csrf', {
				action: 'mlm-tasks',
				task: 'save',
				format: 'json',
				taskData: JSON.stringify( this.getData() )
			} );
		};

		mw.mlm.dialog.prototype.delete = function () {
			const api = new mw.Api();
			return api.postWithToken( 'csrf', {
				action: 'mlm-tasks',
				task: 'delete',
				format: 'json',
				taskData: JSON.stringify( this.getData() )
			} );
		};

		mw.mlm.dialog.prototype.getData = function () {
			const data = {};

			data.srcText = this.srcText.value;
			data.translations = {};
			for ( const i in this.translations ) {
				const translation = this.translations[ i ];
				data.translations[ i ] = {
					lang: i,
					text: translation.input.value
				};
			}
			return data;
		};

		mw.mlm.dialog.prototype.getActionProcess = function ( action ) {
			return mw.mlm.dialog.super.prototype.getActionProcess.call( this, action )
				.next( () => 1000, this )
				.next( function () {
					let closing;
					if ( action === 'save' ) {
						if ( this.broken ) {
							this.broken = false;
							return new OO.ui.Error( 'Server did not respond' );
						}
						var me = this;
						return me.save().done( ( data ) => {
						// success is just emtyed out somewhere for no reason
							if ( data.message.length === 0 ) {
								closing = me.close( { action: action } );
								me.reloadPage();
								return closing;
							}
							me.showRequestErrors( data.message );
						} );
					} else if ( action === 'cancel' ) {
						closing = this.close( { action: action } );
						return closing;
					} else if ( action === 'delete' ) {
						var me = this;
						return this.delete().done( ( data ) => {
						// success is just emtyed out somewhere for no reason
							if ( data.message.length === 0 ) {
								closing = me.close( { action: action } );
								me.reloadPage();
								return closing;
							}
							me.showRequestErrors( data.message );
						} );
						// eslint-disable-next-line no-unreachable
						return closing;
					}

					return mw.mlm.dialog.super.prototype.getActionProcess.call(
						this,
						action
					);
				}, this );
		};

		mw.mlm.dialog.prototype.showRequestErrors = function ( errors ) {
			var errors = errors || {};

			let error = '';
			for ( const i in errors ) {
				error += errors[ i ] + '<br />';
			}

			this.errorSection.$element.html( error );
		};

		mw.mlm.dialog.prototype.reloadPage = function () {
			window.location = mw.util.getUrl(
				mw.config.get( 'wgTitle' )
			);
		};

		mw.mlm.dialog.prototype.onSrcTextChange = function ( value ) {
			const me = this;

			const api = new mw.Api();
			api.postWithToken( 'csrf', {
				action: 'mlm-tasks',
				task: 'get',
				format: 'json',
				taskData: JSON.stringify( {
					srcText: value
				} )
			} )
				.done( ( response, jqXHR ) => {
					if ( !response.success ) {
						return;
					}

					for ( let i = 0; i < response.payload.length; i++ ) {
						const translation = response.payload[ i ];
						me.updateTranslations( {
							lang: translation.lang,
							text: translation.text
						} );
					}

					me.getActions().setAbilities( {
						save: true
					} );
				} );
		};

		mw.mlm.dialog.prototype.onTranslationAdd = function () {
			this.updateTranslations( {
				lang: this.translationLang.getMenu().getSelectedItem().getData(),
				text: this.translationText.getValue()
			} );
			this.getActions().setAbilities( {
				save: true
			} );
		};

		// eslint-disable-next-line no-shadow
		mw.mlm.dialog.prototype.onTranslationDelete = function ( lang ) {
			this.updateTranslations( {
				lang: lang,
				text: ''
			}, true );
			this.getActions().setAbilities( {
				save: true
			} );
		};

		mw.mlm.dialog.prototype.updateTranslations = function ( translation, removeOnly ) {
			removeOnly = removeOnly || false;
			if ( mw.mlm.srcTitle === translation.text ) {
				return;
			}
			if ( this.translations[ translation.lang ] ) {
				this.translations[ translation.lang ].layout.$element.remove();
				delete this.translations[ translation.lang ];
			}
			if ( removeOnly ) {
				return;
			}
			this.translations[ translation.lang ] = {
				lang: new OO.ui.ButtonWidget( {
					disabled: true,
					title: translation.lang
				} ),
				input: new OO.ui.TextInputWidget( {
					value: translation.text,
					required: true
				} ),
				delete: new OO.ui.ButtonWidget( {
					icon: 'trash',
					flags: 'destructive',
					title: mw.message( 'mlm-input-label-delete' ).plain()
				} )
			};

			this.translations[ translation.lang ].lang.$element.find( 'a' ).css(
				'background',
				'url(' + mw.mlm.languageFlags[ translation.lang ] + ')'
			);
			this.translations[ translation.lang ].lang.$element.find( 'a' ).css(
				'background-size',
				'40px 30px'
			);
			this.translations[ translation.lang ].lang.$element.find( 'a' ).css(
				'background-repeat',
				'no-repeat'
			);
			this.translations[ translation.lang ].lang.$element.find( 'a' ).css(
				'width',
				'40px'
			);

			this.translations[ translation.lang ].layout = new OO.ui.HorizontalLayout( {
				items: [
					this.translations[ translation.lang ].lang,
					this.translations[ translation.lang ].input,
					this.translations[ translation.lang ].delete
				]
			} );
			this.translations[ translation.lang ].layout.$element.css(
				'display',
				'flex'
			);

			const me = this;
			this.translations[ translation.lang ].delete.on(
				'click',
				me.onTranslationDelete.bind( this ),
				[ translation.lang ]
			);

			this.translationsSection.addItems( [
				this.translations[ translation.lang ].layout
			] );
		};

		mw.mlm.factory.register( mw.mlm.dialog );
	} );
}() );
