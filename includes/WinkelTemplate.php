<?php
/**
 * Winkel - Modern version of MonoBook with fresh look and many usability
 * improvements.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Skins
 */

use Winkel\Constants;

/**
 * QuickTemplate subclass for Winkel
 * @ingroup Skins
 * @deprecated Since 1.35, duplicate class locally if its functionality is needed.
 * Extensions or skins should extend it under no circumstances.
 */
class WinkelTemplate extends BaseTemplate {
	/** @var array of alternate message keys for menu labels */
	private const MENU_LABEL_KEYS = [
		'cactions' => 'winkel-more-actions',
		'tb' => 'toolbox',
		'personal' => 'personaltools',
		'lang' => 'otherlanguages',
	];
	/** @var int */
	private const MENU_TYPE_DEFAULT = 0;
	/** @var int */
	private const MENU_TYPE_TABS = 1;
	/** @var int */
	private const MENU_TYPE_DROPDOWN = 2;
	private const MENU_TYPE_PORTAL = 3;

	/** @var TemplateParser */
	private $templateParser;
	/** @var string File name of the root (master) template without folder path and extension */
	private $templateRoot;

	/**
	 * @param Config $config
	 * @param TemplateParser $templateParser
	 */
	public function __construct(
		Config $config,
		TemplateParser $templateParser
	) {
		parent::__construct( $config );

		$this->templateParser = $templateParser;
		$this->templateRoot = 'skin';
	}

	/**
	 * @return Config
	 */
	private function getConfig() {
		return $this->config;
	}

	/**
	 * The template parser might be undefined. This function will check if it set first
	 *
	 * @return TemplateParser
	 */
	protected function getTemplateParser() {
		if ( $this->templateParser === null ) {
			throw new \LogicException(
				'TemplateParser has to be set first via setTemplateParser method'
			);
		}
		return $this->templateParser;
	}

	/**
	 * @deprecated Please use Skin::getTemplateData instead
	 * @return array Returns an array of data
	 */
	private function getSkinData() : array {
		// @phan-suppress-next-line PhanUndeclaredMethod
		$contentNavigation = $this->getSkin()->getMenuProps();
		$skin = $this->getSkin();
		$out = $skin->getOutput();
		$title = $out->getTitle();

		$mainPageHref = Skin::makeMainPageUrl();
		// From Skin::getNewtalks(). Always returns string, cast to null if empty.
		$newTalksHtml = $skin->getNewtalks() ?: null;

		$out->addStyle("Winkel/google-fonts.css");

		// @phan-suppress-next-line PhanUndeclaredMethod
		$commonSkinData = $skin->getTemplateData() + [
			'html-headelement' => $out->headElement( $skin ),
			'page-langcode' => $title->getPageViewLanguage()->getHtmlCode(),
			'page-isarticle' => (bool)$out->isArticle(),

			// Remember that the string '0' is a valid title.
			// From OutputPage::getPageTitle, via ::setPageTitle().
			'html-title' => $out->getPageTitle(),
			'msg-tagline' => $skin->msg( 'tagline' )->text(),

			'html-newtalk' => $newTalksHtml ? '<div class="usermessage">' . $newTalksHtml . '</div>' : '',

			'msg-winkel-jumptonavigation' => $skin->msg( 'winkel-jumptonavigation' )->text(),
			'msg-winkel-jumptosearch' => $skin->msg( 'winkel-jumptosearch' )->text(),

			'html-printfooter' => $skin->printSource(),
			'html-categories' => $skin->getCategories(),
			'data-footer' => $this->getFooterData(),
			'html-navigation-heading' => $skin->msg( 'navigation-heading' ),
			'data-search-box' => $this->buildSearchProps(),

			// Header
			'data-logos' => ResourceLoaderSkinModule::getAvailableLogos( $this->getConfig() ),
			'msg-sitetitle' => $skin->msg( 'sitetitle' )->text(),
			'msg-sitesubtitle' => $skin->msg( 'sitesubtitle' )->text(),
			'main-page-href' => $mainPageHref,
			'data-sidebar' => $this->buildSidebar()
		] + $this->getMenuProps();

		return $commonSkinData;
	}

	/**
	 * Renders the entire contents of the HTML page.
	 */
	public function execute() {
		$tp = $this->getTemplateParser();
		echo $tp->processTemplate( $this->templateRoot, $this->getSkinData() );
	}

	/**
	 * Get rows that make up the footer
	 * @return array for use in Mustache template describing the footer elements.
	 */
	private function getFooterData() : array {
		$skin = $this->getSkin();
		$footerRows = [];
		foreach ( $this->getFooterLinks() as $category => $links ) {
			$items = [];
			$rowId = "footer-$category";

			foreach ( $links as $link ) {
				$items[] = [
					'id' => "$rowId-$link",
					'html' => $this->get( $link, '' ),
				];
			}

			$footerRows[] = [
				'id' => $rowId,
				'className' => null,
				'array-items' => $items
			];
		}

		// If footer icons are enabled append to the end of the rows
		$footerIcons = $this->getFooterIcons( 'icononly' );
		if ( count( $footerIcons ) > 0 ) {
			$items = [];
			foreach ( $footerIcons as $blockName => $blockIcons ) {
				$html = '';
				foreach ( $blockIcons as $icon ) {
					$html .= $skin->makeFooterIcon( $icon );
				}
				$items[] = [
					'id' => 'footer-' . htmlspecialchars( $blockName ) . 'ico',
					'html' => $html,
				];
			}

			$footerRows[] = [
				'id' => 'footer-icons',
				'className' => 'noprint',
				'array-items' => $items,
			];
		}

		ob_start();
		Hooks::run( 'WinkelBeforeFooter', [], '1.35' );
		$htmlHookWinkelBeforeFooter = ob_get_contents();
		ob_end_clean();

		$data = [
			'html-hook-winkel-before-footer' => $htmlHookWinkelBeforeFooter,
			'array-footer-rows' => $footerRows,
		];

		return $data;
	}

	/**
	 * Render a series of portals
	 *
	 * @return array
	 */
	private function buildSidebar() : array {
		$skin = $this->getSkin();
		$portals = $skin->buildSidebar();
		$props = [];
		$languages = null;

		// Render portals
		foreach ( $portals as $name => $content ) {
			if ( $content === false ) {
				continue;
			}

			// Numeric strings gets an integer when set as key, cast back - T73639
			$name = (string)$name;

			switch ( $name ) {
				case 'SEARCH':
					break;
				case 'TOOLBOX':
					$portal = $this->getMenuData(
						'tb', $content, self::MENU_TYPE_PORTAL
					);
					// Run deprecated hook.
					// Use SidebarBeforeOutput instead.
					ob_start();
					Hooks::run( 'WinkelAfterToolbox', [], '1.35' );
					$props[] = $portal + [
						'html-hook-winkel-after-toolbox' => ob_get_clean(),
					];
					break;
				case 'LANGUAGES':
					$portal = $this->getMenuData(
						'lang',
						$content,
						self::MENU_TYPE_PORTAL
					);
					// The language portal will be added provided either
					// languages exist or there is a value in html-after-portal
					// for example to show the add language wikidata link (T252800)
					if ( count( $content ) || $portal['html-after-portal'] ) {
						$languages = $portal;
					}
					break;
				default:
					// Historically some portals have been defined using HTML rather than arrays.
					// Let's move away from that to a uniform definition.
					if ( !is_array( $content ) ) {
						$html = $content;
						$content = [];
						wfDeprecated(
							"`content` field in portal $name must be array."
								. "Previously it could be a string but this is no longer supported.",
							'1.35.0'
						);
					} else {
						$html = false;
					}
					$portal = $this->getMenuData(
						$name, $content, self::MENU_TYPE_PORTAL
					);
					if ( $html ) {
						$portal['html-items'] .= $html;
					}
					$props[] = $portal;
					break;
			}
		}

		$firstPortal = $props[0] ?? null;
		if ( $firstPortal ) {
			$firstPortal[ 'class' ] .= ' portal-first';
		}

		return [
			'has-logo' => true,
			'html-logo-attributes' => Xml::expandAttributes(
				Linker::tooltipAndAccesskeyAttribs( 'p-logo' ) + [
					'class' => 'mw-wiki-logo',
					'href' => Skin::makeMainPageUrl(),
				]
			),
			'array-portals-rest' => array_slice( $props, 1 ),
			'data-portals-first' => $firstPortal,
			'data-portals-languages' => $languages,
		];
	}

	/**
	 * @param string $label to be used to derive the id and human readable label of the menu
	 *  If the key has an entry in the constant MENU_LABEL_KEYS then that message will be used for the
	 *  human readable text instead.
	 * @param array $urls to convert to list items stored as string in html-items key
	 * @param int $type of menu (optional) - a plain list (MENU_TYPE_DEFAULT),
	 *   a tab (MENU_TYPE_TABS) or a dropdown (MENU_TYPE_DROPDOWN)
	 * @param array $options (optional) to be passed to makeListItem
	 * @param bool $setLabelToSelected (optional) the menu label will take the value of the
	 *  selected item if found.
	 * @return array
	 */
	private function getMenuData(
		string $label,
		array $urls = [],
		int $type = self::MENU_TYPE_DEFAULT,
		array $options = [],
		bool $setLabelToSelected = false
	) : array {
		$skin = $this->getSkin();
		$extraClasses = [
			self::MENU_TYPE_DROPDOWN => 'winkel-menu winkel-menu-dropdown winkelMenu',
			self::MENU_TYPE_TABS => 'winkel-menu winkel-menu-tabs winkelTabs',
			self::MENU_TYPE_PORTAL => 'winkel-menu winkel-menu-portal portal',
			self::MENU_TYPE_DEFAULT => 'winkel-menu',
		];
		// A list of classes to apply the list element and override the default behavior.
		$listClasses = [
			// `.menu` is on the portal for historic reasons.
			// It should not be applied elsewhere per T253329.
			self::MENU_TYPE_DROPDOWN => 'menu winkel-menu-content-list',
		];
		$isPortal = self::MENU_TYPE_PORTAL === $type;

		// For some menu items, there is no language key corresponding with its menu key.
		// These inconsitencies are captured in MENU_LABEL_KEYS
		$msgObj = $skin->msg( self::MENU_LABEL_KEYS[ $label ] ?? $label );
		$props = [
			'id' => "p-$label",
			'label-id' => "p-{$label}-label",
			// If no message exists fallback to plain text (T252727)
			'label' => $msgObj->exists() ? $msgObj->text() : $label,
			'list-classes' => $listClasses[$type] ?? 'winkel-menu-content-list',
			'html-items' => '',
			'is-dropdown' => self::MENU_TYPE_DROPDOWN === $type,
			'html-tooltip' => Linker::tooltip( 'p-' . $label ),
		];

		foreach ( $urls as $key => $item ) {
			// Add CSS class 'collapsible' to all links EXCEPT watchstar.
			if (
				$key !== 'watch' && $key !== 'unwatch' &&
				isset( $options['winkel-collapsible'] ) && $options['winkel-collapsible'] ) {
				if ( !isset( $item['class'] ) ) {
					$item['class'] = '';
				}
				$item['class'] = rtrim( 'collapsible ' . $item['class'], ' ' );
			}
			$props['html-items'] .= $this->getSkin()->makeListItem( $key, $item, $options );

			// Check the class of the item for a `selected` class and if so, propagate the items
			// label to the main label.
			if ( $setLabelToSelected ) {
				if ( isset( $item['class'] ) && stripos( $item['class'], 'selected' ) !== false ) {
					$props['label'] = $item['text'];
				}
			}
		}

		$props['html-after-portal'] = $isPortal ? $this->getAfterPortlet( $label ) : '';

		// Mark the portal as empty if it has no content
		$class = ( count( $urls ) == 0 && !$props['html-after-portal'] )
			? 'winkel-menu-empty emptyPortlet' : '';
		$props['class'] = trim( "$class $extraClasses[$type]" );
		return $props;
	}

	/**
	 * @return array
	 */
	private function getMenuProps() : array {
		// @phan-suppress-next-line PhanUndeclaredMethod
		$contentNavigation = $this->getSkin()->getMenuProps();
		$personalTools = $this->getPersonalTools();
		$skin = $this->getSkin();

		// For logged out users Winkel shows a "Not logged in message"
		// This should be upstreamed to core, with instructions for how to hide it for skins
		// that do not want it.
		// For now we create a dedicated list item to avoid having to sync the API internals
		// of makeListItem.
		if ( !$skin->getUser()->isLoggedIn() && User::groupHasPermission( '*', 'edit' ) ) {
			$loggedIn =
				Html::element( 'li',
					[ 'id' => 'pt-anonuserpage' ],
					$skin->msg( 'notloggedin' )->text()
				);
		} else {
			$loggedIn = '';
		}

		// This code doesn't belong here, it belongs in the UniversalLanguageSelector
		// It is here to workaround the fact that it wants to be the first item in the personal menus.
		if ( array_key_exists( 'uls', $personalTools ) ) {
			$uls = $skin->makeListItem( 'uls', $personalTools[ 'uls' ] );
			unset( $personalTools[ 'uls' ] );
		} else {
			$uls = '';
		}

		$ptools = $this->getMenuData( 'personal', $personalTools );
		// Append additional link items if present.
		$ptools['html-items'] = $uls . $loggedIn . $ptools['html-items'];

		return [
			'data-personal-menu' => $ptools,
			'data-namespace-tabs' => $this->getMenuData(
				'namespaces',
				$contentNavigation[ 'namespaces' ] ?? [],
				self::MENU_TYPE_TABS
			),
			'data-variants' => $this->getMenuData(
				'variants',
				$contentNavigation[ 'variants' ] ?? [],
				self::MENU_TYPE_DROPDOWN,
				[], true
			),
			'data-page-actions' => $this->getMenuData(
				'views',
				$contentNavigation[ 'views' ] ?? [],
				self::MENU_TYPE_TABS, [
					'winkel-collapsible' => true,
				]
			),
			'data-page-actions-more' => $this->getMenuData(
				'cactions',
				$contentNavigation[ 'actions' ] ?? [],
				self::MENU_TYPE_DROPDOWN
			),
		];
	}

	/**
	 * @return array
	 */
	private function buildSearchProps() : array {
		$config = $this->getConfig();
		$skin = $this->getSkin();
		$props = [
			'form-action' => $config->get( 'Script' ),
			'html-button-search-fallback' => $this->makeSearchButton(
				'fulltext',
				[ 'id' => 'mw-searchButton', 'class' => 'searchButton mw-fallbackSearchButton' ]
			),
			'html-button-search' => $this->makeSearchButton(
				'go',
				[ 'id' => 'searchButton', 'class' => 'searchButton' ]
			),
			'html-input' => $this->makeSearchInput( [ 'id' => 'searchInput' ] ),
			'msg-search' => $skin->msg( 'search' ),
			'page-title' => SpecialPage::getTitleFor( 'Search' )->getPrefixedDBkey(),
		];
		return $props;
	}
}
