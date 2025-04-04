<?php

namespace FL;

use MongoDB\Driver\Query;

class QueryBuilder {

    private $connection = null;
    private $classname;
    private $options;
    private $database;
    private $table;
    private $select = "*";
    private $order;
    private $limit = 0;
    private $offset = 0;
    private $group;
    private $having;
    // for where
    private $where;
    private $where_values = array();
// test
    static $QUOTE_CHARACTER = '`';

    public function __construct($classname, $options, $connection = null) {
        $this->classname = $classname;
        $this->options = $options;
        if ($connection !== null) {
            if ($connection instanceof Connection) {
                $this->connection = $connection;
            }
        }
        if ($this->connection === null) {
            $this->connection = ConnectionManager::getInstance()->getConnection($classname::$connection);
        }
        $this->database = $this->connection->getDatabase();

//        echo "<pre>"; print_r($options); echo "</pre>";
        if (array_key_exists("usetable",$options)) {
            $this->table = $options['usetable'];
        } else {
            $this->table = $classname::$table_name;
        }
        $this->parseOptions($options);
    }

    public static function getInstance($classname, $options = array()) {
        $class = __CLASS__;
        return new $class($classname, $options);
    }

    public static function reverseOrder($orderstring) {
        if (!trim($orderstring)) {
            return $orderstring;
        }

        $parts = explode(',', $orderstring);

        for ($i = 0, $n = count($parts); $i < $n; ++$i) {
            $v = strtolower($parts[$i]);

            if (strpos($v, ' asc') !== false) {
                $parts[$i] = preg_replace('/asc/i', 'DESC', $parts[$i]);
            } elseif (strpos($v, ' desc') !== false) {
                $parts[$i] = preg_replace('/desc/i', 'ASC', $parts[$i]);
            } else {
                $parts[$i] .= ' DESC';
            }
        }
        return join(',', $parts);
    }

    public function parseOptions($options) {
        if (array_key_exists('select', $options)) {
            $this->select = $options['select'];
        }
        if (array_key_exists('reccount', $options)) {
            if ($options["reccount"] === true) {
                $this->select = "count(*) as reccount";
            }
        }
        if (array_key_exists('order', $options)) {
            $this->order = $options['order'];
        }

        if (array_key_exists('limit', $options)) {
            $this->limit = intval($options['limit']);
        }

        if (array_key_exists('offset', $options)) {
            $this->offset = intval($options['offset']);
        }

        if (array_key_exists('group', $options)) {
            $this->group = $options['group'];
        }

        if (array_key_exists('having', $options)) {
            $this->having = $options['having'];
        }

        if (array_key_exists('conditions', $options)) {
            if (is_string($options['conditions'])) {
                $this->where = $options['conditions'];
            } else {
                if (is_array($options['conditions'])) {
                    $this->where = $this->constructWhereString($options['conditions']);
                }
            }
        }
    }

    private function handleInClauses($wherestring, $args) {
        // 1. replace all ? in the wherestring with [?$i] where $i is their sequential order number
        // 2. replace the relevant [?$i] for params with an arrayvalue with the appropriate number of ?
        // 3. replace the remaining [?$i] back to simple ?
        $helper = StringHelper::getInstance($wherestring);
        for ($i = 0; $i < count($args); $i++) {
            for ($i = 0; $i < count($args); $i++) {
                $helper->replace("?", "[[" . $i . "]", StringHelper::FIRST);
            }
        }
        for ($i = 0; $i < count($args); $i++) {
            for ($i = 0; $i < count($args); $i++) {
                $paramvalue = $args[$i];
                if (is_array($paramvalue)) {
                    $replacewith = implode(',', array_fill(0, count($paramvalue), '?')); //create x question marks
                    $helper->replace("[[" . $i . "]", $replacewith, StringHelper::FIRST);
                    $containsarray = true;
                }
            }
        }
        for ($i = 0; $i < count($args); $i++) {
            for ($i = 0; $i < count($args); $i++) {
                $helper->replace("[[" . $i . "]", "?", StringHelper::FIRST);
            }
        }
        return $helper->toString();
    }

    private static function mapPlaceholdersToFields($queryString) {
        // Split the query string by '?'
        //$parts = preg_split('/\?/', $queryString);
        $fields = [];

        if (preg_match_all('/(?:\b(\w+)\b(?=\s*(?:=|>|<|>=|<=|LIKE|IN\s*\(|IS\s+NULL|IS\s+NOT\s+NULL))|(?:YEAR|MONTH|DAY|ISNULL|COALESCE|MAX|MIN|AVG|SUM|COUNT)\(\s*(\w+)\s*\))/i',
            $queryString,
            $matches,
            PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fieldcomplete = $match[0];  // this contains the full field with function. i.e.  YEAR(datefrom)
//echo "match\n";
//print_r($match);
                // Match[1] captures normal fields, Match[2] captures function fields
                $field = $match[1] ?: $match[2];
                $isfunction = false;
                if (count($match) >= 3) {
                    if ($match[2] !== "" && $match[2] !== null) {
                        $isfunction = true;
                    }
                }
                if ($field) {
                    $fields[] = array(trim($field), $isfunction);
                }
            }
        }
        return $fields;
    }

    private static function isValidForType($value, $type) {
        $type = strtolower($type); // Normalize type to lowercase
        $isValid = false;

        switch ($type) {
            // Numeric Types
            case 'tinyint':
                $isValid = filter_var($value, FILTER_VALIDATE_INT, ["options" => ["min_range" => -128, "max_range" => 127]]) !== false;
                break;
            case 'smallint':
                $isValid = filter_var($value, FILTER_VALIDATE_INT, ["options" => ["min_range" => -32768, "max_range" => 32767]]) !== false;
                break;
            case 'mediumint':
                $isValid = filter_var($value, FILTER_VALIDATE_INT, ["options" => ["min_range" => -8388608, "max_range" => 8388607]]) !== false;
                break;
            case 'int':
            case 'integer':
                $isValid = filter_var($value, FILTER_VALIDATE_INT) !== false;
                break;
            case 'bigint':
                $isValid = is_numeric($value) && strlen((string)$value) <= 20 && $value >= -9223372036854775808 && $value <= 9223372036854775807;
                break;
            case 'decimal':
            case 'numeric':
            case 'float':
            case 'double':
            case 'real':
                $isValid = filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
                break;

            // Date and Time Types
            case 'date':
                $isValid = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
                break;
            case 'datetime':
                $isValid = (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) && strtotime($value) !== false;
                break;
            case 'timestamp':
                $isValid = (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) && strtotime($value) !== false;
                break;
            case 'time':
                $isValid = (bool) preg_match('/^-?\d{1,3}:\d{2}:\d{2}$/', $value);
                break;
            case 'year':
                $isValid = (bool) preg_match('/^\d{4}$/', $value) && (int)$value >= 1901 && (int)$value <= 2155;
                break;

            default:
                return true;
        }

        return $isValid;
    }
    private function processValue($fields, $placeholderidx, $value) {
        $returnvalue = $value;
        // check for type of field, if date or numeric it has to be a valid date or a valid number
        if (count($fields) > $placeholderidx) {
            $fieldname = $fields[$placeholderidx][0];
            $fieldisfunction = $fields[$placeholderidx][1];
            if ($value !== null) {
                if (strpos($value, '%') !== false) {
                    // do nothing, we are searching for a like value.
                } else {
                    $classname = $this->classname;
                    $attributes = $classname::$attributes;
                    if ($fieldisfunction == false) {
                        if (array_key_exists($fieldname, $attributes)) {
                            //    echo ", attribute '" . $fieldname . "' exists ";
                            $fieldinfo = $attributes[$fieldname];
                            if (array_key_exists("type", $fieldinfo)) {
                                //    echo ", array key type exists in fieldinfo and is " . $fieldinfo["type"] . " ";
                                $type = $fieldinfo["type"];
                                if (!QueryBuilder::isValidForType($value, $type)) {
                                    //  echo " but value '" . $value . "' is not a valid type '" . $type . "' of field '" . $fieldname . "' \n";
                                    $returnvalue = null;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $returnvalue;
    }

    private function constructWhereString($args) {
        $this->where_values = array();
        $num_args = count($args);
        $wherestring = "";
        if ($num_args > 0) {
            if ($num_args == 1) {
//                $wherestring = $args[0];
                $wherestring = reset($args);
                if (is_array($wherestring)) {
                    $wherestring = implode( " and ", $wherestring);
                }

            } else {
//                $wherestring = $args[0];
                $wherestring = reset($args);
                if (is_array($wherestring)) {
                    $wherestring = implode( " and ", $wherestring);
                }

                array_shift($args); // removes the first item from the array
                // count number of ? characters in the wherestring
                $numberofparametersexpected = StringHelper::getInstance($wherestring)->countOccurrences('?');
                if ($numberofparametersexpected !== ($num_args - 1)) {
                    // this is an error !!!
                    throw new WrongParametersException("can't build query: number of parameters not correct");
                }
                // check if any of the values is an array. In that case the correct number of ? have to be inserted!
                $containsarray = false;
                for ($i = 0; $i < count($args); $i++) {
                    $paramvalue = $args[$i];
                    if (is_array($paramvalue)) {
                        $containsarray = true;
                    }
                }
                if ($containsarray === true) {
                    $wherestring = $this->handleInClauses($wherestring, $args);
                }
                $this->where = $wherestring;
                $fieldnames = QueryBuilder::mapPlaceholdersToFields($this->where);
                for ($i = 0; $i < count($args); $i++) {
                    $paramvalue = $args[$i];
                    if (is_array($paramvalue)) {
                        foreach ($paramvalue as $value) {
                            $this->where_values[] = $this->processValue($fieldnames,$i, $value);
                        }
                    } else {
                        $this->where_values[] = $this->processValue($fieldnames,$i, $paramvalue);
                    }
                }
            }
        }
        return $wherestring;
    }

    private function blockDeletedRecordsIfNeeded() {
        $currentclass = $this->classname;
        if ($currentclass::attributeExists("deleted_at")) {
            $deletefilter = " isnull(deleted_at) ";
            if (strlen(trim('' . $this->where)) > 0) {  // added the '' because PHP8.1 does no longer support passing a null value to trim
                $this->where = "(" . $this->where . ") and " . $deletefilter;
            } else {
                $this->where = $deletefilter;
            }
        }
    }

    public function getPreparedStatementSelect() {
        $sql = "SELECT $this->select FROM " . $this->database . "." . $this->table . " \n";
        $this->blockDeletedRecordsIfNeeded();
        if ($this->where) {
            $sql .= " WHERE $this->where";
        }
        if ($this->group) {
            $sql .= " GROUP BY $this->group";
        }
        if ($this->having) {
            $sql .= " HAVING $this->having";
        }
        if ($this->order) {
            $sql .= " ORDER BY $this->order";
        }
        if ($this->limit || $this->offset) {
            $offset = intval($this->offset);
            $limit = intval($this->limit);
            $sql .= " LIMIT " . $offset . "," . $limit;
        }
        $conn = $this->connection->getConnection();
        $stmt = $conn->prepare($sql);
        //echo $sql;
        //$conn->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING );
        return $stmt;
    }

    public function getValues() {
        return $this->where_values;
    }

}
