<?php
App::uses('ModelBehavior', 'Model');

/**
 * Replace regionalized chars with standard ones on input.
 *
 * “smart quotes” become "dumb quotes" on save
 * „low-high“ become "high-high"
 * same for single quotes (apostrophes)
 * in order to unify them. Basic idea is a unified non-regional version in the database.
 *
 * Using the TypographyHelper we can then format the output
 * according to the language/regional setting (in some languages
 * the high-high smart quotes, in others the low-high ones are preferred)
 *
 * @link http://en.wikipedia.org/wiki/Non-English_usage_of_quotation_marks
 * @cakephp 2.x
 * @license MIT
 * 2011-01-13 ms
 */
class TypographicBehavior extends ModelBehavior {

	protected $map = array(
		'in' => array(
			'‘' => '"',
			//'&lsquo;' => '"', # ‘
			'’' => '"',
			//'&rsquo;' => '"', # ’
			'‚' => '"',
			//'&sbquo;' => '"', # ‚
			'‛' => '"',
			//'&#8219;' => '"', # ‛
			'“' => '"',
			//'&ldquo;' => '"', # “
			'”' => '"',
			//'&rdquo;' => '"', # ”
			'„' => '"',
			//'&bdquo;' => '"', # „
			'‟' => '"',
			//'&#8223;' => '"', # ‟
			'«' => '"',
			//'&laquo;' => '"', # «
			'»' => '"',
			//'&raquo;' => '"', # »
			'‹' => '"',
			//'&laquo;' => '"', # ‹
			'›' => '"',
			//'&raquo;' => '"', # ›
		),
		'out'=> array(
			# use the TypographyHelper for this at runtime
		),
	);

	/**
	 * Initiate behavior for the model using specified settings. Available settings:
	 *
	 *
	 * @param object $Model Model using the behaviour
	 * @param array $settings Settings to override for model.
	 * @access public
	 * 2011-12-06 ms
	 */
	public function setup(Model $Model, $settings = array()) {
		$default = array(
			'before' => 'save',
			'fields' => array()
		);
		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $default;
		}

		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], is_array($settings) ? $settings : array());
		if (empty($this->settings[$Model->alias]['fields'])) {
			$schema = $Model->schema();
			$fields = array();
			foreach ($schema as $field => $v) {
				if (!in_array($v['type'], array('string', 'text'))) {
					continue;
				}
				if (!empty($v['key'])) {
					continue;
				}
				if (isset($v['length']) && $v['length'] === 1) { //TODO: also skip UUID (lenght 36)?
					continue;
				}
				$fields[] = $field;
			}
			$this->settings[$Model->alias]['fields'] = $fields;
		}
	}


	public function beforeValidate(Model $Model) {
		parent::beforeValidate($Model);

		if ($this->settings[$Model->alias]['before'] == 'validate') {
			$this->process($Model);
		}

		return true;
	}

	public function beforeSave(Model $Model) {
		parent::beforeSave($Model);

		if ($this->settings[$Model->alias]['before'] == 'save') {
			$this->process($Model);
		}

		return true;
	}

	/**
	 * Run the behavior over all records of this model
	 * This is useful if you attach it after some records have already been saved without it.
	 * @param object $Model Model about to be saved.
	 * @return int $count Number of affected/changed records
	 * 2012-08-07 ms
	 */
	public function updateTypography(Model $Model, $dryRun = false) {
		$records = $Model->find('all'); //TODO: in multiple runs with limit
		$count = 0;
		foreach ($records as $record) {
			$changed = false;
			foreach ($this->settings[$Model->alias]['fields'] as $field) {
				if (empty($record[$Model->alias][$field])) {
					continue;
				}
				$tmp = $this->_prepareInput($record[$Model->alias][$field]);
				if ($tmp == $record[$Model->alias][$field]) {
					continue;
				}
				$record[$Model->alias][$field] = $tmp;
				$changed = true;
			}
			if ($changed) {
				if (!$dryRun) {
					$Model->save($record, false);
				}
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Run before a model is saved
	 *
	 * @param object $Model Model about to be saved.
	 * @return boolean true if save should proceed, false otherwise
	 * @access public
	 */
	public function process(Model $Model, $return = true) {
		foreach ($this->settings[$Model->alias]['fields'] as $field) {
			if (!empty($Model->data[$Model->alias][$field])) {
				$Model->data[$Model->alias][$field] = $this->_prepareInput($Model->data[$Model->alias][$field]);
			}
		}

		return $return;
	}

	/**
	 * @param string $input
	 * @return string $cleanedInput
	 * 2011-12-06 ms
	 */
	protected function _prepareInput($string) {
		$map = $this->map['in'];

		//return $string;

		$string = str_replace(array_keys($map), array_values($map), $string);
		return $string;
	}

}
