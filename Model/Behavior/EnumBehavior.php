<?php
/**
 * This Behavior gives a convenient way to work with ENUM fields
 *
 * @example
 * In your Model :
 * $actsAs = array(
 *  'CakePHP-Enum-Behavior.Enum' => array(
 *      'exemple_field' => array(1 => 'value_1', 'key' => 'value_2')
 *  )
 * );
 * Or if you want to skip automatic validation of enumerated values
 * $actsAs = array(
 *  'CakePHP-Enum-Behavior.Enum' => array(
 *      'exemple_field' => array(
 *			'values' => array(1 => 'value_1', 'key' => 'value_2')
 *			'validate' => false // boolean false || true. false will skip validation, 
 *								// true will allow validation without having to switch 
 *								// back to the terse field=>values configuration in 
 *								// the example above.
 *		)
 *  )
 * );
 *
 * In your controller :
 * $this->set($this->{$this->ModelName}->enumValues());
 *
 * @author Created: Pierre Aboucaya - Asper <p@asper.fr>
 * @author Updated: Tomasz Mazur <cakephp@tomaszmazur.eu>
 *
 */
class EnumBehavior extends ModelBehavior {

	/**
	 * Setup enum behavior with the specified configuration settings.
	 *
	 * @example $actsAs = array(
	 *  'CakePHP-Enum-Behavior.Enum' => array(
	 *      'exemple_field' => array(1 => 'value_1', 'key' => 'value_2')
	 *  )
	 * );
	 * @param object $Model Model using this behavior
	 * @param array $config Configuration settings for $Model
	 */
	public function setup(Model $Model, $config = array()) {
		$this->settings[$Model->name] = $config;
		$schema = $Model->schema();
		foreach($config as $field => $values){
			$validation_requested = $this->haveValidate($values);
			$field_values = $this->peelValues($values);
			if($validation_requested){
				// validation has been requested but the validate value is false; skip validation attachement
				$need_validation = ($values['validate'] === true) ? true : false;
				if($need_validation){
					$this->attachValidation($Model,$schema,$field,$field_values);									
				}
			}
		}
	}

	private function peelValues($values){
		$validation_requested = $this->haveValidate($values);
		// If validation has been requested, true or false, we need to peel the actual values out of their secondary location in field_name[values] = array('field_value'=>'field_label') instead of looking for them in filed_name[field_value] = field_label
		$field_values = ($validation_requested === true) ? $values['values'] : $values;
		return $field_values;
		
	}

	private function haveValidate($values){
		$validation_requested = (
			// the validate key exists in our incoming filed info
			isset($values['validate']) 
			&& isset($values['values']) 
		) ? true : false;	
		return $validation_requested;
	}

	/**
	 * convert given $field $value (array value) to enum key (array key)
	 * @param object $Model Model using this behavior
	 * @param  string $field requested field
	 * @param  string $value enum value
	 * @return false/int        enum key, or false if not found 
	 */
	public function enumValueToKey(Model $Model, $field, $value) {
		$enums = $this->enumValues($Model);
		if(array_key_exists($field, $enums)) {
			$enum = $enums[$field];
			return array_search($value, $enum);
		}
		return false;
	}

	/**
	 * Convert given $field $key (array key) to value (array value)
	 * @param object $Model Model using this behavior
	 * @param  string $field requested enum field
	 * @param  int $key enum array key
	 * @return false/int        enum value, or false if not found
	 */
	public function enumKeyToValue(Model $Model, $field, $key) {
		$enums = $this->enumValues($Model);
		if(array_key_exists($field, $enums)) {
			$enum = $enums[$field];
			if(array_key_exists($key, $enum)){
				return $enum[$key];
			}
		}
		return false;
	}

	/**
	 * Returns an array of all enum values for the Model
	 * @example $this->set($this->{$this->ModelName}->enumValues());
	 * @param object $Model Model using this behavior
	 */
	public function enumValues(Model $Model){
		$return = array();
		if(isset($this->settings[$Model->name])){
			foreach($this->settings[$Model->name] as $field => $values){
				Dev::speek(array($field=>$values));
				$values = $this->peelValues($values);
				Dev::speek($values);
				if(!empty($values)){
					foreach($values as $key => $value){
						Dev::speek(array($key=>$value));
						$values[$key] =  __(Inflector::humanize($value));
					}
					$return[Inflector::pluralize(Inflector::variable($field))] = $values;
				}
			}
		}
		return $return;
	}

	/**
	 * Translates the values
	 * @param array $values Values of the ENUM field
	 */
	private function __translate($values = array()){
		$return = array();
		foreach($values as $value){
			$return[] = __(Inflector::humanize($value));
		}
		return $return;
	}
	
	/**
	 * Attaches validation rule inList: enum value required
	 * @param object $Model Model using this behavior
	 * @param array $schema the schema Model using this behavior
	 * @param string $$field_name the enum field name as passed in at config time. see $actsAs in $this->setup() above
	 * @param array $values the key enum value['label'] as passed in at 
	 */
	private function attachValidation(&$Model,$schema,$field_name,$values){
		$baseRule = array(
			/* All types to string conversion */
			'rule' => array('inList', array_map(function($v){ return (string)$v; }, array_keys($values)), false), 
			'message' => __('Please choose one of the following values : %s', join(', ', $this->__translate($values))),
			'allowEmpty' => in_array(null, $values) || in_array('', $values),
			'required' => false
		);
		if(
			isset($schema[$field_name])
			&& isset($schema[$field_name]['null']) 
			&& !$schema[$field_name]['null']
		){
			$Model->validate[$field_name]['allowedValuesCreate'] = array_merge(
				$baseRule,
				array(
					'required' => true,
					'on' => 'create'
				)
			);
			$Model->validate[$field_name]['allowedValuesUpdate'] = array_merge(
				$baseRule,
				array(
					'on' => 'update'
				)
			);
		}
		else {
			$Model->validate[$field_name]['allowedValues'] = $baseRule;
		}
	}

}
