<?php
/*
 * Orange Framework Extension
 *
 * @package	CodeIgniter / Orange
 * @author Don Myers
 * @license http://opensource.org/licenses/MIT MIT License
 * @link https://github.com/ProjectOrangeBox
 *
 * required
 * core:
 * libraries:
 * models:
 * helpers:
 * functions:
 *
 */

class Filter_base extends Validate_base {
	public function __construct(&$field_data) {
		$this->field_data   = &$field_data;
	}

	public function filter(&$field, $options) {}

} /* end file */
