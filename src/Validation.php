<?php

namespace FL;

class Validation {

    private $messages = array();
    private $status = true;
    private $model;

    public function __construct($validators, $model) {
        $returnvalue = true;
        $this->model = $model;
        if (is_array($validators)) {
            foreach ($validators as $type => $validatorinfo) {
                if (strtolower($type) === "unique") {
                    foreach ($validatorinfo as $validator) {
                        if ($this->checkUnique($validator) === false) {
                            echo "checkuniquefailed<br>";
                            $returnvalue = false;
                        }
                    }
                }
                if (strtolower($type) === "length") {
                    foreach ($validatorinfo as $validator) {
                        if ($this->checkLength($validator) === false) {
                            echo "checklenght failed<br>";
                            $returnvalue = false;
                        }
                    }
                }
                if (strtolower($type) === "in") {
                    foreach ($validatorinfo as $validator) {
                        if ($this->checkIn($validator) === false) {
                            echo "checkin failed<br>";
                            $returnvalue = false;
                        }
                    }
                }
            }
        }
        $this->status = $returnvalue;
    }

    public function getResult() {
        return $this->status;
    }

    public function getMessages() {
        return $this->messages;
    }

    private function checkUnique($validatorinfo) {
        $returnvalue = true;
        $msg = "is not unique";
        $fieldswithvalues = array();
        foreach ($validatorinfo as $key => $value) {
            if (strtolower($key) === "message") {
                $msg = $validatorinfo[$key];
            } else {
                // this has to be the field(s) name
                if (is_array($value)) {
                    foreach ($value as $fieldname) {
                        $fieldswithvalue[$fieldname] = $this->model->$fieldname;
                    }
                } else {
                    $fieldswithvalue[$value] = $this->model->$value;
                }
            }
        }
        // now construct a query to check if there is already an existing record with these values (with pk different from pk of this model)
        $classname = $this->model->getClassName();
        $pkfieldname = $classname::getPKFieldName();
        $notfields = array();
        $notfields[$pkfieldname] = $this->model->$pkfieldname;
        $recsfound = $classname::findFromFieldValues($fieldswithvalue, $notfields);
        if (is_array($recsfound)) {
            if (count($recsfound) > 0) {
echo "checkunique records found :" . count($recsfound) ."<br>";        
                $returnvalue = false;
            }
        }
        return $returnvalue;
    }

    private function checkLength($validatorinfo) {
        $returnvalue = true;
        $msg = "fails the length constraints";
        $min = null;
        $max = null;
        $fieldswithvalues = array();
        foreach ($validatorinfo as $key => $value) {
            if (strtolower($key) === "message") {
                $msg = $validatorinfo[$key];
            } else {
                if (strtolower($key) === "min") {
                    $min = $validatorinfo[$key];
                } else {
                    if (strtolower($key) === "max") {
                        $max = $validatorinfo[$key];
                    } else {
                        // this has to be the field(s) name
                        if (is_array($value)) {
                            foreach ($value as $fieldname) {
                                $fieldswithvalue[$fieldname] = $this->model->$fieldname;
                            }
                        } else {
                            $fieldswithvalue[$value] = $this->model->$value;
                        }
                    }
                }
            }
        }
echo "min = " . $min . ", max = " . $max . "<br>";
echo "<pre>";print_r($fieldswithvalue); echo "</pre>";
        foreach ($fieldswithvalue as $field => $value) {
            if (is_string($value)) {
                if ($min !== null) {
                    if (strlen($value) < $min) {
                        $this->messages[$field] = $msg . " (min:" . $min . ")";
                        $returnvalue = false;
                    }
                }
                if ($max !== null) {
                    if (strlen($value) > $max) {
                        $this->messages[$field] = $msg . " (max:" . $max . ")";
                        $returnvalue = false;
                    }
                }
            }
        }
        return $returnvalue;
    }

    private function checkIn($validatorinfo) {
        $returnvalue = true;
        $msg = "is not in allowed values";
        $allowed = array();
        $fieldswithvalues = array();
        foreach ($validatorinfo as $key => $value) {
            if (strtolower($key) === "message") {
                $msg = $validatorinfo[$key];
            } else {
                if (strtolower($key) === "allowed") {
                    $allowed = $validatorinfo[$key];
                } else {
                    $fieldswithvalue[$value] = $this->model->$value;
                }
            }
        }
        foreach ($fieldswithvalue as $field => $value) {
            if (is_string($value)) {
                if (!in_array($value, $allowed)) {
                    $this->messages[$field] = $msg;
                    $returnvalue = false;
                }
            }
        }
        return $returnvalue;
    }

}
