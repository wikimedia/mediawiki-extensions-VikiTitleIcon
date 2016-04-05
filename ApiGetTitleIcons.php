<?php
/*
 * Copyright (c) 2014 The MITRE Corporation
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

class ApiGetTitleIcons extends ApiBase {
	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}

	public function execute() {
		$pageTitle = $this->getMain()->getVal( 'pageTitle' );

		global $wgTitleIcon_TitleIconPropertyName;
		$myTitleIconName = $wgTitleIcon_TitleIconPropertyName;

		$pageNameWithSpaces = str_replace( '_', ' ', $pageTitle );
		$titleIconWithSpaces = str_replace( '+', ' ', $myTitleIconName );

		$api = new ApiMain(
			new DerivativeRequest(
				$this->getRequest(),
				array(
					'action' => 'askargs',
					'conditions' => $pageTitle,
					'printouts' => $titleIconWithSpaces
				)
			),
			false
		);

		$api->execute();
		$data = $api->getResult()->getResultData(
			null, ['BC' => [], 'Types' => [], 'Strip' => 'all'] );

		if ( is_array( $data["query"]["results"] ) && count( $data["query"]["results"] ) == 0 ) {
			$this->getResult()->addValue( null, $this->getModuleName(),
				array( 'pageTitle' => $pageTitle,
					'titleIcons' => array()
						) );

			return true;
		}

		if ( array_key_exists( $pageNameWithSpaces, $data["query"]["results"] ) )
			$titleIconNames = $data["query"]["results"]["$pageNameWithSpaces"]["printouts"]["$titleIconWithSpaces"];
		else {
			$key = array_shift( array_keys( $data["query"]["results"] ) );
			$titleIconNames = $data["query"]["results"][$key]["printouts"]["$titleIconWithSpaces"];
		}

		if( count( $titleIconNames ) == 0 ) {
			// If there are no title icons from the page, then get them from the page's categories

			$api = new ApiMain(
				new DerivativeRequest(
					$this->getRequest(),
					array(
						'action' => 'query',
						'titles' => $pageNameWithSpaces,
						'prop' => 'categories'
					)
				),
				false
			);

			$api->execute();
			$data = $api->getResult()->getResultData(
				null, ['BC' => [], 'Types' => [], 'Strip' => 'all'] );
			$keys = array_keys( $data['query']['pages'] );
			$key = array_shift( $keys );


			if( array_key_exists( "categories", $data["query"]["pages"][$key]) ) {
				$categories = array();
				foreach($data["query"]["pages"][$key]["categories"] as $categoryObject) {
					$categories[] = $categoryObject["title"];
				}

				foreach( $categories as $category ) {
					$title = Title::newFromText( $category );

					$discoveredIcons = $this->getPropertyValues( $title, $titleIconWithSpaces );
					foreach($discoveredIcons as $icon) {
						$titleIconNames[] = $icon;
					}
				}
			}
		}


		$titleIconURLs = array();

		foreach ( $titleIconNames as $name ) {

			$api = new ApiMain(
				new DerivativeRequest(
					$this->getRequest(),
					array(
						'action' => 'query',
						'titles' => 'File:' . $name,
						'prop' => 'imageinfo',
						'iiprop' => 'url'
					)
				),
				false
			);

			$api->execute();
			$data = $api->getResult()->getResultData(
				null, ['BC' => [], 'Types' => [], 'Strip' => 'all'] );
			$keys = array_keys( $data['query']['pages'] );
			$key = array_shift( $keys );
			$url = $data["query"]["pages"][$key]["imageinfo"][0]["url"];
			$titleIconURLs[] = $url;
		}

		$this->getResult()->addValue( null, $this->getModuleName(),
			array( 'pageTitle' => $pageTitle,
				'titleIcons' => $titleIconURLs
					) );

		return true;

	}

	private function getPropertyValues( Title $title, $propertyname ) {

		$store = \SMW\StoreFactory::getStore();

		// remove fragment
		$title = Title::newFromText( $title->getPrefixedText() );

		$subject = SMWDIWikiPage::newFromTitle( $title );
		$data = $store->getSemanticData( $subject );
		$property = SMWDIProperty::newFromUserLabel( $propertyname );
		$values = $data->getPropertyValues( $property );

		$strings = array();
		foreach ( $values as $value ) {
			if ( $value->getDIType() == SMWDataItem::TYPE_STRING ||
				$value->getDIType() == SMWDataItem::TYPE_BLOB ) {
				$strings[] = trim( $value->getString() );
			}
		}

		return $strings;
	}

	public function getDescription() {
		return "Get the URLs of all Title Icons for the page, if any exist.

Note that because the returned value is a JSON object, you must specify ".
"format=json in this query; the default xml format will return only an error.";
	}
	public function getAllowedParams() {
		return array(
			'pageTitle' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			)
		);
	}
	public function getParamDescription() {
		return array(
			'pageTitle' => 'title of the page whose title icons you wish to retrieve'
		);
	}
	public function getExamples() {
		return array(
			'api.php?action=getTitleIcons&pageTitle=Test_Page_C&format=jsonfm' );
	}
	public function getHelpUrls() {
		return '';
	}
}
