<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(EXTENSIONS.'/image_upload/fields/field.image_upload.php');
	require_once(EXTENSIONS.'/frontend_localisation/extension.driver.php');
	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');



	final class fieldMultilingual_image_upload extends fieldImage_upload
	{

		/*------------------------------------------------------------------------------------------------*/
		/*  Definition  */
		/*------------------------------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();

			$this->_name = __('Multilingual Image Upload');
		}

		public function createTable(){
			$query = "
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`file` varchar(255) default NULL,
					`size` int(11) unsigned NULL,
					`mimetype` varchar(50) default NULL,
					`meta` varchar(255) default NULL,";

			foreach( FLang::getLangs() as $lc ){
				$query .= sprintf('
					`file-%1$s` varchar(255) default NULL,
					`size-%1$s` int(11) unsigned NULL,
					`mimetype-%1$s` varchar(50) default NULL,
					`meta-%1$s` varchar(255) default NULL,',
					$lc
				);
			}

			$query .= "
					PRIMARY KEY (`id`),
					UNIQUE KEY `entry_id` (`entry_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query($query);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Settings  */
		/*------------------------------------------------------------------------------------------------*/

		public function findDefaults(&$settings){
			$settings['def_ref_lang'] = 'no';

			return parent::findDefaults($settings);
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
			parent::displaySettingsPanel($wrapper, $errors);

			$pos = $wrapper->getNumberOfChildren() - 1;

			$div = new XMLElement('div', null, array('class' => 'two columns'));

			$unique = $wrapper->getChild($pos);
			$unique->setAttribute('class', $unique->getAttribute('class').' column');
			$div->appendChild($unique);

			$this->_appendDefLangValCheckbox($div);

			$wrapper->replaceChildAt($pos, $div);
		}

		private function _appendDefLangValCheckbox(XMLElement &$wrapper){
			$label = Widget::Label(null, null, 'column');
			$input = Widget::Input("fields[{$this->get('sortorder')}][def_ref_lang]", 'yes', 'checkbox');
			if( $this->get('def_ref_lang') == 'yes' ) $input->setAttribute('checked', 'checked');
			$label->setValue(__('%s Use value from main language if selected language has empty value.', array($input->generate())));

			$wrapper->appendChild($label);
		}

		public function commit(){
			if( !parent::commit() ) return false;

			return Symphony::Database()->query(sprintf("
				UPDATE
					`tbl_fields_%s`
				SET
					`def_ref_lang` = '%s'
				WHERE
					`field_id` = '%s';",
				$this->handle(), $this->get('def_ref_lang'), $this->get('id')
			));
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Publish  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = NULL, $flagWithError = NULL, $fieldnamePrefix = NULL, $fieldnamePostfix = NULL){
			Extension_Frontend_Localisation::appendAssets();
			Extension_Multilingual_Image_Upload::appendAssets();

			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();

			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual_image_upload field-multilingual');
			$container = new XMLElement('div', null, array('class' => 'container'));


			/*------------------------------------------------------------------------------------------------*/
			/*  Label  */
			/*------------------------------------------------------------------------------------------------*/

			$label = Widget::Label($this->get('label'), null, 'file');
			$labeliValue = $this->generateHelpMessage();
			if( $this->get('required') != 'yes' ) {
				$labeliValue = $labeliValue . ', ' . __('Optional');
			}
			$label->appendChild(new XMLElement('i', $labeliValue));
			$container->appendChild($label);


			/*------------------------------------------------------------------------------------------------*/
			/*  Tabs  */
			/*------------------------------------------------------------------------------------------------*/

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));
			foreach( $langs as $lc ){
				$li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}

			$container->appendChild($ul);


			/*------------------------------------------------------------------------------------------------*/
			/*  Panels  */
			/*------------------------------------------------------------------------------------------------*/

			foreach( $langs as $lc ){
				$div = new XMLElement('div', null, array('class' => 'file tab-panel tab-'.$lc));

				$file = 'file-'.$lc;

				if( $data[$file] ){
					$filePath = $this->get('destination').'/'.$data[$file];
					
					$div->appendChild(
						Widget::Anchor($filePath, URL.$filePath)
					);
				}

				$div->appendChild(
					Widget::Input(
						"fields{$fieldnamePrefix}[{$this->get('element_name')}][{$lc}]{$fieldnamePostfix}",
						$data[$file],
						$data[$file] ? 'hidden' : 'file'
					)
				);

				$container->appendChild($div);
			}


			/*------------------------------------------------------------------------------------------------*/
			/*  Errors  */
			/*------------------------------------------------------------------------------------------------*/

			if( !is_dir(DOCROOT.$this->get('destination').'/') ){
				$flagWithError = __('The destination directory, <code>%s</code>, does not exist.', array($this->get('destination')));
			}
			elseif( !$flagWithError && !is_writable(DOCROOT.$this->get('destination').'/') ){
				$flagWithError = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
			}

			if( $flagWithError != NULL ){
				$wrapper->appendChild(Widget::Error($container, $flagWithError));
			}
			else{
				$wrapper->appendChild($container);
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Input  */
		/*------------------------------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = NULL){
			$error = self::__OK__;
			$field_data = $data;
			$all_langs = FLang::getAllLangs();

			foreach( FLang::getLangs() as $lc ){

				$file_message = '';
				$data = $this->_getData($field_data[$lc]);

				if( is_array($data) && isset($data['name']) ){
					$data['name'] = $this->getUniqueFilename($data['name'], $lc, true);
				}

				$status = parent::checkPostFieldData($data, $file_message, $entry_id);

				// if one language fails, all fail
				if( $status != self::__OK__ ){
					$message .= "<br />{$all_langs[$lc]}: {$file_message}";
					$error = self::__ERROR__;
				}
			}

			return $error;
		}

		public function processRawFieldData($data, &$status, &$message, $simulate = false, $entry_id = NULL){
			if( !is_array($data) || empty($data) ) return parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);

			$result = array();
			$field_data = $data;

			foreach( FLang::getLangs() as $lc ){

				$data = $this->_getData($field_data[$lc]);

				if( is_array($data) && isset($data['name']) ){
					$data['name'] = $this->getUniqueFilename($data['name'], $lc, true);
				}

				$this->_fakeDefaultFile($lc, $entry_id);

				$file_result = parent::processRawFieldData($data, $status, $message, $simulate, $entry_id, $lc);

				if( is_array($file_result) ){
					foreach( $file_result as $key => $value ){
						$result[$key.'-'.$lc] = $value;
					}
				}
			}

			return $result;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data){
			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from main language
			if( $this->get('def_ref_lang') == 'yes' && $data['file-'.$lang_code] == '' ){
				$lang_code = FLang::getMainLang();
			}

			$data['file'] = $data['file-'.$lang_code];
			$data['meta'] = $data['meta-'.$lang_code];
			$data['mimetype'] = $data['mimetype-'.$lang_code];

			parent::appendFormattedElement($wrapper, $data);
		}

		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null){
			$lang_code = FLang::getLangCode();

			if( $this->get('def_ref_lang') === 'yes' && $data['file-'.$lang_code] === '' ){
				$lang_code = FLang::getMainLang();
			}

			$data['file'] = $data['file-'.$lang_code];

			return parent::prepareTableValue($data, $link, $entry_id);
		}

		public function getParameterPoolValue($data){
			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from main language
			if( $this->get('def_ref_lang') === 'yes' && $data['file-'.$lang_code] === '' ){
				$lang_code = FLang::getMainLang();
			}

			return $data['file-'.$lang_code];
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->get('label').'
					<!-- '.__('Modify just current language value').' -->
					<input name="fields['.$this->get('element_name').'][value-{$url-fl-language}]" type="text" />

					<!-- '.__('Modify all values').' -->');

			foreach( FLang::getLangs() as $lc ){
				$label->appendChild(Widget::Input("fields[{$this->get('element_name')}][value-{$lc}]"));
			}

			return $label;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public function entryDataCleanup($entry_id, $data){

			foreach( FLang::getLangs() as $lc ){
				$file_location = WORKSPACE.'/'.ltrim($data['file-'.$lc], '/');

				if( is_file($file_location) ){
					General::deleteFile($file_location);
				}
			}

			parent::entryDataCleanup($entry_id, $data);

			return true;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  In-house  */
		/*------------------------------------------------------------------------------------------------*/

		protected function getUniqueFilename($filename, $lang_code = null, $enable = false){
			if( $enable ){
				if( empty($lang_code) || !is_string($lang_code) ){
					$lang_code = FLang::getMainLang();
				}

				$crop = '150';
				$replace = $lang_code;

				if( $this->get('unique') == 'yes' ) $replace .= ".'-'.time()";

				return preg_replace("/(.*)(\.[^\.]+)/e", "substr('$1', 0, $crop).'-'.$replace.'$2'", $filename);
			}

			return $filename;
		}


		/**
		 * It is possible that data from Symphony won't come as expected associative array.
		 *
		 * @param array $data
		 */
		private function _getData($data){
			if( is_string($data) ) return $data;

			if( !is_array($data) ) return null;

			if( array_key_exists('name', $data) ){
				return $data;
			}

			return array(
				'name' => $data[0],
				'type' => $data[1],
				'tmp_name' => $data[2],
				'error' => $data[3],
				'size' => $data[4]
			);
		}

		/**
		 * Set default columns (file, mimetype, size and meta) in database table to given reference language values.
		 *
		 * @param string  $lang_code
		 * @param integer $entry_id
		 */
		private function _fakeDefaultFile($lang_code, $entry_id){
			try{
				$row = Symphony::Database()->fetchRow(0, sprintf(
					"SELECT `file-{$lang_code}`, `mimetype-{$lang_code}`, `size-{$lang_code}`, `meta-{$lang_code}` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d",
					$this->get('id'),
					$entry_id
				));
			} catch( Exception $e ){
			}

			$fields['file'] = $row['file-'.$lang_code];
			$fields['size'] = $row['size-'.$lang_code];
			$fields['meta'] = $row['meta-'.$lang_code];
			$fields['mimetype'] = $row['mimetype-'.$lang_code];

			try{
				Symphony::Database()->update($fields, "tbl_entries_data_{$this->get('id')}", "`entry_id` = {$entry_id}");
			}
			catch( Exception $e ){
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Field schema  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFieldSchema($f){}

	}
