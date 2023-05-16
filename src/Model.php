<?php //

    /*
      Book::find('all');
      Book::find('last');
      Book::first();
      Book::last();
      Book::all();
      Book::find(array(2,3));
      Book::all(array('conditions' => 'price < 15.00'));
      Book::find('all', array('conditions' => "title LIKE '%war%'"));
      Book::find('all', array('limit' => 10, 'offset' => 5));
      Book::find('all', array('order' => 'price desc, title asc'));
      Book::find('all', array('select' => 'avg(price) as avg_price, avg(tax) as avg_tax'));
      Book::find('all', array('select' => 'id, title'));
      Book::all(array('group' => 'price'));
      Book::all(array('group' => 'price', 'having' => 'price > 45.00'));
      Book::find_by_sql('select title from `books`');
     */

// TODO: timestamps zetten
// deleted -> in case the extra attribs are there use them, otherwise delete

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace FL;

/**
 * Description of Model
 *
 * @author afeys
 */
class Model {

    const TABLECREATE = "CREATE";
    const TABLEALTER = "ALTER";

//put your code here
    static $attributes = array();
    static $validators = array();
    static $compound_indexes = array();
    static $connection;
    static $table_name;
    static $related_views = array();
    static $default_sortfield; // if none specified in the model, then use the PK field
    static $setters = array(); // the getters and setters can be overriden in the modelclass,
    static $getters = array(); // these take precedence over any default getters and setters.

    private $__attributes = array();
    private $__newrecord = true; // this is true for a new record, false for an already existing record
    private $__dirty = null;   // this flags if attributes have been changed (needed for update operation)
    private $__validation_messages = array();
    private $__ignore_attributes = false;

    // --------------------------------------------------------------------------------------//
    // __ FUNCTIONS                                                                      //
    // --------------------------------------------------------------------------------------//

    /**
     * Initializes a Model instance. 
     * @param array $data
     * 
     * @return object the Model instance
     */
 
    function __construct(array $data = array(), $ignoreattributes = false) {
        $currentclass = get_called_class();
        $attributes = $currentclass::$attributes;
        foreach ($currentclass::$attributes as $name => $parameters) {
            $defaultvalue = null;
            if (is_array($parameters)) {
                if (array_key_exists("default", $parameters)) {
                    $defaultvalue = $parameters["default"];
                }
            }
            $this->$name = $defaultvalue;
        }
        $this->__ignore_attributes = $ignoreattributes;
        $this->assignAttributeValuesFromData($data);
        $this->cleanFlagDirty();
    }

    public function __set($property, $value) {
        $currentclass = get_called_class();
        $attributes = $currentclass::$attributes;
        if (array_key_exists($property, $attributes)) {
            $this->__attributes[$property] = $value;
            $this->setFlagDirty($property);
        } else {
            if ($this->__ignore_attributes == true) {
                // as can be found on https://www.php.net/manual/en/pdostatement.fetchall.php
                // pdo will return the data from the record as an array, but both an array element
                // with the name of the property, and a numbered index will exist!
                // Array
                //  (
                //      [name] => apple
                //      [0] => apple
                //      [colour] => red
                //      [1] => red
                //  )
                // so we will first check if $property is numeric, if it is, ignore it
                if (!is_numeric($property)) {
                    $this->__attributes[$property] = $value;
                }
            } else {
                throw new RecordAttributeDoesNotExistException("Attribute '" . $property . "' does not exist in model '" . $currentclass . "'.");
            }
        }
    }

    public function __get($property) {
        $currentclass = get_called_class();
        $attributes = $currentclass::$attributes;
        
        // check for special getter
        if (in_array("get_$property",static::$getters))
        {
            $name = "get_$property";
            $value = $this->$name();
            return $value;
        }
        if (array_key_exists($property, $attributes)) {
            if (array_key_exists($property, $this->__attributes)) {
                return $this->__attributes[$property];
            } else {
                return null;
            }
        } else {
            if ($this->__ignore_attributes == true) {
                return $this->__attributes[$property];
            } else {
                throw new RecordAttributeDoesNotExistException("Attribute '" . $property . "' does not exist in model '" . $currentclass . "'.");
            }
        }
    }

    // --------------------------------------------------------------------------------------//
    // INITIALISER FUNCTIONS                                                                 //
    // This HAS to be run before using the models, run this in your application              //
    // initialisation routine                                                                //
    // --------------------------------------------------------------------------------------//
    public static function initialize($modeldirectories) {
        if (!is_array($modeldirectories)) {
            $modeldirectory = $modeldirectories;
            $modeldirectories = array();
            $modeldirectories[] = $modeldirectory;
        }
        foreach ($modeldirectories as $dir) {
            $path = $dir;
            $root = realpath(isset($path) ? $path : '.');

            $files = glob($root . "/*.php");
            foreach ($files as $idx => $filepath) {
                require_once "$filepath";
            }
        }
    }

    
    // --------------------------------------------------------------------------------------//
    // EVENT FUNCTIONS                                                                       //
    // Override these functions in your models to implement extra checks, or alter values    //
    // --------------------------------------------------------------------------------------//
    
    public function before_save() {
        return true;
    }

    public function after_save() {
        return true;
    }

    public function before_validation() {
        return true;
    }

    public function after_validation() {
        return true;
    }

    public function before_insert() {
        return true;
    }

    public function after_insert() {
        return true;
    }

    public function before_validation_on_insert() {
        return true;
    }

    public function after_validation_on_insert() {
        return true;
    }

    public function before_update() {
        return true;
    }

    public function after_update() {
        return true;
    }

    public function before_validation_on_update() {
        return true;
    }

    public function after_validation_on_update() {
        return true;
    }

    public function before_delete() {
        return true;
    }

    public function after_delete() {
        return true;
    }

    
    // --------------------------------------------------------------------------------------//
    // GETTER FUNCTIONS                                                                      //
    // --------------------------------------------------------------------------------------//

    public function getExtendedAttributeValues() {
        // this return the __attributes property. This property contains the full set of 
        // datafields. when the find is done with a 'usetable' option (to get data from a view
        // for instance, which contains more fields then the actual table), then you can use this function 
        // to access the returned data.
        // if for instance the main table contains a user_id field, and the view uses a join to the user
        // table and returns the username, then this is the way to access that extra info
        return $this->__attributes;
    }
    public static function getPKFieldName() {
        $currentclass = get_called_class();
        foreach ($currentclass::$attributes as $name => $parameters) {
            if (is_array($parameters)) {
                if (array_key_exists("pk", $parameters)) {
                    return $name;
                }
            }
        }
    }
    
    public function getDescription() {
        // the default getDescription works as follows:
        // if there is a name field and a code field , then the "name (code)" is returned
        // if there is a name field without a code field, then the "name" is returned
        // if there is a firstname and lastname, then "lastname firstname" is returned
        // if there is a title field, then the "title" is returned
        // if there is a description field, then the "description" is returned
        // if none of the above, then the primary keyfield is returned (just to return something)
        // please override in the descendant classes if you want to customize it
        $currentclass = get_called_class();
        $attributes = $currentclass::$attributes;
        if (array_key_exists('name', $attributes)) {
            if (array_key_exists('code', $attributes)) {
                if ($this->code !== "" && $this->code !== null) {
                    return $this->name . " (" . $this->code . ")";
                }
            }
            return $this->name;
        }
        if (array_key_exists('firstname', $attributes)) {
            if (array_key_exists('lastname', $attributes)) {
                return $this->lastname . " " . $this->firstname;
            }
        }
        if (array_key_exists('title', $attributes)) {
            return $this->title;
        }
        if (array_key_exists('description', $_attributes)) {
            return $this->description;
        }
        return $this->$pkfieldname;
    }
    
    public static function getAllAsOptions($idfieldname = "id", $valuefieldname = "name") {
        $options = array();
        $currentclass = get_called_class();
        $optionrecords = $currentclass::find('all');
        foreach ($optionrecords as $optionrecord) {
            $idx = count($options);
            $pkfieldname = $currentclass::getPKFieldName();
            $options[$idx]['value'] = $optionrecord->$pkfieldname;
            $options[$idx]['label'] = $optionrecord->getDescription();
        }
        $options = ArrayHelper::getInstance($options)->array_sort('label', SORT_ASC, false)->toArray();
        return $options;
    }

    public static function getAttribute($attributename) {
        $currentclass = get_called_class();
        $attributes = $currentclass::$attributes;
        if (array_key_exists($attributename, $attributes)) {
            return $attributes[$attributename];
        }
        return null;
    }
    
    public function getValidationMessages() {
        return $this->__validation_messages;
    }

    public function getClassName() {
        return get_called_class();
    }

    public function getDirtyAttributes() {
        return $this->__dirty;
    }

    public function toArray() {
        $returnvalue = array();
        $currentclass = get_called_class();
        $attributes = $currentclass::$attributes;
        if ($this->__ignore_attributes == false) {
        foreach ($currentclass::$attributes as $name => $parameters) {
            $defaultvalue = null;
            if (is_array($parameters)) {
                if (array_key_exists("default", $parameters)) {
                    $defaultvalue = $parameters["default"];
                }
            }
            $returnvalue[$name] = $defaultvalue;
            if ($this->$name !== "" ) {
                $returnvalue[$name] = $this->$name;
            }
        }
        } else {
            foreach($this->__attributes as $name => $__value) {
                $returnvalue[$name] = $this->$name;
            }
        }
        return $returnvalue;
    }
    
    // --------------------------------------------------------------------------------------//
    // SETTER FUNCTIONS                                                                      //
    // --------------------------------------------------------------------------------------//

    private function assignAttributeValuesFromData(array &$data) {
        if (count($data) > 0) {
            $currentclass = get_called_class();
            $attributes = $currentclass::$attributes;
            foreach ($data as $name => $value) {
                if ($this->__ignore_attributes == true) {
                    $this->$name = $value;
                } else {
                    if (array_key_exists($name, $attributes)) {
                        $this->$name = $value;
                    }
                }
            }
            $this->__newrecord = false;
        }
    }

    private function setTimeStamps() {
        $now = date('Y-m-d H:i:s');
        $currentclass = get_called_class();

        if ($currentclass::attributeExists("updated_at")) {
            $this->updated_at = $now;
        }
        if ($currentclass::attributeExists("created_at") && $this->isNewRecord()) {
            $this->created_at = $now;
        }
    }
    
    public function setFlagDirty($name) {
        if (!$this->__dirty)
            $this->__dirty = array();
        $this->__dirty[$name] = true;
    }

    public function cleanFlagDirty() {
        $this->__dirty = array();
    }

    public function clearAttributeValues() {
        $this->__attributes = array();
    }

    
    // --------------------------------------------------------------------------------------//
    // CHECKER FUNCTIONS                                                                     //
    // --------------------------------------------------------------------------------------//

    public function isNewRecord() {
        return $this->__newrecord;
    }
    public function  typeIsNumeric($type) {
        $type = strtolower($type);
        if ($type === "int" || $type === "bigint" || $type === "integer" || $type === "smallint" || $type === "tinyint" || $type === "mediumint" || $type === "decimal" || $type === "float" || $type === "numeric" || $type === "double") {
            return true;
        }
        return false;
    }

    public function fixLength($parameters, $value) {
        // if value is longer than the defined length for this field, then cut off the rest.
        if (array_key_exists("length", $parameters)) {
            $maxlength = $parameters['length'];
            return substr($value, 0, $maxlength);
        }
        return $value;
    }

    public static function attributeExists($attributename) {
        $currentclass = get_called_class();
        $attributes = $currentclass::$attributes;
        if (array_key_exists($attributename, $attributes)) {
            return true;
        }
        return false;
    }

    public static function tableExists() {
// Try a select statement against the table
// Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
        try {
            $currentclass = get_called_class();
            $connection = ConnectionManager::getInstance()->getConnection($currentclass::$connection);
            $table = $currentclass::$table_name;
            $result = $connection->query("SELECT 1 FROM $table LIMIT 1");
        } catch (Exception $e) {
// We got an exception == table not found
            return FALSE;
        }
// Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
        return $result !== FALSE;
    }


    // --------------------------------------------------------------------------------------//
    // FIND FUNCTIONS                                                                        //
    // --------------------------------------------------------------------------------------//
    
    public static function all() {
        return call_user_func_array('static::find', array_merge(array('all'), func_get_args()));
    }

    public static function first() {
        return call_user_func_array('static::find', array_merge(array('first'), func_get_args()));
    }

    public static function last() {
        return call_user_func_array('static::find', array_merge(array('last'), func_get_args()));
    }

    public static function countNumberOfRecords() {
        return call_user_func_array('static::find', array_merge(array('reccount'), func_get_args()));
    }
    

    static $VALID_OPTIONS = array('usetable', 'select', 'conditions', 'limit', 'offset', 'order', 'group', 'having');

    // select = comma separated list of fields to return (default = *)
    // conditions = where statement
    // limit = number of records to return
    // offset = start at record #
    // order = orderstring
    // group = group by statement
    // having = having statement

    public static function processFindOptions(array $array) {
        $options = array();
        if (is_array($array)) {
            $lastparameters = $array[count($array) - 1]; // get the last item. The first one will be either record keys, "all", "first", "last",...
            if (is_array($lastparameters)) {
                if (ArrayHelper::getInstance($lastparameters)->isAssociative()) {
                    $keys = array_keys($lastparameters);
                    $diff = array_diff($keys, self::$VALID_OPTIONS);
                    if (!empty($diff)) {
                        // unknown keys ! throw exception? do something else ? TBD
                    }
                    $intersect = array_intersect($keys, self::$VALID_OPTIONS);
                    if (!empty($intersect)) {
                        $options = $lastparameters;
                    }
                }
            }
        }
        return $options;
    }

    public static function findBySQL($sql) {
        $returnvalue = array();
        $currentclass = get_called_class();
        $connection = ConnectionManager::getInstance()->getConnection($currentclass::$connection);
        $conn = $connection->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $model = new $currentclass($row, true);  // second parameter is true, because we want to ignore the fixed attributes here, a findbysql typically is not on the main table, but on a join, or on a related view.
            $returnvalue[] = $model;
        }
        return $returnvalue;
    }

    public static function findByPk() {
        $currentclass = get_called_class();
        if (func_num_args() <= 0) {
            throw new NoRecordsFoundException("Couldn't find $currentclass without an ID");
        }
        $args = func_get_args();
        $num_args = count($args);

        $values = null;
        if ($num_args > 0) {
            $values = $args[0];
        }
        $instring = "";
        $sqlstring = "";
  
        $findwhat = "first";
        if (is_array($values)) {
            $expected = count($values);
            $instring = implode(',', $values);
            $sqlstring .= self::getPKFieldName() . " IN(" . $instring . ") \n";
            $findwhat = "all";
        } else {
            $expected = 1;
            $instring = $values;
            $sqlstring .= self::getPKFieldName() . " = " . $instring . " \n";
        }
        $args = array("conditions" => array_merge(array($sqlstring)));
        $rowsfound =  call_user_func_array('static::find', array_merge(array($findwhat), array("conditions" => $args)));

        if ($findwhat === "all") {
            if (count($rowsfound) !== $expected) {
                throw new NoRecordsFoundException("Expected $expected records, found " . count($rowsfound));
            }
        }
        return $rowsfound;
    }

    public static function findFromFieldValues($fieldswithvalue, $excludefieldswithvalue = array()) {
        // find all records with field in $fieldswithvalue and where fields in excludefieldswithvalue haven't got the value
        $sqlstring = "";
        $values = array();
        if (count($fieldswithvalue) > 0) {
            $sqlstring .= "(";
            $addand = false;
            foreach ($fieldswithvalue as $field => $value) {
                if ($addand === true) {
                    $sqlstring .= " and ";
                }
                if ($value == "") {
                    $value = 0;
                }
                $values[] = $value;
                $sqlstring .= " " . $field . " = ? ";
                $addand = true;
            }
            $sqlstring .= ")";
        }
        if (count($excludefieldswithvalue) > 0) {
            if (strlen(trim($sqlstring)) > 0) {
                $sqlstring .= " and ";
            }
            $sqlstring .= "(";
            $addand = false;
            foreach ($excludefieldswithvalue as $field => $value) {
                if ($addand === true) {
                    $sqlstring .= " and ";
                }
                if ($value == "") {
                    $value = 0;
                }
                $values[] = $value;
                $sqlstring .= " " . $field . " <> ? ";
                $addand = true;
            }
            $sqlstring .= ")";
        }
        $args = array("conditions" => array_merge(array($sqlstring), $values));
        return call_user_func_array('static::find', array_merge(array('all'), array("conditions" => $args)));
    }
    
    public static function find($what="",$conditions=array()) {
        // there can be one or two function parameters
        // the first one (mandatory) can be:
        // - an id of a record 
        // - a string 'all', 'first', 'last'
        // the second one (which is optional)
        // - an array with query conditions
        $currentclass = get_called_class();
        if (func_num_args() <= 0) {
            throw new NoRecordsFoundException("Couldn't find $currentclass without an ID");
        }
        $args = func_get_args();
        $num_args = count($args);
        $options = static::processFindOptions($args);
        $options["singlerecord"] = true;
        $options["reccount"] = false;
        if ($num_args > 0 && ($args[0] === 'all' || $args[0] === 'first' || $args[0] === 'last' || $args[0] === 'reccount')) {
            switch ($args[0]) {
                case 'reccount':
                    $options["reccount"] = true;
                    $options["singlerecord"] = false;
                    break;
                case 'all':
                    $options["singlerecord"] = false;
                    break;
                case 'last':
                    if (!array_key_exists('order', $options)) {
                        $options['order'] = self::getPKFieldName() . ' DESC';
                    } else {
                        $options['order'] = QueryBuilder::reverseOrder($options['order']);
                    }
                // fallthrough to case first is intentional!
                case 'first':
                    $options["limit"] = 1;
                    $options["offset"] = 0;
                    $options["singlerecord"] = true;
                    break;
            }
            $args = array_slice($args, 1);
            $num_args--;
            $connection = ConnectionManager::getInstance()->getConnection($currentclass::$connection);
            $qb = QueryBuilder::getInstance($currentclass, $options, $connection);
            $statement = $qb->getPreparedStatementSelect();
            $statement->execute($qb->getValues());
            $rows = $statement->fetchAll();
            $returnvalue = null;
            if ($options["singlerecord"] === true) {
                if (count($rows) > 1) {
                    throw new RecordNotUniqueException("expected a single record to be found, found " . count($rows));
                }
                if (count($rows) == 0) {
                    throw new NoRecordsFoundException("record not found, expected a record");
                }
                if (count($rows) == 1) {
                    $returnvalue = new $currentclass($rows[0]);
                }
            } else {
                if ($options["reccount"] === true) {
                    $currentrow = $rows[0];
                    $returnvalue = $currentrow["reccount"];
                } else {
                    $returnvalue = array();
                    foreach ($rows as $row) {
                        if (array_key_exists("usetable",$options)) {
                            $model = new $currentclass($row, true);
                        } else {
                            $model = new $currentclass($row);
                        }
                        
                        $returnvalue[] = $model;
                    }
                }
            }
            $connection->close();
            return $returnvalue;
        } else {
            if (1 === count($args) && 1 == $num_args) {
                $args = $args[0];
            }
        }
        if (!isset($options['conditions'])) {
            return static::findByPk($args, $options);
        }
        throw new NoRecordsFoundException("record not found");
    }


    // --------------------------------------------------------------------------------------//
    // VALIDATION FUNCTIONS                                                                  //
    // --------------------------------------------------------------------------------------//
    
    public function validate() {
        // override this function in the model if needed!
        return true;
    }
    
    public function _validate($validate = true) {
        // override this function in the model if needed!
        if (!$this->before_validation()) {
            return false;
        }
        $this->__validation_messages = array();
        if ($validate === true) {
            $currentclass = get_called_class();
            $validationresult = $currentclass::validate(); // call the validate function (either the empty one in this Model.php or the overriden version in the model
            if ($validationresult === false) {
                return false;
            }
            $validators = $currentclass::$validators;
            $validation = new Validation($validators, $this);
            $this->__validation_messages = $validation->getMessages();
            if ($validation->getResult() === false) {
                return false;
            }
        }
        $this->after_validation();
        return true;
    }

    // --------------------------------------------------------------------------------------//
    // STORAGE FUNCTIONS                                                                     //
    // --------------------------------------------------------------------------------------//
    
    public function save($validate = true) {
        if (!$this->before_save()) {
            return false;
        }
        if ($this->isNewRecord()) {
            $this->insert($validate);
            $this->cleanFlagDirty();
        } else {
            $this->update($validate);
            $this->cleanFlagDirty();
        }
        $this->after_save();
    }

    public function insert($validate = true) {
        if (!$this->before_insert()) {
            return false;
        }

        $this->setTimeStamps();
        $currentclass = get_called_class();
        if (!$this->before_validation_on_insert()) {
            return false;
        }
        if ($this->_validate($validate) === false) {
            return false;
        }
        if (!$this->after_validation_on_insert()) {
            return false;
        }
        $dbname = ConnectionManager::getInstance()->getConnection($currentclass::$connection)->getDatabase();
        $sqlstring = "INSERT INTO " . $dbname . "." . $currentclass::$table_name . " \n";
        $sqlstring .= "( \n";
        $valuesstring = "";
        $i = 0;
        foreach ($currentclass::$attributes as $name => $parameters) {
            if (array_key_exists("pk", $parameters)) {
// do nothing, this is an autoinc field (or should be)
            } else {
                if ($i > 0) {
                    $sqlstring .= ", \n";
                    $valuesstring .= ", \n";
                }
                $sqlstring .= "`" . $name . "`";
                if ($this->$name == null) {
                    $valuesstring .= "null";
                } else {
                    if ($this->typeIsNumeric($parameters["type"] )) {
                        if ($this->$name === "" || is_null($this->$name)) {
                            $valuestring .= "null";
                        } else {
                            $valuesstring .= $this->$name;
                        }
                    } else {
                        $valuesstring .= "'" .  $this->fixLength($parameters, $this->$name) . "'";
                    }
                }
                $i++;
            }
        }
        $sqlstring .= ") \n";
        $sqlstring .= "VALUES \n";
        $sqlstring .= "( \n";
        $sqlstring .= $valuesstring;
        $sqlstring .= "); \n";
        $connection = ConnectionManager::getInstance()->getConnection($currentclass::$connection);
        $result = $connection->query($sqlstring);
        $this->id = $connection->lastInsertId();
        $this->__newrecord = false;
        $connection->close();
        $this->after_insert();
    }

    public function update($validate = true) {
        if (!$this->before_update()) {
            return false;
        }
        $this->setTimeStamps();
        $dirtyattributes = $this->getDirtyAttributes();
        if (self::getPKFieldName() !== null) { // model has a primary key
            if (count($dirtyattributes) > 0) {
                $currentclass = get_called_class();
                if (!$this->before_validation_on_update()) {
                    return false;
                }
                if ($this->_validate($validate) === false) {
                    return false;
                }
                if (!$this->after_validation_on_update()) {
                    return false;
                }
                $dbname = ConnectionManager::getInstance()->getConnection($currentclass::$connection)->getDatabase();
                $sqlstring = "UPDATE " . $dbname . "." . $currentclass::$table_name . " \n";
                $sqlstring .= "SET \n";
                $i = 0;
                $attributes = $currentclass::$attributes;
                foreach ($dirtyattributes as $attributename => $isdirty) {
                    if ($i > 0) {
                        $sqlstring .= ", \n";
                    }
                    $parameters = $attributes[$attributename];
                    if ($this->typeIsNumeric($parameters["type"] )) {
                        if ($this->$attributename === "" || is_null($this->$attributename)) {
                            $sqlstring .=  "`" . $attributename . "` = null";
                        } else {
                            $sqlstring .= "`" . $attributename . "` = " . $this->$attributename;
                        }
                    } else {
                        $sqlstring .= "`" . $attributename . "` = '" . $this->fixLength($parameters, $this->$attributename) . "'";
                    }
                    $i++;
                }
                $sqlstring .= " ";
                $pkfieldname = self::getPKFieldName();
                $sqlstring .= "WHERE `" . self::getPKFieldName() . "` = " . $this->$pkfieldname;
                $sqlstring .= "; \n";
//                echo $sqlstring . "<br>";
                $connection = ConnectionManager::getInstance()->getConnection($currentclass::$connection);
                $result = $connection->query($sqlstring);
                $connection->close();
            } else {
                echo "Nothing to do, nothing changed since last save";
            }
        } else {
            echo "Nothing to do, model has no primary key, can 't do much";
        }
        $this->after_update();
    }

    public function delete() {
        if (!$this->before_delete()) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $currentclass = get_called_class();
        if (self::getPKFieldName() !== null) { // model has a primary key
            if ($currentclass::attributeExists("deleted_at")) {
                $this->deleted_at = $now;

                $dbname = ConnectionManager::getInstance()->getConnection($currentclass::$connection)->getDatabase();
                $sqlstring = "UPDATE " . $dbname . "." . $currentclass::$table_name . " \n";
                $sqlstring .= "SET `deleted_at` = '" . $this->deleted_at . "' \n";
                $pkfieldname = self::getPKFieldName();
                $sqlstring .= "WHERE `" . self::getPKFieldName() . "` = " . $this->$pkfieldname;
                $sqlstring .= "; \n";
//                echo $sqlstring . "<br>";
                $connection = ConnectionManager::getInstance()->getConnection($currentclass::$connection);
                $result = $connection->query($sqlstring);
                $connection->close();

                $this->cleanFlagDirty();
                $this->clearAttributeValues();
                $this->__newrecord = true;
            } else {
                $dbname = ConnectionManager::getInstance()->getConnection($currentclass::$connection)->getDatabase();
                $sqlstring = "DELETE FROM " . $dbname . "." . $currentclass::$table_name . " \n";
                $sqlstring .= " ";
                $pkfieldname = self::getPKFieldName();
                $sqlstring .= "WHERE `" . self::getPKFieldName() . "` = " . $this->$pkfieldname;
                $sqlstring .= "; \n";
//                echo $sqlstring . "<br>";
                $connection = ConnectionManager::getInstance()->getConnection($currentclass::$connection);
                $result = $connection->query($sqlstring);
                $connection->close();
                $this->cleanFlagDirty();
                $this->clearAttributeValues();
                $this->__newrecord = true;
            }
        }
        $this->after_delete();
    }
               
    
    // --------------------------------------------------------------------------------------//
    // DATABASE TABLE MANAGEMENT FUNCTIONS                                                   //
    // --------------------------------------------------------------------------------------//
    
    public static function createOrUpdateTable() {
        $currentclass = get_called_class();
        $connection = ConnectionManager::getInstance()->getConnection($currentclass::$connection);
        if (!$currentclass::tableExists()) {
            $createstatement = $currentclass::generateSQLCreateTable();
            $result = $connection->query($createstatement);
            echo "<hr>" . $createstatement . "</hr>";
            echo "<br>Result: <pre>";
            print_r($result);
            echo "</pre>";
        } else {
            echo "<br>table already exists<br>";
//           $alterstatement = $currentclass::generateSQLAlterTable();
//           $result = $connection->query($alterstatement);
        }
        $connection->close();
    }

    public static function generateSQLAlterTabel() {
// TODO
    }

    public static function generateSQLCreateTable() {
        $sqlstring = "";
        $currentclass = get_called_class();
        if (count($currentclass::$attributes) > 0) {
            $primarykey = "";
            $uniquekey = "";
            $indexes = array();
            $sqlstring .= "CREATE TABLE `" . $currentclass::$table_name . "` ( \n";
            $i = 0;
            foreach ($currentclass::$attributes as $name => $parameters) {
                if ($i > 0) {
                    $sqlstring .= ",\n";
                }
// setting some defaults
                $type = "varchar";
                $length = 255;
                $autoinc = false;
                $pk = false;
                $default = "NULL";
                $mandatory = false;
                if (is_array($parameters)) {
                    if (array_key_exists("type", $parameters)) {
                        $type = $parameters["type"];
                    }
                    if (array_key_exists("length", $parameters)) {
                        $length = $parameters["length"];
                    }
                    if (array_key_exists("indexed", $parameters)) {
                        if ($parameters["indexed"] === true) {
                            $indexes[] = $name;
                        }
                    }
                    if (array_key_exists("autoinc", $parameters)) {
                        $autoinc = $parameters["autoinc"];
                    }
                    if (array_key_exists("default", $parameters)) {
                        $default = $parameters["default"];
                    }
                    if (array_key_exists("pk", $parameters)) {
                        if ($primarykey === "") {
                            $pk = $parameters["pk"];
                            $primarykey = $name;
                            $default = "NOT NULL";
                            $uniquekey = $name;
                        }
                    }
                    if (array_key_exists("mandatory", $parameters)) {
                        $mandatory = $parameters["mandatory"];
                    }
                }
                $collationstring = "";
                if (strtolower($type) === "varchar") {
                    $collationstring = "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                }
                $typestring = $type;
                if (!isEmpty($length) && strtolower($type) !== "date" && strtolower($type) !== "timestamp") {
                    $typestring .= "(" . $length . ")";
                }
                $defaultstring = "";
                if (!isEmpty($default) && $default !== "NOT NULL") {
                    $defaultstring = " DEFAULT " . $default;
                } else {
                    $defaultstring = $default;
                }
                $autoincstring = "";
                if (!isEmpty($autoinc)) {
                    if ($autoinc === true) {
                        $autoincstring = "AUTO_INCREMENT";
                    }
                }
                $mandatorystring = "";
                if ($mandatory === true) {
                    $mandatorystring = "NOT NULL";
                }
                $sqlstring .= "`" . $name . "` " . $typestring . " " . $collationstring . " " . $mandatorystring . " " . $autoincstring . " " . $defaultstring;
                $i++;
            }
            if ($primarykey !== "") {
                $sqlstring .= ",\n";
                $sqlstring .= "PRIMARY KEY (`" . $primarykey . "`) \n";
            }
            if ($uniquekey !== "" || $primarykey !== "") {
                $sqlstring .= ",\n";
                $sqlstring .= "UNIQUE KEY (`" . $uniquekey . "`) \n";
            }
            foreach ($indexes as $index) {
                $sqlstring .= ",\n";
                $sqlstring .= "KEY `idx_" . $index . "` (`" . $index . "`) \n";
            }
            $i = 1;
            foreach ($currentclass::$compound_indexes as $compound_index) {
                if (is_array($compound_index)) {
                    $sqlstring .= ",\n";
                    $sqlstring .= " KEY `idx_multi_" . $i . "` (";
                    $j = 0;
                    foreach ($compound_index as $name) {
                        if ($j > 0) {
                            $sqlstring .= ",";
                        }
                        $sqlstring .= "`" . $name . "`";
                        $j++;
                    }
                    $sqlstring .= ")";
                    $i++;
                }
            }
            $sqlstring .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
        }
        return $sqlstring;
    }

}
