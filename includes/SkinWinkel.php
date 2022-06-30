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

use MediaWiki\MediaWikiServices;
use Winkel\Constants;
use Wikimedia\WrappedString;

/**
 * Skin subclass for Winkel
 * @ingroup Skins
 * @final skins extending SkinWinkel are not supported
 * @unstable
 */
class SkinWinkel extends SkinTemplate {
	public $skinname = Constants::SKIN_NAME;
	public $stylename = 'Winkel';
	public $template = 'WinkelTemplate';

	/**
	 * @inheritDoc
	 * @return array
	 */
	public function getDefaultModules() {
		$modules = parent::getDefaultModules();

		$modules['styles']['skin'][] = 'skins.winkel.styles';
		$modules[Constants::SKIN_NAME] = 'skins.winkel.js';

		return $modules;
	}

	/**
	 * Set up the WinkelTemplate. Overrides the default behaviour of SkinTemplate allowing
	 * the safe calling of constructor with additional arguments. If dropping this method
	 * please ensure that WinkelTemplate constructor arguments match those in SkinTemplate.
	 *
	 * @internal
	 * @param string $classname
	 * @return WinkelTemplate
	 */
	protected function setupTemplate( $classname ) {
		$tp = new TemplateParser( __DIR__ . '/templates' );
		return new WinkelTemplate( $this->getConfig(), $tp );
	}

	/**
	 * @internal only for use inside WinkelTemplate
	 * @return array of data for a Mustache template
	 */
	public function getTemplateData() {
		$out = $this->getOutput();
		$title = $out->getTitle();

		$indicators = [];
		foreach ( $out->getIndicators() as $id => $content ) {
			$indicators[] = [
				'id' => Sanitizer::escapeIdForAttribute( "mw-indicator-$id" ),
				'class' => 'mw-indicator',
				'html' => $content,
			];
		}

		$printFooter = Html::rawElement(
			'div',
			[ 'class' => 'printfooter' ],
			$this->printSource()
		);

		return [
			// Data objects:
			'array-indicators' => $indicators,
			// HTML strings:
			'html-printtail' => WrappedString::join( "\n", [
				MWDebug::getHTMLDebugLog(),
				MWDebug::getDebugHTML( $this->getContext() ),
				$this->bottomScripts(),
				wfReportTime( $out->getCSP()->getNonce() )
			] ) . '</body></html>',
			'html-site-notice' => $this->getSiteNotice(),
			'html-userlangattributes' => $this->prepareUserLanguageAttributes(),
			'html-subtitle' => $this->prepareSubtitle(),
			// Always returns string, cast to null if empty.
			'html-undelete-link' => $this->prepareUndeleteLink() ?: null,
			// Result of OutputPage::addHTML calls
			'html-body-content' => $this->wrapHTML( $title, $out->mBodytext )
				. $printFooter,
			'html-after-content' => $this->afterContentHook(),
		];
	}

	/**
	 * @internal only for use inside WinkelTemplate
	 * @return array
	 */
	public function getMenuProps() {
		return $this->buildContentNavigationUrls();
	}
}
