<?php

namespace Winkel;

use ExtensionRegistry;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use OutputPage;
use RequestContext;
use Skin;
use SkinTemplate;
use SkinWinkel;
use User;

/**
 * Presentation hook handlers for Winkel skin.
 *
 * Hook handler method names should be in the form of:
 *	on<HookName>()
 */
class Hooks {
	/**
	 * BeforePageDisplayMobile hook handler
	 *
	 * Make Legacy Winkel responsive when $wgWinkelResponsive = true
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage $out
	 * @param SkinTemplate $sk
	 */
	public static function onBeforePageDisplay( OutputPage $out, $sk ) {
		if ( !$sk instanceof SkinWinkel ) {
			return;
		}

		$skinVersionLookup = new SkinVersionLookup(
			$out->getRequest(), $sk->getUser(), self::getServiceConfig()
		);

		$mobile = false;
		if ( ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) ) {

			$mobFrontContext = MediaWikiServices::getInstance()->getService( 'MobileFrontend.Context' );
			$mobile = $mobFrontContext->shouldDisplayMobileView();
		}

		if ( $mobile || $sk->getConfig()->get( 'WinkelResponsive' ) ) {
			$out->addMeta( 'viewport', 'width=device-width, initial-scale=1' );
			$out->addModuleStyles( 'skins.winkel.styles.responsive' );
		}
	}

	/**
	 * Add icon class to an existing navigation item inside a menu hook.
	 * See self::onSkinTemplateNavigation.
	 * @param array $item
	 * @return array
	 */
	private static function navigationLinkToIcon( array $item ) {
		if ( !isset( $item['class'] ) ) {
			$item['class'] = '';
		}
		$item['class'] = rtrim( 'icon ' . $item['class'], ' ' );
		return $item;
	}

	/**
	 * Upgrades Winkel's watch action to a watchstar.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 * @param SkinTemplate $sk
	 * @param array &$content_navigation
	 */
	public static function onSkinTemplateNavigation( $sk, &$content_navigation ) {
		if (
			$sk->getSkinName() === 'winkel' &&
			$sk->getConfig()->get( 'WinkelUseIconWatch' )
		) {
			$key = null;
			if ( isset( $content_navigation['actions']['watch'] ) ) {
				$key = 'watch';
			}
			if ( isset( $content_navigation['actions']['unwatch'] ) ) {
				$key = 'unwatch';
			}

			// Promote watch link from actions to views and add an icon
			if ( $key !== null ) {
				$content_navigation['views'][$key] = self::navigationLinkToIcon(
					$content_navigation['actions'][$key]
				);
				unset( $content_navigation['actions'][$key] );
			}
		}
	}

	/**
	 * Add Winkel preferences to the user's Special:Preferences page directly underneath skins.
	 *
	 * @param User $user User whose preferences are being modified.
	 * @param array[] &$prefs Preferences description array, to be fed to a HTMLForm object.
	 */
	public static function onGetPreferences( User $user, array &$prefs ) {
		if ( !self::getConfig( Constants::CONFIG_KEY_SHOW_SKIN_PREFERENCES ) ) {
			// Do not add Winkel skin specific preferences.
			return;
		}

		$skinVersionLookup = new SkinVersionLookup(
			RequestContext::getMain()->getRequest(), $user, self::getServiceConfig()
		);

		// Preferences to add.
		$winkelPrefs = [
			Constants::PREF_KEY_SIDEBAR_VISIBLE => [
				'type' => 'api',
				'default' => self::getConfig( Constants::CONFIG_KEY_DEFAULT_SIDEBAR_VISIBLE_FOR_AUTHORISED_USER )
			],
		];

		// Seek the skin preference section to add Winkel preferences just below it.
		$skinSectionIndex = array_search( 'skin', array_keys( $prefs ) );
		if ( $skinSectionIndex !== false ) {
			// Skin preference section found. Inject Winkel skin-specific preferences just below it.
			// This pattern can be found in Popups too. See T246162.
			$winkelSectionIndex = $skinSectionIndex + 1;
			$prefs = array_slice( $prefs, 0, $winkelSectionIndex, true )
				+ $winkelPrefs
				+ array_slice( $prefs, $winkelSectionIndex, null, true );
		} else {
			// Skin preference section not found. Just append Winkel skin-specific preferences.
			$prefs += $winkelPrefs;
		}
	}

	/**
	 * Hook executed on user's Special:Preferences form save. This is used to convert the boolean
	 * presentation of skin version to a version string. That is, a single preference change by the
	 * user may trigger two writes: a boolean followed by a string.
	 *
	 * @param array $formData Form data submitted by user
	 * @param HTMLForm $form A preferences form
	 * @param User $user Logged-in user
	 * @param bool &$result Variable defining is form save successful
	 * @param array $oldPreferences
	 */
	public static function onPreferencesFormPreSave(
		array $formData,
		HTMLForm $form,
		User $user,
		&$result,
		$oldPreferences
	) {
	}

	/**
	 * Called one time when initializing a users preferences for a newly created account.
	 *
	 * @param User $user Newly created user object.
	 * @param bool $isAutoCreated
	 */
	public static function onLocalUserCreated( User $user, $isAutoCreated ) {
	}

	/**
	 * Called when OutputPage::headElement is creating the body tag to allow skins
	 * and extensions to add attributes they might need to the body of the page.
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @param string[] &$bodyAttrs
	 */
	public static function onOutputPageBodyAttributes( OutputPage $out, Skin $sk, &$bodyAttrs ) {
		if ( !$sk instanceof SkinWinkel ) {
			return;
		}

		$bodyAttrs['class'] .= ' skin-winkel-legacy';
	}

	/**
	 * Get a configuration variable such as `Constants::CONFIG_KEY_SHOW_SKIN_PREFERENCES`.
	 *
	 * @param string $name Name of configuration option.
	 * @return mixed Value configured.
	 * @throws \ConfigException
	 */
	private static function getConfig( $name ) {
		return self::getServiceConfig()->get( $name );
	}

	/**
	 * @return \Config
	 */
	private static function getServiceConfig() {
		return MediaWikiServices::getInstance()->getService( Constants::SERVICE_CONFIG );
	}
}
