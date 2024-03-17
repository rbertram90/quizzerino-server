<?php

namespace rbwebdesigns\quizzerino;

class Logger {

    /**
     * Get a string version of a variable.
     */
    protected static function formatVariable($variable)
    {
        if (is_array($variable)) {
            return json_encode($variable, JSON_PRETTY_PRINT);
        }

        return $variable;
    }

    public static function debug($message) {
        print "DEBUG: " . self::formatVariable($message) . PHP_EOL;
    }

    public static function info($message) {
        print "INFO: " . self::formatVariable($message) . PHP_EOL;
    }

    public static function error($message) {
        print "ERROR: " . self::formatVariable($message) . PHP_EOL;
    }

    public static function warning($message) {
        print "WARNING: " . self::formatVariable($message) . PHP_EOL;
    }

}