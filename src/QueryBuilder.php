<?php

namespace FL;

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
            $this->connection = ConnectionManager::getInstance()->get($classname::$connection);
        }
        $this->database = $this->connection->getDatabase();
        $this->table = $classname::$table_name;
        $this->parseOptions($options);
    }

    public static function getInstance($classname, $options = array()) {
        $class = __CLASS__;
        return new $class($classname, $options);
    }

    public static function reverseOrder($orderstring) {
        if (!trim($order)) {
            return $order;
        }

        $parts = explode(',', $order);

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
                $helper->replace("?", "[?" . $i . "]", StringHelper::FIRST);
            }
        }
        for ($i = 0; $i < count($args); $i++) {
            for ($i = 0; $i < count($args); $i++) {
                $paramvalue = $args[$i];
                if (is_array($paramvalue)) {
                    $replacewith = implode(',', array_fill(0, count($paramvalue), '?')); //create x question marks
                    $helper->replace("[?" . $i . "]", $replacewith, StringHelper::FIRST);
                    $containsarray = true;
                }
            }
        }
        for ($i = 0; $i < count($args); $i++) {
            for ($i = 0; $i < count($args); $i++) {
                $helper->replace("[?" . $i . "]", "?", StringHelper::FIRST);
            }
        }
        return $helper->toString();
    }

    private function processValue($value) {
        return $value;
        /* if ($value === null)
          return null;

          switch ($this->type)
          {
          case self::STRING:	return (string)$value;
          case self::INTEGER:	return (int)$value;
          case self::DECIMAL:	return (double)$value;
          case self::DATETIME:
          case self::DATE:
          if (!$value)
          return null;

          if ($value instanceof DateTime)
          return $value;

          if ($value instanceof \DateTime)
          //					return new DateTime($value->format('Y-m-d H:i:s T'));
          return new DateTime($value->format('Y-m-d H:i:s'));

          return $connection->string_to_datetime($value);
          }
          return $value;
         * 
         */
    }

    private function constructWhereString($args) {
        $this->where_values = array();
        $num_args = count($args);
        if ($num_args > 0) {
            if ($num_args == 1) {
                $wherestring = $args[0];
            } else {
                $wherestring = $args[0];
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
                for ($i = 0; $i < count($args); $i++) {
                    $paramvalue = $args[$i];
                    if (is_array($paramvalue)) {
                        foreach ($paramvalue as $value) {
                            $this->where_values[] = $this->processValue($value);
                        }
                    } else {
                        $this->where_values[] = $this->processValue($paramvalue);
                    }
                }
            }
        }
        return $wherestring;
    }

    private function blockDeletedRecordsIfNeeded() {
        $currentclass = $this->classname;
        if ($currentclass::doesAttributeExists("deleted_at")) {
            $deletefilter = " isnull(deleted_at) ";
            if (strlen(trim($this->where)) > 0) {
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
//echo $sql;        
        $conn = $this->connection->getConnection();
        $stmt = $conn->prepare($sql);
        //$conn->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING );
        return $stmt;
    }

    public function getValues() {
        return $this->where_values;
    }

}
