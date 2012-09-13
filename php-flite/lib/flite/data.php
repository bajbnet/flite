<?php
/**
 * User: brooke.bryan
 * Date: 11/09/12
 * Time: 10:20
 * Description: Data Handlers
 */

class Flite_DataCollection
{
    private $_handlers;
    private $_failed;
    private $_populated;

    /**
     * @param array $handlers
     * @param mixed $populate_data
     */
    public function __construct($handlers=array(),$populate_data=null)
    {
        $this->AddHandlers($handlers);
        if(is_array($populate_data)) $this->PopulateData($populate_data);
    }

    public function AddHandlers($handlers=array())
    {
        foreach($handlers as $handler)
        {
            if($handler instanceof Flite_DataHandler)
            {
                $this->_handlers[$handler->Name()] = $handler;
            }
        }
    }

    public function AddHandler(Flite_DataHandler $handler)
    {
        if($handler instanceof Flite_DataHandler)
        {
            $this->_handlers[$handler->Name()] = $handler;
        }
    }

    public function GetHandler($name)
    {
        return isset($this->_handlers[$name]) ? $this->_handlers[$name] : false;
    }

    public function Handlers()
    {
        return $this->_handlers;
    }

    public function Valid()
    {
        $valid = true;
        foreach($this->_handlers as $handler)
        {
            if(!$handler->Valid())
            {
                $this->_failed[$handler->Name()] = $handler;
                $valid = false;
            }
        }
        return $valid;
    }

    public function PopulateData($keyvalue = array())
    {
        foreach($this->_handlers as $handler)
        {
            if(isset($keyvalue[$handler->Name()]))
            {
                $handler->SetData($keyvalue[$handler->Name()]);
                $this->_populated = true;
            }
        }
    }

    public function Populated()
    {
        return $this->_populated ? true : false;
    }

    public function FailedHandlers()
    {
        return FC::arr($this->_failed);
    }
}

class Flite_DataHandler
{
    private $_name;
    private $_required;
    private $_validators;
    private $_filters;
    private $_options;
    private $_data;
    private $_exceptions;

    public function __construct($name,
                                $required=false,
                                $validators=null,
                                $filters=null,
                                $options=null,
                                $data=null)
    {
        $this->Name($name);
        $this->Required($required ? true : false);
        if(is_array($validators))
        {
            foreach($validators as $validator)
            {
                if(is_string($validator))
                {
                    $this->AddValidator(Flite_Callback::_($validator));
                }
                else if(is_array($validator))
                {
                    if(isset($validator[0]) && is_array($validator[0]))
                    {
                        $this->AddValidator(Flite_Callback::_($validator[0],$validator[1]));
                    }
                }
                else if($validator instanceof Flite_Callback)
                {
                    $this->AddValidator($validator);
                }
            }
        }
        else if(is_string($validators))
        {
            $this->AddValidator(Flite_Callback::_($validators));
        }
        else if($validators instanceof Flite_Callback)
        {
            $this->AddValidator($validators);
        }


        if(is_array($filters))
        {
            foreach($filters as $filter)
            {
                if(is_string($filter))
                {
                    $this->AddFilter(Flite_Callback::_($filter));
                }
                else if(is_array($filter))
                {
                    if(isset($filter[0]) && is_array($filter[0]))
                    {
                        $this->AddFilter(Flite_Callback::_($filter[0],$filter[1]));
                    }
                }
                else if($filter instanceof Flite_Callback)
                {
                    $this->AddFilter($filter);
                }
            }
        }
        else if(is_string($filters))
        {
            $this->AddFilter(Flite_Callback::_($filters));
        }
        else if($filters instanceof Flite_Callback)
        {
            $this->AddFilter($filters);
        }
        $this->SetData($data);
        $this->SetOptions($options);
    }

    public function Name($name=null)
    {
        if(is_string($name))
        {
            $this->_name = $name;
            return $this;
        }
        return $this->_name;
    }

    public function ID()
    {
        return str_replace('_','-',$this->Name());
    }

    public function Required($set=null)
    {
        if(is_bool($set))
        {
            $this->_required = $set;
            return $this;
        }
        return $this->_required ? true : false;
    }

    public function SetData($data)
    {
        $this->_data = $data;
        return $this;
    }

    public function RawData()
    {
        return $this->_data;
    }

    public function Data()
    {
        if(!is_array($this->_filters)) return $this->_data;

        $data = $this->_data;
        foreach($this->_filters as $filter)
        {
            if($filter instanceof Flite_Callback)
            {
                $data = $filter->Process($this->Data());
            }
        }
        return $data;
    }

    public function SetOptions($options)
    {
        $this->_options = $options;
        return $this;
    }

    public function AddOption($option)
    {
        $this->_options[] = $option;
        return $this;
    }

    public function Options()
    {
        return $this->_options;
    }

    public function AddFilter(Flite_Callback $filter)
    {
        $this->_filters[] = $filter;
        return $this;
    }

    public function Filters($replace_filters)
    {
        if(!is_null($replace_filters) && is_array($replace_filters))
        {
            $this->_filters = $replace_filters;
            return $this;
        }
        return $this->_filters;
    }

    public function AddValidator(Flite_Callback $validator)
    {
        $this->_validators[] = $validator;
        return $this;
    }

    public function Validators($replace_validators=null)
    {
        if(!is_null($replace_validators) && is_array($replace_validators))
        {
            $this->_validators = $replace_validators;
            return $this;
        }
        return $this->_validators;
    }

    public function Valid()
    {
        if(!is_array($this->_validators)) return true;

        $valid = true;
        foreach($this->_validators as $validator)
        {
            if($validator instanceof Flite_Callback)
            {
                $passed = false;
                try
                {
                    $passed = $validator->Process($this->Data());
                    if(!$passed)
                    {
                        throw new Exception("Validation failed",0);
                    }
                }
                catch(Exception $e)
                {
                    $this->_exceptions[] = $e;
                }
                if(!$passed) $valid = false;
            }
        }
        return $valid;
    }

    public function Exceptions()
    {
        return FC::arr($this->_exceptions);
    }
}

class Flite_Callback
{
    public function __construct(callable $method,$options = array())
    {
        $this->_method = $method;
        $this->_options = $options;
    }

    public static function _(callable $method,$options=array())
    {
        return new Flite_Callback($method,$options);
    }

    public function Process($input=null)
    {
        return call_user_func_array($this->_method,FC::array_merge(array($input),$this->_options));
    }
}