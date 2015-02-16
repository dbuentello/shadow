<?php

namespace App\Model;

class Schema
{
        protected static $schema = array();

        public static function get ( $schema )
        {

                if (empty(self::$schema)) {
                        self::$schema = \Shadow\Config::get('mongo.schema');
                }

                $collection = false;
                $schema = explode('.', $schema);

                if (isset($schema[0])) {
                        $collection = self::$schema[$schema[0]];
                        array_shift($schema);
                        foreach ($schema as $key) {
                                if (isset($collection[$key])) {
                                        $collection = $collection[$key];
                                }
                        }
                }

                return !empty($collection) ? \Shadow\Mongo::get()->$collection : false;
        }
}

