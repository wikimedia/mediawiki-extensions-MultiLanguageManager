
( function( mw, $ ){
	mw.mlm = mw.mlm || {};
	$(document).on( 'click', '#ca-mlm', function( e ) {
		if( !mw.mlm.dialog ) {
			return;
		}
		var windowManager = new OO.ui.WindowManager( {
			factory: mw.mlm.factory
		} );
		$( 'body' ).append( windowManager.$element );

		windowManager.openWindow( 'mlm' );
		e.stopPropagation();
		return false;
	});

	mw.loader.using( 'oojs-ui', function() {

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
		var lang =  mw.config.get( 'wgContentLanguage' ).split('-');
		mw.mlm.lang = lang[0];

		mw.mlm.dialog = function( config ) {
			this.translations = {};
			mw.mlm.dialog.super.call( this, config );
		};
		OO.inheritClass( mw.mlm.dialog, OO.ui.ProcessDialog );
		OO.initClass( mw.mlm.dialog );

		// Specify a symbolic name (e.g., 'simple', in this example) using the static 'name' property.
		mw.mlm.dialog.static.name = 'mlm';
		mw.mlm.dialog.static.title = mw.message(
			'mlm-contentaction-label'
		).plain();
		mw.mlm.dialog.static.actions = [{
			action: 'save',
			label: mw.message( 'mlm-input-label-save' ).plain(),
			flags: [ 'primary', 'progressive' ]
		}, {
			action: 'cancel',
			label: mw.message( 'mlm-input-label-cancel' ).plain(),
			flags: 'safe'
		}, {
			action: 'delete',
			label: mw.message( 'mlm-input-label-delete' ).plain(),
			flags: 'destructive'
		}];

		mw.mlm.dialog.prototype.initialize = function () {
			mw.mlm.dialog.super.prototype.initialize.call( this );

			this.panel = new OO.ui.PanelLayout( { padded: true, expanded: false, id: 'mlm-manager' } );
			this.content = new OO.ui.FieldsetLayout();
			this.errorSection = new OO.ui.Layout();
			this.errorSection.$element.css( 'color', 'red' );
			this.errorSection.$element.css( 'font-weight', 'bold' );
			this.errorSection.$element.css( 'text-align', 'center' );

			var options = [];
			for( var i = 0; i < mw.mlm.languages.length; i++ ) {
				if( mw.mlm.languages[i] === mw.mlm.lang ) {
					continue;
				}
				options.push( {
					data: mw.mlm.languages[i],
					label: mw.mlm.languages[i]
				});
			}

			this.srcLang = new OO.ui.ButtonWidget( {disabled: true} );
			this.srcLang.$element.find('a').css(
				'background',
				'url(' + mw.mlm.languageFlags[mw.mlm.lang] + ')'
			);
			this.srcLang.$element.find('a').css(
				'background-size',
				'40px 30px'
			);
			this.srcLang.$element.find('a').css(
				'background-repeat',
				'no-repeat'
			);
			this.srcLang.$element.find('a').css(
				'width',
				'40px'
			);
			this.srcText = new OO.ui.TextInputWidget( {
				value: mw.mlm.srcTitle,
				required: true,
				label: mw.message( 'mlm-input-label-sourcetitle' ).plain(),
				disabled: mw.mlm.srcTitle === '' ? false : true
			});

			this.srcSection = new OO.ui.HorizontalLayout( {
				items: [
					this.srcLang,
					this.srcText
				]
			});
			this.srcSection.$element.css( 'display', 'flex' );

			this.translationsSection = new OO.ui.FieldsetLayout();
			for( var i = 0; i < mw.mlm.translations.length; i++ ) {
				this.updateTranslations( mw.mlm.translations[i] );
			}

			this.translationLang = new OO.ui.DropdownInputWidget( {
				value: '',
				options: options,
				label: 'lang'
			});
			this.translationText = new OO.ui.TextInputWidget( {
				value: mw.mlm.srcTitle === '' ? mw.config.get( 'wgTitle' ) : '',
				label: mw.message(
					'mlm-input-label-translationtitles',
					1
				).text()
			});
			this.translationAdd = new OO.ui.ButtonWidget( {
				label: mw.message( 'mlm-input-label-add' ).plain(),
				flags: [ 'primary', 'progressive' ]
			});

			var me = this;
			this.translationAdd.on( 'click', me.onTranslationAdd.bind( this ) );

			this.addSection = new OO.ui.FieldsetLayout( {
				items: [
					this.translationLang,
					this.translationText,
					this.translationAdd
				]
			});

			this.content.addItems([
				this.errorSection,
				this.srcSection,
				this.addSection,
				this.translationsSection
			]);

			this.panel.$element.append( this.content.$element );
			this.$body.append( this.panel.$element );
		};

		mw.mlm.dialog.prototype.save = function() {
			var api = new mw.Api();
			return api.postWithToken( 'csrf', {
				action: 'mlm-tasks',
				task: 'save',
				format: 'json',
				taskData: JSON.stringify( this.getData() )
			});
		};

		mw.mlm.dialog.prototype.delete = function() {
			var api = new mw.Api();
			return api.postWithToken( 'csrf', {
				action: 'mlm-tasks',
				task: 'delete',
				format: 'json',
				taskData: JSON.stringify( this.getData() )
			});
		};

		mw.mlm.dialog.prototype.getData = function() {
			var data = {};

			data.srcText = this.srcText.value;
			data.translations = {};
			for( var i in this.translations ) {
				var translation = this.translations[i];
				data.translations[i] = {
					lang: i,
					text: translation.input.value
				};
			}
			return data;
		};

		mw.mlm.dialog.prototype.getActionProcess = function ( action ) {
			return mw.mlm.dialog.super.prototype.getActionProcess.call( this, action )
			.next( function () {
				return 1000;
			}, this )
			.next( function () {
				var closing;
				if ( action === 'save' ) {
					if ( this.broken ) {
						this.broken = false;
						return new OO.ui.Error( 'Server did not respond' );
					}
					var me = this;
					return me.save().done( function( data ) {
						//success is just emtyed out somewhere for no reason
						if( data.message.length === 0 ) {
							closing = me.close( { action: action } );
							me.reloadPage();
							return closing;
						}
						me.showRequestErrors( data.message );
					});
				} else if ( action === 'cancel' ) {
					closing = this.close( { action: action } );
					return closing;
				}
				else if ( action === 'delete' ) {
					var me = this;
					return this.delete().done( function( data ) {
						//success is just emtyed out somewhere for no reason
						if( data.message.length === 0 ) {
							closing = me.close( { action: action } );
							me.reloadPage();
							return closing;
						}
						me.showRequestErrors( data.message );
					});
					return closing;
				}

				return mw.mlm.dialog.super.prototype.getActionProcess.call(
					this,
					action
				);
			}, this );
		};

		mw.mlm.dialog.prototype.showRequestErrors = function( errors ) {
			var errors = errors || {};

			var error = '';
			for( var i in errors ) {
				error += errors[i] + "<br />";
			}

			this.errorSection.$element.html( error );
		};

		mw.mlm.dialog.prototype.reloadPage = function() {
			window.location = mw.util.getUrl(
				mw.config.get( 'wgTitle' )
			);
		};

		mw.mlm.dialog.prototype.onTranslationAdd = function(){
			this.updateTranslations( {
				'lang': this.translationLang.value,
				'text': this.translationText.value
			});
		};

		mw.mlm.dialog.prototype.onTranslationDelete = function( lang ){
			this.updateTranslations( {
				'lang': lang,
				'text': ''
			}, true);
		};

		mw.mlm.dialog.prototype.updateTranslations = function ( translation, removeOnly ) {
			removeOnly = removeOnly || false;
			if( mw.mlm.srcTitle === translation.text ) {
				return;
			}
			if( this.translations[translation.lang] ) {
				this.translations[translation.lang].layout.$element.remove();
				delete this.translations[translation.lang];
			}
			if( removeOnly ) {
				return;
			}
			this.translations[translation.lang] = {
				'lang': new OO.ui.ButtonWidget( {
					disabled: true,
					title: translation.lang
				}),
				'input': new OO.ui.TextInputWidget( {
					value: translation.text,
					required: true
				}),
				'delete': new OO.ui.ButtonWidget( {
					icon: 'trash',
					flags: 'destructive',
					title: mw.message( 'mlm-input-label-delete' ).plain()
				})
			};

			this.translations[translation.lang].lang.$element.find('a').css(
				'background',
				'url(' + mw.mlm.languageFlags[translation.lang] + ')'
			);
			this.translations[translation.lang].lang.$element.find('a').css(
				'background-size',
				'40px 30px'
			);
			this.translations[translation.lang].lang.$element.find('a').css(
				'background-repeat',
				'no-repeat'
			);
			this.translations[translation.lang].lang.$element.find('a').css(
				'width',
				'40px'
			);

			this.translations[translation.lang].layout = new OO.ui.HorizontalLayout( {
				items: [
					this.translations[translation.lang].lang,
					this.translations[translation.lang].input,
					this.translations[translation.lang].delete
				]
			});
			this.translations[translation.lang].layout.$element.css(
				'display',
				'flex'
			);

			var me = this;
			this.translations[translation.lang].delete.on(
				'click',
				me.onTranslationDelete.bind( this ),
				[translation.lang]
			);

			this.translationsSection.addItems([
				this.translations[translation.lang].layout
			]);
		};

		mw.mlm.factory.register( mw.mlm.dialog );
	});
})( mediaWiki, jQuery );