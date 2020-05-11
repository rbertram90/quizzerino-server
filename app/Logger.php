<?php

namespace rbwebdesigns\quizzerino;

class Logger {

    public static function debug($message) {
        print "DEBUG: " . $message . PHP_EOL;
    }

    public static function info($message) {
        print "INFO: " . $message . PHP_EOL;
    }

    public static function error($message) {
        print "ERROR: " . $message . PHP_EOL;
    }

    public static function warning($message) {
        print "WARNING: " . $message . PHP_EOL;
    }

}